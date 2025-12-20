<?php

namespace SmartContentAI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        spl_autoload_register( array( $this, 'autoload' ) );
    }

    public function autoload( $class ) {
        // Only autoload classes from this namespace
        if ( strpos( $class, 'SmartContentAI\\' ) !== 0 ) {
            return;
        }

        // Remove namespace prefix
        $relative_class = str_replace( 'SmartContentAI\\', '', $class );

        // Map namespace to directory structure
        // Example: SmartContentAI\Core\Bootstrap -> includes/Core/Bootstrap.php
        $file = plugin_dir_path( __FILE__ ) . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
