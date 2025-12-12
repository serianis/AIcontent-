<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Backwards-compatible admin class wrapper.
// The plugin's primary admin implementation lives in AutoblogAI\Admin\Admin.

if ( ! class_exists( 'AutoblogAI_Admin' ) && class_exists( '\AutoblogAI\Admin\Admin' ) ) {
    class AutoblogAI_Admin extends \AutoblogAI\Admin\Admin {
    }
}
