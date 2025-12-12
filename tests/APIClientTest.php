<?php

namespace AutoblogAI\Tests;

use AutoblogAI\API\Client;
use AutoblogAI\Generator\Image;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
}

class APIClientTest extends TestCase {
	private Client $client;

	protected function setUp(): void {
		$this->client = new Client();
	}

	public function test_client_initialization() {
		$this->assertInstanceOf( Client::class, $this->client );
	}

	public function test_missing_api_key_for_text_generation() {
		$prompt = 'Test prompt';
		$result = $this->client->generate_text( $prompt );

		$this->assertWPError( $result );
		$this->assertEquals( 'autoblogai_api_key_missing', $result->get_error_code() );
	}

	public function test_missing_api_key_for_image_generation() {
		$image_generator = new Image( $this->client );
		$result          = $image_generator->generate_image_base64( 'A test image' );

		$this->assertWPError( $result );
		$this->assertEquals( 'autoblogai_api_key_missing', $result->get_error_code() );
	}

	protected function assertWPError( $thing ) {
		$this->assertInstanceOf( 'WP_Error', $thing );
	}
}
