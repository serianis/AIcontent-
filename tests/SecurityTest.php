<?php

namespace smartcontentai\Tests;

use smartcontentai\Core\Security;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
}

class SecurityTest extends TestCase {

	private $security;

	protected function setUp(): void {
		$this->security = new Security();
	}

	public function test_encrypt_and_decrypt_api_key() {
		$api_key = 'test-api-key-12345';
		$encrypted = $this->security->encrypt_api_key( $api_key );

		$this->assertIsString( $encrypted );
		$this->assertNotEmpty( $encrypted );
		$this->assertNotEquals( $api_key, $encrypted );

		$decrypted = $this->security->decrypt_api_key( $encrypted );
		$this->assertEquals( $api_key, $decrypted );
	}

	public function test_encrypt_empty_api_key() {
		$encrypted = $this->security->encrypt_api_key( '' );
		$this->assertEquals( '', $encrypted );
	}

	public function test_decrypt_empty_api_key() {
		$decrypted = $this->security->decrypt_api_key( '' );
		$this->assertEquals( '', $decrypted );
	}

	public function test_sanitize_text() {
		$dirty = '<script>alert("XSS")</script>Hello World';
		$clean = $this->security->sanitize_text( $dirty );
		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertStringContainsString( 'Hello World', $clean );
	}

	public function test_sanitize_email() {
		$valid_email = 'test@example.com';
		$result = $this->security->sanitize_email( $valid_email );
		$this->assertEquals( $valid_email, $result );

		$invalid_email = 'not-an-email<script>';
		$result = $this->security->sanitize_email( $invalid_email );
		$this->assertNotContains( '<script>', $result );
	}

	public function test_sanitize_url() {
		$valid_url = 'https://example.com/path';
		$result = $this->security->sanitize_url( $valid_url );
		$this->assertStringContainsString( 'example.com', $result );

		$invalid_url = 'javascript:alert("XSS")';
		$result = $this->security->sanitize_url( $invalid_url );
		$this->assertEmpty( $result );
	}

	public function test_sanitize_value_with_types() {
		$this->assertEquals( '123', $this->security->sanitize_value( '123', 'text' ) );
		$this->assertEquals( 123, $this->security->sanitize_value( '123', 'int' ) );
		$this->assertEquals( 123.45, $this->security->sanitize_value( '123.45', 'float' ) );
		$this->assertTrue( $this->security->sanitize_value( '1', 'boolean' ) );
		$this->assertFalse( $this->security->sanitize_value( '0', 'boolean' ) );
	}

	public function test_sanitize_array() {
		$dirty_array = array(
			'<script>key</script>' => '<b>value</b>',
			'normal_key'            => 'normal_value',
		);

		$clean = $this->security->sanitize_array( $dirty_array );
		$this->assertIsArray( $clean );
		$this->assertStringNotContainsString( '<script>', json_encode( $clean ) );
	}

	public function test_sanitize_non_array() {
		$result = $this->security->sanitize_array( 'not_an_array' );
		$this->assertEquals( array(), $result );
	}

	public function test_create_nonce() {
		$nonce = $this->security->create_nonce( 'test_action' );
		$this->assertIsString( $nonce );
		$this->assertNotEmpty( $nonce );
	}

	public function test_current_user_can() {
		$result = $this->security->current_user_can( 'manage_options' );
		$this->assertIsBool( $result );
	}
}
