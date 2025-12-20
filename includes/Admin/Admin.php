<?php

namespace SmartContentAI\Admin;

use SmartContentAI\Core\Scheduler;
use SmartContentAI\Core\Security;
use SmartContentAI\Generator\Post;
use SmartContentAI\Utils\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public const OPTION_TOPICS               = 'smartcontentai_topics';
    public const OPTION_SOURCE_TYPE          = 'smartcontentai_source_type';
    public const OPTION_RSS_URL              = 'smartcontentai_rss_url';
    public const OPTION_SCHEDULER_ENABLED    = 'smartcontentai_scheduler_enabled';
    public const OPTION_SCHEDULER_FREQUENCY  = 'smartcontentai_schedule_frequency';
    public const OPTION_PROMPT_TEMPLATE      = 'smartcontentai_prompt_template_custom';
    public const OPTION_ARTICLE_TEMPLATE     = 'smartcontentai_article_template';

    private const NONCE_TOPICS    = 'smartcontentai_topics_nonce';
    private const NONCE_SOURCES   = 'smartcontentai_sources_nonce';
    private const NONCE_SCHEDULER = 'smartcontentai_scheduler_nonce';
    private const NONCE_TEMPLATES = 'smartcontentai_templates_nonce';
    private const NONCE_PREVIEW   = 'smartcontentai_preview_nonce';
    private const NONCE_GENERATE  = 'smartcontentai_generate_nonce';
    private const NONCE_CUSTOM_PROVIDER = 'smartcontentai_custom_provider_nonce';
    private const NONCE_CUSTOM_MODEL = 'smartcontentai_custom_model_nonce';

    private Post $post_generator;
    private Logger $logger;
    private Security $security;
    private Scheduler $scheduler;

    public function __construct( Post $post_generator, Logger $logger, Security $security, Scheduler $scheduler ) {
        $this->post_generator = $post_generator;
        $this->logger         = $logger;
        $this->security       = $security;
        $this->scheduler      = $scheduler;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'admin_post_smartcontentai_add_topic', array( $this, 'handle_add_topic' ) );
        add_action( 'admin_post_smartcontentai_delete_topic', array( $this, 'handle_delete_topic' ) );
        add_action( 'admin_post_smartcontentai_enqueue_topic', array( $this, 'handle_enqueue_topic' ) );

        add_action( 'admin_post_smartcontentai_save_sources', array( $this, 'handle_save_sources' ) );

        add_action( 'admin_post_smartcontentai_save_scheduler', array( $this, 'handle_save_scheduler' ) );
        add_action( 'admin_post_smartcontentai_clear_queue', array( $this, 'handle_clear_queue' ) );

        add_action( 'admin_post_smartcontentai_save_templates', array( $this, 'handle_save_templates' ) );
        
        // Custom provider actions
        add_action( 'admin_post_smartcontentai_save_custom_provider', array( $this, 'handle_save_custom_provider' ) );
        add_action( 'admin_post_smartcontentai_delete_custom_provider', array( $this, 'handle_delete_custom_provider' ) );
        add_action( 'admin_post_smartcontentai_save_custom_model', array( $this, 'handle_save_custom_model' ) );
        add_action( 'admin_post_smartcontentai_delete_custom_model', array( $this, 'handle_delete_custom_model' ) );
        
        add_action( 'wp_ajax_smartcontentai_test_custom_provider', array( $this, 'handle_ajax_test_custom_provider' ) );
        add_action( 'wp_ajax_smartcontentai_import_custom_models', array( $this, 'handle_ajax_import_custom_models' ) );
        add_action( 'wp_ajax_smartcontentai_load_custom_providers', array( $this, 'handle_ajax_load_custom_providers' ) );

        add_action( 'wp_ajax_smartcontentai_generate', array( $this, 'handle_ajax_generate' ) );
        add_action( 'wp_ajax_smartcontentai_preview_post', array( $this, 'handle_ajax_preview' ) );
        add_action( 'wp_ajax_smartcontentai_load_models', array( $this, 'handle_ajax_load_models' ) );
    }

    public function add_admin_menu(): void {
        add_menu_page(
            __( 'SmartContentAI', 'smartcontentai' ),
            'SmartContentAI',
            'manage_options',
            'smartcontentai',
            array( $this, 'render_settings_page' ),
            'dashicons-superhero',
            6
        );

        add_submenu_page( 'smartcontentai', __( 'Settings', 'smartcontentai' ), __( 'Settings', 'smartcontentai' ), 'manage_options', 'smartcontentai', array( $this, 'render_settings_page' ) );
        add_submenu_page( 'smartcontentai', __( 'Topics', 'smartcontentai' ), __( 'Topics', 'smartcontentai' ), 'manage_options', 'smartcontentai-topics', array( $this, 'render_topics_page' ) );
        add_submenu_page( 'smartcontentai', __( 'Sources', 'smartcontentai' ), __( 'Sources', 'smartcontentai' ), 'manage_options', 'smartcontentai-sources', array( $this, 'render_sources_page' ) );
        add_submenu_page( 'smartcontentai', __( 'Generator', 'smartcontentai' ), __( 'Generator', 'smartcontentai' ), 'manage_options', 'smartcontentai-generator', array( $this, 'render_generator_page' ) );
        add_submenu_page( 'smartcontentai', __( 'Scheduler', 'smartcontentai' ), __( 'Scheduler', 'smartcontentai' ), 'manage_options', 'smartcontentai-scheduler', array( $this, 'render_scheduler_page' ) );
        add_submenu_page( 'smartcontentai', __( 'Templates', 'smartcontentai' ), __( 'Templates', 'smartcontentai' ), 'manage_options', 'smartcontentai-templates', array( $this, 'render_templates_page' ) );
        add_submenu_page( 'smartcontentai', __( 'Logs', 'smartcontentai' ), __( 'Logs', 'smartcontentai' ), 'manage_options', 'smartcontentai-logs', array( $this, 'render_logs_page' ) );
    }

    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, 'smartcontentai' ) ) {
            return;
        }

        wp_enqueue_style(
            'smartcontentai-admin',
            SMARTCONTENTAI_URL . 'assets/css/admin-style.css',
            array(),
            SMARTCONTENTAI_VERSION
        );

        wp_enqueue_script(
            'smartcontentai-admin',
            SMARTCONTENTAI_URL . 'assets/js/admin-script.js',
            array( 'jquery', 'underscore' ),
            SMARTCONTENTAI_VERSION,
            true
        );

        wp_localize_script(
            'smartcontentai-admin',
            'SmartContentAIAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonces'  => array(
                    'preview'  => $this->security->create_nonce( self::NONCE_PREVIEW ),
                    'generate' => $this->security->create_nonce( self::NONCE_GENERATE ),
                    'loadModels' => wp_create_nonce('smartcontentai_load_models'),
                    'loadCustomProviders' => wp_create_nonce('smartcontentai_load_custom_providers'),
                    'testProvider' => wp_create_nonce('smartcontentai_test_provider'),
                    'importModels' => wp_create_nonce('smartcontentai_import_models'),
                    'customProvider' => $this->security->create_nonce( self::NONCE_CUSTOM_PROVIDER ),
                    'customModel' => $this->security->create_nonce( self::NONCE_CUSTOM_MODEL ),
                ),
                'i18n'    => array(
                    'missingTopic' => __( 'Please enter a topic.', 'smartcontentai' ),
                    'unknownError' => __( 'Unexpected error. Please try again.', 'smartcontentai' ),
                    'previewReady' => __( 'Preview generated.', 'smartcontentai' ),
                    'generating'   => __( 'Generating… this may take a bit.', 'smartcontentai' ),
                    'created'      => __( 'Post created successfully.', 'smartcontentai' ),
                    'viewPost'     => __( 'View post', 'smartcontentai' ),
                ),
            )
        );
    }

    public function register_settings(): void {
        // General settings
        register_setting(
            'smartcontentai_general_options',
            'smartcontentai_language',
            array(
                'sanitize_callback' => array( $this, 'sanitize_language' ),
                'default'           => 'Greek',
            )
        );

        register_setting(
            'smartcontentai_general_options',
            'smartcontentai_tone',
            array(
                'sanitize_callback' => array( $this, 'sanitize_tone' ),
                'default'           => 'Professional',
            )
        );

        register_setting(
            'smartcontentai_general_options',
            'smartcontentai_temperature',
            array(
                'sanitize_callback' => array( $this, 'sanitize_temperature' ),
                'default'           => 0.7,
            )
        );

        register_setting(
            'smartcontentai_general_options',
            'smartcontentai_rate_limit_per_minute',
            array(
                'sanitize_callback' => array( $this, 'sanitize_rate_limit' ),
                'default'           => 60,
            )
        );

        register_setting(
            'smartcontentai_general_options',
            'smartcontentai_banned_words',
            array(
                'sanitize_callback' => array( $this, 'sanitize_banned_words' ),
                'default'           => '',
            )
        );

        // Provider settings
        register_setting(
            'smartcontentai_providers_options',
            'smartcontentai_openrouter_api_key',
            array(
                'sanitize_callback' => array( $this, 'sanitize_api_key' ),
                'default'           => '',
            )
        );

        register_setting(
            'smartcontentai_providers_options',
            'smartcontentai_openai_api_key',
            array(
                'sanitize_callback' => array( $this, 'sanitize_api_key' ),
                'default'           => '',
            )
        );

        register_setting(
            'smartcontentai_providers_options',
            'smartcontentai_anthropic_api_key',
            array(
                'sanitize_callback' => array( $this, 'sanitize_api_key' ),
                'default'           => '',
            )
        );

        register_setting(
            'smartcontentai_providers_options',
            'smartcontentai_gemini_api_key',
            array(
                'sanitize_callback' => array( $this, 'sanitize_api_key' ),
                'default'           => '',
            )
        );

        // Provider model settings
        register_setting(
            'smartcontentai_providers_options',
            'smartcontentai_openrouter_api_key_model',
            array(
                'sanitize_callback' => array( $this, 'sanitize_text_field' ),
                'default'           => '',
            )
        );

        register_setting(
            'smartcontentai_providers_options',
            'smartcontentai_openai_api_key_model',
            array(
                'sanitize_callback' => array( $this, 'sanitize_text_field' ),
                'default'           => '',
            )
        );

        register_setting(
            'smartcontentai_providers_options',
            'smartcontentai_anthropic_api_key_model',
            array(
                'sanitize_callback' => array( $this, 'sanitize_text_field' ),
                'default'           => '',
            )
        );

        register_setting(
            'smartcontentai_providers_options',
            'smartcontentai_gemini_api_key_model',
            array(
                'sanitize_callback' => array( $this, 'sanitize_text_field' ),
                'default'           => '',
            )
        );

        // Routing settings
        register_setting(
            'smartcontentai_routing_options',
            'smartcontentai_routing_mode',
            array(
                'sanitize_callback' => array( $this, 'sanitize_routing_mode' ),
                'default'           => 'auto',
            )
        );

        register_setting(
            'smartcontentai_routing_options',
            'smartcontentai_fallback_enabled',
            array(
                'sanitize_callback' => array( $this, 'sanitize_boolean' ),
                'default'           => 1,
            )
        );

        register_setting(
            'smartcontentai_routing_options',
            'smartcontentai_fixed_model',
            array(
                'sanitize_callback' => array( $this, 'sanitize_text_field' ),
                'default'           => '',
            )
        );

        // Manual model selection settings
        register_setting(
            'smartcontentai_manual_options',
            'smartcontentai_cheap_model',
            array(
                'sanitize_callback' => array( $this, 'sanitize_text_field' ),
                'default'           => '',
            )
        );

        register_setting(
            'smartcontentai_manual_options',
            'smartcontentai_standard_model',
            array(
                'sanitize_callback' => array( $this, 'sanitize_text_field' ),
                'default'           => '',
            )
        );

        register_setting(
            'smartcontentai_manual_options',
            'smartcontentai_premium_model',
            array(
                'sanitize_callback' => array( $this, 'sanitize_text_field' ),
                'default'           => '',
            )
        );
    }

    public function sanitize_api_key( $value ): string {
        $value = $this->security->sanitize_text( (string) $value );

        if ( '' === trim( $value ) ) {
            return (string) get_option( 'smartcontentai_api_key', '' );
        }

        $encrypted = $this->security->encrypt_api_key( $value );
        if ( false === $encrypted || '' === $encrypted ) {
            return (string) get_option( 'smartcontentai_api_key', '' );
        }

        return $encrypted;
    }

    public function sanitize_language( $value ): string {
        $value   = $this->security->sanitize_text( (string) $value );
        $allowed = array( 'Greek', 'English' );
        return in_array( $value, $allowed, true ) ? $value : 'Greek';
    }

    public function sanitize_tone( $value ): string {
        $value   = $this->security->sanitize_text( (string) $value );
        $allowed = array( 'Professional', 'Casual', 'Journalistic' );
        return in_array( $value, $allowed, true ) ? $value : 'Professional';
    }

    public function sanitize_rate_limit( $value ): int {
        $value = (int) $value;
        if ( $value < 1 ) {
            $value = 1;
        }
        if ( $value > 300 ) {
            $value = 300;
        }
        return $value;
    }

    public function sanitize_banned_words( $value ): string {
        $value = (string) $value;
        $value = str_replace( array( "\r\n", "\n", "\r" ), ',', $value );
        $parts = array_filter( array_map( 'trim', explode( ',', $value ) ) );
        $parts = array_map( array( $this->security, 'sanitize_text' ), $parts );
        return implode( ',', array_filter( $parts ) );
    }

    public function render_settings_page(): void {
        $this->render_template(
            'admin-settings',
            array(
                'security'    => $this->security,
                'has_api_key' => '' !== (string) get_option( 'smartcontentai_api_key', '' ),
            )
        );
    }

    public function render_topics_page(): void {
        $this->render_template(
            'admin-topics',
            array(
                'topics' => $this->get_topics(),
                'nonces' => array(
                    'topics' => $this->security->create_nonce( self::NONCE_TOPICS ),
                ),
            )
        );
    }

    public function render_sources_page(): void {
        $this->render_template(
            'admin-sources',
            array(
                'nonces' => array(
                    'sources' => $this->security->create_nonce( self::NONCE_SOURCES ),
                ),
            )
        );
    }

    public function render_generator_page(): void {
        $this->render_template( 'admin-generator', array() );
    }

    public function render_scheduler_page(): void {
        $this->render_template(
            'admin-scheduler',
            array(
                'status'    => $this->scheduler->get_schedule_status(),
                'schedules' => $this->scheduler->get_admin_schedules(),
                'queue'     => $this->scheduler->get_queued_topics(),
                'nonces'    => array(
                    'scheduler' => $this->security->create_nonce( self::NONCE_SCHEDULER ),
                ),
            )
        );
    }

    public function render_templates_page(): void {
        $this->render_template(
            'admin-templates',
            array(
                'nonces' => array(
                    'templates' => $this->security->create_nonce( self::NONCE_TEMPLATES ),
                ),
            )
        );
    }

    public function render_logs_page(): void {
        $rate = $this->get_rate_limit_stats();

        $generated_posts = array();
        if ( function_exists( 'get_posts' ) ) {
            $generated_posts = get_posts(
                array(
                    'post_type'      => 'post',
                    'posts_per_page' => 10,
                    'meta_key'       => '_smartcontentai_generated',
                    'meta_value'     => 1,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                )
            );
        }

        $this->render_template(
            'admin-logs',
            array(
                'logs'            => $this->logger->get_logs( 50 ),
                'stats'           => $this->logger->get_logs_statistics(),
                'rate'            => $rate,
                'generated_posts' => $generated_posts,
            )
        );
    }

    public function handle_add_topic(): void {
        $this->require_manage_options_nonce( self::NONCE_TOPICS );

        $topic   = $this->security->sanitize_text( $_POST['topic'] ?? '' );
        $keyword = $this->security->sanitize_text( $_POST['keyword'] ?? '' );

        if ( '' !== trim( $topic ) ) {
            $topics   = $this->get_topics();
            $topics[] = array(
                'topic'   => $topic,
                'keyword' => $keyword,
            );
            update_option( self::OPTION_TOPICS, $topics );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai-topics' ) );
        exit;
    }

    public function handle_delete_topic(): void {
        $this->require_manage_options_nonce( self::NONCE_TOPICS );

        $index  = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
        $topics = $this->get_topics();

        if ( isset( $topics[ $index ] ) ) {
            unset( $topics[ $index ] );
            update_option( self::OPTION_TOPICS, array_values( $topics ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai-topics' ) );
        exit;
    }

    public function handle_enqueue_topic(): void {
        $this->require_manage_options_nonce( self::NONCE_TOPICS );

        $index  = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
        $topics = $this->get_topics();

        if ( isset( $topics[ $index ] ) ) {
            $this->scheduler->enqueue_topic( (string) $topics[ $index ]['topic'], (string) $topics[ $index ]['keyword'] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai-scheduler' ) );
        exit;
    }

    public function handle_save_sources(): void {
        $this->require_manage_options_nonce( self::NONCE_SOURCES );

        $source_type = $this->security->sanitize_text( $_POST['source_type'] ?? 'prompt' );
        $allowed     = array( 'prompt', 'rss', 'csv' );

        if ( ! in_array( $source_type, $allowed, true ) ) {
            $source_type = 'prompt';
        }

        update_option( self::OPTION_SOURCE_TYPE, $source_type );

        $rss_url = $this->security->sanitize_url( $_POST['rss_url'] ?? '' );
        update_option( self::OPTION_RSS_URL, $rss_url );

        if ( isset( $_FILES['csv_file'] ) && ! empty( $_FILES['csv_file']['tmp_name'] ) ) {
            $this->import_topics_from_csv( $_FILES['csv_file']['tmp_name'] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai-sources' ) );
        exit;
    }

    public function handle_save_scheduler(): void {
        $this->require_manage_options_nonce( self::NONCE_SCHEDULER );

        $enabled = isset( $_POST['enabled'] ) && '1' === (string) $_POST['enabled'];
        update_option( self::OPTION_SCHEDULER_ENABLED, $enabled ? 1 : 0 );

        $frequency = $this->security->sanitize_text( $_POST['frequency'] ?? 'daily' );
        $schedules = $this->scheduler->get_admin_schedules();
        if ( ! isset( $schedules[ $frequency ] ) ) {
            $frequency = 'daily';
        }

        update_option( self::OPTION_SCHEDULER_FREQUENCY, $frequency );

        $daily_cap = isset( $_POST['daily_cap'] ) ? (int) $_POST['daily_cap'] : 10;
        $this->scheduler->set_daily_publish_cap( $daily_cap );

        if ( $enabled ) {
            $this->scheduler->schedule_events( $frequency );
        } else {
            $this->scheduler->unschedule_events();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai-scheduler' ) );
        exit;
    }

    public function handle_clear_queue(): void {
        $this->require_manage_options_nonce( self::NONCE_SCHEDULER );
        $this->scheduler->clear_queue();
        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai-scheduler' ) );
        exit;
    }

    public function handle_save_templates(): void {
        $this->require_manage_options_nonce( self::NONCE_TEMPLATES );

        $prompt_template  = isset( $_POST['prompt_template'] ) ? (string) wp_unslash( $_POST['prompt_template'] ) : '';
        $article_template = isset( $_POST['article_template'] ) ? (string) wp_unslash( $_POST['article_template'] ) : '';

        $prompt_template  = $this->sanitize_long_text( $prompt_template );
        $article_template = $this->sanitize_long_text( $article_template );

        update_option( self::OPTION_PROMPT_TEMPLATE, $prompt_template );
        update_option( self::OPTION_ARTICLE_TEMPLATE, $article_template );

        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai-templates' ) );
        exit;
    }

    public function handle_ajax_preview(): void {
        if ( ! $this->security->verify_nonce( self::NONCE_PREVIEW, 'nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'smartcontentai' ) );
        }

        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'smartcontentai' ) );
        }

        $topic   = $this->security->sanitize_text( $_POST['topic'] ?? '' );
        $keyword = $this->security->sanitize_text( $_POST['keyword'] ?? '' );

        if ( '' === trim( $topic ) ) {
            wp_send_json_error( __( 'Missing topic.', 'smartcontentai' ) );
        }

        if ( ! method_exists( $this->post_generator, 'generate_preview' ) ) {
            wp_send_json_error( __( 'Preview is not available.', 'smartcontentai' ) );
        }

        $preview = $this->post_generator->generate_preview( $topic, $keyword );

        if ( is_wp_error( $preview ) ) {
            wp_send_json_error( $preview->get_error_message() );
        }

        $preview_html = $this->build_preview_html( $preview );

        wp_send_json_success(
            array(
                'preview_html' => $preview_html,
            )
        );
    }

    public function handle_ajax_generate(): void {
        if ( ! $this->security->verify_nonce( self::NONCE_GENERATE, 'nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'smartcontentai' ) );
        }

        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'smartcontentai' ) );
        }

        $topic        = $this->security->sanitize_text( $_POST['topic'] ?? '' );
        $keyword      = $this->security->sanitize_text( $_POST['keyword'] ?? '' );
        $publish_mode = $this->security->sanitize_text( $_POST['publish_mode'] ?? 'draft' );
        $scheduled_at = $this->security->sanitize_text( $_POST['scheduled_at'] ?? '' );

        if ( '' === trim( $topic ) ) {
            wp_send_json_error( __( 'Missing topic.', 'smartcontentai' ) );
        }

        $allowed_modes = array( 'draft', 'publish', 'scheduled' );
        if ( ! in_array( $publish_mode, $allowed_modes, true ) ) {
            $publish_mode = 'draft';
        }

        $args = array(
            'publish_mode' => $publish_mode,
        );

        if ( 'scheduled' === $publish_mode ) {
            $args['scheduled_at'] = $this->normalize_scheduled_datetime( $scheduled_at );
        }

        $result = $this->post_generator->generate_and_publish( $topic, $keyword, $args );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success(
            array(
                'id'   => $result,
                'link' => function_exists( 'get_permalink' ) ? get_permalink( $result ) : '',
            )
        );
    }

    private function render_template( string $template, array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'smartcontentai' ) );
        }

        $file = plugin_dir_path( __DIR__ ) . '../templates/' . $template . '.php';
        if ( ! file_exists( $file ) ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Template not found.', 'smartcontentai' ) . '</p></div>';
            return;
        }

        include $file;
    }

    private function get_topics(): array {
        $topics = get_option( self::OPTION_TOPICS, array() );
        if ( ! is_array( $topics ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $topics as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $topic = $this->security->sanitize_text( $row['topic'] ?? '' );
            if ( '' === trim( $topic ) ) {
                continue;
            }
            $sanitized[] = array(
                'topic'   => $topic,
                'keyword' => $this->security->sanitize_text( $row['keyword'] ?? '' ),
            );
        }

        return $sanitized;
    }

    private function require_manage_options_nonce( string $nonce_action ): void {
        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'smartcontentai' ) );
        }

        if ( ! $this->security->verify_nonce( $nonce_action, '_wpnonce' ) ) {
            wp_die( esc_html__( 'Invalid nonce.', 'smartcontentai' ) );
        }
    }

    private function import_topics_from_csv( string $path ): void {
        if ( ! is_readable( $path ) ) {
            return;
        }

        $handle = fopen( $path, 'r' );
        if ( ! $handle ) {
            return;
        }

        $topics = $this->get_topics();
        $count  = 0;

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( $count > 200 ) {
                break;
            }

            $topic   = $this->security->sanitize_text( $row[0] ?? '' );
            $keyword = $this->security->sanitize_text( $row[1] ?? '' );

            if ( '' === trim( $topic ) ) {
                continue;
            }

            $topics[] = array(
                'topic'   => $topic,
                'keyword' => $keyword,
            );

            $count++;
        }

        fclose( $handle );

        if ( $count > 0 ) {
            update_option( self::OPTION_TOPICS, $topics );
        }
    }

    private function sanitize_long_text( string $value ): string {
        $value = trim( $value );

        if ( function_exists( 'sanitize_textarea_field' ) ) {
            return sanitize_textarea_field( $value );
        }

        return preg_replace( '/<[^>]+>/', '', $value );
    }

    private function normalize_scheduled_datetime( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        $value = str_replace( 'T', ' ', $value );

        $timestamp = strtotime( $value );
        if ( ! $timestamp ) {
            return '';
        }

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    private function build_preview_html( array $preview ): string {
        $title = $this->escape_html( (string) ( $preview['title'] ?? '' ) );
        $meta  = $this->escape_html( (string) ( $preview['meta_description'] ?? '' ) );

        $content = (string) ( $preview['content_html'] ?? '' );
        $content = $this->sanitize_preview_html( $content );

        $out  = '<h2>' . $title . '</h2>';
        $out .= '<p class="smartcontentai-muted"><strong>' . $this->escape_html( __( 'Meta:', 'smartcontentai' ) ) . '</strong> ' . $meta . '</p>';
        $out .= '<hr />';
        $out .= $content;

        return $out;
    }

    private function sanitize_preview_html( string $html ): string {
        if ( function_exists( 'wp_kses_post' ) ) {
            return (string) wp_kses_post( $html );
        }
        return $html;
    }

    private function escape_html( string $value ): string {
        if ( function_exists( 'esc_html' ) ) {
            return (string) esc_html( $value );
        }
        return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
    }

    private function get_rate_limit_stats(): array {
        $history = get_transient( 'smartcontentai_gemini_rate_history' );
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        $now  = time();
        $used = 0;
        foreach ( $history as $timestamp ) {
            if ( is_numeric( $timestamp ) && (int) $timestamp > ( $now - 60 ) ) {
                $used++;
            }
        }

        return array(
            'used_last_minute' => $used,
            'limit'            => (int) get_option( 'smartcontentai_rate_limit_per_minute', 60 ),
        );
    }

    public function sanitize_temperature( $value ): float {
        $value = (float) $value;
        $value = max( 0.0, min( 1.0, $value ) );
        return round( $value, 2 );
    }

    public function sanitize_routing_mode( $value ): string {
        $value = $this->security->sanitize_text( (string) $value );
        $allowed = array( 'auto', 'fixed', 'manual' );
        return in_array( $value, $allowed, true ) ? $value : 'auto';
    }

    public function sanitize_boolean( $value ): bool {
        return (bool) $value;
    }

    public function sanitize_text_field( $value ): string {
        return $this->security->sanitize_text( (string) $value );
    }

    public function handle_ajax_load_models(): void {
        if ( ! $this->security->verify_nonce( 'smartcontentai_load_models', 'nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'smartcontentai' ) );
        }

        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'smartcontentai' ) );
        }

        try {
            // Ensure all provider classes are loaded before creating ModelManager
            $this->load_provider_classes();
            
            $model_manager = new \SmartContentAI\Models\ModelManager();
            $all_models = $model_manager->get_all_models();
            $available_models = $model_manager->get_available_models();

            // Format models for display
            $formatted_models = array();
            foreach ( $all_models as $slug => $model ) {
                $formatted_models[$slug] = array(
                    'name' => $model['name'],
                    'provider_name' => $model['provider_name'],
                    'tier' => $model['tier'],
                    'max_tokens' => $model['max_tokens'],
                    'cost_per_1k' => $model['cost_per_1k'],
                    'available' => isset( $available_models[$slug] ),
                );
            }

            wp_send_json_success( $formatted_models );
        } catch ( \Exception $e ) {
            // Log the error for debugging
            error_log( 'SmartContentAI Model Loading Error: ' . $e->getMessage() );
            wp_send_json_error( __( 'Failed to load models. Please check your configuration.', 'smartcontentai' ) );
        }
    }
    
    /**
     * Load all provider classes to ensure they're available
     */
    private function load_provider_classes(): void {
        $provider_files = array(
            'OpenRouterProvider.php',
            'OpenAIProvider.php', 
            'AnthropicProvider.php',
            'GeminiProvider.php',
            'CustomProvider.php',
            'ProviderInterface.php'
        );
        
        foreach ( $provider_files as $file ) {
            $file_path = plugin_dir_path( __DIR__ ) . '../Providers/' . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }
    
    // Custom Provider Handlers
    public function handle_save_custom_provider(): void {
        $this->require_manage_options_nonce( self::NONCE_CUSTOM_PROVIDER );
        
        $data = array(
            'id' => isset( $_POST['provider_id'] ) ? (int) $_POST['provider_id'] : null,
            'name' => $this->security->sanitize_text( $_POST['provider_name'] ?? '' ),
            'slug' => $this->security->sanitize_text( $_POST['provider_slug'] ?? '' ),
            'base_url' => $this->security->sanitize_url( $_POST['base_url'] ?? '' ),
            'auth_type' => $this->security->sanitize_text( $_POST['auth_type'] ?? 'api_key' ),
            'api_key' => $this->security->sanitize_text( $_POST['api_key'] ?? '' ),
            'custom_headers' => $this->security->sanitize_textarea_field( $_POST['custom_headers'] ?? '' ),
            'enabled' => isset( $_POST['enabled'] ) ? 1 : 0,
            'is_default' => isset( $_POST['is_default'] ) ? 1 : 0,
        );
        
        if ( empty( $data['name'] ) || empty( $data['base_url'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai&tab=custom-providers&error=missing_fields' ) );
            exit;
        }
        
        $database = \SmartContentAI\Core\Database::get_instance();
        $provider_id = $database->save_custom_provider( $data );
        
        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai&tab=custom-providers&success=1' ) );
        exit;
    }
    
    public function handle_delete_custom_provider(): void {
        $this->require_manage_options_nonce( self::NONCE_CUSTOM_PROVIDER );
        
        $provider_id = isset( $_POST['provider_id'] ) ? (int) $_POST['provider_id'] : 0;
        
        if ( $provider_id > 0 ) {
            $database = \SmartContentAI\Core\Database::get_instance();
            $database->delete_custom_provider( $provider_id );
        }
        
        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai&tab=custom-providers&success=1' ) );
        exit;
    }
    
    public function handle_save_custom_model(): void {
        $this->require_manage_options_nonce( self::NONCE_CUSTOM_MODEL );
        
        $data = array(
            'id' => isset( $_POST['model_id'] ) ? (int) $_POST['model_id'] : null,
            'provider_id' => isset( $_POST['provider_id'] ) ? (int) $_POST['provider_id'] : 0,
            'model_slug' => $this->security->sanitize_text( $_POST['model_slug'] ?? '' ),
            'model_name' => $this->security->sanitize_text( $_POST['model_name'] ?? '' ),
            'tier' => $this->security->sanitize_text( $_POST['tier'] ?? 'standard' ),
            'max_tokens' => isset( $_POST['max_tokens'] ) ? (int) $_POST['max_tokens'] : 4096,
            'cost_per_1k' => isset( $_POST['cost_per_1k'] ) ? (float) $_POST['cost_per_1k'] : 0.0,
            'context_window' => isset( $_POST['context_window'] ) ? (int) $_POST['context_window'] : 4096,
            'enabled' => isset( $_POST['enabled'] ) ? 1 : 0,
        );
        
        if ( empty( $data['model_slug'] ) || empty( $data['model_name'] ) || $data['provider_id'] === 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai&tab=custom-providers&error=missing_fields' ) );
            exit;
        }
        
        $database = \SmartContentAI\Core\Database::get_instance();
        $database->save_custom_model( $data );
        
        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai&tab=custom-providers&success=1' ) );
        exit;
    }
    
    public function handle_delete_custom_model(): void {
        $this->require_manage_options_nonce( self::NONCE_CUSTOM_MODEL );
        
        $model_id = isset( $_POST['model_id'] ) ? (int) $_POST['model_id'] : 0;
        
        if ( $model_id > 0 ) {
            $database = \SmartContentAI\Core\Database::get_instance();
            $database->delete_custom_model( $model_id );
        }
        
        wp_safe_redirect( admin_url( 'admin.php?page=smartcontentai&tab=custom-providers&success=1' ) );
        exit;
    }
    
    public function handle_ajax_test_custom_provider(): void {
        if ( ! $this->security->verify_nonce( 'smartcontentai_test_provider', 'nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'smartcontentai' ) );
        }
        
        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'smartcontentai' ) );
        }
        
        $provider_id = isset( $_POST['provider_id'] ) ? (int) $_POST['provider_id'] : 0;
        
        if ( $provider_id <= 0 ) {
            wp_send_json_error( __( 'Invalid provider ID.', 'smartcontentai' ) );
        }
        
        $database = \SmartContentAI\Core\Database::get_instance();
        $provider_data = $database->get_custom_provider( $provider_id );
        
        if ( ! $provider_data ) {
            wp_send_json_error( __( 'Provider not found.', 'smartcontentai' ) );
        }
        
        $provider = new \SmartContentAI\Providers\CustomProvider( $provider_data );
        $result = $provider->test_connection();
        
        if ( $result['success'] ) {
            wp_send_json_success( __( 'Connection successful!', 'smartcontentai' ) );
        } else {
            wp_send_json_error( $result['error'] );
        }
    }
    
    public function handle_ajax_import_custom_models(): void {
        if ( ! $this->security->verify_nonce( 'smartcontentai_import_models', 'nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'smartcontentai' ) );
        }
        
        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'smartcontentai' ) );
        }
        
        $provider_id = isset( $_POST['provider_id'] ) ? (int) $_POST['provider_id'] : 0;
        
        if ( $provider_id <= 0 ) {
            wp_send_json_error( __( 'Invalid provider ID.', 'smartcontentai' ) );
        }
        
        $database = \SmartContentAI\Core\Database::get_instance();
        $provider_data = $database->get_custom_provider( $provider_id );
        
        if ( ! $provider_data ) {
            wp_send_json_error( __( 'Provider not found.', 'smartcontentai' ) );
        }
        
        $provider = new \SmartContentAI\Providers\CustomProvider( $provider_data );
        $result = $provider->import_models_from_api();
        
        if ( $result['success'] ) {
            wp_send_json_success( sprintf( __( 'Successfully imported %d models.', 'smartcontentai' ), $result['imported_count'] ) );
        } else {
            wp_send_json_error( $result['error'] );
        }
    }
    
    public function handle_ajax_load_custom_providers(): void {
        if ( ! $this->security->verify_nonce( 'smartcontentai_load_custom_providers', 'nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'smartcontentai' ) );
        }
        
        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'smartcontentai' ) );
        }
        
        $database = \SmartContentAI\Core\Database::get_instance();
        $providers = $database->get_custom_providers();
        $model_manager = new \SmartContentAI\Models\ModelManager();
        
        $formatted_providers = array();
        foreach ( $providers as $provider ) {
            $provider_slug = 'custom_' . $provider['id'];
            $models = $model_manager->get_models_by_provider( $provider_slug );
            
            $formatted_providers[] = array(
                'id' => $provider['id'],
                'name' => $provider['name'],
                'slug' => $provider['slug'],
                'base_url' => $provider['base_url'],
                'auth_type' => $provider['auth_type'],
                'enabled' => (bool) $provider['enabled'],
                'is_default' => (bool) $provider['is_default'],
                'models_count' => count( $models ),
                'models' => array_values( $models ),
            );
        }
        
        wp_send_json_success( $formatted_providers );
    }
}
