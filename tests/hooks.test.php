<?php
/**
 * Hooks tests
 *
 * @package Chocante_VAT_EU
 */

require_once __DIR__ . '/fixtures/class-testable-vat-eu.php';
require_once __DIR__ . '/fixtures/class-testable-vat-validation.php';

/**
 * Tests for hooks
 */
class Hooks extends WP_UnitTestCase {
	/**
	 * Test wp_vat_eu_skip_api_rate_limit filter
	 */
	public function test_wp_vat_eu_skip_api_rate_limit() {
		$vat_eu = new Testable_VAT_EU( new Testable_VAT_Validation( fn() => '{"isValid":false,"userError":"' . Chocante_VAT_Validation::ERROR_RATE_LIMIT . '"}' ) );

		add_filter( 'wp_vat_eu_skip_api_rate_limit', '__return_true' );

		$this->assertSame( $vat_eu->validate_vat_number( 'AT', TaxField::TEST_TAX_ID ), TaxField::TEST_TAX_ID );
		$this->assertNull( $vat_eu->error );
	}

	/**
	 * Test wp_vat_eu_validator_{country} filter
	 */
	public function test_wp_vat_eu_validator() {
		add_filter( 'wp_vat_eu_validator_AT', fn( $val, $number ) => (bool) preg_match( '/^\d{3}$/', $number ), 10, 2 );

		$this->assertFalse( Chocante_VAT_EU::instance()->validate_vat_number( 'AT', TaxField::TEST_TAX_ID ) );
		$this->assertSame( Chocante_VAT_EU::instance()->error, Chocante_VAT_EU::ERROR_INVALID );

		Chocante_VAT_EU::instance()->error = null;

		$this->assertSame( Chocante_VAT_EU::instance()->validate_vat_number( 'AT', '123' ), '123' );
		$this->assertNull( Chocante_VAT_EU::instance()->error );
	}
}
