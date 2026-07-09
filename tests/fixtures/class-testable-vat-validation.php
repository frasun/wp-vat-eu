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
	 * Call VIES API
	 *
	 * @param string $country_code Country code.
	 * @param string $vat_number VAT number.
	 * @throws Error API error.
	 * @return array
	 */
	protected function call_vies_api( $country_code, $vat_number ) {
		return ( $this->http_client )( $country_code, $vat_number );
	}
}
