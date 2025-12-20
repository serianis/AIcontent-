<?php

namespace SmartContentAI\Models;

use SmartContentAI\Providers\ProviderInterface;
use SmartContentAI\Providers\OpenRouterProvider;
use SmartContentAI\Providers\OpenAIProvider;
use SmartContentAI\Providers\AnthropicProvider;
use SmartContentAI\Providers\GeminiProvider;
use SmartContentAI\Providers\CustomProvider;
use SmartContentAI\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ModelManager {
    
    private array $providers = array();
    private array $custom_providers = array();
    private array $models = array();
    
    public function __construct() {
        $this->register_providers();
        $this->register_custom_providers();
    }
    
    private function register_providers(): void {
        $this->providers['openrouter'] = new OpenRouterProvider();
        $this->providers['openai'] = new OpenAIProvider();
        $this->providers['anthropic'] = new AnthropicProvider();
        $this->providers['gemini'] = new GeminiProvider();
        
        // Build unified model registry
        $this->build_model_registry();
    }
    
    private function register_custom_providers(): void {
        $database = Database::get_instance();
        $custom_providers_data = $database->get_custom_providers();
        
        foreach ( $custom_providers_data as $provider_data ) {
            $provider_slug = 'custom_' . $provider_data['id'];
            $this->custom_providers[ $provider_slug ] = new CustomProvider( $provider_data );
        }
        
        // Merge custom models with predefined models
        $this->build_model_registry();
    }
    
    private function build_model_registry(): void {
        // Clear existing models
        $this->models = array();
        
        // Add predefined provider models
        foreach ( $this->providers as $provider_slug => $provider ) {
            $provider_models = $provider->get_models();
            
            foreach ( $provider_models as $model_slug => $model_info ) {
                $this->models[$model_slug] = array(
                    'slug' => $model_slug,
                    'name' => $model_info['name'],
                    'tier' => $model_info['tier'],
                    'max_tokens' => $model_info['max_tokens'],
                    'cost_per_1k' => $model_info['cost_per_1k'],
                    'context_window' => $model_info['context_window'],
                    'provider' => $provider_slug,
                    'provider_name' => $provider->get_name(),
                );
            }
        }
        
        // Add custom provider models
        foreach ( $this->custom_providers as $provider_slug => $provider ) {
            $provider_models = $provider->get_models();
            
            foreach ( $provider_models as $model_slug => $model_info ) {
                $this->models[$model_slug] = array(
                    'slug' => $model_slug,
                    'name' => $model_info['name'],
                    'tier' => $model_info['tier'],
                    'max_tokens' => $model_info['max_tokens'],
                    'cost_per_1k' => $model_info['cost_per_1k'],
                    'context_window' => $model_info['context_window'],
                    'provider' => $provider_slug,
                    'provider_name' => $provider->get_name(),
                );
            }
        }
    }
    
    public function get_provider( string $provider_slug ): ?ProviderInterface {
        // Check predefined providers first
        if ( isset( $this->providers[$provider_slug] ) ) {
            return $this->providers[$provider_slug];
        }
        
        // Check custom providers
        if ( isset( $this->custom_providers[$provider_slug] ) ) {
            return $this->custom_providers[$provider_slug];
        }
        
        return null;
    }
    
    public function get_all_providers(): array {
        return array_merge( $this->providers, $this->custom_providers );
    }
    
    public function get_predefined_providers(): array {
        return $this->providers;
    }
    
    public function get_custom_providers(): array {
        return $this->custom_providers;
    }
    
    public function get_all_models(): array {
        return $this->models;
    }
    
    public function get_models_by_tier( string $tier ): array {
        return array_filter( $this->models, function( $model ) use ( $tier ) {
            return $model['tier'] === $tier;
        } );
    }
    
    public function get_models_by_provider( string $provider_slug ): array {
        return array_filter( $this->models, function( $model ) use ( $provider_slug ) {
            return $model['provider'] === $provider_slug;
        } );
    }
    
    public function get_model_info( string $model_slug ): ?array {
        return $this->models[$model_slug] ?? null;
    }
    
    public function get_available_tiers(): array {
        return array( 'cheap', 'standard', 'premium' );
    }
    
    public function get_provider_for_model( string $model_slug ): ?ProviderInterface {
        $model_info = $this->get_model_info( $model_slug );
        if ( ! $model_info ) {
            return null;
        }
        
        return $this->get_provider( $model_info['provider'] );
    }
    
    public function is_model_available( string $model_slug ): bool {
        $model_info = $this->get_model_info( $model_slug );
        if ( ! $model_info ) {
            return false;
        }
        
        $provider_slug = $model_info['provider'];
        $api_key_option = "smartcontentai_{$provider_slug}_api_key";
        
        return ! empty( get_option( $api_key_option, '' ) );
    }
    
    public function get_available_models(): array {
        return array_filter(
            $this->models,
            function( $model_info, $model_slug ) {
                return $this->is_model_available( $model_slug );
            },
            ARRAY_FILTER_USE_BOTH
        );
    }
    
    public function get_available_models_by_tier( string $tier ): array {
        return array_filter(
            $this->get_models_by_tier( $tier ),
            function( $model_info, $model_slug ) {
                return $this->is_model_available( $model_slug );
            },
            ARRAY_FILTER_USE_BOTH
        );
    }
    
    public function get_best_model_for_tier( string $tier ): ?string {
        $available_models = $this->get_available_models_by_tier( $tier );
        
        if ( empty( $available_models ) ) {
            return null;
        }
        
        // Sort by cost (cheapest first) and return the first one
        uasort( $available_models, function( $a, $b ) {
            return $a['cost_per_1k'] <=> $b['cost_per_1k'];
        } );
        
        return array_key_first( $available_models );
    }
    
    public function get_model_tier( string $model_slug ): string {
        $model_info = $this->get_model_info( $model_slug );
        return $model_info['tier'] ?? 'unknown';
    }
    
    public function get_model_cost_per_1k( string $model_slug ): float {
        $model_info = $this->get_model_info( $model_slug );
        return $model_info['cost_per_1k'] ?? 0.0;
    }
    
    public function calculate_cost( string $model_slug, int $tokens_used ): float {
        $cost_per_1k = $this->get_model_cost_per_1k( $model_slug );
        return ( $cost_per_1k * $tokens_used ) / 1000;
    }
    
    public function validate_model_selection( string $model_slug ): array {
        $model_info = $this->get_model_info( $model_slug );
        
        if ( ! $model_info ) {
            return array(
                'valid' => false,
                'error' => 'Model not found: ' . $model_slug,
            );
        }
        
        if ( ! $this->is_model_available( $model_slug ) ) {
            $provider = $model_info['provider'];
            return array(
                'valid' => false,
                'error' => "Model not available. Missing API key for provider: {$model_info['provider_name']}",
            );
        }
        
        return array(
            'valid' => true,
            'error' => null,
        );
    }
    
    public function get_fallback_model( string $preferred_model, string $fallback_tier = 'cheap' ): ?string {
        // First try to get a model from the same tier
        $preferred_tier = $this->get_model_tier( $preferred_model );
        $fallback = $this->get_best_model_for_tier( $preferred_tier );
        
        if ( $fallback && $fallback !== $preferred_model ) {
            return $fallback;
        }
        
        // Then try the specified fallback tier
        return $this->get_best_model_for_tier( $fallback_tier );
    }
    
    public function get_provider_config_map(): array {
        $config = array();
        
        // Add predefined providers
        foreach ( $this->providers as $slug => $provider ) {
            $config[$slug] = array(
                'name' => $provider->get_name(),
                'slug' => $slug,
                'base_url' => $provider->get_base_url(),
                'api_key_option' => "smartcontentai_{$slug}_api_key",
                'models' => $this->get_models_by_provider( $slug ),
                'type' => 'predefined',
            );
        }
        
        // Add custom providers
        foreach ( $this->custom_providers as $slug => $provider ) {
            $config[$slug] = array(
                'name' => $provider->get_name(),
                'slug' => $slug,
                'base_url' => $provider->get_base_url(),
                'api_key_option' => '',
                'models' => $this->get_models_by_provider( $slug ),
                'type' => 'custom',
            );
        }
        
        return $config;
    }
    
    public function refresh_custom_providers(): void {
        $this->custom_providers = array();
        $this->register_custom_providers();
    }
    
    public function is_custom_provider( string $provider_slug ): bool {
        return strpos( $provider_slug, 'custom_' ) === 0;
    }
    
    public function get_custom_provider_id( string $provider_slug ): ?int {
        if ( ! $this->is_custom_provider( $provider_slug ) ) {
            return null;
        }
        
        return (int) str_replace( 'custom_', '', $provider_slug );
    }
}
