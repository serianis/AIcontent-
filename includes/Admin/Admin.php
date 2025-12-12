<?php

namespace AutoblogAI\Admin;

use AutoblogAI\Generator\Post;
use AutoblogAI\Utils\Logger;
use AutoblogAI\Core\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    private $post_generator;
    private $logger;
    private $security;

    public function __construct( Post $post_generator, Logger $logger, Security $security ) {
        $this->post_generator = $post_generator;
        $this->logger         = $logger;
        $this->security       = $security;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_autoblogai_generate', array( $this, 'handle_ajax_generate' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'AutoblogAI',
            'AutoblogAI',
            'manage_options',
            'autoblogai',
            array( $this, 'render_dashboard_page' ),
            'dashicons-superhero',
            6
        );

        add_submenu_page(
            'autoblogai',
            'Logs',
            'Logs',
            'manage_options',
            'autoblogai-logs',
            array( $this, 'render_logs_page' )
        );
    }

    public function register_settings() {
        register_setting( 'autoblogai_options', 'autoblogai_api_key' );
        register_setting( 'autoblogai_options', 'autoblogai_language', array( 'default' => 'Greek' ) );
        register_setting( 'autoblogai_options', 'autoblogai_post_status', array( 'default' => 'draft' ) );
        register_setting( 'autoblogai_options', 'autoblogai_tone', array( 'default' => 'Professional' ) );
        register_setting( 'autoblogai_options', 'autoblogai_temperature', array( 'default' => 0.4 ) );
    }

    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1>AutoblogAI Dashboard</h1>
            
            <form method="post" action="options.php" style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-bottom: 20px;">
                <h2>Ρυθμίσεις API & Γενικά</h2>
                <?php settings_fields( 'autoblogai_options' ); ?>
                <?php do_settings_sections( 'autoblogai_options' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google Gemini API Key</th>
                        <td><input type="password" name="autoblogai_api_key" value="<?php echo esc_attr( get_option( 'autoblogai_api_key' ) ); ?>" style="width: 350px;" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Γλώσσα Άρθρων</th>
                        <td><input type="text" name="autoblogai_language" value="<?php echo esc_attr( get_option( 'autoblogai_language', 'Greek' ) ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Τόνος Φωνής (Tone)</th>
                        <td>
                            <select name="autoblogai_tone">
                                <option value="Professional" <?php selected( get_option( 'autoblogai_tone' ), 'Professional' ); ?>>Professional</option>
                                <option value="Casual" <?php selected( get_option( 'autoblogai_tone' ), 'Casual' ); ?>>Casual</option>
                                <option value="Journalistic" <?php selected( get_option( 'autoblogai_tone' ), 'Journalistic' ); ?>>Journalistic</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Creativity (Temperature)</th>
                        <td>
                            <input type="number" name="autoblogai_temperature" min="0.2" max="0.6" step="0.1" value="<?php echo esc_attr( get_option( 'autoblogai_temperature', 0.4 ) ); ?>" />
                            <p class="description">Lower values are more deterministic. Allowed range: 0.2–0.6</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Status Δημοσίευσης</th>
                        <td>
                            <select name="autoblogai_post_status">
                                <option value="draft" <?php selected( get_option( 'autoblogai_post_status' ), 'draft' ); ?>>Draft</option>
                                <option value="publish" <?php selected( get_option( 'autoblogai_post_status' ), 'publish' ); ?>>Publish</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccc;">
                <h2>Γρήγορη Δημιουργία Άρθρου</h2>
                <p>Δώστε ένα θέμα και το Gemini θα δημιουργήσει κείμενο, meta tags και εικόνα.</p>
                <input type="text" id="ai-topic" placeholder="Εισάγετε θέμα (π.χ. Οφέλη της Μεσογειακής Διατροφής)" style="width: 60%; padding: 8px;">
                <input type="text" id="ai-keyword" placeholder="Keywords (προαιρετικό)" style="width: 30%; padding: 8px;">
                <br><br>
                <button id="ai-generate-btn" class="button button-primary button-large">Δημιουργία Άρθρου</button>
                <div id="ai-result" style="margin-top: 20px; font-weight: bold;"></div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#ai-generate-btn').on('click', function(e) {
                    e.preventDefault();
                    var topic = $('#ai-topic').val();
                    var keyword = $('#ai-keyword').val();
                    
                    if(!topic) { alert('Παρακαλώ εισάγετε θέμα.'); return; }
                    
                    $('#ai-result').html('<span class="dashicons dashicons-update spin"></span> Επεξεργασία... Παρακαλώ περιμένετε (15-30 δευτ).');
                    $(this).prop('disabled', true);

                    $.post(ajaxurl, {
                        action: 'autoblogai_generate',
                        topic: topic,
                        keyword: keyword,
                        nonce: '<?php echo $this->security->create_nonce( 'autoblogai_gen_nonce' ); ?>'
                    }, function(response) {
                        $('#ai-generate-btn').prop('disabled', false);
                        if(response.success) {
                            $('#ai-result').html('<span style="color:green;">Επιτυχία!</span> Άρθρο: <a href="'+response.data.link+'" target="_blank">Προβολή</a>');
                        } else {
                            $('#ai-result').html('<span style="color:red;">Σφάλμα: ' + response.data + '</span>');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    public function render_logs_page() {
        $results = $this->logger->get_logs();
        ?>
        <div class="wrap">
            <h1>Logs Δραστηριότητας</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Ημερομηνία</th>
                        <th>Payload/Topic</th>
                        <th>Status</th>
                        <th>Response/Message</th>
                        <th>Post ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $results ) : foreach ( $results as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row->created_at ); ?></td>
                        <td><?php echo esc_html( mb_strimwidth( $row->request_payload, 0, 100, '...' ) ); ?></td>
                        <td><?php echo esc_html( $row->status ); ?></td>
                        <td><?php echo esc_html( mb_strimwidth( $row->response_excerpt, 0, 100, '...' ) ); ?></td>
                        <td><?php echo $row->post_id ? '<a href="' . get_edit_post_link( $row->post_id ) . '">#' . $row->post_id . '</a>' : '-'; ?></td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr><td colspan="5">Δεν υπάρχουν logs ακόμα.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_ajax_generate() {
        if ( ! $this->security->verify_nonce( 'autoblogai_gen_nonce', 'nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        if ( ! $this->security->current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Δεν έχετε δικαιώματα.' );
        }

        $topic   = $this->security->sanitize_text( $_POST['topic'] ?? '' );
        $keyword = $this->security->sanitize_text( $_POST['keyword'] ?? '' );

        $result = $this->post_generator->create_post( $topic, $keyword );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( array( 'id' => $result, 'link' => get_permalink( $result ) ) );
        }
    }
}
