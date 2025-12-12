<?php

namespace AutoblogAI\Generator;

use AutoblogAI\API\Client;
use AutoblogAI\Utils\Logger;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post {

    private $api_client;
    private $image_generator;
    private $logger;

    public function __construct( Client $api_client, Image $image_generator, Logger $logger ) {
        $this->api_client      = $api_client;
        $this->image_generator = $image_generator;
        $this->logger          = $logger;
    }

    public function create_post( $topic, $keyword = '' ) {
        $language = get_option( 'autoblogai_language', 'Greek' );
        $tone     = get_option( 'autoblogai_tone', 'Professional' );

        // 1. Generate Text
        $prompt = "You are an expert SEO content writer. Write a comprehensive WordPress article in {$language}. 
        Topic: '{$topic}'. Keyword: '{$keyword}'. Tone: {$tone}.
        
        Requirements:
        1. Title: Catchy, SEO friendly, under 60 chars.
        2. Content: HTML format. Use <h2> and <h3> tags. Include a 'Lede' (intro), main body (min 600 words), bullet points, and an FAQ section.
        3. Meta: Provide a meta_title (max 60 chars) and meta_description (max 160 chars).
        4. Image Prompt: Describe a photorealistic hero image for this article in English (under 40 words).
        
        OUTPUT FORMAT: Strictly Valid JSON with keys: 'title', 'content_html', 'meta_title', 'meta_description', 'image_prompt'. Do not include markdown formatting ```json.";

        $prompt = apply_filters( 'autoblogai_prompt_template', $prompt, $topic, $keyword, $language, $tone );

        $content_data = $this->api_client->generate_text( $prompt );

        if ( is_wp_error( $content_data ) ) {
            $this->logger->log( $topic, $content_data->get_error_message(), 'error' );
            return $content_data;
        }

        if ( ! is_array( $content_data ) ) {
            $this->logger->log( $topic, 'Invalid API response format', 'error' );
            return new WP_Error( 'invalid_response', 'Invalid API response format' );
        }

        // 2. Generate Image
        $image_id = 0;
        if ( ! empty( $content_data['image_prompt'] ) ) {
            $image_prompt = apply_filters(
                'autoblogai_image_prompt',
                $content_data['image_prompt'],
                array(
                    'type'    => 'hero',
                    'topic'   => $topic,
                    'keyword' => $keyword,
                    'title'   => $content_data['title'],
                )
            );

            $image_id_or_error = $this->image_generator->generate_and_upload(
                $image_prompt,
                $content_data['title'],
                array( 'type' => 'hero' )
            );
            if ( ! is_wp_error( $image_id_or_error ) ) {
                $image_id = $image_id_or_error;
            }
        }

        // 3. Create Post
        do_action( 'autoblogai_before_post_create', $content_data );

        $post_status = get_option( 'autoblogai_post_status', 'draft' );

        $post_data = array(
            'post_title'   => wp_strip_all_tags( $content_data['title'] ),
            'post_content' => $content_data['content_html'],
            'post_status'  => $post_status,
            'post_author'  => get_current_user_id(),
            'post_type'    => 'post',
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            $this->logger->log( $topic, 'WP Post creation failed.', 'error' );
            return $post_id;
        }

        // 4. Attach Meta & Image
        if ( $image_id ) {
            set_post_thumbnail( $post_id, $image_id );
        }

        // Save SEO Meta (Custom fields compatible with most SEO plugins logic if bridged, or used by our schema generator)
        update_post_meta( $post_id, '_autoblogai_meta_title', $content_data['meta_title'] );
        update_post_meta( $post_id, '_autoblogai_meta_desc', $content_data['meta_description'] );
        update_post_meta( $post_id, '_yoast_wpseo_title', $content_data['meta_title'] ); // Yoast compat
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $content_data['meta_description'] ); // Yoast compat

        do_action( 'autoblogai_after_post_create', $post_id, $content_data );

        $this->logger->log( $topic, 'Article created successfully.', 'success', $post_id );

        return $post_id;
    }
}
