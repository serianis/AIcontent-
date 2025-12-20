<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Backwards-compatible admin class wrapper.
// The plugin's primary admin implementation lives in SmartContentAI\Admin\Admin.

if ( ! class_exists( 'SmartContentAI_Admin' ) && class_exists( '\SmartContentAI\Admin\Admin' ) ) {
    class SmartContentAI_Admin extends \SmartContentAI\Admin\Admin {
    }
}
