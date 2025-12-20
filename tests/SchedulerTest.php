<?php

namespace smartcontentai\Tests;

use smartcontentai\Core\Scheduler;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
}

class SchedulerTest extends TestCase {

	private $scheduler;

	protected function setUp(): void {
		$this->scheduler = new Scheduler();
	}

	public function test_enqueue_and_dequeue_topic() {
		$this->scheduler->clear_queue();

		$topic = 'Test Article Topic';
		$keyword = 'test keyword';

		$this->scheduler->enqueue_topic( $topic, $keyword );
		$this->assertEquals( 1, $this->scheduler->get_queued_topics_count() );

		$dequeued = $this->scheduler->dequeue_topic();
		$this->assertNotEmpty( $dequeued );
		$this->assertEquals( 'Test Article Topic', $dequeued['topic'] );
		$this->assertEquals( 'test keyword', $dequeued['keyword'] );
		$this->assertEquals( 0, $this->scheduler->get_queued_topics_count() );
	}

	public function test_multiple_enqueue() {
		$this->scheduler->clear_queue();

		$this->scheduler->enqueue_topic( 'Topic 1', 'keyword1' );
		$this->scheduler->enqueue_topic( 'Topic 2', 'keyword2' );
		$this->scheduler->enqueue_topic( 'Topic 3', 'keyword3' );

		$this->assertEquals( 3, $this->scheduler->get_queued_topics_count() );

		$first = $this->scheduler->dequeue_topic();
		$this->assertEquals( 'Topic 1', $first['topic'] );
		$this->assertEquals( 2, $this->scheduler->get_queued_topics_count() );
	}

	public function test_dequeue_empty_queue() {
		$this->scheduler->clear_queue();
		$result = $this->scheduler->dequeue_topic();
		$this->assertFalse( $result );
	}

	public function test_get_admin_schedules() {
		$schedules = $this->scheduler->get_admin_schedules();
		$this->assertIsArray( $schedules );
		$this->assertArrayHasKey( 'daily', $schedules );
		$this->assertArrayHasKey( 'twicedaily', $schedules );
		$this->assertArrayHasKey( 'hourly', $schedules );
	}

	public function test_get_schedule_status() {
		$status = $this->scheduler->get_schedule_status();
		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'is_scheduled', $status );
		$this->assertArrayHasKey( 'next_run', $status );
		$this->assertArrayHasKey( 'queue_count', $status );
		$this->assertArrayHasKey( 'daily_cap', $status );
		$this->assertArrayHasKey( 'posts_today', $status );
	}

	public function test_set_daily_publish_cap() {
		$result = $this->scheduler->set_daily_publish_cap( 5 );
		$this->assertTrue( $result );
		$this->assertEquals( 5, $this->scheduler->get_daily_publish_cap() );
	}

	public function test_set_invalid_daily_publish_cap() {
		$result = $this->scheduler->set_daily_publish_cap( -1 );
		$this->assertWPError( $result );

		$result = $this->scheduler->set_daily_publish_cap( 'invalid' );
		$this->assertWPError( $result );
	}

	public function test_clear_queue() {
		$this->scheduler->enqueue_topic( 'Topic', 'keyword' );
		$this->assertEquals( 1, $this->scheduler->get_queued_topics_count() );

		$this->scheduler->clear_queue();
		$this->assertEquals( 0, $this->scheduler->get_queued_topics_count() );
	}

	protected function assertWPError( $thing ) {
		$this->assertInstanceOf( 'WP_Error', $thing );
	}
}
