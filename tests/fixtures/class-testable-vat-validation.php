<?php
/**
 * EU VAT Validation (mock)
 *
 * @package Chocante_VAT_EU
 */

/**
 * Modified validator class
 */
class Testable_VAT_Validation extends Chocante_VAT_Validation {
	/**
	 * HTTP request mock
	 *
	 * @var callable
	 */
	private $http_client;

	/**
	 * Add ability to modify instance
	 *
	 * @param callable $api_response Modified API method.
	 */
	public function __construct( $api_response ) {
		$this->http_client = $api_response;
	}

	/**
	 * Mock VIES API call method
	 *
	 * @param string $url API url.
	 * @throws Error API error.
	 * @return string
	 */
	protected function call_vies_api( $url ) {
		return ( $this->http_client )( $url );
	}
}
