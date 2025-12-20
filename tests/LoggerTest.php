<?php

namespace smartcontentai\Tests;

use smartcontentai\Utils\Logger;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
}

class LoggerTest extends TestCase {

	private $logger;

	protected function setUp(): void {
		$this->logger = new Logger();
	}

	public function test_logger_instantiation() {
		$this->assertInstanceOf( Logger::class, $this->logger );
	}

	public function test_log_with_string_payload() {
		$payload = 'Test payload string';
		$response = 'Test response';
		$status = 'success';

		$this->logger->log( $payload, $response, $status, 123 );
		$logs = $this->logger->get_logs( 1 );

		$this->assertNotEmpty( $logs );
		$this->assertEquals( $response, $logs[0]->response_excerpt );
		$this->assertEquals( $status, $logs[0]->status );
		$this->assertEquals( 123, $logs[0]->post_id );
	}

	public function test_log_with_array_payload() {
		$payload = array(
			'topic'   => 'Test Article',
			'keyword' => 'test keyword',
		);
		$response = 'Article generated successfully';
		$status = 'success';

		$this->logger->log( $payload, $response, $status, 456 );
		$logs = $this->logger->get_logs( 1 );

		$this->assertNotEmpty( $logs );
		$this->assertStringContainsString( 'Test Article', $logs[0]->request_payload );
	}

	public function test_request_hash_generation() {
		$payload = 'Test payload for hashing';
		$this->logger->log( $payload, 'response', 'success' );

		$logs = $this->logger->get_logs( 1 );
		$this->assertNotEmpty( $logs[0]->request_hash );
		$this->assertEquals( 64, strlen( $logs[0]->request_hash ) );
	}

	public function test_get_logs_by_status() {
		$this->logger->log( 'test1', 'response1', 'success' );
		$this->logger->log( 'test2', 'response2', 'error' );
		$this->logger->log( 'test3', 'response3', 'success' );

		$success_logs = $this->logger->get_logs_by_status( 'success' );
		$this->assertGreaterThan( 1, count( $success_logs ) );

		$error_logs = $this->logger->get_logs_by_status( 'error' );
		$this->assertGreaterThan( 0, count( $error_logs ) );
	}

	public function test_get_logs_by_post_id() {
		$this->logger->log( 'test1', 'response1', 'success', 789 );
		$this->logger->log( 'test2', 'response2', 'success', 789 );
		$this->logger->log( 'test3', 'response3', 'success', 999 );

		$logs = $this->logger->get_logs_by_post_id( 789 );
		$this->assertGreaterThan( 1, count( $logs ) );

		foreach ( $logs as $log ) {
			$this->assertEquals( 789, $log->post_id );
		}
	}

	public function test_get_logs_statistics() {
		$this->logger->log( 'test1', 'response1', 'success', 100 );
		$this->logger->log( 'test2', 'response2', 'error', 101 );

		$stats = $this->logger->get_logs_statistics();
		$this->assertIsObject( $stats );
		$this->assertObjectHasAttribute( 'total_logs', $stats );
		$this->assertObjectHasAttribute( 'successful', $stats );
		$this->assertObjectHasAttribute( 'failed', $stats );
		$this->assertObjectHasAttribute( 'unique_posts', $stats );
	}

	public function test_clear_old_logs() {
		$this->logger->log( 'test', 'response', 'success' );
		$cleared = $this->logger->clear_old_logs( 0 );
		$this->assertIsNumeric( $cleared );
	}
}
