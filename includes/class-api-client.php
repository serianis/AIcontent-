<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AutoblogAI_API_Client {
    public const OPTION_API_KEY      = 'autoblogai_api_key';
    public const OPTION_TEMPERATURE  = 'autoblogai_temperature';
    public const TRANSIENT_RATE_LOG  = 'autoblogai_gemini_rate_history';

    public const ERROR_MISSING_API_KEY   = 'autoblogai_api_key_missing';
    public const ERROR_HTTP_UNAVAILABLE  = 'autoblogai_http_unavailable';
    public const ERROR_HTTP_TRANSPORT    = 'autoblogai_http_transport_error';
    public const ERROR_RATE_LIMITED      = 'autoblogai_rate_limited';
    public const ERROR_HTTP_ERROR        = 'autoblogai_http_error';
    public const ERROR_API_ERROR         = 'autoblogai_api_error';
    public const ERROR_PARSE_ERROR       = 'autoblogai_parse_error';
    public const ERROR_JSON_DECODE_ERROR = 'autoblogai_json_decode_error';

    private string $api_key;
    private string $base_url;
    private string $text_model;
    private float $temperature;
    private int $max_retries;
    private float $initial_backoff_seconds;
    private int $rate_limit_per_minute;

    /** @var callable|null */
    private $http_post;
    /** @var callable */
    private $time_fn;
    /** @var callable */
    private $sleep_fn;
    /** @var callable */
    private $get_transient;
    /** @var callable */
    private $set_transient;

    public function __construct( array $args = array() ) {
        $this->api_key  = (string) ( $args['api_key'] ?? get_option( self::OPTION_API_KEY, '' ) );
        $this->base_url = (string) ( $args['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/' );
        $this->text_model = (string) ( $args['text_model'] ?? apply_filters( 'autoblogai_gemini_text_model', 'gemini-2.0-flash-exp' ) );

        $temperature       = $args['temperature'] ?? get_option( self::OPTION_TEMPERATURE, 0.4 );
        $this->temperature = $this->normalize_temperature( $temperature );

        $this->max_retries             = (int) ( $args['max_retries'] ?? 2 );
        $this->initial_backoff_seconds = (float) ( $args['initial_backoff_seconds'] ?? 1.0 );
        $this->rate_limit_per_minute   = (int) ( $args['rate_limit_per_minute'] ?? apply_filters( 'autoblogai_rate_limit_per_minute', 60 ) );

        $this->http_post = $args['http_post'] ?? ( function_exists( 'wp_remote_post' ) ? 'wp_remote_post' : null );
        $this->time_fn   = $args['time_fn'] ?? 'time';
        $this->sleep_fn  = $args['sleep_fn'] ?? static function ( $seconds ) {
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

    public function generate_text( string $prompt, array $args = array() ) {
        return $this->generate_json( $prompt, $args );
    }

    public function generate_json( string $prompt, array $args = array() ) {
        $args['expect_json'] = true;
        $args['response_mime_type'] = $args['response_mime_type'] ?? 'application/json';
        return $this->generate_content( $prompt, $args );
    }

    /**
     * Generate content from Gemini.
     *
     * @return string|array|WP_Error Returns string for plain text; array for expect_json; WP_Error on failure.
     */
    public function generate_content( string $prompt, array $args = array() ) {
        if ( '' === trim( $this->api_key ) ) {
            return new WP_Error( self::ERROR_MISSING_API_KEY, 'Missing Gemini API key.' );
        }

        if ( ! $this->http_post ) {
            return new WP_Error( self::ERROR_HTTP_UNAVAILABLE, 'WordPress HTTP functions are not available.' );
        }

        $expect_json       = (bool) ( $args['expect_json'] ?? false );
        $response_mime_type = $args['response_mime_type'] ?? null;
        $temperature       = isset( $args['temperature'] ) ? $this->normalize_temperature( $args['temperature'] ) : $this->temperature;
        $max_output_tokens = isset( $args['max_output_tokens'] ) ? (int) $args['max_output_tokens'] : $this->compute_max_output_tokens( $prompt, $expect_json );
        $max_output_tokens = (int) apply_filters( 'autoblogai_max_output_tokens', $max_output_tokens, $prompt, $args );

        $url = $this->base_url . $this->text_model . ':generateContent?key=' . rawurlencode( $this->api_key );

        $body = array(
            'contents'         => array(
                array( 'parts' => array( array( 'text' => $prompt ) ) ),
            ),
            'generationConfig' => array(
                'temperature'    => $temperature,
                'maxOutputTokens' => $max_output_tokens,
            ),
        );

        if ( $response_mime_type ) {
            $body['generationConfig']['responseMimeType'] = $response_mime_type;
        }

        $request_args = array(
            'body'    => wp_json_encode( $body ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => (int) ( $args['timeout'] ?? 60 ),
        );

        $attempt = 0;
        $last_error = null;

        while ( $attempt <= $this->max_retries ) {
            $this->wait_for_rate_limit_slot();

            $response = call_user_func( $this->http_post, $url, $request_args );

            if ( $this->is_wp_error( $response ) ) {
                $last_error = new WP_Error(
                    self::ERROR_HTTP_TRANSPORT,
                    $this->redact_api_key_from_string( $response->get_error_message() )
                );

                if ( $attempt < $this->max_retries ) {
                    $this->backoff_sleep( $attempt );
                    $attempt++;
                    continue;
                }

                return $last_error;
            }

            $status_code = $this->get_response_code( $response );
            $body_raw    = $this->get_response_body( $response );

            if ( $status_code >= 400 ) {
                $wp_error = $this->map_http_error( $status_code, $body_raw );
                $last_error = $wp_error;

                if ( $this->is_retriable_status( $status_code ) && $attempt < $this->max_retries ) {
                    $this->backoff_sleep( $attempt, $this->get_retry_after_seconds( $response ) );
                    $attempt++;
                    continue;
                }

                return $wp_error;
            }

            $data = json_decode( (string) $body_raw, true );

            if ( ! is_array( $data ) ) {
                return new WP_Error( self::ERROR_PARSE_ERROR, 'Could not parse Gemini response.' );
            }

            if ( isset( $data['error'] ) ) {
                return new WP_Error( self::ERROR_API_ERROR, $this->redact_api_key_from_string( (string) ( $data['error']['message'] ?? 'Gemini API error' ) ) );
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ( '' === $text ) {
                return new WP_Error( self::ERROR_PARSE_ERROR, 'Gemini response missing candidates.' );
            }

            if ( ! $expect_json ) {
                return trim( (string) $text );
            }

            $raw_json = str_replace( array( "```json", "```" ), '', (string) $text );
            $decoded  = json_decode( $raw_json, true );

            if ( ! is_array( $decoded ) ) {
                return new WP_Error( self::ERROR_JSON_DECODE_ERROR, 'Gemini returned invalid JSON.' );
            }

            return $decoded;
        }

        return $last_error instanceof WP_Error ? $last_error : new WP_Error( self::ERROR_HTTP_ERROR, 'Gemini request failed.' );
    }

    private function normalize_temperature( $temperature ): float {
        $temperature = (float) $temperature;
        $temperature = max( 0.2, min( 0.6, $temperature ) );
        return round( $temperature, 2 );
    }

    private function compute_max_output_tokens( string $prompt, bool $expect_json ): int {
        $base = $expect_json ? 2048 : 256;
        $bonus = (int) floor( strlen( $prompt ) / 40 );
        $tokens = $base + $bonus;
        $max = $expect_json ? 4096 : 512;
        $min = $expect_json ? 1024 : 64;
        return max( $min, min( $max, $tokens ) );
    }

    private function wait_for_rate_limit_slot(): void {
        $limit = (int) $this->rate_limit_per_minute;
        if ( $limit < 1 ) {
            return;
        }

        $now = (int) call_user_func( $this->time_fn );
        $history = call_user_func( $this->get_transient, self::TRANSIENT_RATE_LOG );
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
        call_user_func( $this->set_transient, self::TRANSIENT_RATE_LOG, $history, 60 );
    }

    private function backoff_sleep( int $attempt, ?int $retry_after = null ): void {
        if ( null !== $retry_after && $retry_after > 0 ) {
            call_user_func( $this->sleep_fn, $retry_after );
            return;
        }

        $seconds = $this->initial_backoff_seconds * ( 2 ** $attempt );
        call_user_func( $this->sleep_fn, $seconds );
    }

    private function is_retriable_status( int $status_code ): bool {
        return 429 === $status_code || $status_code >= 500;
    }

    private function map_http_error( int $status_code, string $body_raw ): WP_Error {
        $message = '';
        $decoded = json_decode( $body_raw, true );
        if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
            $message = (string) $decoded['error']['message'];
        }

        $message = $message ?: 'Gemini HTTP error.';
        $message = $this->redact_api_key_from_string( $message );

        if ( 429 === $status_code ) {
            return new WP_Error( self::ERROR_RATE_LIMITED, $message, array( 'status_code' => $status_code ) );
        }

        return new WP_Error( self::ERROR_HTTP_ERROR, $message, array( 'status_code' => $status_code ) );
    }

    private function redact_api_key_from_string( string $value ): string {
        if ( '' !== $this->api_key ) {
            $value = str_replace( $this->api_key, '[REDACTED]', $value );
        }
        $value = preg_replace( '/([?&]key=)([^&]+)/', '$1[REDACTED]', $value );
        return (string) $value;
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

    private function get_retry_after_seconds( $response ): ?int {
        $headers = $response['headers']['retry-after'] ?? null;
        if ( is_string( $headers ) && is_numeric( $headers ) ) {
            return (int) $headers;
        }

        return null;
    }

    private function is_wp_error( $thing ): bool {
        if ( function_exists( 'is_wp_error' ) ) {
            return is_wp_error( $thing );
        }

        return $thing instanceof WP_Error;
    }
}
