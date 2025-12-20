<?php

namespace SmartContentAI\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OpenAIProvider implements ProviderInterface {
    
    public function get_name(): string {
        return 'OpenAI';
    }
    
    public function get_slug(): string {
        return 'openai';
    }
    
    public function get_base_url(): string {
        return 'https://api.openai.com/v1';
    }
    
    public function get_headers( string $api_key ): array {
        return array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        );
    }
    
    public function get_models(): array {
        return array(
            // Premium Models
            'o3' => array(
                'name' => 'o3 (Reasoning)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.02,
                'context_window' => 200000,
            ),
            'o3-mini' => array(
                'name' => 'o3-mini (Reasoning)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.006,
                'context_window' => 200000,
            ),

            'gpt-4o' => array(
                'name' => 'GPT-4o (Multimodal)',
                'tier' => 'premium',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.005,
                'context_window' => 128000,
            ),
            'gpt-4-turbo' => array(
                'name' => 'GPT-4 Turbo',
                'tier' => 'premium',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.01,
                'context_window' => 128000,
            ),
            'gpt-4' => array(
                'name' => 'GPT-4',
                'tier' => 'premium',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.03,
                'context_window' => 8192,
            ),
            'gpt-5.2' => array(
                'name' => 'GPT-5.2',
                'tier' => 'premium',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.015,
                'context_window' => 128000,
            ),
            
            // Standard Models
            'gpt-4o-mini' => array(
                'name' => 'GPT-4o Mini',
                'tier' => 'standard',
                'max_tokens' => 16384,
                'cost_per_1k' => 0.00015,
                'context_window' => 128000,
            ),
            'gpt-3.5-turbo' => array(
                'name' => 'GPT-3.5 Turbo',
                'tier' => 'standard',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.0005,
                'context_window' => 16385,
            ),
            
            // Cheap Models (limited availability)
            'gpt-3.5-turbo-16k' => array(
                'name' => 'GPT-3.5 Turbo 16K',
                'tier' => 'cheap',
                'max_tokens' => 16384,
                'cost_per_1k' => 0.003,
                'context_window' => 16385,
            ),
        );
    }
    
    public function make_request( string $model, array $messages, array $options = array() ): array {
        $url = $this->get_base_url() . '/chat/completions';
        
        $api_key = get_option( 'smartcontentai_openai_api_key', '' );
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'error' => 'Missing OpenAI API key',
                'data' => null,
            );
        }
        
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
                'error' => 'HTTP Error: ' . $status_code,
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
        $choice = $data['choices'][0] ?? array();
        
        return array(
            'content' => $choice['message']['content'] ?? '',
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'model' => $data['model'] ?? '',
            'finish_reason' => $choice['finish_reason'] ?? 'unknown',
        );
    }
    
    public function get_error_message( array $response ): string {
        if ( $response['success'] ) {
            return '';
        }
        
        if ( isset( $response['data']['error']['message'] ) ) {
            return $response['data']['error']['message'];
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
}
