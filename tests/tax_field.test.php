<?php
/**
 * Tax ID field tests
 *
 * @package Chocante_VAT_EU
 */

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

require_once __DIR__ . '/fixtures/class-testable-vat-eu.php';
require_once __DIR__ . '/fixtures/class-mock-vat-validation.php';

/**
 * Tests for Validator class
 */
class TaxField extends WP_UnitTestCase {
	const TEST_TAX_ID  = 'ATU12345678';
	const TEST_EMAIL   = 'test@user.com';
	const TEST_COMPANY = 'Acme';

	/**
	 * Run after each test
	 */
	protected function tearDown(): void {
		wc_clear_notices();
		parent::tearDown();
	}

	/**
	 * Test adding Tax ID field to my address form
	 */
	public function test_field_address_form() {
		$countries = new WC_Countries();
		$this->assertArrayHasKey( Chocante_VAT_EU::TAX_ID, $countries->get_address_fields() );
	}

	/**
	 * Test displaying Tax ID field in my billing address
	 */
	public function test_field_myaddress() {
		$customer_id = wc_create_new_customer( self::TEST_EMAIL );
		$customer    = new WC_Customer( $customer_id );

		$this->assertStringNotContainsString( self::TEST_TAX_ID, wc_get_account_formatted_address( 'billing', $customer ) );

		$customer->update_meta_data( Chocante_VAT_EU::TAX_ID, self::TEST_TAX_ID );
		$customer->save();

		$this->assertStringContainsString( self::TEST_TAX_ID, wc_get_account_formatted_address( 'billing', $customer ) );
	}

	/**
	 * Test displaying Tax ID field in order billing data
	 */
	public function test_field_order_billing() {
		$customer_id = wc_create_new_customer( self::TEST_EMAIL );
		$order       = wc_create_order( array( 'customer_id' => $customer_id ) );

		$order->update_meta_data( '_' . Chocante_VAT_EU::TAX_ID, self::TEST_TAX_ID );
		$order->save();

		$billing_data = apply_filters( 'woocommerce_order_formatted_billing_address', array(), $order );

		$this->assertArrayHasKey( 'tax_id', $billing_data );
		$this->assertSame( $billing_data['tax_id'], self::TEST_TAX_ID );
	}

	/**
	 * Test adding Tax ID field to customer address in admin
	 */
	public function test_field_user_address() {
		$admin_user_profile = new WC_Admin_Profile();
		$this->assertArrayHasKey( Chocante_VAT_EU::TAX_ID, $admin_user_profile->get_customer_meta_fields()['billing']['fields'] );
	}

	/**
	 * Test displaying Tax ID field in customer details in order (block checkout)
	 */
	public function test_field_order() {
		$customer_id = wc_create_new_customer( self::TEST_EMAIL );
		$order       = wc_create_order( array( 'customer_id' => $customer_id ) );

		$order->update_meta_data( CheckoutFields::get_group_key( 'other' ) . Chocante_VAT_EU::TAX_ID_FIELD, self::TEST_TAX_ID );
		$order->save();

		do_action( 'woocommerce_store_api_checkout_order_processed', $order );

		$customer = new WC_Customer( $order->get_customer_id() );

		$this->assertSame( $customer->get_meta( Chocante_VAT_EU::TAX_ID ), self::TEST_TAX_ID );
	}

	/**
	 * Test adding Tax ID to customer which created account after placing an order
	 */
	public function test_filed_delayed_customer() {
		$order = wc_create_order();

		$order->set_billing_email( self::TEST_EMAIL );
		$order->update_meta_data( '_' . Chocante_VAT_EU::TAX_ID, self::TEST_TAX_ID );
		$order->save();

		$customer_id = wc_create_new_customer( email: self::TEST_EMAIL, args: array( 'source' => 'delayed-account-creation' ) );
		$customer    = new WC_Customer( $customer_id );

		$this->assertSame( $customer->get_meta( Chocante_VAT_EU::TAX_ID ), self::TEST_TAX_ID );
	}

	/**
	 * Test Tax ID validation in my addrress - empty data
	 */
	public function test_field_validation_address_empty() {
		$vat_eu      = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$customer_id = wc_create_new_customer( self::TEST_EMAIL );
		$customer    = new WC_Customer( $customer_id );

		// Empty data.
		do_action( 'woocommerce_after_save_address_validation', $customer_id, 'billing', array(), $customer );

		$this->assertNull( $vat_eu->error );
		$this->assertEquals( wc_notice_count( 'error' ), 0 );
	}

	/**
	 * Test Tax ID validation in my addrress - missing company name
	 */
	public function test_field_validation_address_missing_company() {
		$vat_eu      = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$customer_id = wc_create_new_customer( self::TEST_EMAIL );
		$customer    = new WC_Customer( $customer_id );

		$customer->update_meta_data( Chocante_VAT_EU::TAX_ID, self::TEST_TAX_ID );
		$vat_eu->validate_tax_id_in_my_address( $customer_id, 'billing', array(), $customer );

		$this->assertSame( $vat_eu->error, Chocante_VAT_EU::ERROR_MISSING_COMPANY );
		$this->assertEquals( wc_notice_count( 'error' ), 1 );
	}

	/**
	 * Test Tax ID validation in my addrress - missing tax id
	 */
	public function test_field_validation_address_missing_tax_id() {
		$vat_eu      = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$customer_id = wc_create_new_customer( self::TEST_EMAIL );
		$customer    = new WC_Customer( $customer_id );

		$customer->set_billing_company( self::TEST_COMPANY );
		$vat_eu->validate_tax_id_in_my_address( $customer_id, 'billing', array(), $customer );

		$this->assertSame( $vat_eu->error, Chocante_VAT_Validation::ERROR_MISSING_VAT_ID );
		$this->assertEquals( wc_notice_count( 'error' ), 1 );
	}

	/**
	 * Test Tax ID validation in my addrress - valid data
	 */
	public function test_field_validation_address() {
		$vat_eu      = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$customer_id = wc_create_new_customer( self::TEST_EMAIL );
		$customer    = new WC_Customer( $customer_id );

		$customer->set_billing_company( self::TEST_COMPANY );
		$customer->update_meta_data( Chocante_VAT_EU::TAX_ID, self::TEST_TAX_ID );
		$vat_eu->validate_tax_id_in_my_address( $customer_id, 'billing', array(), $customer );

		$this->assertNull( $vat_eu->error );
		$this->assertEquals( wc_notice_count( 'error' ), 0 );
		$this->assertSame( $customer->get_meta( Chocante_VAT_EU::TAX_ID ), Mock_VAT_Validation::MOCK_TAX_ID );
	}

	/**
	 * Test Tax ID validation in checkout (classic) - missing tax id
	 */
	public function test_field_validation_checkout_missing_tax_id() {
		$vat_eu = new Testable_VAT_EU( new Mock_VAT_Validation() );

		$vat_eu->validate_tax_id_in_checkout();

		$this->assertNull( $vat_eu->error );
		$this->assertEquals( wc_notice_count( 'error' ), 0 );
	}

	/**
	 * Test Tax ID validation in checkout (classic) - missing company name
	 */
	public function test_field_validation_checkout_missing_company() {
		$vat_eu                           = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$_POST[ Chocante_VAT_EU::TAX_ID ] = self::TEST_TAX_ID;

		$vat_eu->validate_tax_id_in_checkout();

		$this->assertSame( $vat_eu->error, Chocante_VAT_EU::ERROR_MISSING_COMPANY );
		$this->assertEquals( wc_notice_count( 'error' ), 1 );
	}

	/**
	 * Test Tax ID validation in checkout (classic) - wrong tax id
	 */
	public function test_field_validation_checkout_wrong_tax_id() {
		$vat_eu                           = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$_POST['billing_company']         = self::TEST_COMPANY;
		$_POST[ Chocante_VAT_EU::TAX_ID ] = Mock_VAT_Validation::MOCK_BAD_TAX_ID;

		$vat_eu->validate_tax_id_in_checkout();

		$this->assertSame( $vat_eu->error, Chocante_VAT_Validation::ERROR_INCORRECT_FORMAT );
		$this->assertEquals( wc_notice_count( 'error' ), 1 );
	}

	/**
	 * Test Tax ID validation in checkout (classic) - valid
	 */
	public function test_field_validation_checkout() {
		$vat_eu                           = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$_POST['billing_company']         = self::TEST_COMPANY;
		$_POST[ Chocante_VAT_EU::TAX_ID ] = self::TEST_TAX_ID;

		$vat_eu->validate_tax_id_in_checkout();

		$this->assertNull( $vat_eu->error );
		$this->assertEquals( wc_notice_count( 'error' ), 0 );
    // @phpcs:ignore
		$this->assertSame( $_POST[ Chocante_VAT_EU::TAX_ID ], Mock_VAT_Validation::MOCK_TAX_ID );
	}

	/**
	 * Test Tax ID validation in checkout (block) - missing tax id
	 */
	public function test_field_validation_block_checkout_missing_tax_id() {
		$vat_eu = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$order  = wc_create_order();
		$errors = new WP_Error();

		$order->set_billing_company( self::TEST_COMPANY );
		$vat_eu->validate_tax_id_field_in_block_checkout( $order, $errors );

		$this->assertFalse( $errors->has_errors() );
	}

	/**
	 * Test Tax ID validation in checkout (block) - missing company
	 */
	public function test_field_validation_block_checkout_missing_company() {
		$vat_eu = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$order  = wc_create_order();
		$errors = new WP_Error();

		$order->update_meta_data( CheckoutFields::get_group_key( 'other' ) . Chocante_VAT_EU::TAX_ID_FIELD, self::TEST_TAX_ID );
		$vat_eu->validate_tax_id_field_in_block_checkout( $order, $errors );

		$this->assertTrue( $errors->has_errors() );
		$this->assertSame( $errors->get_error_code(), Chocante_VAT_EU::ERROR_MISSING_COMPANY );
	}

	/**
	 * Test Tax ID validation in checkout (block) - missing company
	 */
	public function test_field_validation_block_checkout_wrong_tax_id() {
		$vat_eu = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$order  = wc_create_order();
		$errors = new WP_Error();

		$order->set_billing_company( self::TEST_COMPANY );
		$order->update_meta_data( CheckoutFields::get_group_key( 'other' ) . Chocante_VAT_EU::TAX_ID_FIELD, Mock_VAT_Validation::MOCK_BAD_TAX_ID );
		$vat_eu->validate_tax_id_field_in_block_checkout( $order, $errors );

		$this->assertTrue( $errors->has_errors() );
		$this->assertSame( $errors->get_error_code(), Chocante_VAT_Validation::ERROR_INCORRECT_FORMAT );
	}

	/**
	 * Test Tax ID validation in checkout (block) - valid
	 */
	public function test_field_validation_block_checkout() {
		$vat_eu = new Testable_VAT_EU( new Mock_VAT_Validation() );
		$order  = wc_create_order();
		$errors = new WP_Error();

		$order->set_billing_company( self::TEST_COMPANY );
		$order->update_meta_data( CheckoutFields::get_group_key( 'other' ) . Chocante_VAT_EU::TAX_ID_FIELD, self::TEST_TAX_ID );
		$vat_eu->validate_tax_id_field_in_block_checkout( $order, $errors );

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( $order->get_meta( CheckoutFields::get_group_key( 'other' ) . Chocante_VAT_EU::TAX_ID_FIELD ), Mock_VAT_Validation::MOCK_TAX_ID );
	}
}
