<?php

namespace AutoblogAI\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\AutoblogAI_API_Client' ) ) {
	require_once dirname( __DIR__ ) . '/class-api-client.php';
}

class Client extends \AutoblogAI_API_Client {
}
