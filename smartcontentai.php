<?php
/**
 * Plugin Name: SmartContent AI
 * Description: Automatic creation of articles and images using Google Gemini API, SEO optimization and Schema markup.
 * Version: 2.0.0
 * Author: Stelios Theodoridis
 * Author URI: https://texnologia.net
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: smartcontentai
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Global constants
define( 'SMARTCONTENTAI_VERSION', '2.0.0' );
define( 'SMARTCONTENTAI_DB_VERSION', '2.0' ); // Bumped DB version
define( 'SMARTCONTENTAI_TABLE_LOGS', 'smartcontentai_logs' );
define( 'SMARTCONTENTAI_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMARTCONTENTAI_URL', plugin_dir_url( __FILE__ ) );

// Load Autoloader
require_once SMARTCONTENTAI_PATH . 'includes/Autoloader.php';

SmartContentAI\Autoloader::get_instance();

// Activation Hook
register_activation_hook( __FILE__, 'smartcontentai_activate' );

function smartcontentai_activate() {
    global $wpdb;
    
    // Create old logs table for backward compatibility
    $table_name      = $wpdb->prefix . SMARTCONTENTAI_TABLE_LOGS;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        request_payload text NOT NULL,
        request_hash varchar(64) DEFAULT NULL,
        status varchar(50) NOT NULL,
        response_excerpt text NOT NULL,
        post_id mediumint(9) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY request_hash (request_hash),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Create new custom provider tables
    \SmartContentAI\Core\Database::init();

    add_option( 'smartcontentai_db_version', SMARTCONTENTAI_DB_VERSION );
}

// Deactivation Hook
register_deactivation_hook( __FILE__, 'smartcontentai_deactivate' );

function smartcontentai_deactivate() {
    $scheduler = new SmartContentAI\Core\Scheduler();
    $scheduler->unschedule_events();
}

// Initialize Plugin
function smartcontentai_init() {
    // Text Domain
    load_plugin_textdomain( 'smartcontentai', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Initialize database for custom providers FIRST, before any classes that need it
    \SmartContentAI\Core\Database::init();
    
    // Initialize database instance immediately to create tables if needed
    \SmartContentAI\Core\Database::get_instance();

    // Instantiate Core Classes
    $logger          = new SmartContentAI\Utils\Logger();
    $security        = new SmartContentAI\Core\Security();
    $scheduler       = new SmartContentAI\Core\Scheduler();
    $api_client      = new SmartContentAI\API\Client();
    $image_generator = new SmartContentAI\Generator\Image( $api_client );
    $post_generator  = new SmartContentAI\Generator\Post( $api_client, $image_generator, $logger );

    // Process scheduled queue items.
    add_action(
        'smartcontentai_process_queued_topic',
        static function ( $item ) use ( $post_generator, $logger ) {
            if ( ! is_array( $item ) || empty( $item['topic'] ) ) {
                return;
            }

            $topic   = (string) ( $item['topic'] ?? '' );
            $keyword = (string) ( $item['keyword'] ?? '' );

            $result = $post_generator->generate_and_publish( $topic, $keyword, array( 'publish_mode' => 'publish' ) );
            if ( is_wp_error( $result ) ) {
                $logger->log( $topic, $result->get_error_message(), 'error' );
            }
        }
    );

    // Scheduler enablement.
    if ( (bool) get_option( 'smartcontentai_scheduler_enabled', false ) ) {
        $frequency = get_option( 'smartcontentai_schedule_frequency', 'daily' );
        $scheduler->schedule_events( $frequency );
    }

    // Admin
    if ( is_admin() ) {
        new SmartContentAI\Admin\Admin( $post_generator, $logger, $security, $scheduler );
    }

    // Frontend Schema
    $schema = new SmartContentAI\Frontend\Schema();
    add_action( 'wp_head', array( $schema, 'insert_schema_json_ld' ) );
}

add_action( 'plugins_loaded', 'smartcontentai_init' );
