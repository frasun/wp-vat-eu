<?php
/**
 * EU VAT Validation
 *
 * @package Chocante_VAT_Validation
 */

require_once __DIR__ . '/vat-validation-patterns.php';

use const WP_VAT_EU\COUNTRY_PATTERNS;

/**
 * The Chocante_VAT_Validation class.
 */
class Chocante_VAT_Validation {
	const ERROR_MISSING_VAT_ID   = 'MISSING_VAT_ID';
	const ERROR_MISSING_COUNTRY  = 'MISSING_COUNTRY';
	const ERROR_INCORRECT_FORMAT = 'INCORRECT_FORMAT';
	const ERROR_API              = 'VIES_API_ERROR';

	/**
	 * Validation error
	 *
	 * @var string
	 */
	protected $error = null;

	/**
	 * Sanitize input data
	 *
	 * @param string $argument Input string.
	 * @return string
	 */
	public static function sanitize_input( $argument ) {
		$argument = preg_replace( '/[^a-zA-Z0-9]/', '', $argument );
		$argument = strtoupper( $argument );
		$argument = filter_var( $argument, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );

		return $argument;
	}

	/**
	 * Check valid EU country VAT pattern
	 *
	 * @param string $country Country code.
	 * @param string $vat_number EU VAT number.
	 * @return bool
	 */
	public static function validate_vat_format( $country, $vat_number ) {
		return isset( COUNTRY_PATTERNS[ $country ] ) && preg_match( COUNTRY_PATTERNS[ $country ]['pattern'], $vat_number );
	}

	/**
	 * Remove country code from the VAT number
	 *
	 * @param string $country Selected country.
	 * @param string $vat_number VAT number.
	 * @return string
	 */
	private static function split_vat_id( $country, $vat_number = '' ) {
		return str_starts_with( $vat_number, $country ) ? substr( $vat_number, 2 ) : $vat_number;
	}

	/**
	 * Validate EU VAT number
	 *
	 * @param string $country Country code.
	 * @param string $vat_id VAT number.
	 * @throws Error API error.
	 * @return bool
	 */
	public function validate( $country, $vat_id ) {
		if ( empty( $vat_id ) ) {
			$this->error = self::ERROR_MISSING_VAT_ID;
			return false;
		}

		if ( empty( $country ) ) {
			$this->error = self::ERROR_MISSING_COUNTRY;
			return false;
		}

		$vat_number = self::split_vat_id( $country, $vat_id );

		if ( ! self::validate_vat_format( $country, $vat_number ) ) {
			$this->error = self::ERROR_INCORRECT_FORMAT;
			return false;
		}

		try {
			$result = $this->call_vies_api( $country, $vat_number );
		} catch ( Throwable $ex ) {
			return $this->validate_local( $country, $vat_number );
		}

		return $result['valid'];
	}

	/**
	 * Return last error
	 *
	 * @return string
	 */
	public function get_error() {
		return $this->error;
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
		// phpcs:disable
		$ch = curl_init();

		$body = json_encode(
			array(
				'countryCode' => $country_code,
				'vatNumber'   => $vat_number,
			)
		);

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_SSLVERSION     => 6,
				CURLOPT_SSL_OPTIONS    => 128,
				CURLOPT_USERAGENT      => 'curl/7.81.0',
				CURLOPT_TIMEOUT        => 20,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $body,
				CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
			)
		);

		$response  = curl_exec( $ch );
		$error     = curl_error( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		// phpcs:enable

		if ( $error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( htmlspecialchars( $error ) );
		}

		$data = json_decode( $response, true );

		if ( 200 !== $http_code ) {
			$message = $data['errorWrappers'][0]['error'] ?? ( 'HTTP Code: ' . $http_code );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( htmlspecialchars( $message ) );
		}

		return $data;
	}

	/**
	 * Validate VAT number using local validator
	 *
	 * @param string $country Country code.
	 * @param string $vat_id VAT number.
	 * @return bool
	 */
	public function validate_local( $country, $vat_id ) {
		$vat_number = self::split_vat_id( $country, $vat_id );
		$validator  = COUNTRY_PATTERNS[ $country ]['validator'];

		return empty( $validator ) || $validator( $vat_number );
	}
}
