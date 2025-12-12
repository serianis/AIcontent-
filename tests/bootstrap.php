<?php
/**
 * Bootstrap file for PHPUnit tests
 */

// Define WordPress constants if not already defined
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
}

if ( ! defined( 'AUTOBLOGAI_PATH' ) ) {
    define( 'AUTOBLOGAI_PATH', ABSPATH . 'includes/' );
}

if ( ! defined( 'AUTOBLOGAI_VERSION' ) ) {
    define( 'AUTOBLOGAI_VERSION', '1.0.0' );
}

if ( ! defined( 'AUTOBLOGAI_TABLE_LOGS' ) ) {
    define( 'AUTOBLOGAI_TABLE_LOGS', 'autoblogai_logs' );
}

// Load the autoloader
require_once ABSPATH . 'includes/Autoloader.php';

// Initialize autoloader
AutoblogAI\Autoloader::get_instance();

// Mock WordPress functions for unit testing
if ( ! function_exists( 'get_option' ) ) {
    $GLOBALS['mock_options'] = array();

    function get_option( $option, $default = false ) {
        if ( isset( $GLOBALS['mock_options'][ $option ] ) ) {
            return $GLOBALS['mock_options'][ $option ];
        }
        return $default;
    }

    function update_option( $option, $value ) {
        $GLOBALS['mock_options'][ $option ] = $value;
        return true;
    }

    function delete_option( $option ) {
        unset( $GLOBALS['mock_options'][ $option ] );
        return true;
    }
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) {
        return rtrim( dirname( $file ), '/\\' ) . '/';
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value, ...$args ) {
        return $value;
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    $GLOBALS['mock_transients'] = array();

    function get_transient( $transient ) {
        return $GLOBALS['mock_transients'][ $transient ] ?? false;
    }

    function set_transient( $transient, $value, $expiration = 0 ) {
        $GLOBALS['mock_transients'][ $transient ] = $value;
        return true;
    }

    function delete_transient( $transient ) {
        unset( $GLOBALS['mock_transients'][ $transient ] );
        return true;
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $title ) {
        $title = strtolower( trim( preg_replace( '/[^a-zA-Z0-9\s-]/', '', (string) $title ) ) );
        $title = preg_replace( '/\s+/', '-', $title );
        return trim( $title, '-' );
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $string ) {
        return trim( strip_tags( (string) $string ) );
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return 1;
    }
}

if ( ! function_exists( 'wp_insert_post' ) ) {
    function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
        return 123;
    }
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
    function set_post_thumbnail( $post, $thumbnail_id ) {
        return true;
    }
}

if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
        return true;
    }
}

if ( ! function_exists( 'wp_generate_password' ) ) {
    function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ( $special_chars ) {
            $chars .= '!@#$%^&*()';
        }
        $result = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $result .= $chars[ rand( 0, strlen( $chars ) - 1 ) ];
        }
        return $result;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type = 'mysql', $gmt = 0 ) {
        if ( 'mysql' === $type ) {
            return gmdate( 'Y-m-d H:i:s' );
        }
        return gmdate( 'U' );
    }
}

if ( ! isset( $GLOBALS['wpdb'] ) ) {
    class AutoblogAI_Mock_WPDB {
        public string $prefix = 'wp_';

        private array $tables = array();

        public function insert( $table, $data ) {
            if ( ! isset( $this->tables[ $table ] ) ) {
                $this->tables[ $table ] = array();
            }

            $this->tables[ $table ][] = (object) $data;
            return true;
        }

        public function get_results( $query ) {
            $table = $this->extract_table_name( $query );
            $rows  = $this->tables[ $table ] ?? array();

            $rows = $this->apply_where_filters( $query, $rows );
            $rows = array_reverse( $rows );

            $limit = $this->extract_limit( $query );
            if ( null !== $limit ) {
                $rows = array_slice( $rows, 0, $limit );
            }

            return $rows;
        }

        public function get_row( $query ) {
            if ( false !== strpos( $query, 'COUNT(*) as total_logs' ) ) {
                $table = $this->extract_table_name( $query );
                $rows  = $this->tables[ $table ] ?? array();
                $total = count( $rows );
                $successful = 0;
                $failed = 0;
                $unique_posts = array();
                $last_log_time = null;

                foreach ( $rows as $row ) {
                    if ( 'success' === ( $row->status ?? '' ) ) {
                        $successful++;
                    }
                    if ( 'error' === ( $row->status ?? '' ) ) {
                        $failed++;
                    }
                    if ( ! empty( $row->post_id ) ) {
                        $unique_posts[] = $row->post_id;
                    }
                    if ( isset( $row->created_at ) ) {
                        $last_log_time = max( $last_log_time ?? $row->created_at, $row->created_at );
                    }
                }

                return (object) array(
                    'total_logs'   => $total,
                    'successful'   => $successful,
                    'failed'       => $failed,
                    'unique_posts' => count( array_unique( $unique_posts ) ),
                    'last_log_time' => $last_log_time,
                );
            }

            $results = $this->get_results( $query );
            return isset( $results[0] ) ? $results[0] : null;
        }

        public function query( $query ) {
            $table = $this->extract_table_name( $query );
            if ( ! isset( $this->tables[ $table ] ) ) {
                return 0;
            }

            $removed = 0;
            if ( preg_match( "/created_at < '([^']+)'/", $query, $m ) ) {
                $cutoff = $m[1];
                $remaining = array();
                foreach ( $this->tables[ $table ] as $row ) {
                    if ( isset( $row->created_at ) && $row->created_at < $cutoff ) {
                        $removed++;
                        continue;
                    }
                    $remaining[] = $row;
                }
                $this->tables[ $table ] = $remaining;
            }

            return $removed;
        }

        public function prepare( $query, ...$args ) {
            foreach ( $args as $arg ) {
                if ( false !== strpos( $query, '%d' ) ) {
                    $query = preg_replace( '/%d/', (string) (int) $arg, $query, 1 );
                    continue;
                }
                if ( false !== strpos( $query, '%s' ) ) {
                    $escaped = str_replace( "'", "\\'", (string) $arg );
                    $query   = preg_replace( '/%s/', "'{$escaped}'", $query, 1 );
                }
            }
            return $query;
        }

        private function extract_table_name( $query ) {
            if ( preg_match( '/FROM\s+([^\s]+)/i', $query, $m ) ) {
                return $m[1];
            }
            if ( preg_match( '/INTO\s+([^\s]+)/i', $query, $m ) ) {
                return $m[1];
            }
            return $this->prefix . 'autoblogai_logs';
        }

        private function extract_limit( $query ): ?int {
            if ( preg_match( '/LIMIT\s+(\d+)/i', $query, $m ) ) {
                return (int) $m[1];
            }
            return null;
        }

        private function apply_where_filters( $query, array $rows ): array {
            if ( preg_match( "/WHERE status = '([^']+)'/", $query, $m ) ) {
                $status = $m[1];
                $rows   = array_values( array_filter( $rows, static function ( $row ) use ( $status ) {
                    return ( $row->status ?? null ) === $status;
                } ) );
            }

            if ( preg_match( '/WHERE post_id = (\d+)/', $query, $m ) ) {
                $post_id = (int) $m[1];
                $rows    = array_values( array_filter( $rows, static function ( $row ) use ( $post_id ) {
                    return (int) ( $row->post_id ?? 0 ) === $post_id;
                } ) );
            }

            if ( preg_match( "/created_at BETWEEN '([^']+)' AND '([^']+)'/", $query, $m ) ) {
                $start = $m[1];
                $end   = $m[2];
                $rows  = array_values( array_filter( $rows, static function ( $row ) use ( $start, $end ) {
                    $created = $row->created_at ?? '';
                    return $created >= $start && $created <= $end;
                } ) );
            }

            if ( preg_match( "/WHERE request_hash = '([^']+)'/", $query, $m ) ) {
                $hash = $m[1];
                $rows = array_values( array_filter( $rows, static function ( $row ) use ( $hash ) {
                    return ( $row->request_hash ?? null ) === $hash;
                } ) );
            }

            return $rows;
        }
    }

    $GLOBALS['wpdb'] = new AutoblogAI_Mock_WPDB();
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) {
        return trim( strip_tags( $text ) );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return filter_var( $url, FILTER_VALIDATE_URL );
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return filter_var( $email, FILTER_VALIDATE_EMAIL );
    }
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = '_wpnonce', $die = true ) {
        return true;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        return false;
    }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) {
        return wp_hash( $action . gmdate( 'Y-m-d H:i' ) . wp_salt(), 'nonce' );
    }
}

if ( ! function_exists( 'wp_hash' ) ) {
    function wp_hash( $data, $scheme = 'auth' ) {
        return hash_hmac( 'sha256', $data, wp_salt() );
    }
}

if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( $scheme = 'auth' ) {
        return 'put your unique phrase here';
    }
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook, $args = array() ) {
        return false;
    }
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
        return true;
    }
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
    function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
        return true;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $function_to_add, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( $hook, ...$args ) {
        return null;
    }
}

// Define WP_Error if not available
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();

        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( empty( $code ) ) {
                return;
            }

            $this->errors[ $code ][] = $message;

            if ( ! empty( $data ) ) {
                $this->error_data[ $code ] = $data;
            }
        }

        public function get_error_message( $code = '' ) {
            if ( empty( $code ) ) {
                $code = $this->get_error_code();
            }

            if ( isset( $this->errors[ $code ][0] ) ) {
                return $this->errors[ $code ][0];
            }

            return '';
        }

        public function get_error_code() {
            $codes = array_keys( $this->errors );
            return isset( $codes[0] ) ? $codes[0] : '';
        }
    }
}
