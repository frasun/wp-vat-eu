<?php
/**
 * Fired during plugin activation
 *
 * @package Chocante_VAT_EU
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

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
	public $version;

	/**
	 * VAT Validation class
	 *
	 * @var Chocante_VAT_Validation
	 */
	private $validator;

	/**
	 * Checkout Fields
	 *
	 * @var CheckoutFields
	 */
	private $checkout_fields;

	/**
	 * Customer Tax ID holder
	 *
	 * @var string
	 */
	private $customer_tax_id;

	/**
	 * Field name
	 */
	const TAX_ID            = 'billing_tax_id';
	const TAX_ID_FIELD      = 'chocante_vat_eu/tax_id';
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

		// Add field to block checkout.
		add_action( 'woocommerce_init', array( $this, 'init_block_checkout' ) );
		add_filter( 'woocommerce_get_default_value_for_' . self::TAX_ID_FIELD, array( $this, 'populate_tax_id_in_block_checkout' ), 10, 3 );
		add_action( 'woocommerce_set_additional_field_value', array( $this, 'set_session_tax_id' ), 10, 2 );
		add_filter( 'woocommerce_sanitize_additional_field', array( $this, 'sanitize_tax_id_field' ), 10, 2 );
		add_action( 'woocommerce_checkout_validate_order_before_payment', array( $this, 'validate_tax_id_field_on_payment' ), 10, 2 );

		// Display company field in address block.
		add_filter( 'default_option_woocommerce_checkout_company_field', array( $this, 'display_company_field_in_block_checkout' ) );
		add_filter( 'option_woocommerce_checkout_company_field', array( $this, 'display_company_field_in_block_checkout' ) );

		// Save / update Tax ID in block checkout.
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'set_vat_exempt_on_store_api_customer_update' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'set_vat_exempt_on_store_api_order_update' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'set_vat_id_on_customer' ) );
		add_action( 'woocommerce_created_customer', array( $this, 'save_tax_id_on_delayed_account_creation' ), 10, 2 );
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
			$company_name     = $changes['billing']['company'] ?? $current_customer->get_billing_company();
			$country          = $changes['billing']['country'] ?? $current_customer->get_billing_country();
			$tax_id           = $customer->get_meta( self::TAX_ID );
			$has_company_name = ! empty( $company_name );
			$has_tax_id       = ! empty( $tax_id );
			$is_vat_exempt    = false;

			if ( $has_tax_id || $has_company_name ) {
				if ( ! $has_company_name ) {
					wc_add_notice( $this->get_validation_error( 'MISSING_COMPANY_NAME' ), 'error' );
					return;
				}

				$validated_tax_id = $this->validator->validate( $country, $tax_id );

				if ( false === $validated_tax_id ) {
					wc_add_notice( $this->get_validation_error( $this->validator->get_error() ), 'error' );
					return;
				}

				$customer->update_meta_data( self::TAX_ID, $validated_tax_id );
				$is_vat_exempt = $this->validate_eu_company( $validated_tax_id, $company_name, $country );
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

		if ( $has_tax_id ) {
			if ( ! $has_company_name ) {
				wc_add_notice( $this->get_validation_error( 'MISSING_COMPANY_NAME' ), 'error' );
			} else {
				$validated_tax_id = $this->validator->validate( $country, $tax_id );

				if ( false === $validated_tax_id ) {
					wc_add_notice( $this->get_validation_error( $this->validator->get_error() ), 'error' );
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
	 * @return string
	 */
	private function get_validation_error( $error ) {
		switch ( $error ) {
			case 'MISSING_VAT_ID':
				// translators: Missing Tax ID.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), __( 'VAT / Tax ID', 'chocante-vat-eu' ) );
			case 'MISSING_COUNTRY':
				// translators: Missing country.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), __( 'Company name', 'woocommerce' ) );
			case 'MISSING_COMPANY_NAME':
				// translators: Missing company name.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), __( 'Country / Region', 'woocommerce' ) );
			case 'INCORRECT_FORMAT':
				// translators: Incorrect Tax ID format.
				return sprintf( __( 'Field %s has incorrect format.', 'chocante-vat-eu' ), __( 'VAT / Tax ID', 'chocante-vat-eu' ) );
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
		if ( $this->is_block_checkout() ) {
			return $formats;
		}

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
			plugin_dir_url( __DIR__ ) . "js/{$script}",
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
	 * Set VAT exempt cookie on user login
	 *
	 * @param string     $user_login User login.
	 * @param int|object $user       User.
	 */
	public function set_cookie_on_login( $user_login, $user ) {
		$customer      = new WC_Customer( $user->ID, true );
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

		$should_exempt = false;
		$customer      = wc()->customer;
		$country       = $customer->get_billing_country();

		if ( has_block( 'woocommerce/checkout' ) ) {
			$tax_id        = $customer->get_meta( self::TAX_ID );
			$company       = $customer->get_billing_company();
			$should_exempt = $this->validate_eu_company( $tax_id, $company, $country );
		} else {
			$cookie_set    = ! empty( $_COOKIE[ self::VAT_EXEMPT_COOKIE ] );
			$countries     = new WC_Countries();
			$eu_countries  = $countries->get_european_union_countries( 'eu_vat' );
			$is_eu         = in_array( $country, $eu_countries, true );
			$should_exempt = $cookie_set && $is_eu;
		}

		$this->set_vat_exemption( $customer, $should_exempt );
	}

	/**
	 * Add TAX ID field to block checkout
	 */
	public function init_block_checkout() {
		$this->checkout_fields = Package::container()->get( CheckoutFields::class );

		if ( is_user_logged_in() ) {
			$customer              = new WC_Customer( get_current_user_id() );
			$this->customer_tax_id = $customer->get_meta( self::TAX_ID );
		}

		$countries        = new WC_Countries();
		$eu_vat_countries = $countries->get_european_union_countries( 'eu_vat' );

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => self::TAX_ID_FIELD,
				'label'    => __( 'VAT / Tax ID', 'chocante-vat-eu' ),
				'location' => 'order',
				'required' => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'billing_address' => array(
									'properties' => array(
										'country' => array(
											'enum' => $eu_vat_countries,
										),
									),
								),
							),
						),
					),
				),
				'hidden'   => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'billing_address' => array(
									'properties' => array(
										'company' => array(
											'not' => array(
												'minLength' => 1,
											),
										),
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Get Tax ID value in block checkout
	 *
	 * @param null    $value The default value for the filter, always null.
	 * @param string  $group The group of this key (shipping|billing|other).
	 * @param WC_Data $wc_object The object to get the field value for.
	 */
	public function populate_tax_id_in_block_checkout( $value, $group, $wc_object ) {
		if ( $this->is_block_checkout() ) {
			return $this->customer_tax_id;
		}

		return $value;
	}

	/**
	 * Set session customer Tax ID
	 *
	 * @param string $key The key of the field being saved.
	 * @param mixed  $value The value of the field being saved.
	 */
	public function set_session_tax_id( $key, $value ) {
		if ( self::TAX_ID_FIELD === $key ) {
			$this->customer_tax_id = $value;
		}
	}

	/**
	 * Sanitize Tax ID field in block checkout
	 *
	 * @param mixed  $field_value The value of the field being sanitized.
	 * @param string $field_key   Key of the field being sanitized.
	 */
	public function sanitize_tax_id_field( $field_value, $field_key ) {
		if ( self::TAX_ID_FIELD === $field_key ) {
			$field_value = str_replace( array( '.', ',', '-', ' ' ), '', $field_value );
			$field_value = strtoupper( $field_value );
			$field_value = filter_var( $field_value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );
		}

		return $field_value;
	}

	/**
	 * Validate Tax ID field before payment using VIES API
	 *
	 * @param WC_Order $order             The order object.
	 * @param WP_Error $validation_errors WP_Error object to add custom errors to.
	 */
	public function validate_tax_id_field_on_payment( $order, $validation_errors ) {
		$country          = $order->get_billing_country();
		$tax_id           = $this->checkout_fields->get_field_from_object( self::TAX_ID_FIELD, $order, 'other' );
		$validated_tax_id = $this->validator->validate( $country, $tax_id );

		if ( false === $validated_tax_id ) {
			$error = $this->validator->get_error();
			$validation_errors->add( $error, $this->get_validation_error( $error ) );
		} else {
			$order->update_meta_data( CheckoutFields::get_group_key( 'other' ) . self::TAX_ID_FIELD, $validated_tax_id );
			$order->save();
		}
	}

	/**
	 * Display company in address fields in block checkout
	 */
	public function display_company_field_in_block_checkout() {
		return 'optional';
	}

	/**
	 * Set VAT exempt on customer address change in block checkout
	 *
	 * @param WC_Customer $customer Customer object.
	 */
	public function set_vat_exempt_on_store_api_customer_update( $customer ) {
		$changes = $customer->get_changes();

		if ( isset( $changes['billing']['company'] ) || isset( $changes['billing']['country'] ) ) {
			$country       = $changes['billing']['country'] ?? $customer->get_billing_country();
			$company       = $changes['billing']['company'] ?? $customer->get_billing_company();
			$order_id      = absint( WC()->session->get( 'store_api_draft_order' ) );
			$order         = $order_id ? wc_get_order( $order_id ) : null;
			$tax_id        = $this->checkout_fields->get_field_from_object( self::TAX_ID_FIELD, $order, 'other' );
			$should_exempt = $this->validate_eu_company( $tax_id, $company, $country );

			$this->set_vat_exemption( $customer, $should_exempt );
		}
	}

	/**
	 * Set VAT exempt on customer address change in block checkout
	 *
	 * @param WC_Order        $order Order object.
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function set_vat_exempt_on_store_api_order_update( $order, $request ) {
		if ( isset( $request->get_param( 'additional_fields' )[ self::TAX_ID_FIELD ] ) ) {
			$customer         = wc()->customer;
			$session_customer = $customer->get_changes();
			$country          = $session_customer['billing']['country'] ?? $customer->get_billing_country();
			$company          = $session_customer['billing']['company'] ?? $customer->get_billing_company();
			$tax_id           = $this->checkout_fields->get_field_from_object( self::TAX_ID_FIELD, $order, 'other' );
			$should_exempt    = $this->validate_eu_company( $tax_id, $company, $country );

			$this->set_vat_exemption( $customer, $should_exempt );
		}
	}

	/**
	 * Save Tax ID on customer object
	 *
	 * @param WC_Order $order Order object.
	 */
	public function set_vat_id_on_customer( $order ) {
		$tax_id = $this->checkout_fields->get_field_from_object( self::TAX_ID_FIELD, $order, 'other' );

		if ( empty( $tax_id ) ) {
			return;
		}

		$order->update_meta_data( '_' . self::TAX_ID, $tax_id );
		$order->delete_meta_data( CheckoutFields::get_group_key( 'other' ) . self::TAX_ID_FIELD );
		$order->save();

		$customer_id = $order->get_customer_id();

		if ( $customer_id ) {
			$customer = new WC_Customer( $customer_id );
			$customer->update_meta_data( self::TAX_ID, $tax_id );
			$customer->save();
		}
	}

	/**
	 * Save Tax ID on new customer account
	 *
	 * @param int   $customer_id       New WP user ID.
	 * @param array $new_customer_data Customer data including 'source'.
	 */
	public function save_tax_id_on_delayed_account_creation( $customer_id, $new_customer_data ) {
		if ( empty( $new_customer_data['source'] ) || 'delayed-account-creation' !== $new_customer_data['source'] ) {
			return;
		}

		$email = $new_customer_data['user_email'] ?? '';
		if ( empty( $email ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $email,
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		if ( empty( $orders ) ) {
			return;
		}

		$tax_id = $orders[0]->get_meta( '_' . self::TAX_ID );

		if ( $tax_id ) {
			$customer = new WC_Customer( $customer_id );
			$customer->update_meta_data( self::TAX_ID, $tax_id );
			$customer->save();
		}
	}

	/**
	 * Check if this is block checkout
	 */
	private function is_block_checkout() {
		return is_checkout() && has_block( 'woocommerce/checkout' ) && ! is_order_received_page();
	}
}
