<?php

namespace SmartContentAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database {
    
    private const VERSION = '1.0.0';
    
    /** @var Database|null */
    private static $instance = null;
    
    /**
     * Get the singleton instance
     * 
     * @return Database
     */
    public static function get_instance(): Database {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        // Ensure tables exist immediately when instance is created
        $this->ensure_tables_exist();
        // Initialize hooks
        $this->init_instance();
    }
    
    /**
     * Ensure all required tables exist, creating them if necessary
     */
    private function ensure_tables_exist(): void {
        if ( ! $this->tables_exist() ) {
            $this->create_tables();
        }
    }
    
    public static function init(): void {
        self::get_instance()->init_instance();
    }
    
    /**
     * Initialize the database instance
     */
    private function init_instance(): void {
        add_action( 'admin_init', array( $this, 'check_version' ) );
        add_action( 'plugins_loaded', array( $this, 'maybe_create_tables' ) );
    }
    
    public function check_version(): void {
        $current_version = get_option( 'smartcontentai_db_version', '0' );
        
        if ( version_compare( $current_version, self::VERSION, '<' ) ) {
            $this->create_tables();
            update_option( 'smartcontentai_db_version', self::VERSION );
        }
    }
    
    public function maybe_create_tables(): void {
        if ( ! $this->tables_exist() ) {
            $this->create_tables();
        }
    }
    
    private function tables_exist(): bool {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'smartcontentai_custom_providers',
            $wpdb->prefix . 'smartcontentai_custom_models',
            $wpdb->prefix . 'smartcontentai_logs'
        );
        
        foreach ( $tables as $table_name ) {
            $result = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
            if ( $result !== $table_name ) {
                return false;
            }
        }
        
        return true;
    }
    
    private function create_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Custom providers table
        $table_name = $wpdb->prefix . 'smartcontentai_custom_providers';
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(100) NOT NULL,
            base_url varchar(500) NOT NULL,
            auth_type varchar(50) NOT NULL DEFAULT 'api_key',
            api_key text,
            custom_headers text,
            enabled tinyint(1) DEFAULT 1,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY enabled (enabled)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        // Custom models table
        $table_name = $wpdb->prefix . 'smartcontentai_custom_models';
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) unsigned NOT NULL,
            model_slug varchar(100) NOT NULL,
            model_name varchar(255) NOT NULL,
            tier varchar(50) DEFAULT 'standard',
            max_tokens int(11) DEFAULT 4096,
            cost_per_1k decimal(10,6) DEFAULT 0.000000,
            context_window int(11) DEFAULT 4096,
            enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY provider_model (provider_id, model_slug),
            KEY provider_id (provider_id),
            KEY enabled (enabled)
        ) {$charset_collate};";
        
        dbDelta( $sql );
        
        // Create logs table
        $this->create_logs_table();
        
        // Update any existing installations
        update_option( 'smartcontentai_db_version', self::VERSION );
    }
    
    public function get_custom_providers(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_custom_providers';
        
        // Ensure table exists before querying
        $this->ensure_tables_exist();
        
        // Check if table exists
        $result = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
        if ( $result !== $table_name ) {
            return array();
        }
        
        $results = $wpdb->get_results( 
            "SELECT * FROM {$table_name} WHERE enabled = 1 ORDER BY name ASC",
            ARRAY_A
        );
        
        return $results ?: array();
    }
    
    public function get_custom_provider( int $id ): ?array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_custom_providers';
        
        // Ensure table exists before querying
        $this->ensure_tables_exist();
        
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id ),
            ARRAY_A
        );
        
        return $result ?: null;
    }
    
    public function get_custom_provider_models( int $provider_id ): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_custom_models';
        
        // Ensure table exists before querying
        $this->ensure_tables_exist();
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE provider_id = %d AND enabled = 1 ORDER BY model_name ASC",
                $provider_id
            ),
            ARRAY_A
        );
        
        return $results ?: array();
    }
    
    public function save_custom_provider( array $data ): int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_custom_providers';
        
        $defaults = array(
            'name' => '',
            'slug' => '',
            'base_url' => '',
            'auth_type' => 'api_key',
            'api_key' => '',
            'custom_headers' => '',
            'enabled' => 1,
            'is_default' => 0,
        );
        
        $data = wp_parse_args( $data, $defaults );
        
        // Sanitize data
        $data['name'] = sanitize_text_field( $data['name'] );
        $data['slug'] = sanitize_title( $data['slug'] );
        $data['base_url'] = esc_url_raw( $data['base_url'] );
        $data['auth_type'] = sanitize_text_field( $data['auth_type'] );
        $data['api_key'] = sanitize_text_field( $data['api_key'] );
        $data['custom_headers'] = sanitize_textarea_field( $data['custom_headers'] );
        $data['enabled'] = (int) $data['enabled'];
        $data['is_default'] = (int) $data['is_default'];
        
        // If this is set as default, unset other defaults
        if ( $data['is_default'] ) {
            $wpdb->update(
                $table_name,
                array( 'is_default' => 0 ),
                array(),
                array( '%d' ),
                array()
            );
        }
        
        if ( isset( $data['id'] ) && $data['id'] ) {
            // Update existing provider
            $wpdb->update(
                $table_name,
                $data,
                array( 'id' => (int) $data['id'] ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ),
                array( '%d' )
            );
            return (int) $data['id'];
        } else {
            // Create new provider
            unset( $data['id'] );
            $wpdb->insert( $table_name, $data );
            return (int) $wpdb->insert_id;
        }
    }
    
    public function delete_custom_provider( int $id ): bool {
        global $wpdb;
        
        $providers_table = $wpdb->prefix . 'smartcontentai_custom_providers';
        $models_table = $wpdb->prefix . 'smartcontentai_custom_models';
        
        // Delete models for this provider first
        $wpdb->delete( $models_table, array( 'provider_id' => $id ), array( '%d' ) );
        
        // Delete the provider
        $result = $wpdb->delete( $providers_table, array( 'id' => $id ), array( '%d' ) );
        
        return $result !== false;
    }
    
    public function save_custom_model( array $data ): int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_custom_models';
        
        $defaults = array(
            'provider_id' => 0,
            'model_slug' => '',
            'model_name' => '',
            'tier' => 'standard',
            'max_tokens' => 4096,
            'cost_per_1k' => 0.000000,
            'context_window' => 4096,
            'enabled' => 1,
        );
        
        $data = wp_parse_args( $data, $defaults );
        
        // Sanitize data
        $data['provider_id'] = (int) $data['provider_id'];
        $data['model_slug'] = sanitize_text_field( $data['model_slug'] );
        $data['model_name'] = sanitize_text_field( $data['model_name'] );
        $data['tier'] = sanitize_text_field( $data['tier'] );
        $data['max_tokens'] = (int) $data['max_tokens'];
        $data['cost_per_1k'] = (float) $data['cost_per_1k'];
        $data['context_window'] = (int) $data['context_window'];
        $data['enabled'] = (int) $data['enabled'];
        
        if ( isset( $data['id'] ) && $data['id'] ) {
            // Update existing model
            $wpdb->update(
                $table_name,
                $data,
                array( 'id' => (int) $data['id'] ),
                array( '%d', '%s', '%s', '%s', '%d', '%f', '%d', '%d' ),
                array( '%d' )
            );
            return (int) $data['id'];
        } else {
            // Create new model
            unset( $data['id'] );
            $wpdb->insert( $table_name, $data );
            return (int) $wpdb->insert_id;
        }
    }
    
    public function delete_custom_model( int $id ): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_custom_models';
        
        return $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) ) !== false;
    }
    
    /**
     * Log API usage for tracking costs and performance
     * 
     * @param array $log_data Usage data to log
     * @return bool Success status
     */
    public function log_usage( array $log_data ): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_logs';
        
        // Ensure the logs table exists (should already exist from create_tables)
        if ( ! $this->logs_table_exists() ) {
            $this->create_logs_table();
        }
        
        $default_data = array(
            'model_id' => null,
            'request_type' => 'text',
            'tokens_used' => 0,
            'response_time_ms' => 0,
            'success' => 0,
            'error_message' => null,
            'cost' => 0,
        );
        
        $data = wp_parse_args( $log_data, $default_data );
        
        // Sanitize the data
        $data['model_id'] = (int) $data['model_id'];
        $data['request_type'] = sanitize_text_field( $data['request_type'] );
        $data['tokens_used'] = (int) $data['tokens_used'];
        $data['response_time_ms'] = (int) $data['response_time_ms'];
        $data['success'] = (int) $data['success'];
        $data['error_message'] = sanitize_textarea_field( $data['error_message'] );
        $data['cost'] = (float) $data['cost'];
        
        $result = $wpdb->insert( $table_name, $data );
        
        return $result !== false;
    }
    
    /**
     * Check if logs table exists
     * 
     * @return bool
     */
    private function logs_table_exists(): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_logs';
        $result = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
        
        return $result === $table_name;
    }
    
    /**
     * Create the logs table if it doesn't exist
     */
    private function create_logs_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smartcontentai_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            model_id bigint(20) unsigned DEFAULT NULL,
            request_type varchar(50) NOT NULL DEFAULT 'text',
            tokens_used int(11) DEFAULT 0,
            response_time_ms int(11) DEFAULT 0,
            success tinyint(1) DEFAULT 0,
            error_message text DEFAULT NULL,
            cost decimal(10,6) DEFAULT 0.000000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY model_id (model_id),
            KEY success (success),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
