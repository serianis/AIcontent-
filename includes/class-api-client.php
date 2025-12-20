<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use SmartContentAI\Models\ModelManager;
use SmartContentAI\Models\Router;
use SmartContentAI\Core\Database;
use SmartContentAI\Utils\Logger;

class SmartContentAI_API_Client {
    public const OPTION_TEMPERATURE  = 'smartcontentai_temperature';
    public const TRANSIENT_RATE_LOG  = 'smartcontentai_rate_history';

    public const ERROR_MISSING_API_KEY   = 'smartcontentai_api_key_missing';
    public const ERROR_HTTP_UNAVAILABLE  = 'smartcontentai_http_unavailable';
    public const ERROR_HTTP_TRANSPORT    = 'smartcontentai_http_transport_error';
    public const ERROR_RATE_LIMITED      = 'smartcontentai_rate_limited';
    public const ERROR_HTTP_ERROR        = 'smartcontentai_http_error';
    public const ERROR_API_ERROR         = 'smartcontentai_api_error';
    public const ERROR_PARSE_ERROR       = 'smartcontentai_parse_error';
    public const ERROR_JSON_DECODE_ERROR = 'smartcontentai_json_decode_error';
    public const ERROR_NO_MODEL_AVAILABLE = 'smartcontentai_no_model_available';

    private ModelManager $model_manager;
    private Router $router;
    private Database $database;
    private Logger $logger;
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
        $this->model_manager = new ModelManager();
        $this->router = new Router( $this->model_manager );
        $this->database = Database::get_instance();
        $this->logger = new Logger();

        $temperature       = $args['temperature'] ?? get_option( self::OPTION_TEMPERATURE, 0.4 );
        $this->temperature = $this->normalize_temperature( $temperature );

        $this->max_retries             = (int) ( $args['max_retries'] ?? 2 );
        $this->initial_backoff_seconds = (float) ( $args['initial_backoff_seconds'] ?? 1.0 );

        $default_rate_limit          = (int) get_option( 'smartcontentai_rate_limit_per_minute', 60 );
        $filtered_default_rate_limit = (int) apply_filters( 'smartcontentai_rate_limit_per_minute', $default_rate_limit );
        $this->rate_limit_per_minute = (int) ( $args['rate_limit_per_minute'] ?? $filtered_default_rate_limit );

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
     * Generate content using provider-agnostic routing.
     *
     * @return string|array|WP_Error Returns string for plain text; array for expect_json; WP_Error on failure.
     */
    public function generate_content( string $prompt, array $args = array() ) {
        $start_time = microtime( true );
        
        // Prepare messages array
        $messages = array(
            array( 'role' => 'user', 'content' => $prompt )
        );
        
        // Check if we should use provider-specific model selection
        $provider_specific_args = $this->get_provider_specific_args( $args );
        
        // Select model using router
        $model_selection = $this->router->select_model( $messages, $provider_specific_args );
        
        if ( ! $model_selection['success'] ) {
            return new WP_Error( self::ERROR_NO_MODEL_AVAILABLE, $model_selection['error'] );
        }
        
        $model_slug = $model_selection['model'];
        $provider = $model_selection['provider'];
        
        if ( ! $provider ) {
            return new WP_Error( self::ERROR_NO_MODEL_AVAILABLE, 'Provider not found for model: ' . $model_slug );
        }
        
        // Prepare request options
        $request_options = array(
            'temperature' => $args['temperature'] ?? $this->temperature,
            'max_tokens' => $args['max_output_tokens'] ?? $provider->get_max_tokens( $model_slug ),
            'timeout' => $args['timeout'] ?? 60,
        );
        
        $attempt = 0;
        $last_error = null;
        $used_fallback = false;
        
        while ( $attempt <= $this->max_retries ) {
            $this->wait_for_rate_limit_slot();
            
            // Make request through provider
            $response = $provider->make_request( $model_slug, $messages, $request_options );
            
            if ( $response['success'] ) {
                $parsed = $provider->parse_response( $response );
                $content = $parsed['content'];
                
                // Check if we need to escalate due to low confidence
                if ( ! $used_fallback && $this->router->should_escalate( $content, $model_selection['complexity'] ?? 0.5 ) ) {
                    // Try to get a better model
                    $fallback_selection = $this->router->select_fallback_model( $model_slug, 'premium' );
                    
                    if ( $fallback_selection['success'] && $fallback_selection['model'] !== $model_slug ) {
                        $model_slug = $fallback_selection['model'];
                        $provider = $fallback_selection['provider'];
                        $used_fallback = true;
                        $attempt = 0; // Reset attempts for new model
                        continue;
                    }
                }
                
                // Log successful request
                $this->log_request( true, $model_slug, $response, $start_time );
                
                // Return based on expected format
                if ( $args['expect_json'] ?? false ) {
                    $raw_json = str_replace( array( "```json", "```" ), '', $content );
                    $decoded = json_decode( $raw_json, true );
                    
                    if ( ! is_array( $decoded ) ) {
                        return new WP_Error( self::ERROR_JSON_DECODE_ERROR, 'Invalid JSON response.' );
                    }
                    
                    return $decoded;
                }
                
                return trim( $content );
            }
            
            $last_error = $response['error'] ?? 'Unknown error';
            
            // Try fallback on failure
            if ( ! $used_fallback && $this->should_use_fallback( $last_error ) ) {
                $fallback_selection = $this->router->select_fallback_model( $model_slug );
                
                if ( $fallback_selection['success'] ) {
                    $model_slug = $fallback_selection['model'];
                    $provider = $fallback_selection['provider'];
                    $used_fallback = true;
                    $attempt = 0; // Reset attempts for new model
                    continue;
                }
            }
            
            // Retry logic
            if ( $attempt < $this->max_retries && $this->should_retry( $last_error ) ) {
                $this->backoff_sleep( $attempt );
                $attempt++;
                continue;
            }
            
            break;
        }
        
        // Log failed request
        $this->log_request( false, $model_slug, array( 'error' => $last_error ), $start_time );
        
        return new WP_Error( self::ERROR_HTTP_ERROR, $last_error ?? 'Request failed.' );
    }
    
    private function should_retry( string $error ): bool {
        $retry_errors = array(
            'rate limit',
            'timeout',
            'connection',
            'temporary',
        );
        
        $error_lower = strtolower( $error );
        
        foreach ( $retry_errors as $retry_error ) {
            if ( strpos( $error_lower, $retry_error ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    private function should_use_fallback( string $error ): bool {
        $fallback_errors = array(
            'model not found',
            'model not available',
            'invalid model',
            'access denied',
            'authentication',
        );
        
        $error_lower = strtolower( $error );
        
        foreach ( $fallback_errors as $fallback_error ) {
            if ( strpos( $error_lower, $fallback_error ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    private function log_request( bool $success, string $model_slug, array $response, float $start_time ): void {
        $response_time_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );
        
        $model_info = $this->model_manager->get_model_info( $model_slug );
        $model_id = null;
        
        // Try to get model ID from database
        if ( $model_info ) {
            global $wpdb;
            $model_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ai_models WHERE model_slug = %s LIMIT 1",
                $model_slug
            ) );
        }
        
        $log_data = array(
            'model_id' => $model_id,
            'request_type' => 'text',
            'tokens_used' => $response['tokens_used'] ?? 0,
            'response_time_ms' => $response_time_ms,
            'success' => $success ? 1 : 0,
            'error_message' => $success ? null : ( $response['error'] ?? null ),
        );
        
        if ( $success && isset( $response['tokens_used'] ) && $model_info ) {
            $log_data['cost'] = $this->model_manager->calculate_cost( $model_slug, $response['tokens_used'] );
        }
        
        $this->database->log_usage( $log_data );
    }

    private function normalize_temperature( $temperature ): float {
        $temperature = (float) $temperature;
        $temperature = max( 0.0, min( 1.0, $temperature ) );
        return round( $temperature, 2 );
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

    private function is_wp_error( $thing ): bool {
        if ( function_exists( 'is_wp_error' ) ) {
            return is_wp_error( $thing );
        }

        return $thing instanceof WP_Error;
    }
    
    /**
     * Get provider-specific arguments for model selection
     */
    private function get_provider_specific_args( array $args ): array {
        $provider_specific_args = $args;
        
        // Check each provider for specific model configuration
        $providers = array( 'openrouter', 'openai', 'anthropic', 'gemini' );
        
        foreach ( $providers as $provider_slug ) {
            $api_key_option = "smartcontentai_{$provider_slug}_api_key";
            $model_option = "smartcontentai_{$provider_slug}_api_key_model";
            
            // If provider has API key and specific model configured
            if ( ! empty( get_option( $api_key_option, '' ) ) && ! empty( get_option( $model_option, '' ) ) ) {
                $provider_specific_args['preferred_provider'] = $provider_slug;
                break; // Use the first provider with specific model
            }
        }
        
        return $provider_specific_args;
    }
}
