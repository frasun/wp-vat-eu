<?php
/**
 * VAT exemption tests
 *
 * @package Chocante_VAT_EU
 */

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

/**
 * Tests for VAT exemption
 */
class VATExemption extends WP_UnitTestCase {
	/**
	 * Run after each test
	 */
	protected function tearDown(): void {
		wc()->customer = new WC_Customer();
		parent::tearDown();
	}

	/**
	 * Test VAT exemption on page load
	 */
	public function test_vat_exempt() {
		$customer = wc()->customer;
		$customer->set_billing_country( 'AT' );
		$customer->update_meta_data( Chocante_VAT_EU::TAX_ID, TaxField::TEST_TAX_ID );
		$customer->set_billing_company( TaxField::TEST_COMPANY );

		Chocante_VAT_EU::instance()->maybe_set_vat_exemption();

		$this->assertTrue( $customer->is_vat_exempt() );
	}

	/**
	 * Test VAT exemption on ajax order review (classic checkout)
	 */
	public function test_vat_exempt_order_review() {
		$customer = wc()->customer;
		$customer->set_billing_country( 'AT' );
		$customer->update_meta_data( Chocante_VAT_EU::TAX_ID, TaxField::TEST_TAX_ID );
		$customer->set_billing_company( TaxField::TEST_COMPANY );

		$_REQUEST['wc-ajax'] = 'update_order_review';

		Chocante_VAT_EU::instance()->maybe_set_vat_exemption();

		$this->assertFalse( $customer->is_vat_exempt() );
	}

	/**
	 * Test VAT exemption on page load - cookie
	 */
	public function test_vat_exempt_cookie() {
		$customer                                      = wc()->customer;
		$_COOKIE[ Chocante_VAT_EU::VAT_EXEMPT_COOKIE ] = 1;

		$customer->set_billing_country( 'AT' );
		$filter = fn() => 'DE';
		add_filter( 'woocommerce_countries_base_country', $filter );

		Chocante_VAT_EU::instance()->maybe_set_vat_exemption();

		$this->assertTrue( $customer->is_vat_exempt() );

		remove_filter( 'woocommerce_countries_base_country', $filter );
	}

	/**
	 * Test VAT exemption on page load - cookie - non-EU country
	 */
	public function test_vat_exempt_cookie_non_eu() {
		$customer                                      = wc()->customer;
		$_COOKIE[ Chocante_VAT_EU::VAT_EXEMPT_COOKIE ] = 1;

		$customer->set_billing_country( 'US' );
		$filter = fn() => 'DE';
		add_filter( 'woocommerce_countries_base_country', $filter );

		Chocante_VAT_EU::instance()->maybe_set_vat_exemption();

		$this->assertFalse( $customer->is_vat_exempt() );

		remove_filter( 'woocommerce_countries_base_country', $filter );
	}

	/**
	 * Test VAT exemption on page load - cookie - non-EU base country
	 */
	public function test_vat_exempt_cookie_non_eu_base() {
		$customer                                      = wc()->customer;
		$_COOKIE[ Chocante_VAT_EU::VAT_EXEMPT_COOKIE ] = 1;

		$customer->set_billing_country( 'AT' );
		$filter = fn() => 'US';
		add_filter( 'woocommerce_countries_base_country', $filter );

		Chocante_VAT_EU::instance()->maybe_set_vat_exemption();

		$this->assertFalse( $customer->is_vat_exempt() );

		remove_filter( 'woocommerce_countries_base_country', $filter );
	}

	/**
	 * Test VAT exemption on page load - cookie - base country
	 */
	public function test_vat_exempt_cookie_eu_base() {
		$customer                                      = wc()->customer;
		$_COOKIE[ Chocante_VAT_EU::VAT_EXEMPT_COOKIE ] = 1;

		$customer->set_billing_country( 'AT' );
		$filter = fn() => 'AT';
		add_filter( 'woocommerce_countries_base_country', $filter );

		Chocante_VAT_EU::instance()->maybe_set_vat_exemption();

		$this->assertFalse( $customer->is_vat_exempt() );

		remove_filter( 'woocommerce_countries_base_country', $filter );
	}

	/**
	 * Test VAT exemption on page load - cookie - checkout
	 */
	public function test_vat_exempt_cookie_checkout() {
		$customer                                      = wc()->customer;
		$_COOKIE[ Chocante_VAT_EU::VAT_EXEMPT_COOKIE ] = 1;

		$customer->set_billing_country( 'AT' );
		$filter = fn() => 'DE';
		add_filter( 'woocommerce_countries_base_country', $filter );
		add_filter( 'woocommerce_is_checkout', '__return_true' );

		Chocante_VAT_EU::instance()->maybe_set_vat_exemption();

		$this->assertFalse( $customer->is_vat_exempt() );

		remove_filter( 'woocommerce_countries_base_country', $filter );
		remove_filter( 'woocommerce_is_checkout', '__return_true' );
	}

	/**
	 * Test VAT exemption in checkout (classic)
	 */
	public function test_vat_exempt_checkout_change() {
		$post_query = Chocante_VAT_EU::TAX_ID . '=' . TaxField::TEST_TAX_ID . '&billing_company=' . TaxField::TEST_COMPANY;
		wc()->customer->set_billing_country( 'AT' );

		Chocante_VAT_EU::instance()->save_tax_id_in_checkout( $post_query );

		$this->assertSame( wc()->customer->get_meta( Chocante_VAT_EU::TAX_ID ), TaxField::TEST_TAX_ID );
		$this->assertSame( wc()->customer->get_billing_company(), TaxField::TEST_COMPANY );
		$this->assertTrue( wc()->customer->is_vat_exempt() );
	}

	/**
	 * Test VAT exemption in checkout (block) - tax id change
	 */
	public function test_vat_exempt_checkout_block_customer_change() {
		$order = wc_create_order();

		wc()->customer->set_billing_country( 'AT' );
		wc()->customer->set_billing_company( TaxField::TEST_COMPANY );

		$order->update_meta_data( CheckoutFields::get_group_key( 'other' ) . Chocante_VAT_EU::TAX_ID_FIELD, TaxField::TEST_TAX_ID );
		$order->save();
		wc()->session->set( 'store_api_draft_order', $order->get_id() );

		Chocante_VAT_EU::instance()->set_vat_exempt_on_store_api_customer_update( wc()->customer );

		$this->assertTrue( wc()->customer->is_vat_exempt() );
	}

	/**
	 * Test VAT exemption in checkout (block) - customer data change
	 */
	public function test_vat_exempt_checkout_block_order_change() {
		$order   = wc_create_order();
		$request = new WP_REST_Request();

		wc()->customer->set_billing_country( 'AT' );
		wc()->customer->set_billing_company( TaxField::TEST_COMPANY );

		$request->set_param( 'additional_fields', array( Chocante_VAT_EU::TAX_ID_FIELD => TaxField::TEST_TAX_ID ) );
		wc()->session->set( 'store_api_draft_order', $order->get_id() );

		Chocante_VAT_EU::instance()->set_vat_exempt_on_store_api_order_update( $order, $request );

		$this->assertTrue( wc()->customer->is_vat_exempt() );
	}
}
