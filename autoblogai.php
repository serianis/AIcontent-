<?php
/**
 * Plugin Name: AutoblogAI - Gemini Powered Content
 * Description: Αυτόματη δημιουργία άρθρων και εικόνων με χρήση Google Gemini API, SEO optimization και Schema markup.
 * Version: 1.0.0
 * Author: Gemini AI
 * Text Domain: autoblogai
 */

if (!defined('ABSPATH')) {
    exit;
}

// Global constants
define('AUTOBLOGAI_VERSION', '1.0.0');
define('AUTOBLOGAI_DB_VERSION', '1.0');
define('AUTOBLOGAI_TABLE_LOGS', 'autoblogai_logs');

/**
 * Main Class setup
 */
class AutoblogAI {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_head', array($this, 'insert_schema_json_ld'));
        
        // AJAX handlers for manual generation
        add_action('wp_ajax_autoblogai_generate', array($this, 'handle_ajax_generate'));
    }

    /**
     * Database Creation on Activation
     */
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . AUTOBLOGAI_TABLE_LOGS;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            topic text NOT NULL,
            status varchar(50) NOT NULL,
            message text NOT NULL,
            post_id mediumint(9) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        add_option('autoblogai_db_version', AUTOBLOGAI_DB_VERSION);
    }

    /**
     * Admin Menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'AutoblogAI',
            'AutoblogAI',
            'manage_options',
            'autoblogai',
            array($this, 'render_dashboard_page'),
            'dashicons-superhero',
            6
        );

        add_submenu_page(
            'autoblogai',
            'Logs',
            'Logs',
            'manage_options',
            'autoblogai-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Register Settings
     */
    public function register_settings() {
        register_setting('autoblogai_options', 'autoblogai_api_key');
        register_setting('autoblogai_options', 'autoblogai_language', ['default' => 'Greek']);
        register_setting('autoblogai_options', 'autoblogai_post_status', ['default' => 'draft']);
        register_setting('autoblogai_options', 'autoblogai_tone', ['default' => 'Professional']);
    }

    /**
     * Render Dashboard/Generator Page
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1>AutoblogAI Dashboard</h1>
            
            <form method="post" action="options.php" style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-bottom: 20px;">
                <h2>Ρυθμίσεις API & Γενικά</h2>
                <?php settings_fields('autoblogai_options'); ?>
                <?php do_settings_sections('autoblogai_options'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google Gemini API Key</th>
                        <td><input type="password" name="autoblogai_api_key" value="<?php echo esc_attr(get_option('autoblogai_api_key')); ?>" style="width: 350px;" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Γλώσσα Άρθρων</th>
                        <td><input type="text" name="autoblogai_language" value="<?php echo esc_attr(get_option('autoblogai_language', 'Greek')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Τόνος Φωνής (Tone)</th>
                        <td>
                            <select name="autoblogai_tone">
                                <option value="Professional" <?php selected(get_option('autoblogai_tone'), 'Professional'); ?>>Professional</option>
                                <option value="Casual" <?php selected(get_option('autoblogai_tone'), 'Casual'); ?>>Casual</option>
                                <option value="Journalistic" <?php selected(get_option('autoblogai_tone'), 'Journalistic'); ?>>Journalistic</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Status Δημοσίευσης</th>
                        <td>
                            <select name="autoblogai_post_status">
                                <option value="draft" <?php selected(get_option('autoblogai_post_status'), 'draft'); ?>>Draft</option>
                                <option value="publish" <?php selected(get_option('autoblogai_post_status'), 'publish'); ?>>Publish</option>
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
                        nonce: '<?php echo wp_create_nonce("autoblogai_gen_nonce"); ?>'
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

    /**
     * Render Logs Page
     */
    public function render_logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . AUTOBLOGAI_TABLE_LOGS;
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 50");
        ?>
        <div class="wrap">
            <h1>Logs Δραστηριότητας</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Ημερομηνία</th>
                        <th>Θέμα</th>
                        <th>Status</th>
                        <th>Μήνυμα</th>
                        <th>Post ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results) : foreach ($results as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row->time); ?></td>
                        <td><?php echo esc_html($row->topic); ?></td>
                        <td><?php echo esc_html($row->status); ?></td>
                        <td><?php echo esc_html(mb_strimwidth($row->message, 0, 100, '...')); ?></td>
                        <td><?php echo $row->post_id ? '<a href="'.get_edit_post_link($row->post_id).'">#'.$row->post_id.'</a>' : '-'; ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5">Δεν υπάρχουν logs ακόμα.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * AJAX Handler for Generation
     */
    public function handle_ajax_generate() {
        check_ajax_referer('autoblogai_gen_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Δεν έχετε δικαιώματα.');
        }

        $topic = sanitize_text_field($_POST['topic']);
        $keyword = sanitize_text_field($_POST['keyword']);
        
        $generator = new AutoblogAI_Generator();
        $result = $generator->create_post($topic, $keyword);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array('id' => $result, 'link' => get_permalink($result)));
        }
    }

    /**
     * Schema JSON-LD Injection
     */
    public function insert_schema_json_ld() {
        if (!is_single()) return;
        
        global $post;
        // Check if this post was generated by our plugin (via meta flag) or just apply generally
        // For now, applying to all posts for better utility
        
        $excerpt = get_post_meta($post->ID, '_autoblogai_meta_desc', true);
        if(!$excerpt) $excerpt = wp_trim_words($post->post_content, 20);

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title(),
            'description' => $excerpt,
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author()
            )
        );

        if (has_post_thumbnail()) {
            $schema['image'] = get_the_post_thumbnail_url(null, 'full');
        }

        echo '<script type="application/ld+json">' . json_encode($schema) . '</script>';
    }
}

/**
 * Class: Content Generator Logic
 */
class AutoblogAI_Generator {

    private $api_client;
    
    public function __construct() {
        $this->api_client = new AutoblogAI_Gemini_Client();
    }

    public function create_post($topic, $keyword = '') {
        $language = get_option('autoblogai_language', 'Greek');
        $tone = get_option('autoblogai_tone', 'Professional');
        
        // 1. Generate Text
        $prompt = "You are an expert SEO content writer. Write a comprehensive WordPress article in {$language}. 
        Topic: '{$topic}'. Keyword: '{$keyword}'. Tone: {$tone}.
        
        Requirements:
        1. Title: Catchy, SEO friendly, under 60 chars.
        2. Content: HTML format. Use <h2> and <h3> tags. Include a 'Lede' (intro), main body (min 600 words), bullet points, and an FAQ section.
        3. Meta: Provide a meta_title (max 60 chars) and meta_description (max 160 chars).
        4. Image Prompt: Describe a photorealistic hero image for this article in English (under 40 words).
        
        OUTPUT FORMAT: Strictly Valid JSON with keys: 'title', 'content_html', 'meta_title', 'meta_description', 'image_prompt'. Do not include markdown formatting ```json.";

        $content_data = $this->api_client->generate_text($prompt);

        if (is_wp_error($content_data)) {
            $this->log($topic, 'error', $content_data->get_error_message());
            return $content_data;
        }

        // 2. Generate Image
        $image_id = 0;
        if (!empty($content_data['image_prompt'])) {
            $image_url = $this->api_client->generate_image($content_data['image_prompt']);
            if (!is_wp_error($image_url)) {
                $image_id = $this->upload_image_from_url($image_url, $content_data['title']);
            }
        }

        // 3. Create Post
        $post_status = get_option('autoblogai_post_status', 'draft');
        
        $post_data = array(
            'post_title'    => wp_strip_all_tags($content_data['title']),
            'post_content'  => $content_data['content_html'],
            'post_status'   => $post_status,
            'post_author'   => get_current_user_id(),
            'post_type'     => 'post',
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->log($topic, 'error', 'WP Post creation failed.');
            return $post_id;
        }

        // 4. Attach Meta & Image
        if ($image_id) {
            set_post_thumbnail($post_id, $image_id);
        }

        // Save SEO Meta (Custom fields compatible with most SEO plugins logic if bridged, or used by our schema generator)
        update_post_meta($post_id, '_autoblogai_meta_title', $content_data['meta_title']);
        update_post_meta($post_id, '_autoblogai_meta_desc', $content_data['meta_description']);
        update_post_meta($post_id, '_yoast_wpseo_title', $content_data['meta_title']); // Yoast compat
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $content_data['meta_description']); // Yoast compat

        $this->log($topic, 'success', 'Article created successfully.', $post_id);

        return $post_id;
    }

    private function upload_image_from_url($url, $title) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Download to temp
        $tmp = download_url($url);
        if (is_wp_error($tmp)) return false;

        $file_array = array(
            'name' => sanitize_title($title) . '.jpg',
            'tmp_name' => $tmp
        );

        // Upload to media library
        $id = media_handle_sideload($file_array, 0);

        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return false;
        }
        
        // Update Alt Text
        update_post_meta($id, '_wp_attachment_image_alt', $title);

        return $id;
    }

    private function log($topic, $status, $message, $post_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . AUTOBLOGAI_TABLE_LOGS;
        $wpdb->insert(
            $table,
            array(
                'time' => current_time('mysql'),
                'topic' => $topic,
                'status' => $status,
                'message' => $message,
                'post_id' => $post_id
            )
        );
    }
}

/**
 * Class: Google Gemini API Client
 */
class AutoblogAI_Gemini_Client {
    private $api_key;
    private $base_url = '[https://generativelanguage.googleapis.com/v1beta/models/](https://generativelanguage.googleapis.com/v1beta/models/)';

    public function __construct() {
        $this->api_key = get_option('autoblogai_api_key');
    }

    public function generate_text($prompt) {
        if (empty($this->api_key)) return new WP_Error('no_key', 'Missing Gemini API Key');

        // Using Gemini 2.0 Flash or Pro depending on availability, falling back to 1.5
        $model = 'gemini-2.0-flash-exp'; 
        $url = $this->base_url . $model . ':generateContent?key=' . $this->api_key;

        $body = array(
            'contents' => array(
                array('parts' => array(array('text' => $prompt)))
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'responseMimeType' => 'application/json' // Force JSON
            )
        );

        $response = wp_remote_post($url, array(
            'body' => json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 60
        ));

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $raw_json = $data['candidates'][0]['content']['parts'][0]['text'];
            // Clean up any potential markdown wrapping
            $raw_json = str_replace(array('```json', '```'), '', $raw_json);
            return json_decode($raw_json, true);
        }

        return new WP_Error('parse_error', 'Could not parse Gemini response');
    }

    public function generate_image($image_prompt) {
        if (empty($this->api_key)) return new WP_Error('no_key', 'Missing API Key');

        // Note: Imagen 3 API integration via Gemini endpoint
        // If not available on the key, we might need a fallback.
        // For this demo, we assume the user has access to Imagen-3 or similar capability via API.
        
        $model = 'imagen-3.0-generate-001';
        $url = $this->base_url . $model . ':predict?key=' . $this->api_key;
        
        $body = array(
            'instances' => array(
                array('prompt' => $image_prompt)
            ),
            'parameters' => array(
                'sampleCount' => 1,
                'aspectRatio' => '16:9'
            )
        );

        $response = wp_remote_post($url, array(
            'body' => json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) return $response;
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['predictions'][0]['bytesBase64Encoded'])) {
            // Convert Base64 to a temporary URL or handle directly. 
            // Since wp_sideload needs a file or URL, we'll save base64 to a temp file
            $base64 = $data['predictions'][0]['bytesBase64Encoded'];
            $img = base64_decode($base64);
            $upload_dir = wp_upload_dir();
            $filename = 'gen_' . uniqid() . '.png';
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            file_put_contents($file_path, $img);
            
            return $upload_dir['url'] . '/' . $filename;
        }
        
        // Fallback: If image gen fails or model not found, return error so we skip image
        return new WP_Error('img_api_error', 'Image generation failed or model not available.');
    }
}

// Initialize Plugin
register_activation_hook(__FILE__, array('AutoblogAI', 'activate'));
add_action('plugins_loaded', array('AutoblogAI', 'get_instance'));