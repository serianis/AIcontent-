<?php

namespace SmartContentAI\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\SmartContentAI_API_Client' ) ) {
	require_once dirname( __DIR__ ) . '/class-api-client.php';
}

class Client extends \SmartContentAI_API_Client {
}
