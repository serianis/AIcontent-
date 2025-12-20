<?php

namespace SmartContentAI\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GeminiProvider implements ProviderInterface {
    
    public function get_name(): string {
        return 'Google Gemini';
    }
    
    public function get_slug(): string {
        return 'gemini';
    }
    
    public function get_base_url(): string {
        return 'https://generativelanguage.googleapis.com/v1';
    }
    
    public function get_headers( string $api_key ): array {
        return array(
            'Content-Type' => 'application/json',
        );
    }
    
    public function get_models(): array {
        return array(
            // Premium Models
            'gemini-3' => array(
                'name' => 'Gemini 3 (Latest Flagship)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.008,
                'context_window' => 2097152,
            ),
            'gemini-2.5-pro' => array(
                'name' => 'Gemini 2.5 Pro (High Intelligence)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.005,
                'context_window' => 2097152,
            ),
            'gemini-2.0-pro' => array(
                'name' => 'Gemini 2.0 Pro',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.005,
                'context_window' => 2097152,
            ),
            'gemini-1.5-pro-002' => array(
                'name' => 'Gemini 1.5 Pro (002)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.0035,
                'context_window' => 2097152,
            ),
            'gemini-1.5-pro-latest' => array(
                'name' => 'Gemini 1.5 Pro (Latest)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.0035,
                'context_window' => 1048576,
            ),
            'gemini-1.5-pro' => array(
                'name' => 'Gemini 1.5 Pro',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.0035,
                'context_window' => 1048576,
            ),
            'gemini-2.0-flash-exp' => array(
                'name' => 'Gemini 2.0 Flash (Experimental)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.00015,
                'context_window' => 1048576,
            ),
            
            // Standard Models
            'gemini-2.5-flash' => array(
                'name' => 'Gemini 2.5 Flash (Faster Model)',
                'tier' => 'standard',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.0001,
                'context_window' => 1048576,
            ),
            'gemini-1.5-flash-002' => array(
                'name' => 'Gemini 1.5 Flash (002)',
                'tier' => 'standard',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.00015,
                'context_window' => 1048576,
            ),
            'gemini-2.0-flash-8b' => array(
                'name' => 'Gemini 2.0 Flash 8B',
                'tier' => 'standard',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.000075,
                'context_window' => 1048576,
            ),
            'gemini-1.5-flash' => array(
                'name' => 'Gemini 1.5 Flash',
                'tier' => 'standard',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.00015,
                'context_window' => 1048576,
            ),
            'gemini-pro' => array(
                'name' => 'Gemini Pro',
                'tier' => 'standard',
                'max_tokens' => 2048,
                'cost_per_1k' => 0.0005,
                'context_window' => 32768,
            ),
            'gemini-pro-vision' => array(
                'name' => 'Gemini Pro Vision',
                'tier' => 'standard',
                'max_tokens' => 2048,
                'cost_per_1k' => 0.00025,
                'context_window' => 16384,
            ),
            
            // Cheap Models
            'gemini-1.0-pro' => array(
                'name' => 'Gemini 1.0 Pro',
                'tier' => 'cheap',
                'max_tokens' => 2048,
                'cost_per_1k' => 0.0005,
                'context_window' => 32768,
            ),
        );
    }
    
    public function make_request( string $model, array $messages, array $options = array() ): array {
        $api_key = get_option( 'smartcontentai_gemini_api_key', '' );
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'error' => 'Missing Google Gemini API key',
                'data' => null,
            );
        }
        
        // Gemini uses a different endpoint structure
        $url = $this->get_base_url() . '/models/' . $model . ':generateContent?key=' . $api_key;
        
        $body = array(
            'contents' => $this->format_messages( $messages ),
        );
        
        // Add optional parameters
        if ( isset( $options['temperature'] ) ) {
            $body['generationConfig'] = $body['generationConfig'] ?? array();
            $body['generationConfig']['temperature'] = (float) $options['temperature'];
        }
        if ( isset( $options['max_tokens'] ) ) {
            $body['generationConfig'] = $body['generationConfig'] ?? array();
            $body['generationConfig']['maxOutputTokens'] = (int) $options['max_tokens'];
        }
        if ( isset( $options['top_p'] ) ) {
            $body['generationConfig'] = $body['generationConfig'] ?? array();
            $body['generationConfig']['topP'] = (float) $options['top_p'];
        }
        if ( isset( $options['top_k'] ) ) {
            $body['generationConfig'] = $body['generationConfig'] ?? array();
            $body['generationConfig']['topK'] = (int) $options['top_k'];
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
        $candidate = $data['candidates'][0] ?? array();
        
        return array(
            'content' => $candidate['content']['parts'][0]['text'] ?? '',
            'tokens_used' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            'model' => $data['model'] ?? '',
            'finish_reason' => $candidate['finishReason'] ?? 'unknown',
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
        return $models[$model]['max_tokens'] ?? 2048;
    }
    
    public function get_cost_per_1k( string $model ): float {
        $models = $this->get_models();
        return $models[$model]['cost_per_1k'] ?? 0.0;
    }
    
    private function format_messages( array $messages ): array {
        $contents = array();
        
        foreach ( $messages as $message ) {
            if ( is_array( $message ) && isset( $message['role'], $message['content'] ) ) {
                $role = $message['role'] === 'assistant' ? 'model' : 'user';
                $contents[] = array(
                    'role' => $role,
                    'parts' => array(
                        array(
                            'text' => $message['content'],
                        ),
                    ),
                );
            } elseif ( is_string( $message ) ) {
                // Assume user message if just a string
                $contents[] = array(
                    'role' => 'user',
                    'parts' => array(
                        array(
                            'text' => $message,
                        ),
                    ),
                );
            }
        }
        
        return $contents;
    }
}
