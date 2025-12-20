<?php

namespace SmartContentAI\Generator;

use SmartContentAI\API\Client;
use SmartContentAI\Utils\Logger;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post {
    public const OPTION_BANNED_WORDS           = 'smartcontentai_banned_words';
    public const OPTION_SIMILARITY_THRESHOLD   = 'smartcontentai_similarity_threshold';
    public const OPTION_MAX_POSTS_PER_DAY      = 'smartcontentai_max_posts_per_day';
    public const TRANSIENT_DAILY_POST_COUNT    = 'smartcontentai_daily_post_count';

    public const ERROR_FAILED_PROMPT_ASSEMBLY  = 'smartcontentai_prompt_assembly_failed';
    public const ERROR_INVALID_RESPONSE        = 'smartcontentai_invalid_response';
    public const ERROR_BANNED_WORDS_DETECTED   = 'smartcontentai_banned_words_detected';
    public const ERROR_DUPLICATE_CONTENT       = 'smartcontentai_duplicate_content';
    public const ERROR_DAILY_CAP_EXCEEDED      = 'smartcontentai_daily_cap_exceeded';
    public const ERROR_POST_CREATION_FAILED    = 'smartcontentai_post_creation_failed';

    private $api_client;
    private $image_generator;
    private $logger;

    public function __construct( Client $api_client, Image $image_generator = null, Logger $logger = null ) {
        $this->api_client      = $api_client;
        $this->image_generator = $image_generator;
        $this->logger          = $logger;
    }

    /**
     * Legacy method for backward compatibility.
     * Delegates to the new generate_and_publish method with default settings.
     *
     * @param string $topic The topic for the article.
     * @param string $keyword Optional SEO keyword.
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    public function create_post( $topic, $keyword = '' ) {
        return $this->generate_and_publish( $topic, $keyword, array() );
    }

    /**
     * Main orchestration method to generate and publish a post.
     *
     * @param string $topic The main topic for the article.
     * @param string $keyword Optional keyword for SEO.
     * @param array  $args Optional arguments: locale, tone, publish_mode, scheduled_at.
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    public function generate_and_publish( $topic, $keyword = '', $args = array() ) {
        $locale       = $args['locale'] ?? get_option( 'smartcontentai_language', 'Greek' );
        $tone         = $args['tone'] ?? get_option( 'smartcontentai_tone', 'Professional' );
        $publish_mode = $args['publish_mode'] ?? get_option( 'smartcontentai_post_status', 'draft' );
        $scheduled_at = $args['scheduled_at'] ?? null;

        // Check daily cap
        if ( 'publish' === $publish_mode || 'scheduled' === $publish_mode ) {
            $result = $this->check_daily_publishing_cap();
            if ( is_wp_error( $result ) ) {
                if ( $this->logger ) {
                    $this->logger->log( $topic, $result->get_error_message(), 'rejected' );
                }
                return $result;
            }
        }

        // Assemble the prompt
        $prompt = $this->assemble_prompt( $topic, $keyword, $locale, $tone );
        if ( is_wp_error( $prompt ) ) {
            if ( $this->logger ) {
                $this->logger->log( $topic, $prompt->get_error_message(), 'error' );
            }
            return $prompt;
        }

        // Generate content from API
        $content_data = $this->api_client->generate_json( $prompt );
        if ( is_wp_error( $content_data ) ) {
            if ( $this->logger ) {
                $this->logger->log( $topic, $content_data->get_error_message(), 'error' );
            }
            return $content_data;
        }

        // Parse and validate the response
        $parsed = $this->parse_and_validate_response( $content_data );
        if ( is_wp_error( $parsed ) ) {
            if ( $this->logger ) {
                $this->logger->log( $topic, $parsed->get_error_message(), 'error' );
            }
            return $parsed;
        }

        // Check for banned words
        $banned_check = $this->check_banned_words( $parsed );
        if ( is_wp_error( $banned_check ) ) {
            if ( $this->logger ) {
                $this->logger->log( $topic, $banned_check->get_error_message(), 'flagged' );
            }
            return $banned_check;
        }

        // Check for duplicate content
        $duplicate_check = $this->check_duplicate_content( $parsed['title'], $parsed['content_html'] );
        if ( is_wp_error( $duplicate_check ) ) {
            if ( $this->logger ) {
                $this->logger->log( $topic, $duplicate_check->get_error_message(), 'rejected' );
            }
            return $duplicate_check;
        }

        // Fire pre-publish filters/actions
        $parsed = apply_filters( 'smartcontentai_before_publish_post', $parsed, $topic, $keyword, $locale );

        // Generate and attach hero image
        $image_id = 0;
        if ( ! empty( $parsed['image_prompts'][0] ) && $this->image_generator ) {
            $image_prompt = apply_filters(
                'smartcontentai_hero_image_prompt',
                $parsed['image_prompts'][0],
                array( 'topic' => $topic, 'title' => $parsed['title'] )
            );
            $image_result = $this->image_generator->generate_and_upload(
                $image_prompt,
                $parsed['title'],
                array( 'type' => 'hero' )
            );
            if ( ! is_wp_error( $image_result ) ) {
                $image_id = $image_result;
            }
        }

        $post_content_html  = $this->build_post_content_html( $parsed );

        // Attach JSON-LD schema to post content
        $content_with_schema = $this->attach_json_ld_schema( $post_content_html, $parsed );

        // Create the post
        $post_status = $publish_mode;
        $post_date   = null;

        if ( 'scheduled' === $publish_mode && $scheduled_at ) {
            $post_date   = $scheduled_at;
            $post_status = 'future';
        }

        $post_data = array(
            'post_title'   => wp_strip_all_tags( $parsed['title'] ),
            'post_content' => $content_with_schema,
            'post_status'  => $post_status,
            'post_author'  => get_current_user_id(),
            'post_type'    => 'post',
        );

        if ( $post_date ) {
            $post_data['post_date']     = $post_date;
            $post_data['post_date_gmt'] = get_gmt_from_date( $post_date );
        }

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            if ( $this->logger ) {
                $this->logger->log( $topic, 'Failed to create WordPress post', 'error' );
            }
            return $post_id;
        }

        // Attach featured image
        if ( $image_id ) {
            set_post_thumbnail( $post_id, $image_id );
        }

        // Update SEO metadata
        $this->update_seo_metadata( $post_id, $parsed );

        // Fire post-publish actions
        do_action( 'smartcontentai_after_post_created', $post_id, $parsed, $topic, $keyword );

        // Increment daily published count if publishing/scheduling
        if ( 'publish' === $publish_mode || 'scheduled' === $publish_mode ) {
            $this->increment_daily_post_count();
        }

        // Log success
        if ( $this->logger ) {
            $this->logger->log( $topic, "Article published successfully (ID: $post_id)", 'success', $post_id );
        }

        return $post_id;
    }

    /**
     * Generate a preview of the post without creating a WordPress post.
     *
     * @param string $topic The main topic.
     * @param string $keyword Optional keyword.
     * @param array  $args Optional args (locale, tone).
     * @return array|WP_Error Parsed content data.
     */
    public function generate_preview( $topic, $keyword = '', $args = array() ) {
        $locale = $args['locale'] ?? get_option( 'smartcontentai_language', 'Greek' );
        $tone   = $args['tone'] ?? get_option( 'smartcontentai_tone', 'Professional' );

        $prompt = $this->assemble_prompt( $topic, $keyword, $locale, $tone );
        if ( is_wp_error( $prompt ) ) {
            return $prompt;
        }

        $content_data = $this->api_client->generate_json( $prompt );
        if ( is_wp_error( $content_data ) ) {
            return $content_data;
        }

        $parsed = $this->parse_and_validate_response( $content_data );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        $banned_check = $this->check_banned_words( $parsed );
        if ( is_wp_error( $banned_check ) ) {
            return $banned_check;
        }

        if ( function_exists( 'get_posts' ) ) {
            $duplicate_check = $this->check_duplicate_content( $parsed['title'], $parsed['content_html'] );
            if ( is_wp_error( $duplicate_check ) ) {
                return $duplicate_check;
            }
        }

        $parsed['content_html'] = $this->build_post_content_html( $parsed );

        return $parsed;
    }

    /**
     * Assemble the prompt for Gemini with specific structure requirements.
     *
     * @param string $topic The main topic.
     * @param string $keyword Optional SEO keyword.
     * @param string $locale Language/locale (e.g., 'Greek', 'English').
     * @param string $tone Tone of voice.
     * @return string|WP_Error The assembled prompt or error.
     */
    private function assemble_prompt( $topic, $keyword, $locale, $tone ) {
        $prompt = apply_filters( 'smartcontentai_prompt_before_assembly', '', $topic, $keyword, $locale, $tone );

        if ( '' !== $prompt ) {
            return $prompt;
        }

        $custom_template = get_option( 'smartcontentai_prompt_template_custom', '' );
        if ( is_string( $custom_template ) && '' !== trim( $custom_template ) ) {
            $replacements = array(
                '{{topic}}'   => $topic,
                '{{keyword}}' => $keyword,
                '{{locale}}'  => $locale,
                '{{tone}}'    => $tone,
            );

            $custom_prompt = strtr( $custom_template, $replacements );
            $custom_prompt = apply_filters( 'smartcontentai_prompt_template', $custom_prompt, $topic, $keyword, $locale, $tone );

            if ( is_string( $custom_prompt ) && '' !== trim( $custom_prompt ) ) {
                return $custom_prompt;
            }
        }

        $prompt = <<<PROMPT
You are an expert SEO content writer and journalist. Write a comprehensive, detailed article in {$locale} for WordPress.

**Article Requirements:**

**Topic:** {$topic}
**SEO Keyword:** {$keyword}
**Tone:** {$tone}

**Structure Requirements:**

1. **Title**: SEO-optimized, catchy, under 60 characters. Must include the keyword if possible.

2. **Lede (Introduction)**: Engaging introduction paragraph with 40–80 words that hooks the reader and previews the main points.

3. **Content Body**: 1400–2000 words of high-quality content with:
   - Multiple H2 subheadings (use <h2> tags)
   - H3 sub-sections under relevant H2s (use <h3> tags)
   - Bullet points and lists where appropriate
   - Clear, flowing transitions between sections
   - Each section should be comprehensive and valuable to readers

4. **Call-to-Action (CTA)**: A brief, persuasive closing paragraph encouraging reader engagement (sign up, share, comment, etc.)

5. **FAQ Section**: Exactly 5 FAQ pairs (question + answer). Each question should be a common query related to the topic. Answers should be 50–150 words each.

6. **Meta Information**:
   - meta_title: Under 60 characters, includes keyword, compelling
   - meta_description: Under 160 characters, summarizes the article
   - keywords: 5–10 relevant keywords, comma-separated
   - image_prompts: Array of 3 detailed English prompts for inline/hero images

**Output Format:**

Return ONLY valid JSON (no markdown code blocks, no triple backticks) with these exact keys:
{
  "title": "...",
  "lede": "...",
  "content_html": "<h2>...</h2>...",
  "cta": "...",
  "faq": [
    {"question": "...", "answer": "..."},
    ...
  ],
  "meta_title": "...",
  "meta_description": "...",
  "keywords": "...",
  "image_prompts": ["...", "...", "..."]
}

**Important:**
- Use proper HTML tags in content_html (<h2>, <h3>, <p>, <ul>, <li>, etc.)
- Ensure the content is original, well-researched, and grammatically correct
- Do NOT use markdown; use HTML only
- Do NOT include the JSON code block markers
- All values must be strings or arrays of strings
PROMPT;

        $prompt = apply_filters( 'smartcontentai_prompt_template', $prompt, $topic, $keyword, $locale, $tone );

        if ( ! is_string( $prompt ) || '' === trim( $prompt ) ) {
            return new WP_Error( self::ERROR_FAILED_PROMPT_ASSEMBLY, 'Failed to assemble prompt' );
        }

        return $prompt;
    }

    /**
     * Parse and validate the Gemini response.
     *
     * @param mixed $response Response from API.
     * @return array|WP_Error Parsed content array or error.
     */
    private function parse_and_validate_response( $response ) {
        if ( ! is_array( $response ) ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, 'API response is not a valid array' );
        }

        $required_fields = array( 'title', 'lede', 'content_html', 'cta', 'faq', 'meta_title', 'meta_description', 'keywords', 'image_prompts' );
        foreach ( $required_fields as $field ) {
            if ( ! isset( $response[ $field ] ) ) {
                return new WP_Error( self::ERROR_INVALID_RESPONSE, "Missing required field: $field" );
            }
        }

        // Validate title length
        $title = trim( (string) $response['title'] );
        if ( strlen( $title ) > 60 ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, 'Title exceeds 60 characters' );
        }
        if ( strlen( $title ) < 5 ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, 'Title is too short' );
        }

        // Validate lede word count
        $lede = trim( (string) $response['lede'] );
        $lede_words = str_word_count( $lede );
        if ( $lede_words < 40 || $lede_words > 80 ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, "Lede word count ($lede_words) must be 40–80 words" );
        }

        // Validate content length
        $content = (string) $response['content_html'];
        $content_words = str_word_count( wp_strip_all_tags( $content ) );
        if ( $content_words < 1400 || $content_words > 2000 ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, "Content word count ($content_words) must be 1400–2000 words" );
        }

        // Validate meta fields
        $meta_title = trim( (string) $response['meta_title'] );
        if ( strlen( $meta_title ) > 60 ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, 'Meta title exceeds 60 characters' );
        }

        $meta_desc = trim( (string) $response['meta_description'] );
        if ( strlen( $meta_desc ) > 160 ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, 'Meta description exceeds 160 characters' );
        }

        // Validate FAQ
        $faq = $response['faq'];
        if ( ! is_array( $faq ) || count( $faq ) !== 5 ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, 'FAQ must contain exactly 5 pairs' );
        }

        foreach ( $faq as $pair ) {
            if ( ! is_array( $pair ) || ! isset( $pair['question'], $pair['answer'] ) ) {
                return new WP_Error( self::ERROR_INVALID_RESPONSE, 'Invalid FAQ structure' );
            }
        }

        // Validate image prompts
        $image_prompts = $response['image_prompts'];
        if ( ! is_array( $image_prompts ) || count( $image_prompts ) < 1 ) {
            return new WP_Error( self::ERROR_INVALID_RESPONSE, 'At least one image prompt is required' );
        }

        $cta     = trim( (string) $response['cta'] );
        $faq_html = $this->render_faq_html( $faq );

        // Combine content with lede/CTA/FAQ for default output.
        $content_with_faq = $this->combine_content_with_faq( $content, $lede, $cta, $faq );

        return array(
            'title'            => $title,
            'lede'             => $lede,
            'body_html'        => $content,
            'content_html'     => $content_with_faq,
            'cta'              => $cta,
            'faq'              => $faq,
            'faq_html'         => $faq_html,
            'meta_title'       => $meta_title,
            'meta_description' => $meta_desc,
            'keywords'         => trim( (string) $response['keywords'] ),
            'image_prompts'    => $image_prompts,
        );
    }

    /**
     * Combine main content with lede, CTA and FAQ.
     *
     * @param string $content Main HTML content.
     * @param string $lede Introduction paragraph.
     * @param string $cta CTA text.
     * @param array  $faq FAQ pairs.
     * @return string Combined HTML content.
     */
    private function combine_content_with_faq( $content, $lede, $cta, $faq ) {
        $combined = '<p>' . wp_strip_all_tags( $lede ) . '</p>';
        $combined .= $content;

        if ( '' !== trim( $cta ) ) {
            $combined .= '<p>' . wp_strip_all_tags( $cta ) . '</p>';
        }

        $combined .= $this->render_faq_html( $faq );

        return $combined;
    }

    private function render_faq_html( array $faq ): string {
        $heading = function_exists( '__' ) ? __( 'Frequently Asked Questions', 'smartcontentai' ) : 'Frequently Asked Questions';

        $out = '<h2>' . esc_html( $heading ) . '</h2>';

        foreach ( $faq as $pair ) {
            $question = wp_strip_all_tags( $pair['question'] ?? '' );
            $answer   = wp_strip_all_tags( $pair['answer'] ?? '' );

            if ( '' === trim( $question ) && '' === trim( $answer ) ) {
                continue;
            }

            $out .= '<h3>' . esc_html( $question ) . '</h3>';
            $out .= '<p>' . wp_strip_all_tags( $answer ) . '</p>';
        }

        return $out;
    }

    private function build_post_content_html( array $content_data ): string {
        $template = get_option( 'smartcontentai_article_template', '' );

        if ( ! is_string( $template ) || '' === trim( $template ) ) {
            return (string) ( $content_data['content_html'] ?? '' );
        }

        $faq_html = (string) ( $content_data['faq_html'] ?? '' );
        if ( '' === $faq_html && isset( $content_data['faq'] ) && is_array( $content_data['faq'] ) ) {
            $faq_html = $this->render_faq_html( $content_data['faq'] );
        }

        $replacements = array(
            '{{title}}'            => esc_html( wp_strip_all_tags( (string) ( $content_data['title'] ?? '' ) ) ),
            '{{lede}}'             => esc_html( wp_strip_all_tags( (string) ( $content_data['lede'] ?? '' ) ) ),
            '{{body_html}}'        => (string) ( $content_data['body_html'] ?? '' ),
            '{{content_html}}'     => (string) ( $content_data['content_html'] ?? '' ),
            '{{cta}}'              => esc_html( wp_strip_all_tags( (string) ( $content_data['cta'] ?? '' ) ) ),
            '{{faq_html}}'         => $faq_html,
            '{{meta_title}}'       => esc_html( wp_strip_all_tags( (string) ( $content_data['meta_title'] ?? '' ) ) ),
            '{{meta_description}}' => esc_html( wp_strip_all_tags( (string) ( $content_data['meta_description'] ?? '' ) ) ),
            '{{keywords}}'         => esc_html( wp_strip_all_tags( (string) ( $content_data['keywords'] ?? '' ) ) ),
        );

        return strtr( $template, $replacements );
    }

    /**
     * Check for banned words in content.
     *
     * @param array $content_data The parsed content.
     * @return bool|WP_Error True if no banned words, WP_Error if found.
     */
    private function check_banned_words( $content_data ) {
        $banned_words = get_option( self::OPTION_BANNED_WORDS, '' );
        if ( '' === trim( $banned_words ) ) {
            return true;
        }

        $banned_list = array_filter( array_map( 'trim', explode( ',', $banned_words ) ) );
        if ( empty( $banned_list ) ) {
            return true;
        }

        $text_to_check = strtolower(
            wp_strip_all_tags( $content_data['title'] . ' ' . $content_data['content_html'] )
        );

        foreach ( $banned_list as $word ) {
            if ( '' === trim( $word ) ) {
                continue;
            }
            $word_lower = strtolower( $word );
            if ( false !== strpos( $text_to_check, $word_lower ) ) {
                return new WP_Error(
                    self::ERROR_BANNED_WORDS_DETECTED,
                    "Content contains banned word: $word"
                );
            }
        }

        return true;
    }

    /**
     * Check for duplicate content against recent posts.
     *
     * @param string $title Article title.
     * @param string $content Article content.
     * @return bool|WP_Error True if unique, WP_Error if duplicate.
     */
    private function check_duplicate_content( $title, $content ) {
        $threshold = (float) get_option( self::OPTION_SIMILARITY_THRESHOLD, 0.75 );
        $days_back = apply_filters( 'smartcontentai_similarity_check_days', 30 );

        $recent_posts = get_posts( array(
            'post_type'      => 'post',
            'posts_per_page' => 10,
            'date_query'     => array(
                array(
                    'after' => date( 'Y-m-d', strtotime( "-$days_back days" ) ),
                ),
            ),
        ) );

        if ( empty( $recent_posts ) ) {
            return true;
        }

        $new_text = strtolower( wp_strip_all_tags( $title . ' ' . $content ) );
        $new_words = str_word_count( $new_text, 1 );

        foreach ( $recent_posts as $post ) {
            $existing_text = strtolower( wp_strip_all_tags( $post->post_title . ' ' . $post->post_content ) );
            $existing_words = str_word_count( $existing_text, 1 );

            $similarity = $this->calculate_similarity( $new_words, $existing_words );

            if ( $similarity >= $threshold ) {
                return new WP_Error(
                    self::ERROR_DUPLICATE_CONTENT,
                    "Content is too similar to post #{$post->ID} (similarity: " . round( $similarity * 100, 2 ) . '%)'
                );
            }
        }

        return true;
    }

    /**
     * Calculate Jaccard similarity between two word arrays.
     *
     * @param array $words1 First array of words.
     * @param array $words2 Second array of words.
     * @return float Similarity score between 0 and 1.
     */
    private function calculate_similarity( $words1, $words2 ) {
        $unique1 = array_unique( $words1 );
        $unique2 = array_unique( $words2 );

        $intersection = array_intersect( $unique1, $unique2 );
        $union = array_unique( array_merge( $unique1, $unique2 ) );

        if ( empty( $union ) ) {
            return 0.0;
        }

        return count( $intersection ) / count( $union );
    }

    /**
     * Attach JSON-LD Article schema to post content.
     *
     * @param string $content HTML content.
     * @param array  $content_data Parsed content data.
     * @return string Content with embedded JSON-LD.
     */
    private function attach_json_ld_schema( $content, $content_data ) {
        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => $content_data['title'],
            'description'   => $content_data['meta_description'],
            'author'        => array(
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', get_current_user_id() ),
            ),
            'datePublished' => gmdate( 'c' ),
            'dateModified'  => gmdate( 'c' ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_home_url(),
            ),
        );

        if ( ! empty( $content_data['image_prompts'][0] ) ) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url'   => '',
            );
        }

        $json_ld = '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
        return $json_ld . $content;
    }

    /**
     * Update SEO metadata for the post.
     *
     * @param int   $post_id The post ID.
     * @param array $content_data The parsed content data.
     */
    private function update_seo_metadata( $post_id, $content_data ) {
        update_post_meta( $post_id, '_smartcontentai_meta_title', $content_data['meta_title'] );
        update_post_meta( $post_id, '_smartcontentai_meta_description', $content_data['meta_description'] );
        update_post_meta( $post_id, '_smartcontentai_keywords', $content_data['keywords'] );

        update_post_meta( $post_id, '_yoast_wpseo_title', $content_data['meta_title'] );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $content_data['meta_description'] );

        update_post_meta( $post_id, '_smartcontentai_generated', 1 );
        update_post_meta( $post_id, '_smartcontentai_generated_at', current_time( 'mysql' ) );
    }

    /**
     * Check if the daily publishing cap has been reached.
     *
     * @return bool|WP_Error True if within cap, WP_Error if exceeded.
     */
    private function check_daily_publishing_cap() {
        $max_per_day = (int) get_option( self::OPTION_MAX_POSTS_PER_DAY, 10 );
        $published_today = $this->get_daily_post_count();

        if ( $published_today >= $max_per_day ) {
            return new WP_Error(
                self::ERROR_DAILY_CAP_EXCEEDED,
                "Daily publishing cap of $max_per_day posts has been reached ($published_today published today)"
            );
        }

        return true;
    }

    /**
     * Get the number of posts published today.
     *
     * @return int Number of posts published today.
     */
    private function get_daily_post_count() {
        $count = get_transient( self::TRANSIENT_DAILY_POST_COUNT );
        return false === $count ? 0 : (int) $count;
    }

    /**
     * Increment the daily post count.
     */
    private function increment_daily_post_count() {
        $count = $this->get_daily_post_count();
        set_transient( self::TRANSIENT_DAILY_POST_COUNT, $count + 1, DAY_IN_SECONDS );
    }

    /**
     * Reset the daily post count (typically called at midnight).
     */
    public function reset_daily_post_count() {
        delete_transient( self::TRANSIENT_DAILY_POST_COUNT );
    }
}
