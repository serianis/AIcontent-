<?php

namespace SmartContentAI\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scheduler {

    public const DAILY_EVENT_HOOK     = 'smartcontentai_daily_publish_event';
    public const QUEUE_OPTION_KEY     = 'smartcontentai_publish_queue';
    public const DAILY_CAP_OPTION     = 'smartcontentai_max_posts_per_day';
    public const FREQUENCY_OPTION_KEY = 'smartcontentai_schedule_frequency';

    private bool $is_running = false;

    public function __construct() {
        add_action( self::DAILY_EVENT_HOOK, array( $this, 'process_scheduled_posts' ) );
    }

    public function schedule_events( ?string $recurrence = null ) {
        $recurrence = $recurrence ?: (string) get_option( self::FREQUENCY_OPTION_KEY, 'daily' );

        $allowed = array_keys( $this->get_admin_schedules() );
        if ( ! in_array( $recurrence, $allowed, true ) ) {
            $recurrence = 'daily';
        }

        // Ensure we don't keep old recurrences around.
        $this->unschedule_events();

        if ( ! wp_next_scheduled( self::DAILY_EVENT_HOOK ) ) {
            wp_schedule_event( time(), $recurrence, self::DAILY_EVENT_HOOK );
        }
    }

    public function unschedule_events() {
        while ( $timestamp = wp_next_scheduled( self::DAILY_EVENT_HOOK ) ) {
            wp_unschedule_event( $timestamp, self::DAILY_EVENT_HOOK );
        }
    }

    public function get_admin_schedules(): array {
        return array(
            'daily'      => array( 'label' => __( 'Daily', 'smartcontentai' ) ),
            'twicedaily' => array( 'label' => __( 'Twice Daily', 'smartcontentai' ) ),
            'hourly'     => array( 'label' => __( 'Hourly', 'smartcontentai' ) ),
        );
    }

    public function get_schedule_status(): array {
        $timestamp = wp_next_scheduled( self::DAILY_EVENT_HOOK );

        return array(
            'is_scheduled' => false !== $timestamp,
            'next_run'     => $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null,
            'queue_count'  => $this->get_queued_topics_count(),
            'daily_cap'    => $this->get_daily_publish_cap(),
            'posts_today'  => $this->get_posts_published_today(),
        );
    }

    public function set_daily_publish_cap( $cap ) {
        if ( ! is_numeric( $cap ) || $cap < 0 ) {
            return new WP_Error( 'invalid_cap', 'Daily publish cap must be a non-negative number' );
        }

        update_option( self::DAILY_CAP_OPTION, (int) $cap );
        return true;
    }

    public function get_daily_publish_cap(): int {
        return (int) get_option( self::DAILY_CAP_OPTION, 10 );
    }

    public function get_posts_published_today(): int {
        $count = get_transient( 'smartcontentai_daily_post_count' );
        return false === $count ? 0 : (int) $count;
    }

    public function enqueue_topic( $topic, $keyword = '' ) {
        $queue = get_option( self::QUEUE_OPTION_KEY, array() );
        if ( ! is_array( $queue ) ) {
            $queue = array();
        }

        $queue[] = array(
            'topic'     => sanitize_text_field( $topic ),
            'keyword'   => sanitize_text_field( $keyword ),
            'queued_at' => current_time( 'mysql' ),
        );

        update_option( self::QUEUE_OPTION_KEY, $queue );
        return true;
    }

    public function dequeue_topic() {
        $queue = get_option( self::QUEUE_OPTION_KEY, array() );
        if ( ! is_array( $queue ) || empty( $queue ) ) {
            return false;
        }

        $topic = array_shift( $queue );
        update_option( self::QUEUE_OPTION_KEY, $queue );
        return $topic;
    }

    public function get_queued_topics(): array {
        $queue = get_option( self::QUEUE_OPTION_KEY, array() );
        return is_array( $queue ) ? $queue : array();
    }

    public function get_queued_topics_count(): int {
        return count( $this->get_queued_topics() );
    }

    public function clear_queue(): bool {
        delete_option( self::QUEUE_OPTION_KEY );
        return true;
    }

    public function process_scheduled_posts() {
        if ( $this->is_running ) {
            return new WP_Error( 'already_running', 'Scheduler is already running' );
        }

        $this->is_running = true;

        try {
            $daily_cap       = $this->get_daily_publish_cap();
            $published_today = $this->get_posts_published_today();

            if ( $daily_cap > 0 && $published_today >= $daily_cap ) {
                $this->is_running = false;
                return new WP_Error( 'daily_cap_reached', 'Daily publish cap has been reached' );
            }

            $topic = $this->dequeue_topic();
            if ( ! $topic ) {
                $this->is_running = false;
                return new WP_Error( 'no_topics', 'No topics in queue' );
            }

            do_action( 'smartcontentai_process_queued_topic', $topic );

            $this->is_running = false;
            return true;

        } catch ( \Exception $e ) {
            $this->is_running = false;
            return new WP_Error( 'process_error', $e->getMessage() );
        }
    }
}
