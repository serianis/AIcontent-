<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AutoblogAI_API_Client' ) ) {
    require_once __DIR__ . '/class-api-client.php';
}

class AutoblogAI_Image_Generator {
    public const OPTION_API_KEY = 'autoblogai_api_key';

    public const ERROR_MISSING_API_KEY      = 'autoblogai_api_key_missing';
    public const ERROR_HTTP_UNAVAILABLE     = 'autoblogai_http_unavailable';
    public const ERROR_HTTP_TRANSPORT       = 'autoblogai_http_transport_error';
    public const ERROR_IMAGE_API_ERROR      = 'autoblogai_image_api_error';
    public const ERROR_IMAGE_DECODE_ERROR   = 'autoblogai_image_decode_error';
    public const ERROR_IMAGE_UPLOAD_ERROR   = 'autoblogai_image_upload_error';
    public const ERROR_WEBP_CONVERT_ERROR   = 'autoblogai_webp_conversion_error';

    private string $api_key;
    private string $base_url;
    private string $image_model;
    private int $max_retries;
    private float $initial_backoff_seconds;

    private ?AutoblogAI_API_Client $text_client;

    private int $rate_limit_per_minute;

    /** @var callable|null */
    private $http_post;
    /** @var callable */
    private $sleep_fn;
    /** @var callable */
    private $time_fn;
    /** @var callable */
    private $get_transient;
    /** @var callable */
    private $set_transient;

    public function __construct( ?AutoblogAI_API_Client $text_client = null, array $args = array() ) {
        $this->api_key    = (string) ( $args['api_key'] ?? get_option( self::OPTION_API_KEY, '' ) );
        $this->base_url   = (string) ( $args['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/' );
        $this->image_model = (string) ( $args['image_model'] ?? apply_filters( 'autoblogai_gemini_image_model', 'imagen-3.0-generate-001' ) );

        $this->max_retries             = (int) ( $args['max_retries'] ?? 2 );
        $this->initial_backoff_seconds = (float) ( $args['initial_backoff_seconds'] ?? 1.0 );

        $this->rate_limit_per_minute = (int) ( $args['rate_limit_per_minute'] ?? apply_filters( 'autoblogai_rate_limit_per_minute', 60 ) );

        $this->text_client = $text_client;
        $this->http_post   = $args['http_post'] ?? ( function_exists( 'wp_remote_post' ) ? 'wp_remote_post' : null );
        $this->time_fn     = $args['time_fn'] ?? 'time';
        $this->sleep_fn    = $args['sleep_fn'] ?? static function ( $seconds ) {
            $seconds = (int) ceil( (float) $seconds );
            if ( $seconds > 0 ) {
                sleep( $seconds );
            }
        };

        $this->get_transient = $args['get_transient'] ?? ( function_exists( 'get_transient' ) ? 'get_transient' : static function ( $key ) {
            return null;
        } );
        $this->set_transient = $args['set_transient'] ?? ( function_exists( 'set_transient' ) ? 'set_transient' : static function ( $key, $value, $expiration = 0 ) {
            return true;
        } );
    }

    /**
     * Low-level image generation (for testing and advanced usage).
     *
     * @return string|WP_Error Base64-encoded image bytes.
     */
    public function generate_image_base64( string $image_prompt, array $args = array() ) {
        if ( '' === trim( $this->api_key ) ) {
            return new WP_Error( self::ERROR_MISSING_API_KEY, 'Missing Gemini API key.' );
        }

        if ( ! $this->http_post ) {
            return new WP_Error( self::ERROR_HTTP_UNAVAILABLE, 'WordPress HTTP functions are not available.' );
        }

        $type = (string) ( $args['type'] ?? 'hero' );
        $image_prompt = apply_filters( 'autoblogai_image_prompt', $image_prompt, array( 'type' => $type ) );

        $url = $this->base_url . $this->image_model . ':predict?key=' . rawurlencode( $this->api_key );

        $body = array(
            'instances'  => array(
                array( 'prompt' => $image_prompt ),
            ),
            'parameters' => array(
                'sampleCount' => (int) ( $args['sample_count'] ?? 1 ),
                'aspectRatio' => (string) ( $args['aspect_ratio'] ?? '16:9' ),
            ),
        );

        $request_args = array(
            'body'    => wp_json_encode( $body ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => (int) ( $args['timeout'] ?? 60 ),
        );

        $attempt = 0;

        while ( $attempt <= $this->max_retries ) {
            $this->wait_for_rate_limit_slot();
            $response = call_user_func( $this->http_post, $url, $request_args );

            if ( $this->is_wp_error( $response ) ) {
                if ( $attempt < $this->max_retries ) {
                    $this->backoff_sleep( $attempt );
                    $attempt++;
                    continue;
                }

                return new WP_Error( self::ERROR_HTTP_TRANSPORT, $response->get_error_message() );
            }

            $status_code = $this->get_response_code( $response );
            $body_raw    = $this->get_response_body( $response );

            if ( $status_code >= 400 ) {
                $message = 'Image generation failed.';
                $decoded = json_decode( (string) $body_raw, true );
                if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
                    $message = (string) $decoded['error']['message'];
                }

                if ( ( 429 === $status_code || $status_code >= 500 ) && $attempt < $this->max_retries ) {
                    $this->backoff_sleep( $attempt );
                    $attempt++;
                    continue;
                }

                return new WP_Error( self::ERROR_IMAGE_API_ERROR, $message, array( 'status_code' => $status_code ) );
            }

            $data = json_decode( (string) $body_raw, true );
            if ( ! is_array( $data ) || empty( $data['predictions'][0]['bytesBase64Encoded'] ) ) {
                return new WP_Error( self::ERROR_IMAGE_API_ERROR, 'Image generation response missing image bytes.' );
            }

            return (string) $data['predictions'][0]['bytesBase64Encoded'];
        }

        return new WP_Error( self::ERROR_IMAGE_API_ERROR, 'Image generation failed.' );
    }

    /**
     * Generate an image and upload it into the WordPress media library.
     *
     * @return int|WP_Error Attachment ID.
     */
    public function generate_and_upload( string $image_prompt, string $context_title = '', array $args = array() ) {
        $base64_or_error = $this->generate_image_base64( $image_prompt, $args );
        if ( $this->is_wp_error( $base64_or_error ) ) {
            return $base64_or_error;
        }

        $bytes = base64_decode( (string) $base64_or_error );
        if ( false === $bytes || '' === $bytes ) {
            return new WP_Error( self::ERROR_IMAGE_DECODE_ERROR, 'Could not decode generated image.' );
        }

        $quality    = (int) ( $args['webp_quality'] ?? 82 );
        $converted  = $this->convert_bytes_to_webp( $bytes, $quality );
        $detected   = $this->detect_image_type( $bytes );
        $upload_bytes = $converted['bytes'] ?? $bytes;
        $extension    = $converted['extension'] ?? $detected['extension'];
        $mime_type    = $converted['mime_type'] ?? $detected['mime_type'];

        $filename_base = sanitize_title( $context_title ?: 'autoblogai-image' );
        $filename      = $filename_base ? $filename_base . '-' . uniqid() . '.' . $extension : 'autoblogai-' . uniqid() . '.' . $extension;

        $tmp_file = function_exists( 'wp_tempnam' ) ? wp_tempnam( $filename ) : tempnam( sys_get_temp_dir(), 'autoblogai_' );
        if ( ! $tmp_file ) {
            return new WP_Error( self::ERROR_IMAGE_UPLOAD_ERROR, 'Could not create temporary file.' );
        }

        if ( false === file_put_contents( $tmp_file, $upload_bytes ) ) {
            @unlink( $tmp_file );
            return new WP_Error( self::ERROR_IMAGE_UPLOAD_ERROR, 'Could not write temporary image file.' );
        }

        if ( ! function_exists( 'media_handle_sideload' ) ) {
            @unlink( $tmp_file );
            return new WP_Error( self::ERROR_IMAGE_UPLOAD_ERROR, 'WordPress media functions are not available.' );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp_file,
            'type'     => $mime_type,
        );

        $parent_post_id = (int) ( $args['parent_post_id'] ?? 0 );
        $attachment_id  = media_handle_sideload( $file_array, $parent_post_id );
        if ( $this->is_wp_error( $attachment_id ) ) {
            @unlink( $tmp_file );
            return $attachment_id;
        }

        $alt_text = $this->generate_alt_text( $image_prompt, $context_title );
        if ( $alt_text ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
        }

        return (int) $attachment_id;
    }

    private function generate_alt_text( string $image_prompt, string $context_title ): string {
        $context_title = trim( $context_title );

        if ( $this->text_client instanceof AutoblogAI_API_Client ) {
            $prompt = 'Write concise, descriptive alt text for an image. ';
            if ( '' !== $context_title ) {
                $prompt .= "Article title: \"{$context_title}\". ";
            }
            $prompt .= "Image description: \"{$image_prompt}\". Output only the alt text.";

            $alt = $this->text_client->generate_content(
                $prompt,
                array(
                    'expect_json'       => false,
                    'max_output_tokens' => 40,
                    'temperature'       => 0.2,
                )
            );

            if ( is_string( $alt ) && '' !== trim( $alt ) ) {
                $alt = trim( preg_replace( '/\s+/', ' ', $alt ) );
                $alt = substr( $alt, 0, 125 );
                return sanitize_text_field( $alt );
            }
        }

        $fallback = $context_title ?: $image_prompt;
        $fallback = trim( preg_replace( '/\s+/', ' ', $fallback ) );
        $fallback = substr( $fallback, 0, 125 );
        return sanitize_text_field( $fallback );
    }

    private function detect_image_type( string $bytes ): array {
        $mime = 'image/png';
        if ( function_exists( 'getimagesizefromstring' ) ) {
            $info = @getimagesizefromstring( $bytes );
            if ( is_array( $info ) && ! empty( $info['mime'] ) ) {
                $mime = (string) $info['mime'];
            }
        }

        switch ( $mime ) {
            case 'image/jpeg':
                return array( 'mime_type' => 'image/jpeg', 'extension' => 'jpg' );
            case 'image/gif':
                return array( 'mime_type' => 'image/gif', 'extension' => 'gif' );
            case 'image/webp':
                return array( 'mime_type' => 'image/webp', 'extension' => 'webp' );
            case 'image/png':
            default:
                return array( 'mime_type' => 'image/png', 'extension' => 'png' );
        }
    }

    private function convert_bytes_to_webp( string $bytes, int $quality ): array {
        $quality = max( 40, min( 95, $quality ) );

        if ( class_exists( 'Imagick' ) ) {
            try {
                $imagick = new Imagick();
                $imagick->readImageBlob( $bytes );
                $imagick->setImageFormat( 'webp' );
                $imagick->setImageCompressionQuality( $quality );
                if ( method_exists( $imagick, 'stripImage' ) ) {
                    $imagick->stripImage();
                }

                return array(
                    'bytes'     => $imagick->getImagesBlob(),
                    'extension' => 'webp',
                    'mime_type' => 'image/webp',
                );
            } catch ( Throwable $e ) {
                // Fall through to GD.
            }
        }

        if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagewebp' ) ) {
            $image = @imagecreatefromstring( $bytes );
            if ( false !== $image ) {
                ob_start();
                imagewebp( $image, null, $quality );
                $webp = ob_get_clean();
                imagedestroy( $image );

                if ( is_string( $webp ) && '' !== $webp ) {
                    return array(
                        'bytes'     => $webp,
                        'extension' => 'webp',
                        'mime_type' => 'image/webp',
                    );
                }
            }
        }

        return array();
    }

    private function wait_for_rate_limit_slot(): void {
        $limit = (int) $this->rate_limit_per_minute;
        if ( $limit < 1 ) {
            return;
        }

        $now = (int) call_user_func( $this->time_fn );
        $history = call_user_func( $this->get_transient, AutoblogAI_API_Client::TRANSIENT_RATE_LOG );
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        $history = array_values(
            array_filter(
                $history,
                static function ( $timestamp ) use ( $now ) {
                    return is_numeric( $timestamp ) && (int) $timestamp > ( $now - 60 );
                }
            )
        );

        if ( count( $history ) >= $limit ) {
            sort( $history );
            $oldest = (int) $history[0];
            $wait_seconds = ( $oldest + 60 ) - $now;
            if ( $wait_seconds > 0 ) {
                call_user_func( $this->sleep_fn, $wait_seconds );
                $now = (int) call_user_func( $this->time_fn );
                $history = array_values(
                    array_filter(
                        $history,
                        static function ( $timestamp ) use ( $now ) {
                            return is_numeric( $timestamp ) && (int) $timestamp > ( $now - 60 );
                        }
                    )
                );
            }
        }

        $history[] = $now;
        call_user_func( $this->set_transient, AutoblogAI_API_Client::TRANSIENT_RATE_LOG, $history, 60 );
    }

    private function backoff_sleep( int $attempt ): void {
        $seconds = $this->initial_backoff_seconds * ( 2 ** $attempt );
        call_user_func( $this->sleep_fn, $seconds );
    }

    private function get_response_code( $response ): int {
        if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
            return (int) wp_remote_retrieve_response_code( $response );
        }

        return (int) ( $response['response']['code'] ?? 0 );
    }

    private function get_response_body( $response ): string {
        if ( function_exists( 'wp_remote_retrieve_body' ) ) {
            return (string) wp_remote_retrieve_body( $response );
        }

        return (string) ( $response['body'] ?? '' );
    }

    private function is_wp_error( $thing ): bool {
        if ( function_exists( 'is_wp_error' ) ) {
            return is_wp_error( $thing );
        }

        return $thing instanceof WP_Error;
    }
}
