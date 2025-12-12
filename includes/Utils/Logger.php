<?php

namespace AutoblogAI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'autoblogai_logs';
    }

    public function log( $request_payload, $response_excerpt, $status, $post_id = null ) {
        global $wpdb;

        $request_payload = $this->redact_sensitive_data( $request_payload );
        $request_hash    = $this->generate_request_hash( $request_payload );

        $wpdb->insert(
            $this->table_name,
            array(
                'created_at'       => current_time( 'mysql' ),
                'request_payload'  => is_array( $request_payload ) || is_object( $request_payload ) ? json_encode( $request_payload ) : $request_payload,
                'request_hash'     => $request_hash,
                'response_excerpt' => $response_excerpt,
                'status'           => $status,
                'post_id'          => $post_id,
            )
        );
    }

    public function get_logs( $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d", $limit ) );
    }

    public function get_logs_by_status( $status, $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY created_at DESC LIMIT %d", $status, $limit ) );
    }

    public function get_logs_by_post_id( $post_id, $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY created_at DESC LIMIT %d", $post_id, $limit ) );
    }

    public function get_logs_by_date_range( $start_date, $end_date, $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT %d", $start_date, $end_date, $limit ) );
    }

    public function get_log_by_request_hash( $request_hash ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE request_hash = %s ORDER BY created_at DESC LIMIT 1", $request_hash ) );
    }

    public function get_logs_statistics() {
        global $wpdb;
        return $wpdb->get_row( "SELECT 
            COUNT(*) as total_logs, 
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful, 
            SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed,
            COUNT(DISTINCT post_id) as unique_posts,
            MAX(created_at) as last_log_time
        FROM {$this->table_name}" );
    }

    public function clear_old_logs( $days = 30 ) {
        global $wpdb;
        $date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        return $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE created_at < %s", $date ) );
    }

    private function redact_sensitive_data( $payload ) {
        $api_key = get_option( 'autoblogai_api_key', '' );
        return $this->redact_sensitive_value( $payload, $api_key );
    }

    private function redact_sensitive_value( $value, $api_key ) {
        if ( is_string( $value ) ) {
            if ( ! empty( $api_key ) ) {
                $value = str_replace( $api_key, '[REDACTED]', $value );
            }
            $value = preg_replace( '/([?&]key=)([^&]+)/', '$1[REDACTED]', $value );
            $value = preg_replace( '/(Authorization:\s*Bearer\s+)(\S+)/i', '$1[REDACTED]', $value );
            return $value;
        }

        if ( is_array( $value ) ) {
            $redacted = array();
            foreach ( $value as $k => $v ) {
                $key = is_string( $k ) ? $k : $k;
                if ( is_string( $key ) && preg_match( '/(api_?key|authorization|key)/i', $key ) ) {
                    $redacted[ $k ] = '[REDACTED]';
                    continue;
                }
                $redacted[ $k ] = $this->redact_sensitive_value( $v, $api_key );
            }
            return $redacted;
        }

        if ( is_object( $value ) ) {
            return $this->redact_sensitive_value( (array) $value, $api_key );
        }

        return $value;
    }

    private function generate_request_hash( $request_payload ) {
        $payload_string = is_array( $request_payload ) || is_object( $request_payload ) ? json_encode( $request_payload ) : $request_payload;
        return hash( 'sha256', $payload_string );
    }
}
