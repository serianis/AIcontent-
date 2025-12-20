<?php

namespace SmartContentAI\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ProviderInterface {
    
    /**
     * Get provider name
     */
    public function get_name(): string;
    
    /**
     * Get provider slug
     */
    public function get_slug(): string;
    
    /**
     * Get base URL for API requests
     */
    public function get_base_url(): string;
    
    /**
     * Get headers for API requests
     */
    public function get_headers( string $api_key ): array;
    
    /**
     * Get available models for this provider
     */
    public function get_models(): array;
    
    /**
     * Make API request
     */
    public function make_request( string $model, array $messages, array $options = array() ): array;
    
    /**
     * Parse response from API
     */
    public function parse_response( array $response ): array;
    
    /**
     * Get error message from failed response
     */
    public function get_error_message( array $response ): string;
    
    /**
     * Check if response is successful
     */
    public function is_successful( array $response ): bool;
    
    /**
     * Get max tokens for model
     */
    public function get_max_tokens( string $model ): int;
    
    /**
     * Get cost per 1k tokens for model
     */
    public function get_cost_per_1k( string $model ): float;
}
