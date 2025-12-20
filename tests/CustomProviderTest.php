<?php

use PHPUnit\Framework\TestCase;
use SmartContentAI\Providers\CustomProvider;
use SmartContentAI\Core\Database;

/**
 * Custom Provider Test Case
 * 
 * Tests the CustomProvider functionality including:
 * - Provider creation and configuration
 * - API request handling
 * - Model management
 * - Authentication methods
 */
class CustomProviderTest extends TestCase {
    
    private $testProviderData;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Sample provider data for testing
        $this->testProviderData = array(
            'id' => 1,
            'name' => 'Test Provider',
            'slug' => 'test-provider',
            'base_url' => 'https://api.test.com/v1',
            'auth_type' => 'api_key',
            'api_key' => 'test-api-key',
            'custom_headers' => "X-Custom-Header: test-value\nAnother-Header: value2",
            'enabled' => 1,
            'is_default' => 0,
        );
    }
    
    public function testProviderCreation() {
        $provider = new CustomProvider( $this->testProviderData );
        
        $this->assertEquals( 'Test Provider', $provider->get_name() );
        $this->assertEquals( 'test-provider', $provider->get_slug() );
        $this->assertEquals( 'https://api.test.com/v1', $provider->get_base_url() );
    }
    
    public function testHeadersWithApiKey() {
        $provider = new CustomProvider( $this->testProviderData );
        $headers = $provider->get_headers( '' );
        
        $this->assertArrayHasKey( 'Authorization', $headers );
        $this->assertEquals( 'Bearer test-api-key', $headers['Authorization'] );
        $this->assertArrayHasKey( 'Content-Type', $headers );
        $this->assertEquals( 'application/json', $headers['Content-Type'] );
        \n    }\n    \n    public function testHeadersWithCustomAuthType() {\n        $data = $this->testProviderData;\n        $data['auth_type'] = 'custom_header';\n        \n        $provider = new CustomProvider( $data );\n        $headers = $provider->get_headers( '' );\n        \n        $this->assertArrayHasKey( 'X-API-Key', $headers );\n        $this->assertEquals( 'test-api-key', $headers['X-API-Key'] );\n    }\n    \n    public function testCustomHeaders() {\n        $provider = new CustomProvider( $this->testProviderData );\n        $headers = $provider->get_headers( '' );\n        \n        $this->assertArrayHasKey( 'X-Custom-Header', $headers );\n        $this->assertEquals( 'test-value', $headers['X-Custom-Header'] );\n        $this->assertArrayHasKey( 'Another-Header', $headers );\n        $this->assertEquals( 'value2', $headers['Another-Header'] );\n    }\n    \n    public function testModelConfiguration() {\n        $provider = new CustomProvider( $this->testProviderData );\n        $models = $provider->get_models();\n        \n        // Should return empty array since no models are configured yet\n        $this->assertIsArray( $models );\n        $this->assertEmpty( $models );\n    }\n    \n    public function testMaxTokens() {\n        $provider = new CustomProvider( $this->testProviderData );\n        $maxTokens = $provider->get_max_tokens( 'test-model' );\n        \n        $this->assertEquals( 4096, $maxTokens );\n    }\n    \n    public function testCostPer1k() {\n        $provider = new CustomProvider( $this->testProviderData );\n        $cost = $provider->get_cost_per_1k( 'test-model' );\n        \n        $this->assertEquals( 0.0, $cost );\n    }\n    \n    public function testErrorResponse() {\n        $provider = new CustomProvider( $this->testProviderData );\n        \n        $errorResponse = array(\n            'success' => false,\n            'error' => 'Test error message',\n            'data' => null,\n        );\n        \n        $parsed = $provider->parse_response( $errorResponse );\n        \n        $this->assertEquals( '', $parsed['content'] );\n        $this->assertEquals( 0, $parsed['tokens_used'] );\n        $this->assertEquals( 'error', $parsed['finish_reason'] );\n    }\n    \n    public function testSuccessfulResponse() {\n        $provider = new CustomProvider( $this->testProviderData );\n        \n        $successResponse = array(\n            'success' => true,\n            'error' => null,\n            'data' => array(\n                'choices' => array(\n                    array(\n                        'message' => array(\n                            'content' => 'Test response content',\n                        ),\n                        'finish_reason' => 'stop',\n                    ),\n                ),\n                'usage' => array(\n                    'total_tokens' => 100,\n                ),\n                'model' => 'test-model',\n            ),\n        );\n        \n        $parsed = $provider->parse_response( $successResponse );\n        \n        $this->assertEquals( 'Test response content', $parsed['content'] );\n        $this->assertEquals( 100, $parsed['tokens_used'] );\n        $this->assertEquals( 'test-model', $parsed['model'] );\n        $this->assertEquals( 'stop', $parsed['finish_reason'] );\n    }\n    \n    public function testEmptyBaseUrl() {\n        $data = $this->testProviderData;\n        $data['base_url'] = '';\n        \n        $provider = new CustomProvider( $data );\n        $result = $provider->make_request( 'test-model', array( array( 'role' => 'user', 'content' => 'test' ) ) );\n        \n        $this->assertFalse( $result['success'] );\n        $this->assertStringContainsString( 'No base URL configured', $result['error'] );\n    }\n    \n    public function testEmptyApiKey() {\n        $data = $this->testProviderData;\n        $data['api_key'] = '';\n        \n        $provider = new CustomProvider( $data );\n        $result = $provider->make_request( 'test-model', array( array( 'role' => 'user', 'content' => 'test' ) ) );\n        \n        $this->assertFalse( $result['success'] );\n        $this->assertStringContainsString( 'No API key configured', $result['error'] );\n    }\n    \n    public function testMessageFormatting() {\n        $provider = new CustomProvider( $this->testProviderData );\n        \n        // Test string message conversion\n        $messages = array( 'Hello world' );\n        $formatted = $this->invokeMethod( $provider, 'format_messages', array( $messages ) );\n        \n        $this->assertIsArray( $formatted );\n        $this->assertEquals( 'user', $formatted[0]['role'] );\n        $this->assertEquals( 'Hello world', $formatted[0]['content'] );\n        \n        // Test structured message\n        $messages = array( array( 'role' => 'user', 'content' => 'Hello' ) );\n        $formatted = $this->invokeMethod( $provider, 'format_messages', array( $messages ) );\n        \n        $this->assertIsArray( $formatted );\n        $this->assertEquals( 'user', $formatted[0]['role'] );\n        $this->assertEquals( 'Hello', $formatted[0]['content'] );\n    }\n    \n    public function testCustomHeadersParsing() {\n        $provider = new CustomProvider( $this->testProviderData );\n        \n        $headersText = \"X-Custom-Header: value1\\nAnother-Header: value2\\n\\nInvalid-Line\";\n        $parsed = $this->invokeMethod( $provider, 'parse_custom_headers', array( $headersText ) );\n        \n        $this->assertArrayHasKey( 'X-Custom-Header', $parsed );\n        $this->assertEquals( 'value1', $parsed['X-Custom-Header'] );\n        $this->assertArrayHasKey( 'Another-Header', $parsed );\n        $this->assertEquals( 'value2', $parsed['Another-Header'] );\n    }\n    \n    /**\n     * Helper method to invoke private/protected methods\n     */\n    private function invokeMethod( $object, $methodName, array $parameters = array() ) {\n        $reflection = new \\ReflectionClass( get_class( $object ) );\n        $method = $reflection->getMethod( $methodName );\n        $method->setAccessible( true );\n        return $method->invokeArgs( $object, $parameters );\n    }\n}\n