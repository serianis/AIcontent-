<?php

namespace SmartContentAI\Providers;

use SmartContentAI\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomProvider implements ProviderInterface {
    
    private array $config;
    private array $models_cache = array();
    
    public function __construct( array $config ) {
        $this->config = $config;
        $this->load_models();
    }
    
    public function get_name(): string {
        return $this->config['name'] ?? 'Custom Provider';
    }
    
    public function get_slug(): string {
        return $this->config['slug'] ?? 'custom';
    }
    
    public function get_base_url(): string {
        return rtrim( $this->config['base_url'] ?? '', '/' );
    }
    
    public function get_headers( string $api_key ): array {
        $headers = array(
            'Content-Type' => 'application/json',
        );
        
        $auth_type = $this->config['auth_type'] ?? 'api_key';
        
        switch ( $auth_type ) {
            case 'api_key':
                // Try multiple common API key header formats
                $api_key_value = $api_key ?: $this->config['api_key'] ?? '';
                if ( ! empty( $api_key_value ) ) {
                    $headers['Authorization'] = 'Bearer ' . $api_key_value;
                }
                break;
                
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $api_key;
                break;
                
            case 'custom_header':
                if ( ! empty( $this->config['api_key'] ) ) {
                    $headers['X-API-Key'] = $this->config['api_key'];
                }
                break;
        }
        
        // Add custom headers if provided
        if ( ! empty( $this->config['custom_headers'] ) ) {
            $custom_headers = $this->parse_custom_headers( $this->config['custom_headers'] );
            $headers = array_merge( $headers, $custom_headers );
        }
        
        // Add WordPress site info if available
        if ( function_exists( 'get_site_url' ) ) {
            $headers['HTTP-Referer'] = get_site_url();
        }
        if ( function_exists( 'get_bloginfo' ) ) {
            $headers['X-Title'] = get_bloginfo( 'name', 'SmartContent AI' );
        }
        
        return $headers;
    }
    
    public function get_models(): array {
        if ( empty( $this->models_cache ) ) {
            $this->load_models();
        }
        
        return $this->models_cache;
    }
    
    private function load_models(): void {
        if ( ! isset( $this->config['id'] ) ) {
            return;
        }
        
        $database = Database::get_instance();
        $models = $database->get_custom_provider_models( (int) $this->config['id'] );
        
        foreach ( $models as $model ) {
            $this->models_cache[ $model['model_slug'] ] = array(
                'name' => $model['model_name'],
                'tier' => $model['tier'],
                'max_tokens' => (int) $model['max_tokens'],
                'cost_per_1k' => (float) $model['cost_per_1k'],
                'context_window' => (int) $model['context_window'],
            );
        }
    }
    
    public function make_request( string $model, array $messages, array $options = array() ): array {
        $base_url = $this->get_base_url();
        if ( empty( $base_url ) ) {
            return array(
                'success' => false,
                'error' => 'No base URL configured for custom provider',
                'data' => null,
            );
        }
        
        $api_key = $this->config['api_key'] ?? '';
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'error' => 'No API key configured for custom provider: ' . $this->get_name(),
                'data' => null,
            );
        }
        
        // Try different endpoint patterns for OpenAI-compatible APIs
        $endpoints = array(
            '/chat/completions',
            '/v1/chat/completions',
            '/api/chat/completions',
            '/v1/completions',
        );
        
        $last_error = '';
        
        foreach ( $endpoints as $endpoint ) {
            $url = $base_url . $endpoint;
            
            $result = $this->try_endpoint( $url, $model, $messages, $options );
            
            if ( $result['success'] ) {
                return $result;
            }
            
            $last_error = $result['error'];
        }
        
        // If all endpoints failed, return the last error
        return array(
            'success' => false,
            'error' => 'All endpoints failed. Last error: ' . $last_error,
            'data' => null,
        );
    }
    
    private function try_endpoint( string $url, string $model, array $messages, array $options ): array {
        $body = array(
            'model' => $model,
            'messages' => $this->format_messages( $messages ),
        );
        
        // Add optional parameters
        if ( isset( $options['temperature'] ) ) {
            $body['temperature'] = (float) $options['temperature'];
        }
        if ( isset( $options['max_tokens'] ) ) {
            $body['max_tokens'] = (int) $options['max_tokens'];
        }
        if ( isset( $options['stream'] ) && $options['stream'] ) {
            $body['stream'] = true;
        }
        
        $api_key = $this->config['api_key'] ?? '';
        $request_args = array(
            'headers' => $this->get_headers( $api_key ),
            'body' => wp_json_encode( $body ),
            'timeout' => $options['timeout'] ?? 60,
            'method' => 'POST',
        );
        
        $response = wp_remote_post( $url, $request_args );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'data' => null,
            );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $status_code = wp_remote_retrieve_response_code( $response );
        
        if ( $status_code >= 400 ) {
            return array(
                'success' => false,
                'error' => 'HTTP Error: ' . $status_code . ' - ' . $body,
                'data' => json_decode( $body, true ),
            );
        }
        
        $data = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'success' => false,
                'error' => 'JSON decode error: ' . json_last_error_msg(),
                'data' => null,
            );
        }
        
        return array(
            'success' => true,
            'error' => null,
            'data' => $data,
        );
    }
    
    public function parse_response( array $response ): array {
        if ( ! $this->is_successful( $response ) ) {
            return array(
                'content' => '',
                'tokens_used' => 0,
                'model' => '',
                'finish_reason' => 'error',
            );
        }
        
        $data = $response['data'];
        
        // Try OpenAI format first
        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return array(
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                'model' => $data['model'] ?? '',
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown',
            );
        }
        
        // Try alternative formats
        if ( isset( $data['choices'][0]['text'] ) ) {
            return array(
                'content' => $data['choices'][0]['text'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                'model' => $data['model'] ?? '',
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown',
            );
        }
        
        // Fallback
        return array(
            'content' => '',
            'tokens_used' => 0,
            'model' => '',
            'finish_reason' => 'unknown',
        );
    }
    
    public function get_error_message( array $response ): string {
        if ( $response['success'] ) {
            return '';
        }
        
        if ( isset( $response['data']['error']['message'] ) ) {
            return $response['data']['error']['message'];
        }
        
        if ( isset( $response['data']['error']['type'] ) ) {
            return $response['data']['error']['type'] . ': ' . ( $response['data']['error']['message'] ?? 'Unknown error' );
        }
        
        return $response['error'] ?? 'Unknown error';
    }
    
    public function is_successful( array $response ): bool {
        return $response['success'] ?? false;
    }
    
    public function get_max_tokens( string $model ): int {
        $models = $this->get_models();
        return $models[$model]['max_tokens'] ?? 4096;
    }
    
    public function get_cost_per_1k( string $model ): float {
        $models = $this->get_models();
        return $models[$model]['cost_per_1k'] ?? 0.0;
    }
    
    private function format_messages( array $messages ): array {
        $formatted = array();
        
        foreach ( $messages as $message ) {
            if ( is_array( $message ) && isset( $message['role'], $message['content'] ) ) {
                $formatted[] = array(
                    'role' => $message['role'],
                    'content' => $message['content'],
                );
            } elseif ( is_string( $message ) ) {
                // Assume user message if just a string
                $formatted[] = array(
                    'role' => 'user',
                    'content' => $message,
                );
            }
        }
        
        return $formatted;
    }
    
    private function parse_custom_headers( string $headers_text ): array {
        $headers = array();
        $lines = explode( "\n", $headers_text );
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( strpos( $line, ':' ) !== false ) {
                list( $key, $value ) = explode( ':', $line, 2 );
                $key = trim( $key );
                $value = trim( $value );
                
                if ( ! empty( $key ) && ! empty( $value ) ) {
                    $headers[ $key ] = $value;
                }
            }
        }
        
        return $headers;
    }
    
    public function test_connection(): array {
        $test_result = $this->make_request(
            $this->get_test_model(),
            array( array( 'role' => 'user', 'content' => 'Hello' ) ),
            array( 'max_tokens' => 10, 'timeout' => 30 )
        );
        
        return $test_result;
    }
    
    private function get_test_model(): string {
        $models = $this->get_models();
        return ! empty( $models ) ? array_key_first( $models ) : 'gpt-3.5-turbo';
    }
    
    public function import_models_from_api(): array {
        $base_url = $this->get_base_url();
        if ( empty( $base_url ) ) {
            return array(
                'success' => false,
                'error' => 'No base URL configured',
            );
        }
        
        $models_url = $base_url . '/v1/models';
        $api_key = $this->config['api_key'] ?? '';
        
        $response = wp_remote_get(
            $models_url,
            array(
                'headers' => $this->get_headers( $api_key ),
                'timeout' => 30,
            )
        );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code >= 400 ) {
            return array(
                'success' => false,
                'error' => 'HTTP Error: ' . $status_code,
            );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'success' => false,
                'error' => 'JSON decode error: ' . json_last_error_msg(),
            );
        }
        
        if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
            return array(
                'success' => false,
                'error' => 'Invalid models response format',
            );
        }
        
        $imported_count = 0;
        foreach ( $data['data'] as $model_data ) {
            if ( ! isset( $model_data['id'] ) ) {
                continue;
            }
            
            $model_info = array(
                'provider_id' => $this->config['id'],
                'model_slug' => $model_data['id'],
                'model_name' => $model_data['id'],
                'tier' => 'standard',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.000000,
                'context_window' => 8192,
                'enabled' => 1,
            );
            
            // Try to extract additional info if available
            if ( isset( $model_data['context_length'] ) ) {
                $model_info['context_window'] = (int) $model_data['context_length'];
            }
            
            $database = Database::get_instance();
            $database->save_custom_model( $model_info );
            $imported_count++;
        }
        
        // Reload models cache
        $this->load_models();
        
        return array(
            'success' => true,
            'imported_count' => $imported_count,
        );
    }
}