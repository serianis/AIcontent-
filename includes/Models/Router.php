<?php

namespace SmartContentAI\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Router {
    
    private ModelManager $model_manager;
    private array $routing_config;
    
    public function __construct( ModelManager $model_manager ) {
        $this->model_manager = $model_manager;
        $this->load_routing_config();
    }
    
    private function load_routing_config(): void {
        $this->routing_config = array(
            'routing_mode' => get_option( 'smartcontentai_routing_mode', 'auto' ),
            'fallback_enabled' => (bool) get_option( 'smartcontentai_fallback_enabled', true ),
            'fixed_model' => get_option( 'smartcontentai_fixed_model', '' ),
            'tier_mappings' => array(
                'cheap' => get_option( 'smartcontentai_cheap_model', '' ),
                'standard' => get_option( 'smartcontentai_standard_model', '' ),
                'premium' => get_option( 'smartcontentai_premium_model', '' ),
            ),
            'provider_models' => array(
                'openrouter' => get_option( 'smartcontentai_openrouter_api_key_model', '' ),
                'openai' => get_option( 'smartcontentai_openai_api_key_model', '' ),
                'anthropic' => get_option( 'smartcontentai_anthropic_api_key_model', '' ),
                'gemini' => get_option( 'smartcontentai_gemini_api_key_model', '' ),
            ),
        );
    }
    
    /**
     * Get provider-specific model if configured
     */
    private function get_provider_specific_model( array $options ): ?array {
        $provider = $options['preferred_provider'] ?? null;
        
        if ( ! $provider ) {
            return null;
        }
        
        $provider_slug = $this->get_provider_slug( $provider );
        if ( ! $provider_slug ) {
            return null;
        }
        
        $selected_model = $this->routing_config['provider_models'][$provider_slug] ?? '';
        
        if ( empty( $selected_model ) ) {
            return null;
        }
        
        // Validate the selected model
        $validation = $this->model_manager->validate_model_selection( $selected_model );
        if ( ! $validation['valid'] ) {
            return null;
        }
        
        return array(
            'success' => true,
            'error' => null,
            'model' => $selected_model,
            'provider' => $this->model_manager->get_provider_for_model( $selected_model ),
            'routing_mode' => 'provider_specific',
        );
    }
    
    /**
     * Convert provider name to slug
     */
    private function get_provider_slug( $provider ): ?string {
        $provider_slugs = array(
            'openrouter' => 'openrouter',
            'OpenRouter' => 'openrouter',
            'openai' => 'openai',
            'OpenAI' => 'openai',
            'anthropic' => 'anthropic',
            'Anthropic' => 'anthropic',
            'gemini' => 'gemini',
            'Gemini' => 'gemini',
        );
        
        return $provider_slugs[$provider] ?? null;
    }
    
    public function select_model( array $messages, array $options = array() ): array {
        $this->load_routing_config(); // Refresh config
        
        // Check for provider-specific model selection first
        $provider_model = $this->get_provider_specific_model( $options );
        if ( $provider_model ) {
            return $provider_model;
        }
        
        if ( $this->routing_config['routing_mode'] === 'fixed' ) {
            return $this->handle_fixed_routing( $options );
        }
        
        if ( $this->routing_config['routing_mode'] === 'manual' ) {
            return $this->handle_manual_routing( $messages, $options );
        }
        
        return $this->handle_auto_routing( $messages, $options );
    }
    
    private function handle_fixed_routing( array $options ): array {
        $model_slug = $this->routing_config['fixed_model'];
        
        if ( empty( $model_slug ) ) {
            // Fallback to auto routing if no fixed model is set
            return $this->handle_auto_routing( array(), $options );
        }
        
        $validation = $this->model_manager->validate_model_selection( $model_slug );
        if ( ! $validation['valid'] ) {
            if ( $this->routing_config['fallback_enabled'] ) {
                return $this->select_fallback_model( $model_slug, $options );
            }
            
            return array(
                'success' => false,
                'error' => $validation['error'],
                'model' => null,
            );
        }
        
        return array(
            'success' => true,
            'error' => null,
            'model' => $model_slug,
            'provider' => $this->model_manager->get_provider_for_model( $model_slug ),
        );
    }
    
    private function handle_auto_routing( array $messages, array $options ): array {
        $complexity = $this->estimate_complexity( $messages );
        $target_tier = $this->complexity_to_tier( $complexity );
        
        // Get the specific model for this tier from configuration
        $model_slug = $this->routing_config['tier_mappings'][$target_tier];
        
        if ( empty( $model_slug ) ) {
            // Fallback to best available model for tier
            $model_slug = $this->model_manager->get_best_model_for_tier( $target_tier );
        }
        
        if ( ! $model_slug ) {
            if ( $this->routing_config['fallback_enabled'] ) {
                return $this->select_fallback_model( $model_slug, $options );
            }
            
            return array(
                'success' => false,
                'error' => "No available models for tier: {$target_tier}",
                'model' => null,
            );
        }
        
        $validation = $this->model_manager->validate_model_selection( $model_slug );
        if ( ! $validation['valid'] ) {
            if ( $this->routing_config['fallback_enabled'] ) {
                return $this->select_fallback_model( $model_slug, $options );
            }
            
            return array(
                'success' => false,
                'error' => $validation['error'],
                'model' => null,
            );
        }
        
        return array(
            'success' => true,
            'error' => null,
            'model' => $model_slug,
            'provider' => $this->model_manager->get_provider_for_model( $model_slug ),
            'complexity' => $complexity,
            'tier' => $target_tier,
        );
    }
    
    private function handle_manual_routing( array $messages, array $options ): array {
        $complexity = $this->estimate_complexity( $messages );
        $target_tier = $this->complexity_to_tier( $complexity );
        
        // Get the manually selected model for this tier
        $model_slug = $this->routing_config['tier_mappings'][$target_tier];
        
        if ( empty( $model_slug ) ) {
            // Fallback to auto routing if no manual model is set
            return $this->handle_auto_routing( $messages, $options );
        }
        
        $validation = $this->model_manager->validate_model_selection( $model_slug );
        if ( ! $validation['valid'] ) {
            if ( $this->routing_config['fallback_enabled'] ) {
                return $this->select_fallback_model( $model_slug, $options );
            }
            
            return array(
                'success' => false,
                'error' => $validation['error'],
                'model' => null,
            );
        }
        
        return array(
            'success' => true,
            'error' => null,
            'model' => $model_slug,
            'provider' => $this->model_manager->get_provider_for_model( $model_slug ),
            'complexity' => $complexity,
            'tier' => $target_tier,
            'routing_mode' => 'manual',
        );
    }
    
    private function select_fallback_model( string $failed_model, array $options ): array {
        $failed_tier = $this->model_manager->get_model_tier( $failed_model );
        
        // Try same tier first
        $fallback_model = $this->model_manager->get_best_model_for_tier( $failed_tier );
        if ( $fallback_model && $fallback_model !== $failed_model ) {
            $validation = $this->model_manager->validate_model_selection( $fallback_model );
            if ( $validation['valid'] ) {
                return array(
                    'success' => true,
                    'error' => null,
                    'model' => $fallback_model,
                    'provider' => $this->model_manager->get_provider_for_model( $fallback_model ),
                    'fallback' => true,
                    'fallback_reason' => 'same_tier_alternative',
                );
            }
        }
        
        // Try lower tiers
        $tiers = array( 'premium', 'standard', 'cheap' );
        $current_tier_index = array_search( $failed_tier, $tiers, true );
        
        for ( $i = $current_tier_index + 1; $i < count( $tiers ); $i++ ) {
            $fallback_tier = $tiers[$i];
            $fallback_model = $this->model_manager->get_best_model_for_tier( $fallback_tier );
            
            if ( $fallback_model ) {
                $validation = $this->model_manager->validate_model_selection( $fallback_model );
                if ( $validation['valid'] ) {
                    return array(
                        'success' => true,
                        'error' => null,
                        'model' => $fallback_model,
                        'provider' => $this->model_manager->get_provider_for_model( $fallback_model ),
                        'fallback' => true,
                        'fallback_reason' => 'lower_tier_fallback',
                    );
                }
            }
        }
        
        return array(
            'success' => false,
            'error' => 'No fallback models available',
            'model' => null,
        );
    }
    
    public function estimate_complexity( array $messages ): float {
        if ( empty( $messages ) ) {
            return 0.1; // Very low complexity for empty messages
        }
        
        $score = 0.0;
        
        // Character count factor (0-0.4)
        $total_chars = 0;
        foreach ( $messages as $message ) {
            if ( is_array( $message ) ) {
                $total_chars += strlen( $message['content'] ?? '' );
            } elseif ( is_string( $message ) ) {
                $total_chars += strlen( $message );
            }
        }
        $score += min( $total_chars / 5000, 0.4 );
        
        // Question count factor (0-0.2)
        $question_count = 0;
        foreach ( $messages as $message ) {
            if ( is_array( $message ) ) {
                $question_count += substr_count( $message['content'] ?? '', '?' );
            } elseif ( is_string( $message ) ) {
                $question_count += substr_count( $message, '?' );
            }
        }
        $score += min( $question_count * 0.1, 0.2 );
        
        // Code/technical indicators (0-0.2)
        $technical_indicators = 0;
        foreach ( $messages as $message ) {
            if ( is_array( $message ) ) {
                $content = $message['content'] ?? '';
            } elseif ( is_string( $message ) ) {
                $content = $message;
            } else {
                continue;
            }
            
            $technical_indicators += substr_count( strtolower( $content ), 'function' );
            $technical_indicators += substr_count( strtolower( $content ), 'class' );
            $technical_indicators += substr_count( strtolower( $content ), 'algorithm' );
            $technical_indicators += substr_count( strtolower( $content ), 'database' );
            $technical_indicators += preg_match_all( '/```[\s\S]*?```/', $content );
        }
        $score += min( $technical_indicators * 0.05, 0.2 );
        
        // Creative/writing indicators (0-0.2)
        $creative_indicators = 0;
        foreach ( $messages as $message ) {
            if ( is_array( $message ) ) {
                $content = $message['content'] ?? '';
            } elseif ( is_string( $message ) ) {
                $content = $message;
            } else {
                continue;
            }
            
            $creative_indicators += substr_count( strtolower( $content ), 'story' );
            $creative_indicators += substr_count( strtolower( $content ), 'creative' );
            $creative_indicators += substr_count( strtolower( $content ), 'poem' );
            $creative_indicators += substr_count( strtolower( $content ), 'article' );
        }
        $score += min( $creative_indicators * 0.05, 0.2 );
        
        return min( $score, 1.0 );
    }
    
    private function complexity_to_tier( float $complexity ): string {
        if ( $complexity < 0.3 ) {
            return 'cheap';
        } elseif ( $complexity < 0.7 ) {
            return 'standard';
        }
        return 'premium';
    }
    
    public function is_low_confidence_response( string $response ): bool {
        $low_confidence_phrases = array(
            'i am not sure',
            'i cannot',
            'i do not know',
            'i am uncertain',
            'cannot reliably',
            'not confident',
            'i do not have enough information',
        );
        
        $response_lower = strtolower( $response );
        
        foreach ( $low_confidence_phrases as $phrase ) {
            if ( strpos( $response_lower, $phrase ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    public function should_escalate( string $response, float $complexity ): bool {
        return $this->is_low_confidence_response( $response ) || $complexity > 0.8;
    }
    
    public function get_routing_stats(): array {
        $all_models = $this->model_manager->get_all_models();
        $available_models = $this->model_manager->get_available_models();
        
        $stats = array(
            'total_models' => count( $all_models ),
            'available_models' => count( $available_models ),
            'routing_mode' => $this->routing_config['routing_mode'],
            'fallback_enabled' => $this->routing_config['fallback_enabled'],
            'tiers' => array(),
        );
        
        foreach ( $this->model_manager->get_available_tiers() as $tier ) {
            $tier_models = $this->model_manager->get_available_models_by_tier( $tier );
            $stats['tiers'][$tier] = array(
                'count' => count( $tier_models ),
                'cheapest' => null,
                'most_expensive' => null,
            );
            
            if ( ! empty( $tier_models ) ) {
                $costs = array_column( $tier_models, 'cost_per_1k' );
                $stats['tiers'][$tier]['cheapest'] = min( $costs );
                $stats['tiers'][$tier]['most_expensive'] = max( $costs );
            }
        }
        
        return $stats;
    }
    
    public function update_routing_config( array $config ): void {
        if ( isset( $config['routing_mode'] ) ) {
            update_option( 'smartcontentai_routing_mode', $config['routing_mode'] );
        }
        
        if ( isset( $config['fallback_enabled'] ) ) {
            update_option( 'smartcontentai_fallback_enabled', $config['fallback_enabled'] ? 1 : 0 );
        }
        
        if ( isset( $config['fixed_model'] ) ) {
            update_option( 'smartcontentai_fixed_model', $config['fixed_model'] );
        }
        
        if ( isset( $config['tier_mappings'] ) && is_array( $config['tier_mappings'] ) ) {
            foreach ( $config['tier_mappings'] as $tier => $model ) {
                update_option( "smartcontentai_{$tier}_model", $model );
            }
        }
        
        $this->load_routing_config(); // Refresh after update
    }
}
