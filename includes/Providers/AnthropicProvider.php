<?php

namespace SmartContentAI\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AnthropicProvider implements ProviderInterface {
    
    public function get_name(): string {
        return 'Anthropic';
    }
    
    public function get_slug(): string {
        return 'anthropic';
    }
    
    public function get_base_url(): string {
        return 'https://api.anthropic.com/v1';
    }
    
    public function get_headers( string $api_key ): array {
        return array(
            'x-api-key' => $api_key,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        );
    }
    
    public function get_models(): array {
        return array(
            // Premium Models
            'claude-4.5-opus' => array(
                'name' => 'Claude 4.5 Opus (Latest Top-Tier)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.025,
                'context_window' => 200000,
            ),
            'claude-4-sonnet' => array(
                'name' => 'Claude 4 Sonnet (Creative Writing & Analysis)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.012,
                'context_window' => 200000,
            ),
            'claude-3.5-opus' => array(
                'name' => 'Claude 3.5 Opus (Coders)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.008,
                'context_window' => 200000,
            ),
            'claude-3.7-sonnet' => array(
                'name' => 'Claude 3.7 Sonnet',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.005,
                'context_window' => 200000,
            ),
            'claude-4.5-sonnet' => array(
                'name' => 'Claude 4.5 Sonnet',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.015,
                'context_window' => 200000,
            ),
            'claude-4.5-haiku' => array(
                'name' => 'Claude 4.5 Haiku',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.001,
                'context_window' => 200000,
            ),
            'claude-3-opus' => array(
                'name' => 'Claude 3 Opus',
                'tier' => 'premium',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.015,
                'context_window' => 200000,
            ),
            
            // Standard Models
            'claude-3.5-sonnet' => array(
                'name' => 'Claude 3.5 Sonnet',
                'tier' => 'standard',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.003,
                'context_window' => 200000,
            ),
            'claude-3-sonnet' => array(
                'name' => 'Claude 3 Sonnet',
                'tier' => 'standard',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.003,
                'context_window' => 200000,
            ),
            
            // Cheap Models
            'claude-3-haiku' => array(
                'name' => 'Claude 3 Haiku',
                'tier' => 'cheap',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.00025,
                'context_window' => 200000,
            ),
        );
    }
    
    public function make_request( string $model, array $messages, array $options = array() ): array {
        $url = $this->get_base_url() . '/messages';
        
        $api_key = get_option( 'smartcontentai_anthropic_api_key', '' );
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'error' => 'Missing Anthropic API key',
                'data' => null,
            );
        }
        
        $body = array(
            'model' => $model,
            'max_tokens' => $options['max_tokens'] ?? $this->get_max_tokens( $model ),
            'messages' => $this->format_messages( $messages ),
        );
        
        // Add optional parameters
        if ( isset( $options['temperature'] ) ) {
            $body['temperature'] = (float) $options['temperature'];
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
        
        return array(
            'content' => $data['content'][0]['text'] ?? '',
            'tokens_used' => $data['usage']['input_tokens'] + $data['usage']['output_tokens'],
            'model' => $data['model'] ?? '',
            'finish_reason' => $data['stop_reason'] ?? 'unknown',
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
            return $response['data']['error']['type'];
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
                // Convert roles to Anthropic format
                $role = $message['role'];
                if ( $role === 'assistant' ) {
                    $formatted[] = array(
                        'role' => 'assistant',
                        'content' => $message['content'],
                    );
                } elseif ( $role === 'system' ) {
                    // Anthropic doesn't have system messages in the same way
                    // We'll prepend it to the first user message
                    continue;
                } else {
                    // Default to user
                    $formatted[] = array(
                        'role' => 'user',
                        'content' => $message['content'],
                    );
                }
            } elseif ( is_string( $message ) ) {
                // Assume user message if just a string
                $formatted[] = array(
                    'role' => 'user',
                    'content' => $message,
                );
            }
        }
        
        // Handle system message by prepending to first user message
        $system_message = '';
        $final_messages = array();
        
        foreach ( $messages as $message ) {
            if ( is_array( $message ) && $message['role'] === 'system' ) {
                $system_message = $message['content'];
            } else {
                if ( ! empty( $system_message ) && $message['role'] === 'user' ) {
                    $final_messages[] = array(
                        'role' => 'user',
                        'content' => $system_message . "\n\n" . $message['content'],
                    );
                    $system_message = '';
                } else {
                    $final_messages[] = $message;
                }
            }
        }
        
        return !empty($final_messages) ? $final_messages : $formatted;
    }
}
