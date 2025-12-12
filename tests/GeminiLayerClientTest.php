<?php

namespace AutoblogAI\Tests;

use AutoblogAI\API\Client;
use PHPUnit\Framework\TestCase;

class GeminiLayerClientTest extends TestCase {
    public function test_http_transport_error_redacts_api_key() {
        $api_key  = 'SECRETKEY';
        $last_url = null;

        $client = new Client(
            array(
                'api_key'      => $api_key,
                'max_retries'  => 0,
                'http_post'    => static function ( $url, $args ) use ( &$last_url ) {
                    $last_url = $url;
                    return new \WP_Error( 'http_request_failed', 'Request failed for ' . $url );
                },
            )
        );

        $result = $client->generate_content( 'Hello', array( 'expect_json' => false ) );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'autoblogai_http_transport_error', $result->get_error_code() );
        $this->assertNotNull( $last_url );
        $this->assertStringContainsString( 'key=', $last_url );
        $this->assertStringNotContainsString( $api_key, $result->get_error_message() );
    }

    public function test_http_429_maps_to_rate_limited_error() {
        $client = new Client(
            array(
                'api_key'     => 'X',
                'max_retries' => 0,
                'http_post'   => static function ( $url, $args ) {
                    return array(
                        'response' => array( 'code' => 429 ),
                        'body'     => json_encode( array( 'error' => array( 'message' => 'Too many requests' ) ) ),
                    );
                },
            )
        );

        $result = $client->generate_content( 'Hello', array( 'expect_json' => false ) );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'autoblogai_rate_limited', $result->get_error_code() );
    }

    public function test_rate_limiter_waits_when_over_limit() {
        $now   = 1000;
        $slept = array();
        $store = array(
            'autoblogai_gemini_rate_history' => array( 950, 980 ),
        );

        $client = new Client(
            array(
                'api_key'               => 'X',
                'rate_limit_per_minute' => 2,
                'max_retries'           => 0,
                'time_fn'               => static function () use ( &$now ) {
                    return $now;
                },
                'sleep_fn'              => static function ( $seconds ) use ( &$now, &$slept ) {
                    $slept[] = (int) $seconds;
                    $now    += (int) $seconds;
                },
                'get_transient'         => static function ( $key ) use ( &$store ) {
                    return $store[ $key ] ?? false;
                },
                'set_transient'         => static function ( $key, $value, $expiration = 0 ) use ( &$store ) {
                    $store[ $key ] = $value;
                    return true;
                },
                'http_post'             => static function ( $url, $args ) {
                    return array(
                        'response' => array( 'code' => 200 ),
                        'body'     => json_encode(
                            array(
                                'candidates' => array(
                                    array(
                                        'content' => array(
                                            'parts' => array( array( 'text' => 'OK' ) ),
                                        ),
                                    ),
                                ),
                            )
                        ),
                    );
                },
            )
        );

        $result = $client->generate_content( 'Hello', array( 'expect_json' => false ) );

        $this->assertEquals( 'OK', $result );
        $this->assertEquals( array( 10 ), $slept );
        $this->assertContains( 1010, $store['autoblogai_gemini_rate_history'] );
    }
}
