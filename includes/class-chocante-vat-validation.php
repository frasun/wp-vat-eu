<?php
/**
 * EU VAT Validation
 * Based on SunVatChecker Class
 *
 * @category  Vat Validation
 * @package   Chocante_VAT_Validation
 * @author    Mehmet Selcuk Batal <batalms@gmail.com>
 * @copyright Copyright (c) 2022, Sunhill Technology <www.sunhillint.com>
 * @license   https://opensource.org/licenses/lgpl-3.0.html The GNU Lesser General Public License, version 3.0
 * @link      https://github.com/msbatal/PHP-EU-VAT-Number-Validation
 * @version   1.1.3
 */

/**
 * The Chocante_VAT_Validation class.
 */
class Chocante_VAT_Validation {

	/**
	 * EU Country List
	 *
	 * @var array
	 */
	protected const EU_COUNTRY_LIST = array(
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
		'EL' => array(
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

	/**
	 * Error details
	 *
	 * @var string
	 */
	private $error = null;

	/**
	 * VIES API URL
	 *
	 * @var string
	 */
	protected $api_url = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/$1/vat/$2';

	/**
	 * Remove country code from VAT number (if consists)
	 *
	 * @param string $vat_id VAT number.
	 * @param string $country Selected country.
	 * @return string
	 */
	private function split_vat_id( $country, $vat_id = '' ) {
		$vat_id = $this->filter_argument( $vat_id );
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
	private function filter_argument( $argument = '' ) {
		$argument = str_replace( array( '.', ',', '-', ' ' ), '', $argument );

		return filter_var( $argument, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );
	}

	/**
	 * Validate data to prevent XSS and nasty things
	 *
	 * @param string $argument VAT number.
	 * @return bool
	 */
	private function validate_argument( $argument = '' ) {
		$regexp = '/^[a-zA-Z0-9\s\.\-,&\+\(\)\/º\pL]+$/u';
		if ( filter_var( $argument, FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => $regexp ) ) ) === false ) {
			return false;
		}
		return true;
	}

	/**
	 * Validate country is intra-EU or not
	 *
	 * @param string $country Country code.
	 * @return bool
	 */
	private function validate_country( $country = '' ) {
		return isset( self::EU_COUNTRY_LIST[ $country ] );
	}

	/**
	 * Validate EU VAT ID
	 *
	 * @param string $country Country code.
	 * @param string $vat_id VAT number.
	 * @return bool
	 */
	public function validate( $country = '', $vat_id = '' ) {
		// Missing data.
		if ( empty( $vat_id ) ) {
			$this->error = 'MISSING_VAT_ID';
			return false;
		}

		// Missing country code.
		if ( empty( $country ) ) {
			$this->error = 'MISSING_COUNTRY';
			return false;
		}

		// Non-EU country.
		if ( $this->validate_country( $country ) === false ) {
			return $vat_id;
		}

		$vat_number = $this->split_vat_id( $country, strtoupper( $vat_id ) );
		$vat_id     = $country . $vat_number;

		if ( ! preg_match( self::EU_COUNTRY_LIST[ $country ]['pattern'], $vat_number ) ) {
			// Incorrect VAT number.
			$this->error = 'INCORRECT_FORMAT';
			return false;
		}

		if ( ! $this->validate_argument( $vat_id ) ) {
			// Incorrect VAT number.
			$this->error = 'INCORRECT_FORMAT';
			return false;
		}

		// External validator.
		$external = apply_filters( 'wp_vat_eu_validator_' . $country, null, $vat_number );

		if ( isset( $external ) ) {
			return $external ? $vat_id : $external;
		}

		try {
			$query = str_replace( array( '$1', '$2' ), array( $country, $vat_number ), $this->api_url );
			// $result = wp_remote_get( $query );
			// Handle modern server config.
			$result = $this->safe_remote_get( $query );

			// API request error.
			if ( is_wp_error( $result ) ) {
				$this->error = $result->get_error_message();
				return false;
			}

			$res_arr = json_decode( $result, true );

			if ( ! is_array( $res_arr ) ) {
				$this->error = 'INVALID_JSON_RESPONSE';
				return false;
			}
		} catch ( Exception $ex ) {
			// API exception.
			$this->error = $ex->getMessage();
			return false;
		}

		// VAT number valid.
		if ( true === $res_arr['isValid'] ) {
			return $vat_id;
		}

		// API limit issue.
		if ( 'MS_MAX_CONCURRENT_REQ' === $res_arr['userError'] ) {
			$this->error = $res_arr['userError'];
			return false;
		}

		// Not valid VAT number.
		$this->error = $res_arr['userError'];
		return false;
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
	 * Check if EU country VAT
	 *
	 * @param string $country Country code.
	 * @param string $vat_id EU VAT number.
	 * @return bool
	 */
	public function validate_vat_format( $country, $vat_id ) {
		if ( false === $this->validate_country( $country ) ) {
			return true;
		}

		$vat_number = $this->split_vat_id( $country, strtoupper( $vat_id ) );

		return preg_match( self::EU_COUNTRY_LIST[ $country ]['pattern'], $vat_number );
	}

	/**
	 * Fetch data using CURL directly intead of wp_remote_get
	 *
	 * @param string $url URL to fetch data.
	 */
	private function safe_remote_get( $url ) {
		// phpcs:ignore
		$ch = curl_init();

		// phpcs:ignore
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_SSLVERSION     => 6,
				CURLOPT_SSL_OPTIONS    => 128,
				// phpcs:ignore
				// CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1',
				CURLOPT_USERAGENT      => 'curl/7.81.0',
				CURLOPT_TIMEOUT        => 20,
				CURLOPT_CONNECTTIMEOUT => 10,
			)
		);

		// phpcs:ignore
		$response  = curl_exec( $ch );
		// phpcs:ignore
		$error     = curl_error( $ch );
		// phpcs:ignore
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		// phpcs:ignore
		curl_close( $ch );

		if ( $error ) {
			return new WP_Error( 'vies_curl_error', $error );
		}

		if ( 200 !== $http_code ) {
			return new WP_Error( 'vies_http_error', 'HTTP Code: ' . $http_code );
		}

		return $response;
	}
}
