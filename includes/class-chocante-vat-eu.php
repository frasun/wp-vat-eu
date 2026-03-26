<?php
/**
 * Fired during plugin activation
 *
 * @package Chocante_VAT_EU
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Chocante_VAT_EU class.
 */
class Chocante_VAT_EU {
	/**
	 * This class instance.
	 *
	 * @var \Chocante_VAT_EU Single instance of this class.
	 */
	private static $instance;

	/**
	 * The current version of the plugin.
	 *
	 * @var string The current version of the plugin.
	 */
	protected $version;

	/**
	 * VAT Validation class
	 *
	 * @var Chocante_VAT_Validation
	 */
	private $validator;

	/**
	 * Field name
	 */
	const TAX_ID            = 'billing_tax_id';
	const VAT_EXEMPT_COOKIE = 'chocante_vat_exempt';

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( defined( 'CHOCANTE_VAT_EU_VERSION' ) ) {
			$this->version = CHOCANTE_VAT_EU_VERSION;
		} else {
			$this->version = '1.0.0';
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-chocante-vat-validation.php';
		$this->validator = new Chocante_VAT_Validation();

		$this->init();
	}

	/**
	 * Cloning is forbidden
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'chocante-vat-eu' ), $this->version );
	}

	/**
	 * Unserializing instances of this class is forbidden
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'chocante-vat-eu' ), $this->version );
	}

	/**
	 * Gets the main instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return \Chocante_VAT_EU
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks
	 */
	private function init() {
		// Add Tax ID field to billing address form.
		add_filter( 'woocommerce_billing_fields', array( $this, 'add_tax_id_to_billing_address' ) );

		// Validate Tax ID in my addresses.
		add_action( 'woocommerce_after_save_address_validation', array( $this, 'validate_tax_id_in_my_address' ), 10, 4 );

		// Validate Tax ID in checkout.
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_tax_id_in_checkout' ) );

		// Save Tax ID in session.
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'save_tax_id_in_checkout' ) );
		add_filter( 'woocommerce_customer_allowed_session_meta_keys', array( $this, 'store_tax_id_in_session' ) );

		// Display Tax ID in my addresses.
		add_filter( 'woocommerce_my_account_my_address_formatted_address', array( $this, 'add_tax_id_to_my_address' ), 10, 3 );
		add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'add_tax_id_to_formatted_address' ), 10, 2 );
		add_filter( 'woocommerce_localisation_address_formats', array( $this, 'add_tax_id_to_localised_address' ), 10, 1 );

		// Display Tax ID in order.
		add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'add_tax_id_to_order_address' ), 10, 2 );

		// Display Tax ID in user profile.
		add_filter( 'woocommerce_customer_meta_fields', array( $this, 'add_tax_id_to_user_profile' ) );

		// Add front-end validation to checkout.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'add_client_checkout_validation' ) );

		// Display prices without VAT.
		add_action( 'wp', array( $this, 'maybe_set_vat_exemption' ) );
		add_action( 'wp_login', array( $this, 'set_cookie_on_login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'delete_cookie_on_logout' ) );
	}

	/**
	 * Add Tax ID to address fields
	 *
	 * @param array $fields Default address fields.
	 * @return array
	 */
	public function add_tax_id_to_billing_address( $fields ) {
		$fields[ self::TAX_ID ] = array(
			'label'    => __( 'VAT / Tax ID', 'chocante-vat-eu' ),
			'required' => 'required' === get_option( 'woocommerce_checkout_company_field', 'optional' ),
			'class'    => array( 'form-row-wide' ),
			'priority' => 35,
		);

		return $fields;
	}

	/**
	 * Validate Tax ID in my address
	 *
	 * @param int         $user_id User ID being saved.
	 * @param string      $address_type Type of address; 'billing' or 'shipping'.
	 * @param array       $address The address fields.
	 * @param WC_Customer $customer The customer object being saved.
	 */
	public function validate_tax_id_in_my_address( $user_id, $address_type, $address, $customer ) {
		if ( 'billing' === $address_type ) {
			$current_customer = new WC_Customer( $user_id );
			$changes          = $customer->get_changes();
			$company_name     = isset( $changes['billing']['company'] ) ? $changes['billing']['company'] : $current_customer->get_billing_company();
			$country          = isset( $changes['billing']['country'] ) ? $changes['billing']['country'] : $current_customer->get_billing_country();
			$tax_id           = $customer->get_meta( self::TAX_ID );
			$has_company_name = ! empty( $company_name );
			$has_tax_id       = ! empty( $tax_id );
			$is_vat_exempt    = false;

			if ( $has_tax_id ) {
				if ( ! $has_company_name ) {
					wc_add_notice( $this->get_validation_error( 'MISSING_COMPANY_NAME', $address ), 'error' );
				} else {
					$validated_tax_id = $this->validator->validate( $country, $tax_id );

					if ( false === $validated_tax_id ) {
						wc_add_notice( $this->get_validation_error( $this->validator->get_error(), $address ), 'error' );
						return;
					} else {
						$customer->update_meta_data( self::TAX_ID, $validated_tax_id );

						$is_vat_exempt = $this->validate_eu_company( $validated_tax_id, $company_name, $country );
					}
				}
			}

			$this->set_vat_exemption( $customer, $is_vat_exempt );
		}
	}

	/**
	 * Validate Tax ID in checkout
	 */
	public function validate_tax_id_in_checkout() {
		$company_name     = isset( $_POST['billing_company'] ) ? wp_unslash( sanitize_text_field( $_POST['billing_company'] ) ) : ''; // @codingStandardsIgnoreLine.
		$country          = isset( $_POST['billing_country'] ) ? wp_unslash( sanitize_text_field( $_POST['billing_country'] ) ) : ''; // @codingStandardsIgnoreLine.
		$tax_id           = isset( $_POST[self::TAX_ID] ) ? wp_unslash( sanitize_text_field( $_POST[self::TAX_ID] ) ) : ''; // @codingStandardsIgnoreLine.
		$has_company_name = ! empty( $company_name );
		$has_tax_id       = ! empty( $tax_id );

		$labels = array(
			self::TAX_ID      => array(
				'label' => __( 'VAT / Tax ID', 'chocante-vat-eu' ),
			),
			'billing_company' => array(
				'label' => __( 'Company name', 'woocommerce' ),
			),
			'billing_country' => array(
				'label' => __( 'Country / Region', 'woocommerce' ),
			),
		);

		if ( $has_tax_id ) {
			if ( ! $has_company_name ) {
				wc_add_notice( $this->get_validation_error( 'MISSING_COMPANY_NAME', $labels ), 'error' );
			} else {
				$validated_tax_id = $this->validator->validate( $country, $tax_id );

				if ( false === $validated_tax_id ) {
					wc_add_notice( $this->get_validation_error( $this->validator->get_error(), $labels ), 'error' );
				} else {
					$_POST[ self::TAX_ID ] = $validated_tax_id;
				}
			}
		}
	}

	/**
	 * Output validation error message
	 *
	 * @param string $error Error ID.
	 * @param array  $fields Address fields.
	 * @return string
	 */
	private function get_validation_error( $error, $fields ) {
		error_log( print_r( $fields, true ) );
		switch ( $error ) {
			case 'MISSING_VAT_ID':
				// translators: Missing Tax ID.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), $fields[ self::TAX_ID ]['label'] );
			case 'MISSING_COUNTRY':
				// translators: Missing country.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), $fields['billing_country']['label'] );
			case 'MISSING_COMPANY_NAME':
				// translators: Missing company name.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), $fields['billing_company']['label'] );
			case 'INCORRECT_FORMAT':
				// translators: Incorrect Tax ID format.
				return sprintf( __( 'Field %s has incorrect format.', 'chocante-vat-eu' ), $fields[ self::TAX_ID ]['label'] );
			case 'MS_MAX_CONCURRENT_REQ':
				// translators: Service temporarily unavailable.
				return __( 'Unable to verify VAT / Tax ID. Please wait and try again.', 'chocante-vat-eu' );
			default:
				// translators: Invalid Tax ID.
				return __( 'VAT / Tax ID is invalid.', 'chocante-vat-eu' );
		}
	}

	/**
	 * Add Tax ID to my billing adress.
	 *
	 * @param array  $address Customer address.
	 * @param int    $customer_id Customer ID.
	 * @param string $address_type Type of address; 'billing' or 'shipping'.
	 * @return array
	 */
	public function add_tax_id_to_my_address( $address, $customer_id, $address_type ) {
		if ( 'billing' === $address_type ) {
			$customer = new WC_Customer( $customer_id );
			$tax_id   = $customer->get_meta( self::TAX_ID );

			if ( isset( $tax_id ) && ! empty( $tax_id ) ) {
				$address['tax_id'] = $tax_id;
			}
		}

		return $address;
	}

	/**
	 * Add Tax ID to formatted address.
	 *
	 * @param array $fields Formatted address fields.
	 * @param array $args Address.
	 * @return array
	 */
	public function add_tax_id_to_formatted_address( $fields, $args ) {
		$fields['{tax_id}'] = isset( $args['tax_id'] ) ? $args['tax_id'] : '';
		return $fields;
	}

	/**
	 * Add Tax ID to localised address formats.
	 *
	 * @param array $formats Formatted address fields.
	 * @return array
	 */
	public function add_tax_id_to_localised_address( $formats ) {
		foreach ( $formats as &$format ) {
			$format = str_replace( "{company}\n", "{company}\n{tax_id}\n", $format );
		}

		return $formats;
	}

	/**
	 * Add Tax ID to address in order
	 *
	 * @param array    $address Order address.
	 * @param WC_Order $order Current order.
	 * @return array
	 */
	public function add_tax_id_to_order_address( $address, $order ) {
		$tax_id = $order->get_meta( '_' . self::TAX_ID );

		if ( isset( $tax_id ) && ! empty( $tax_id ) ) {
			$address['tax_id'] = $tax_id;
		}

		return $address;
	}

	/**
	 * Add Tax ID to address in order
	 *
	 * @param array $profile_fields User profile fields.
	 * @return array
	 */
	public function add_tax_id_to_user_profile( $profile_fields ) {
		$tax_id = array(
			self::TAX_ID => array(
				'label'       => __( 'VAT / Tax ID', 'chocante-vat-eu' ),
				'description' => '',
				'type'        => 'text',
				'default'     => '',
			),
		);

		$billing_fields = $profile_fields['billing']['fields'];
		$position       = array_search( 'billing_company', array_keys( $billing_fields ), true );

		$before = array_slice( $billing_fields, 0, $position + 1, true );
		$after  = array_slice( $billing_fields, $position + 1, null, true );

		$profile_fields['billing']['fields'] = $before + $tax_id + $after;

		return $profile_fields;
	}

	/**
	 * Add form validation to fields in checkout
	 */
	public function add_client_checkout_validation() {
		if ( 'production' === wp_get_environment_type() ) {
			$script = 'chocante-vat-eu.min.js';
		} else {
			$script = 'chocante-vat-eu.js';
		}

		wp_enqueue_script(
			'chocante-vat-eu',
			plugin_dir_url( __DIR__ ) . "/js/{$script}",
			array(),
			'1.0.0',
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Save tax id on changes in checkout form
	 *
	 * @param string $query Checkout form fields query params.
	 */
	public function save_tax_id_in_checkout( $query ) {
		parse_str( $query, $data );

		$post_tax_id  = isset( $data[ self::TAX_ID ] ) ? wc_clean( wp_unslash( $data[ self::TAX_ID ] ) ) : null;
		$post_company = isset( $data['billing_company'] ) ? wc_clean( wp_unslash( $data['billing_company'] ) ) : null;
		$customer     = WC()->customer;

		if ( $post_tax_id ) {
			$customer->update_meta_data( self::TAX_ID, $post_tax_id );
		}

		if ( $post_company ) {
			$customer->set_billing_company( $post_company );
		}

		$tax_id  = $post_tax_id ?? $customer->get_meta( self::TAX_ID );
		$company = $post_company ?? $customer->get_billing_company();
		$country = isset( $data['billing_country'] ) ? wc_clean( wp_unslash( $data['billing_country'] ) ) : $customer->get_billing_country();

		$is_vat_exempt = $this->validate_eu_company( $tax_id, $company, $country );
		$this->set_vat_exemption( $customer, $is_vat_exempt );
	}

	/**
	 * Validate customer data
	 *
	 * @param string $tax_id Customer Tax ID.
	 * @param string $company Customer company name.
	 * @param string $country Customer billing country.
	 * @return bool
	 */
	private function validate_eu_company( $tax_id, $company, $country ) {
		if ( empty( $tax_id ) || empty( $company ) ) {
			return false;
		}

		$base_country = WC()->countries->get_base_country();
		$eu_countries = WC()->countries->get_european_union_countries( 'eu_vat' );

		if ( in_array( $base_country, $eu_countries, true ) && $base_country === $country ) {
			return false;
		}

		return $this->validator->validate_vat_format( $country, $tax_id );
	}

	/**
	 * Set VAT exemption
	 *
	 * @param WC_Customer $customer Customer object.
	 * @param bool        $is_vat_exempt Has VAT exemption.
	 */
	public function set_vat_exemption( $customer, $is_vat_exempt ) {
		$customer->set_is_vat_exempt( $is_vat_exempt );

		if ( ( $is_vat_exempt && ! empty( $_COOKIE[ self::VAT_EXEMPT_COOKIE ] ) ) || ( ! $is_vat_exempt && empty( $_COOKIE[ self::VAT_EXEMPT_COOKIE ] ) ) ) {
			return;
		}

		$cookie_expiration = $is_vat_exempt ? intval( apply_filters( 'wc_session_expiration', is_user_logged_in() ? WEEK_IN_SECONDS : 2 * DAY_IN_SECONDS ) ) : - DAY_IN_SECONDS;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		setcookie( self::VAT_EXEMPT_COOKIE, $is_vat_exempt ? 1 : 0, time() + $cookie_expiration, '/', COOKIE_DOMAIN, true, false );
	}

	/**
	 * Store Tax ID in customer session
	 *
	 * @param array $keys Keys allowed to store.
	 */
	public function store_tax_id_in_session( $keys ) {
		$keys[] = self::TAX_ID;
		return $keys;
	}

	/**
	 * Set VAT exempt cookie on user login
	 *
	 * @param string     $user_login User login.
	 * @param int|object $user       User.
	 */
	public function set_cookie_on_login( $user_login, $user ) {
		$customer      = new \WC_Customer( $user->ID, true );
		$tax_id        = $customer->get_meta( self::TAX_ID );
		$company_name  = $customer->get_billing_company();
		$country       = $customer->get_billing_country();
		$is_vat_exempt = $this->validate_eu_company( $tax_id, $company_name, $country );

		$this->set_vat_exemption( $customer, $is_vat_exempt );
	}

	/**
	 * Delete VAT exempt cookie on user logout
	 */
	public function delete_cookie_on_logout() {
		$this->set_vat_exemption( WC()->customer, false );
	}

	/**
	 * Set customer as VAT exempt if the cookie is present (and other conditions met).
	 */
	public function maybe_set_vat_exemption() {
		if ( ! WC()->customer ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['wc-ajax'] ) && 'update_order_review' === $_REQUEST['wc-ajax'] ) {
			return;
		}

		$cookie_set    = ! empty( $_COOKIE[ self::VAT_EXEMPT_COOKIE ] );
		$customer      = WC()->customer;
		$countries     = new WC_Countries();
		$country       = $customer->get_billing_country();
		$eu_countries  = $countries->get_european_union_countries( 'eu_vat' );
		$is_eu         = in_array( $country, $eu_countries, true );
		$should_exempt = $cookie_set && $is_eu;

		$this->set_vat_exemption( $customer, $should_exempt );
	}
}
