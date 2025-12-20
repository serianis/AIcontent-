<?php

namespace SmartContentAI\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OpenRouterProvider implements ProviderInterface {
    
    public function get_name(): string {
        return 'OpenRouter';
    }
    
    public function get_slug(): string {
        return 'openrouter';
    }
    
    public function get_base_url(): string {
        return 'https://openrouter.ai/api/v1';
    }
    
    public function get_headers( string $api_key ): array {
        return array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => function_exists( 'get_site_url' ) ? get_site_url() : '',
            'X-Title'       => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'SmartContent AI',
        );
    }
    
    public function get_models(): array {
        return array(
            // Premium Models
            'openai/gpt-5.2' => array(
                'name' => 'GPT-5.2 (Frontier)',
                'tier' => 'premium',
                'max_tokens' => 16384,
                'cost_per_1k' => 0.03,
                'context_window' => 512000,
            ),
            'openai/gpt-5.1' => array(
                'name' => 'GPT-5.1',
                'tier' => 'premium',
                'max_tokens' => 16384,
                'cost_per_1k' => 0.02,
                'context_window' => 512000,
            ),
            'anthropic/claude-4.5-opus' => array(
                'name' => 'Claude 4.5 Opus (Latest Top-Tier)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.025,
                'context_window' => 200000,
            ),
            'anthropic/claude-4-sonnet' => array(
                'name' => 'Claude 4 Sonnet (Creative Writing & Analysis)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.012,
                'context_window' => 200000,
            ),
            'anthropic/claude-3.5-opus' => array(
                'name' => 'Claude 3.5 Opus (Coders)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.008,
                'context_window' => 200000,
            ),
            'anthropic/claude-3.7-sonnet' => array(
                'name' => 'Claude 3.7 Sonnet',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.005,
                'context_window' => 200000,
            ),
            'anthropic/claude-4.5-sonnet' => array(
                'name' => 'Claude 4.5 Sonnet',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.015,
                'context_window' => 200000,
            ),
            'anthropic/claude-4.5-haiku' => array(
                'name' => 'Claude 4.5 Haiku',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.001,
                'context_window' => 200000,
            ),
            'google/gemini-3' => array(
                'name' => 'Gemini 3 (Latest Flagship)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.008,
                'context_window' => 2097152,
            ),
            'google/gemini-2.5-pro' => array(
                'name' => 'Gemini 2.5 Pro (High Intelligence)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.005,
                'context_window' => 2097152,
            ),
            'openai/o3' => array(
                'name' => 'o3 (Reasoning)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.02,
                'context_window' => 200000,
            ),
            'openai/o3-mini' => array(
                'name' => 'o3-mini (Reasoning)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.006,
                'context_window' => 200000,
            ),
            'openai/gpt-4.5-turbo' => array(
                'name' => 'GPT-4.5 Turbo',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.0075,
                'context_window' => 256000,
            ),
            'openai/gpt-4o' => array(
                'name' => 'GPT-4o (Multimodal)',
                'tier' => 'premium',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.005,
                'context_window' => 128000,
            ),
            'google/gemini-2.0-pro' => array(
                'name' => 'Gemini 2.0 Pro',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.005,
                'context_window' => 2097152,
            ),
            'google/gemini-1.5-pro-002' => array(
                'name' => 'Gemini 1.5 Pro (002)',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.0035,
                'context_window' => 2097152,
            ),
            'google/gemini-2.0-flash-exp' => array(
                'name' => 'Gemini 2.0 Flash',
                'tier' => 'premium',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.00015,
                'context_window' => 1048576,
            ),
            
            // Standard Models
            'openai/gpt-4o-mini' => array(
                'name' => 'GPT-4o Mini',
                'tier' => 'standard',
                'max_tokens' => 16384,
                'cost_per_1k' => 0.00015,
                'context_window' => 128000,
            ),
            'anthropic/claude-3.5-sonnet' => array(
                'name' => 'Claude 3.5 Sonnet',
                'tier' => 'standard',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.003,
                'context_window' => 200000,
            ),
            'google/gemini-2.5-flash' => array(
                'name' => 'Gemini 2.5 Flash (Faster Model)',
                'tier' => 'standard',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.0001,
                'context_window' => 1048576,
            ),
            'google/gemini-1.5-pro-002' => array(
                'name' => 'Gemini 1.5 Pro (002)',
                'tier' => 'standard',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.0035,
                'context_window' => 2097152,
            ),
            'meta-llama/llama-3.3-70b-instruct' => array(
                'name' => 'Llama 3.3 70B Instruct',
                'tier' => 'standard',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.00027,
                'context_window' => 131072,
            ),
            
            // Cheap Models
            'google/gemini-1.5-flash-002' => array(
                'name' => 'Gemini 1.5 Flash (002)',
                'tier' => 'cheap',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.000075,
                'context_window' => 1048576,
            ),
            'google/gemini-1.5-flash' => array(
                'name' => 'Gemini 1.5 Flash',
                'tier' => 'cheap',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.000075,
                'context_window' => 1000000,
            ),
            'meta-llama/llama-3.2-3b-instruct' => array(
                'name' => 'Llama 3.2 3B Instruct',
                'tier' => 'cheap',
                'max_tokens' => 131072,
                'cost_per_1k' => 0.00015,
                'context_window' => 131072,
            ),
            'microsoft/phi-3.5-mini-128k-instruct' => array(
                'name' => 'Phi-3.5 Mini 128K',
                'tier' => 'cheap',
                'max_tokens' => 128000,
                'cost_per_1k' => 0.00015,
                'context_window' => 128000,
            ),
            'deepseek/deepseek-reasoner' => array(
                'name' => 'DeepSeek Reasoner',
                'tier' => 'cheap',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.00014,
                'context_window' => 163840,
            ),
            'qwen/qwen-2.5-72b-instruct' => array(
                'name' => 'Qwen 2.5 72B Instruct',
                'tier' => 'cheap',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.00027,
                'context_window' => 131072,
            ),
        );
    }
    
    public function make_request( string $model, array $messages, array $options = array() ): array {
        $url = $this->get_base_url() . '/chat/completions';
        
        $api_key = get_option( 'smartcontentai_openrouter_api_key', '' );
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'error' => 'Missing OpenRouter API key',
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
