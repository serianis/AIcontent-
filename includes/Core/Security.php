<?php

namespace SmartContentAI\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Security {

    private $encryption_key;

    public function __construct() {
        $this->encryption_key = $this->get_or_create_encryption_key();
    }

    public function verify_nonce( $action, $query_arg = '_wpnonce' ) {
        return check_ajax_referer( $action, $query_arg, false );
    }

    public function current_user_can( $capability ) {
        return current_user_can( $capability );
    }

    public function create_nonce( $action ) {
        return wp_create_nonce( $action );
    }

    public function verify_capability_and_nonce( $capability, $nonce_action, $query_arg = '_wpnonce' ) {
        if ( ! $this->current_user_can( $capability ) ) {
            return new WP_Error( 'insufficient_permissions', 'User does not have required capability' );
        }

        if ( ! $this->verify_nonce( $nonce_action, $query_arg ) ) {
            return new WP_Error( 'invalid_nonce', 'Invalid nonce verification' );
        }

        return true;
    }

    public function encrypt_api_key( $plain_key ) {
        if ( empty( $plain_key ) ) {
            return '';
        }

        $algorithm = 'aes-256-cbc';
        $iv        = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $algorithm ) );
        $encrypted = openssl_encrypt( $plain_key, $algorithm, $this->encryption_key, 0, $iv );

        if ( false === $encrypted ) {
            return false;
        }

        return base64_encode( $iv . $encrypted );
    }

    public function decrypt_api_key( $encrypted_key ) {
        if ( empty( $encrypted_key ) ) {
            return '';
        }

        $algorithm      = 'aes-256-cbc';
        $decoded        = base64_decode( $encrypted_key );
        $iv_length      = openssl_cipher_iv_length( $algorithm );
        $iv             = substr( $decoded, 0, $iv_length );
        $encrypted_text = substr( $decoded, $iv_length );

        $decrypted = openssl_decrypt( $encrypted_text, $algorithm, $this->encryption_key, 0, $iv );

        return ( false === $decrypted ) ? '' : $decrypted;
    }

    public function sanitize_text( $text ) {
        return sanitize_text_field( $text );
    }

    public function sanitize_url( $url ) {
        return esc_url_raw( $url );
    }

    public function sanitize_email( $email ) {
        return sanitize_email( $email );
    }

    public function sanitize_textarea_field( $text ) {
        return sanitize_textarea_field( $text );
    }

    public function sanitize_array( $array, $sanitize_type = 'text' ) {
        if ( ! is_array( $array ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $array as $key => $value ) {
            $sanitized[ $this->sanitize_text( $key ) ] = $this->sanitize_value( $value, $sanitize_type );
        }

        return $sanitized;
    }

    public function sanitize_value( $value, $type = 'text' ) {
        switch ( $type ) {
            case 'email':
                return $this->sanitize_email( $value );
            case 'url':
                return $this->sanitize_url( $value );
            case 'int':
                return intval( $value );
            case 'float':
                return floatval( $value );
            case 'boolean':
                return (bool) $value;
            case 'text':
            default:
                return $this->sanitize_text( $value );
        }
    }

    private function get_or_create_encryption_key() {
        $option_name = 'smartcontentai_encryption_key';
        $key         = get_option( $option_name );

        if ( empty( $key ) ) {
            $key = wp_generate_password( 32, true, true );
            update_option( $option_name, $key );
        }

        return $key;
    }
}
