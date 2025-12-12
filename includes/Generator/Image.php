<?php

namespace AutoblogAI\Generator;

use AutoblogAI\API\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\AutoblogAI_Image_Generator' ) ) {
	require_once dirname( __DIR__ ) . '/class-image-generator.php';
}

class Image extends \AutoblogAI_Image_Generator {
	public function __construct( Client $api_client, array $args = array() ) {
		parent::__construct( $api_client, $args );
	}
}
