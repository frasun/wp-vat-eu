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
	}

	/**
	 * Add Tax ID to address fields
	 *
	 * @param array $fields Default address fields.
	 * @return array
	 */
	public function add_tax_id_to_billing_address( $fields ) {
		$fields['billing_tax_id'] = array(
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
			$tax_id           = $customer->get_meta( 'billing_tax_id' );
			$has_company_name = ! empty( $company_name );
			$has_tax_id       = ! empty( $tax_id );

			if ( $has_company_name ) {
				$validated_tax_id = $this->validator->validate( $country, $tax_id );

				if ( false === $validated_tax_id ) {
					wc_add_notice( $this->get_validation_error( $this->validator->get_error(), $address ), 'error' );
				} else {
					$customer->update_meta_data( 'billing_tax_id', $validated_tax_id );
				}
			} elseif ( $has_tax_id ) {
				wc_add_notice( $this->get_validation_error( 'MISSING_COMPANY_NAME', $address ), 'error' );
			}
		}
	}

	/**
	 * Validate Tax ID in checkout
	 */
	public function validate_tax_id_in_checkout() {
		$company_name     = isset( $_POST['billing_company'] ) ? wp_unslash( sanitize_text_field( $_POST['billing_company'] ) ) : ''; // @codingStandardsIgnoreLine.
		$country          = isset( $_POST['billing_country'] ) ? wp_unslash( sanitize_text_field( $_POST['billing_country'] ) ) : ''; // @codingStandardsIgnoreLine.
		$tax_id           = isset( $_POST['billing_tax_id'] ) ? wp_unslash( sanitize_text_field( $_POST['billing_tax_id'] ) ) : ''; // @codingStandardsIgnoreLine.
		$has_company_name = ! empty( $company_name );
		$has_tax_id       = ! empty( $tax_id );

		$labels = array(
			'billing_tax_id'  => array(
				'label' => __( 'VAT / Tax ID', 'chocante-vat-eu' ),
			),
			'billing_company' => array(
				'label' => __( 'Company name', 'woocommerce' ),
			),
			'billing_country' => array(
				'label' => __( 'Country / Region', 'woocommerce' ),
			),
		);

		if ( $has_company_name ) {
			$validated_tax_id = $this->validator->validate( $country, $tax_id );

			if ( false === $validated_tax_id ) {
				wc_add_notice( $this->get_validation_error( $this->validator->get_error(), $labels ), 'error' );
			} else {
				$_POST['billing_tax_id'] = $validated_tax_id;
			}
		} elseif ( $has_tax_id ) {
			wc_add_notice( $this->get_validation_error( 'MISSING_COMPANY_NAME', $labels ), 'error' );
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
		switch ( $error ) {
			case 'MISSING_VAT_ID':
				// translators: Missing Tax ID.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), $fields['billing_tax_id']['label'] );
			case 'MISSING_COUNTRY':
				// translators: Missing country.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), $fields['billing_country']['label'] );
			case 'MISSING_COMPANY_NAME':
				// translators: Missing company name.
				return sprintf( __( 'Please enter %s.', 'chocante-vat-eu' ), $fields['billing_company']['label'] );
			case 'INCORRECT_FORMAT':
				// translators: Incorrect Tax ID format.
				return sprintf( __( 'Field %s has incorrect format.', 'chocante-vat-eu' ), $fields['billing_tax_id']['label'] );
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
			$tax_id   = $customer->get_meta( 'billing_tax_id' );

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
		$tax_id = $order->get_meta( '_billing_tax_id' );

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
			'billing_tax_id' => array(
				'label'       => __( 'VAT / Tax ID', 'chocante-vat-eu' ),
				'description' => '',
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
}
