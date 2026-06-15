<?php
/**
 * EU VAT Validation
 *
 * @package Chocante_VAT_Validation
 */

/**
 * The Chocante_VAT_Validation class.
 */
class Chocante_VAT_Validation {
	private const EU_COUNTRY_LIST = array(
		// Austria.
		'AT' => array(
			'length'  => 9,
			'pattern' => '/U\d{8}/',
		),
		// Belgium.
		'BE' => array(
			'length'  => 10,
			'pattern' => '/\d{10}/',
		),
		// Bulgaria.
		'BG' => array(
			'length'  => 10,
			'pattern' => '/\d{9,10}/',
		),
		// Cyprus.
		'CY' => array(
			'length'  => 9,
			'pattern' => '/\d{8}[A-Z]/',
		),
		// Czech Republic.
		'CZ' => array(
			'length'  => 10,
			'pattern' => '/\d{8,10}/',
		),
		// Germany.
		'DE' => array(
			'length'  => 9,
			'pattern' => '/\d{9}/',
		),
		// Denmark.
		'DK' => array(
			'length'  => 8,
			'pattern' => '/\d{8}/',
		),
		// Estonia.
		'EE' => array(
			'length'  => 9,
			'pattern' => '/\d{9}/',
		),
		// Greece.
		'GR' => array(
			'length'  => 9,
			'pattern' => '/\d{9}/',
		),
		// Spain.
		'ES' => array(
			'length'  => 9,
			'pattern' => '/[A-Z]\d{2}(?:\d{6}|\d{5}[A-Z])/',
		),
		// Finland.
		'FI' => array(
			'length'  => 8,
			'pattern' => '/\d{8}$/',
		),
		// France.
		'FR' => array(
			'length'  => 11,
			'pattern' => '/[A-Z0-9]{2}\d{9}/',
		),
		// Croatia.
		'HR' => array(
			'length'  => 11,
			'pattern' => '/\d{11}/',
		),
		// Hungary.
		'HU' => array(
			'length'  => 8,
			'pattern' => '/\d{8}/',
		),
		// Ireland.
		'IE' => array(
			'length'  => 9,
			'pattern' => '/(\d{7}[A-Z]{1,2}|(\d{1}[A-Z]{1}\d{5}[A-Z]{1}))/',
		),
		// Italy.
		'IT' => array(
			'length'  => 11,
			'pattern' => '/\d{11}/',
		),
		// Luxembourg.
		'LU' => array(
			'length'  => 8,
			'pattern' => '/\d{8}/',
		),
		// Latvia.
		'LV' => array(
			'length'  => 11,
			'pattern' => '/\d{11}/',
		),
		// Lithuania.
		'LT' => array(
			'length'  => 12,
			'pattern' => '/(\d{9}|\d{12})/',
		),
		// Malta.
		'MT' => array(
			'length'  => 8,
			'pattern' => '/\d{8}/',
		),
		// Netherlands.
		'NL' => array(
			'length'  => 12,
			'pattern' => '/\d{9}B\d{2}/',
		),
		// Poland.
		'PL' => array(
			'length'  => 10,
			'pattern' => '/\d{10}/',
		),
		// Portugal.
		'PT' => array(
			'length'  => 9,
			'pattern' => '/\d{9}/',
		),
		// Romania.
		'RO' => array(
			'length'  => 8,
			'pattern' => '/\d{2,10}/',
		),
		// Sweden.
		'SE' => array(
			'length'  => 12,
			'pattern' => '/\d{12}/',
		),
		// Slovenia.
		'SI' => array(
			'length'  => 8,
			'pattern' => '/\d{8}/',
		),
		// Slovakia.
		'SK' => array(
			'length'  => 10,
			'pattern' => '/\d{10}/',
		),
	);

	const ERROR_MISSING_VAT_ID   = 'MISSING_VAT_ID';
	const ERROR_MISSING_COUNTRY  = 'MISSING_COUNTRY';
	const ERROR_INCORRECT_FORMAT = 'INCORRECT_FORMAT';
	const ERROR_RATE_LIMIT       = 'MS_MAX_CONCURRENT_REQ';
	const ERROR_INVALID          = 'INVALID_JSON_RESPONSE';

	/**
	 * VIES API URL
	 *
	 * @var string
	 */
	private $api_url = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/$1/vat/$2';

	/**
	 * Error details
	 *
	 * @var string
	 */
	protected $error = null;

	/**
	 * Remove country code from VAT number (if consists)
	 *
	 * @param string $country Selected country.
	 * @param string $vat_id VAT number.
	 * @return string
	 */
	private static function split_vat_id( $country, $vat_id = '' ) {
		if ( 'FR' === $country ) {
			$vat_number = substr( $vat_id, -self::EU_COUNTRY_LIST[ $country ]['length'], self::EU_COUNTRY_LIST[ $country ]['length'] );
		} elseif ( ctype_alpha( substr( $vat_id, 0, 2 ) ) ) {
			$vat_number = substr( $vat_id, 2 );
		} else {
			$vat_number = $vat_id;
		}

		return $vat_number;
	}

	/**
	 * Filter data before sending to the VIES service
	 *
	 * @param string $argument Raw string.
	 * @return string
	 */
	public static function sanitize_input( $argument ) {
		$argument = preg_replace( '/[^a-zA-Z0-9]/', '', $argument );
		$argument = strtoupper( $argument );
		$argument = filter_var( $argument, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );

		return $argument;
	}

	/**
	 * Validate country is intra-EU or not
	 *
	 * @param string $country Country code.
	 * @return bool
	 */
	private static function validate_country( $country ) {
		return isset( $country, self::EU_COUNTRY_LIST[ $country ] );
	}

	/**
	 * Validate VAT ID pattern
	 *
	 * @param string $country Country code.
	 * @param string $vat_number VAT number.
	 * @return bool
	 */
	private static function validate_pattern( $country, $vat_number ) {
		$country_pattern = self::EU_COUNTRY_LIST[ $country ];

		return strlen( $vat_number ) === $country_pattern['length'] && preg_match( $country_pattern['pattern'], $vat_number );
	}

	/**
	 * Check if EU country VAT
	 *
	 * @param string $country Country code.
	 * @param string $vat_id EU VAT number.
	 * @return bool
	 */
	public static function validate_vat_format( $country, $vat_id ) {
		if ( ! self::validate_country( $country ) ) {
			return false;
		}

		$vat_number = self::split_vat_id( $country, strtoupper( $vat_id ) );
		return self::validate_pattern( $country, $vat_number );
	}

	/**
	 * Validate EU VAT ID
	 *
	 * @param string $country Country code.
	 * @param string $vat_id VAT number.
	 * @throws Error API error.
	 * @return bool
	 */
	public function validate( $country, $vat_id ) {
		// Missing vat id.
		if ( empty( $vat_id ) ) {
			$this->error = self::ERROR_MISSING_VAT_ID;
			return false;
		}

		// Missing country code.
		if ( empty( $country ) ) {
			$this->error = self::ERROR_MISSING_COUNTRY;
			return false;
		}

		// Non-EU country.
		if ( false === self::validate_country( $country ) ) {
			return $vat_id;
		}

		// Incorrect VAT number format.
		if ( ! $this::validate_vat_format( $country, $vat_id ) ) {
			$this->error = self::ERROR_INCORRECT_FORMAT;
			return false;
		}

		$vat_number = self::split_vat_id( $country, strtoupper( $vat_id ) );
		$vat_id     = $country . $vat_number;

		try {
			$query   = str_replace( array( '$1', '$2' ), array( $country, $vat_number ), $this->api_url );
			$result  = $this->call_vies_api( $query );
			$res_arr = json_decode( $result, true );

			if ( ! is_array( $res_arr ) ) {
				throw new Error( self::ERROR_INVALID );
			}

			// Invalid VAT number / API rate limit error.
			if ( false === $res_arr['isValid'] ) {
				throw new Error( $res_arr['userError'] );
			}
		} catch ( Throwable $ex ) {
			// API request error.
			$this->error = $ex->getMessage();
			return false;
		}

		// VAT number valid.
		return $vat_id;
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
	 * @param string $url URL to fetch data.
	 * @throws Error API error.
	 * @return string
	 */
	protected function call_vies_api( $url ) {
		// phpcs:disable
		$ch = curl_init();

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_SSLVERSION     => 6,
				CURLOPT_SSL_OPTIONS    => 128,
				CURLOPT_USERAGENT      => 'curl/7.81.0',
				CURLOPT_TIMEOUT        => 20,
				CURLOPT_CONNECTTIMEOUT => 10,
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

		if ( 200 !== $http_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( htmlspecialchars( 'HTTP Code: ' . $http_code ) );
		}

		return $response;
	}
}
