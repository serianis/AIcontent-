<?php

namespace SmartContentAI\Generator;

use SmartContentAI\API\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\SmartContentAI_Image_Generator' ) ) {
	require_once dirname( __DIR__ ) . '/class-image-generator.php';
}

class Image extends \SmartContentAI_Image_Generator {
	public function __construct( Client $api_client, array $args = array() ) {
		parent::__construct( $api_client, $args );
	}
}
