<?php

namespace AutoblogAI\Admin;

use AutoblogAI\Core\Scheduler;
use AutoblogAI\Core\Security;
use AutoblogAI\Generator\Post;
use AutoblogAI\Utils\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public const OPTION_TOPICS               = 'autoblogai_topics';
    public const OPTION_SOURCE_TYPE          = 'autoblogai_source_type';
    public const OPTION_RSS_URL              = 'autoblogai_rss_url';
    public const OPTION_SCHEDULER_ENABLED    = 'autoblogai_scheduler_enabled';
    public const OPTION_SCHEDULER_FREQUENCY  = 'autoblogai_schedule_frequency';
    public const OPTION_PROMPT_TEMPLATE      = 'autoblogai_prompt_template_custom';
    public const OPTION_ARTICLE_TEMPLATE     = 'autoblogai_article_template';

    private const NONCE_TOPICS    = 'autoblogai_topics_nonce';
    private const NONCE_SOURCES   = 'autoblogai_sources_nonce';
    private const NONCE_SCHEDULER = 'autoblogai_scheduler_nonce';
    private const NONCE_TEMPLATES = 'autoblogai_templates_nonce';
    private const NONCE_PREVIEW   = 'autoblogai_preview_nonce';
    private const NONCE_GENERATE  = 'autoblogai_generate_nonce';

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

        add_action( 'admin_post_autoblogai_add_topic', array( $this, 'handle_add_topic' ) );
        add_action( 'admin_post_autoblogai_delete_topic', array( $this, 'handle_delete_topic' ) );
        add_action( 'admin_post_autoblogai_enqueue_topic', array( $this, 'handle_enqueue_topic' ) );

        add_action( 'admin_post_autoblogai_save_sources', array( $this, 'handle_save_sources' ) );

        add_action( 'admin_post_autoblogai_save_scheduler', array( $this, 'handle_save_scheduler' ) );
        add_action( 'admin_post_autoblogai_clear_queue', array( $this, 'handle_clear_queue' ) );

        add_action( 'admin_post_autoblogai_save_templates', array( $this, 'handle_save_templates' ) );

        add_action( 'wp_ajax_autoblogai_generate', array( $this, 'handle_ajax_generate' ) );
        add_action( 'wp_ajax_autoblogai_preview_post', array( $this, 'handle_ajax_preview' ) );
    }

    public function add_admin_menu(): void {
        add_menu_page(
            __( 'AutoblogAI', 'autoblogai' ),
            'AutoblogAI',
            'manage_options',
            'autoblogai',
            array( $this, 'render_settings_page' ),
            'dashicons-superhero',
            6
        );

        add_submenu_page( 'autoblogai', __( 'Settings', 'autoblogai' ), __( 'Settings', 'autoblogai' ), 'manage_options', 'autoblogai', array( $this, 'render_settings_page' ) );
        add_submenu_page( 'autoblogai', __( 'Topics', 'autoblogai' ), __( 'Topics', 'autoblogai' ), 'manage_options', 'autoblogai-topics', array( $this, 'render_topics_page' ) );
        add_submenu_page( 'autoblogai', __( 'Sources', 'autoblogai' ), __( 'Sources', 'autoblogai' ), 'manage_options', 'autoblogai-sources', array( $this, 'render_sources_page' ) );
        add_submenu_page( 'autoblogai', __( 'Generator', 'autoblogai' ), __( 'Generator', 'autoblogai' ), 'manage_options', 'autoblogai-generator', array( $this, 'render_generator_page' ) );
        add_submenu_page( 'autoblogai', __( 'Scheduler', 'autoblogai' ), __( 'Scheduler', 'autoblogai' ), 'manage_options', 'autoblogai-scheduler', array( $this, 'render_scheduler_page' ) );
        add_submenu_page( 'autoblogai', __( 'Templates', 'autoblogai' ), __( 'Templates', 'autoblogai' ), 'manage_options', 'autoblogai-templates', array( $this, 'render_templates_page' ) );
        add_submenu_page( 'autoblogai', __( 'Logs', 'autoblogai' ), __( 'Logs', 'autoblogai' ), 'manage_options', 'autoblogai-logs', array( $this, 'render_logs_page' ) );
    }

    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, 'autoblogai' ) ) {
            return;
        }

        wp_enqueue_style(
            'autoblogai-admin',
            AUTOBLOGAI_URL . 'assets/css/admin-style.css',
            array(),
            AUTOBLOGAI_VERSION
        );

        wp_enqueue_script(
            'autoblogai-admin',
            AUTOBLOGAI_URL . 'assets/js/admin-script.js',
            array( 'jquery', 'underscore' ),
            AUTOBLOGAI_VERSION,
            true
        );

        wp_localize_script(
            'autoblogai-admin',
            'AutoblogAIAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonces'  => array(
                    'preview'  => $this->security->create_nonce( self::NONCE_PREVIEW ),
                    'generate' => $this->security->create_nonce( self::NONCE_GENERATE ),
                ),
                'i18n'    => array(
                    'missingTopic' => __( 'Please enter a topic.', 'autoblogai' ),
                    'unknownError' => __( 'Unexpected error. Please try again.', 'autoblogai' ),
                    'previewReady' => __( 'Preview generated.', 'autoblogai' ),
                    'generating'   => __( 'Generating… this may take a bit.', 'autoblogai' ),
                    'created'      => __( 'Post created successfully.', 'autoblogai' ),
                    'viewPost'     => __( 'View post', 'autoblogai' ),
                ),
            )
        );
    }

    public function register_settings(): void {
        register_setting(
            'autoblogai_options',
            'autoblogai_api_key',
            array(
                'sanitize_callback' => array( $this, 'sanitize_api_key' ),
                'default'           => '',
            )
        );

        register_setting(
            'autoblogai_options',
            'autoblogai_language',
            array(
                'sanitize_callback' => array( $this, 'sanitize_language' ),
                'default'           => 'Greek',
            )
        );

        register_setting(
            'autoblogai_options',
            'autoblogai_tone',
            array(
                'sanitize_callback' => array( $this, 'sanitize_tone' ),
                'default'           => 'Professional',
            )
        );

        register_setting(
            'autoblogai_options',
            'autoblogai_rate_limit_per_minute',
            array(
                'sanitize_callback' => array( $this, 'sanitize_rate_limit' ),
                'default'           => 60,
            )
        );

        register_setting(
            'autoblogai_options',
            'autoblogai_banned_words',
            array(
                'sanitize_callback' => array( $this, 'sanitize_banned_words' ),
                'default'           => '',
            )
        );
    }

    public function sanitize_api_key( $value ): string {
        $value = $this->security->sanitize_text( (string) $value );

        if ( '' === trim( $value ) ) {
            return (string) get_option( 'autoblogai_api_key', '' );
        }

        $encrypted = $this->security->encrypt_api_key( $value );
        if ( false === $encrypted || '' === $encrypted ) {
            return (string) get_option( 'autoblogai_api_key', '' );
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
                'has_api_key' => '' !== (string) get_option( 'autoblogai_api_key', '' ),
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
                    'meta_key'       => '_autoblogai_generated',
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

        wp_safe_redirect( admin_url( 'admin.php?page=autoblogai-topics' ) );
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

        wp_safe_redirect( admin_url( 'admin.php?page=autoblogai-topics' ) );
        exit;
    }

    public function handle_enqueue_topic(): void {
        $this->require_manage_options_nonce( self::NONCE_TOPICS );

        $index  = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
        $topics = $this->get_topics();

        if ( isset( $topics[ $index ] ) ) {
            $this->scheduler->enqueue_topic( (string) $topics[ $index ]['topic'], (string) $topics[ $index ]['keyword'] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=autoblogai-scheduler' ) );
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

        wp_safe_redirect( admin_url( 'admin.php?page=autoblogai-sources' ) );
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

        wp_safe_redirect( admin_url( 'admin.php?page=autoblogai-scheduler' ) );
        exit;
    }

    public function handle_clear_queue(): void {
        $this->require_manage_options_nonce( self::NONCE_SCHEDULER );
        $this->scheduler->clear_queue();
        wp_safe_redirect( admin_url( 'admin.php?page=autoblogai-scheduler' ) );
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

        wp_safe_redirect( admin_url( 'admin.php?page=autoblogai-templates' ) );
        exit;
    }

    public function handle_ajax_preview(): void {
        if ( ! $this->security->verify_nonce( self::NONCE_PREVIEW, 'nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce.', 'autoblogai' ) );
        }

        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'autoblogai' ) );
        }

        $topic   = $this->security->sanitize_text( $_POST['topic'] ?? '' );
        $keyword = $this->security->sanitize_text( $_POST['keyword'] ?? '' );

        if ( '' === trim( $topic ) ) {
            wp_send_json_error( __( 'Missing topic.', 'autoblogai' ) );
        }

        if ( ! method_exists( $this->post_generator, 'generate_preview' ) ) {
            wp_send_json_error( __( 'Preview is not available.', 'autoblogai' ) );
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
            wp_send_json_error( __( 'Invalid nonce.', 'autoblogai' ) );
        }

        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'autoblogai' ) );
        }

        $topic        = $this->security->sanitize_text( $_POST['topic'] ?? '' );
        $keyword      = $this->security->sanitize_text( $_POST['keyword'] ?? '' );
        $publish_mode = $this->security->sanitize_text( $_POST['publish_mode'] ?? 'draft' );
        $scheduled_at = $this->security->sanitize_text( $_POST['scheduled_at'] ?? '' );

        if ( '' === trim( $topic ) ) {
            wp_send_json_error( __( 'Missing topic.', 'autoblogai' ) );
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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'autoblogai' ) );
        }

        $file = trailingslashit( AUTOBLOGAI_PATH ) . 'templates/' . $template . '.php';
        if ( ! file_exists( $file ) ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Template not found.', 'autoblogai' ) . '</p></div>';
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
            wp_die( esc_html__( 'Insufficient permissions.', 'autoblogai' ) );
        }

        if ( ! $this->security->verify_nonce( $nonce_action, '_wpnonce' ) ) {
            wp_die( esc_html__( 'Invalid nonce.', 'autoblogai' ) );
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
        $out .= '<p class="autoblogai-muted"><strong>' . $this->escape_html( __( 'Meta:', 'autoblogai' ) ) . '</strong> ' . $meta . '</p>';
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
        $history = get_transient( 'autoblogai_gemini_rate_history' );
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
            'limit'            => (int) get_option( 'autoblogai_rate_limit_per_minute', 60 ),
        );
    }
}
