<?php

namespace smartcontentai\Tests;

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
}

require_once ABSPATH . 'includes/class-post-generator.php';

class PostGeneratorPipelineTest extends TestCase {

    private $api_client;
    private $image_generator;
    private $logger;
    private $post_generator;

    protected function setUp(): void {
        $this->api_client      = $this->createMock( 'stdClass' );
        $this->image_generator = $this->createMock( 'stdClass' );
        $this->logger          = $this->createMock( 'stdClass' );

        $this->post_generator = new \smartcontentai_Post_Generator(
            $this->api_client,
            $this->image_generator,
            $this->logger
        );
    }

    public function test_post_generator_initialization() {
        $this->assertInstanceOf( 'smartcontentai_Post_Generator', $this->post_generator );
    }

    public function test_banned_words_detection() {
        update_option( 'smartcontentai_banned_words', 'spam,malware,scam' );

        $content_data = array(
            'title'        => 'Test Article',
            'content_html' => '<p>This is spam content</p>',
            'cta'          => 'Subscribe',
            'faq'          => array(),
            'meta_title'   => 'Test',
            'meta_description' => 'Description',
            'keywords'     => 'test',
            'image_prompts' => array( 'test' ),
        );

        $reflection = new \ReflectionClass( $this->post_generator );
        $method = $reflection->getMethod( 'check_banned_words' );
        $method->setAccessible( true );

        $result = $method->invoke( $this->post_generator, $content_data );
        $this->assertWPError( $result );
        $this->assertEquals( 'smartcontentai_banned_words_detected', $result->get_error_code() );
    }

    public function test_valid_content_passes_banned_words() {
        update_option( 'smartcontentai_banned_words', 'spam,malware' );

        $content_data = array(
            'title'        => 'Test Article',
            'content_html' => '<p>This is valid content</p>',
            'cta'          => 'Subscribe',
            'faq'          => array(),
            'meta_title'   => 'Test',
            'meta_description' => 'Description',
            'keywords'     => 'test',
            'image_prompts' => array( 'test' ),
        );

        $reflection = new \ReflectionClass( $this->post_generator );
        $method = $reflection->getMethod( 'check_banned_words' );
        $method->setAccessible( true );

        $result = $method->invoke( $this->post_generator, $content_data );
        $this->assertTrue( $result );
    }

    public function test_similarity_calculation() {
        $reflection = new \ReflectionClass( $this->post_generator );
        $method = $reflection->getMethod( 'calculate_similarity' );
        $method->setAccessible( true );

        $words1 = array( 'the', 'quick', 'brown', 'fox' );
        $words2 = array( 'the', 'quick', 'fox' );

        $similarity = $method->invoke( $this->post_generator, $words1, $words2 );

        $this->assertGreaterThan( 0, $similarity );
        $this->assertLessThanOrEqual( 1, $similarity );
    }

    public function test_daily_post_count_tracking() {
        $reflection = new \ReflectionClass( $this->post_generator );

        $get_count_method = $reflection->getMethod( 'get_daily_post_count' );
        $get_count_method->setAccessible( true );

        $initial_count = $get_count_method->invoke( $this->post_generator );
        $this->assertEquals( 0, $initial_count );

        $increment_method = $reflection->getMethod( 'increment_daily_post_count' );
        $increment_method->setAccessible( true );

        $increment_method->invoke( $this->post_generator );
        $new_count = $get_count_method->invoke( $this->post_generator );

        $this->assertEquals( 1, $new_count );
    }

    public function test_daily_cap_enforcement() {
        update_option( 'smartcontentai_max_posts_per_day', 2 );

        $reflection = new \ReflectionClass( $this->post_generator );
        $increment_method = $reflection->getMethod( 'increment_daily_post_count' );
        $increment_method->setAccessible( true );

        $increment_method->invoke( $this->post_generator );
        $increment_method->invoke( $this->post_generator );

        $cap_method = $reflection->getMethod( 'check_daily_publishing_cap' );
        $cap_method->setAccessible( true );

        $result = $cap_method->invoke( $this->post_generator );
        $this->assertWPError( $result );
        $this->assertEquals( 'smartcontentai_daily_cap_exceeded', $result->get_error_code() );
    }

    public function test_valid_response_structure_validation() {
        $valid_response = array(
            'title'              => 'Test Article Title',
            'lede'               => 'This is a 50-word introduction that provides context and hooks the reader. It explains what the article covers and why it matters to the audience. Lorem ipsum dolor sit amet consectetur adipiscing.',
            'content_html'       => '<h2>Section 1</h2>' . str_repeat( 'Word ', 400 ) . '<h2>Section 2</h2>' . str_repeat( 'Word ', 600 ) . '<h2>Section 3</h2>' . str_repeat( 'Word ', 400 ),
            'cta'                => 'Subscribe to our newsletter',
            'faq'                => array(
                array( 'question' => 'Q1?', 'answer' => 'Answer 1' ),
                array( 'question' => 'Q2?', 'answer' => 'Answer 2' ),
                array( 'question' => 'Q3?', 'answer' => 'Answer 3' ),
                array( 'question' => 'Q4?', 'answer' => 'Answer 4' ),
                array( 'question' => 'Q5?', 'answer' => 'Answer 5' ),
            ),
            'meta_title'         => 'SEO Title',
            'meta_description'   => 'Description',
            'keywords'           => 'keyword1, keyword2',
            'image_prompts'      => array( 'Hero image prompt', 'Inline image 1', 'Inline image 2' ),
        );

        $reflection = new \ReflectionClass( $this->post_generator );
        $method = $reflection->getMethod( 'parse_and_validate_response' );
        $method->setAccessible( true );

        $result = $method->invoke( $this->post_generator, $valid_response );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'title', $result );
        $this->assertArrayHasKey( 'content_html', $result );
    }

    public function test_invalid_response_missing_field() {
        $invalid_response = array(
            'title' => 'Test Article',
        );

        $reflection = new \ReflectionClass( $this->post_generator );
        $method = $reflection->getMethod( 'parse_and_validate_response' );
        $method->setAccessible( true );

        $result = $method->invoke( $this->post_generator, $invalid_response );

        $this->assertWPError( $result );
        $this->assertEquals( 'smartcontentai_invalid_response', $result->get_error_code() );
    }

    public function test_title_length_validation() {
        $too_long_response = array(
            'title'              => 'This is a title that is way too long and exceeds the 60 character limit significantly',
            'lede'               => 'This is a 50-word introduction that provides context and hooks the reader. It explains what the article covers and why it matters to the audience. Lorem ipsum dolor sit amet consectetur adipiscing.',
            'content_html'       => str_repeat( '<p>Word </p>', 400 ),
            'cta'                => 'Subscribe',
            'faq'                => array(
                array( 'question' => 'Q1?', 'answer' => 'Answer 1' ),
                array( 'question' => 'Q2?', 'answer' => 'Answer 2' ),
                array( 'question' => 'Q3?', 'answer' => 'Answer 3' ),
                array( 'question' => 'Q4?', 'answer' => 'Answer 4' ),
                array( 'question' => 'Q5?', 'answer' => 'Answer 5' ),
            ),
            'meta_title'         => 'Meta Title',
            'meta_description'   => 'Description',
            'keywords'           => 'keywords',
            'image_prompts'      => array( 'Image prompt' ),
        );

        $reflection = new \ReflectionClass( $this->post_generator );
        $method = $reflection->getMethod( 'parse_and_validate_response' );
        $method->setAccessible( true );

        $result = $method->invoke( $this->post_generator, $too_long_response );

        $this->assertWPError( $result );
        $this->assertStringContainsString( 'Title exceeds 60 characters', $result->get_error_message() );
    }

    public function test_faq_validation() {
        $invalid_faq_response = array(
            'title'              => 'Test Article',
            'lede'               => 'This is a 50-word introduction that provides context and hooks the reader. It explains what the article covers and why it matters to the audience. Lorem ipsum dolor sit amet consectetur adipiscing.',
            'content_html'       => str_repeat( '<p>Word </p>', 400 ),
            'cta'                => 'Subscribe',
            'faq'                => array(
                array( 'question' => 'Q1?', 'answer' => 'Answer 1' ),
            ),
            'meta_title'         => 'Meta',
            'meta_description'   => 'Desc',
            'keywords'           => 'keywords',
            'image_prompts'      => array( 'Image prompt' ),
        );

        $reflection = new \ReflectionClass( $this->post_generator );
        $method = $reflection->getMethod( 'parse_and_validate_response' );
        $method->setAccessible( true );

        $result = $method->invoke( $this->post_generator, $invalid_faq_response );

        $this->assertWPError( $result );
        $this->assertStringContainsString( 'FAQ must contain exactly 5 pairs', $result->get_error_message() );
    }

    protected function assertWPError( $thing ) {
        if ( function_exists( 'is_wp_error' ) ) {
            $this->assertTrue( is_wp_error( $thing ) );
        } else {
            $this->assertInstanceOf( 'WP_Error', $thing );
        }
    }
}
