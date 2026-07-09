<?php
/**
 * Validator tests
 *
 * @package Chocante_VAT_EU
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/class-testable-vat-validation.php';

/**
 * Tests for Validator class
 */
class ValidatorTest extends TestCase {
	/**
	 * Test sanitize function
	 *
	 * @dataProvider data_vat_inputs
	 *
	 * @param string $input Test input.
	 * @param string $expected Expected output.
	 */
	public function test_sanitize( $input, $expected ) {
		$this->assertSame( Chocante_VAT_Validation::sanitize_input( $input ), $expected );
	}

	/**
	 * Test input data
	 */
	public function data_vat_inputs() {
		return array( array( '1-2-3-4-5-6', '123456' ), array( '1 2    3, ?4-5-6', '123456' ), array( 'aaa123', 'AAA123' ), array( "123\n4\x015", '12345' ) );
	}

	/**
	 * Test VAT format validation
	 *
	 * @dataProvider data_vat_formats
	 *
	 * @param string $country Test number.
	 * @param string $vat_id Test country code.
	 * @param bool   $expected Expected output.
	 */
	public function test_validate_vat_format( $country, $vat_id, $expected ) {
		$this->assertEquals( Chocante_VAT_Validation::validate_vat_format( $country, $vat_id ), $expected );
	}

	/**
	 * Test VAT formats
	 */
	public function data_vat_formats() {
		return array(
			array( 'ES', 'W2858339J', true ),
			array( 'FR', '57428936561', true ),
			array( 'NL', '9471967', false ),
			array( 'PT', '9471967123123123', false ),
			array( 'US', '9471967123123123', false ),
		);
	}

	/**
	 * Test VAT EU validation
	 */
	public function test_validation() {
		$validator = new Testable_VAT_Validation( fn() => array( 'valid' => true ) );

		// Missing VAT number & country.
		$this->assertFalse( $validator->validate( null, null ) );
		$this->assertSame( $validator->get_error(), 'MISSING_VAT_ID' );

		// Missing VAT number.
		$this->assertFalse( $validator->validate( 'DE', null ) );
		$this->assertSame( $validator->get_error(), 'MISSING_VAT_ID' );

		// Missing country.
		$this->assertFalse( $validator->validate( null, '123123123' ) );
		$this->assertSame( $validator->get_error(), 'MISSING_COUNTRY' );

		// Incorrect format.
		$this->assertFalse( $validator->validate( 'EE', '123' ) );
		$this->assertSame( $validator->get_error(), 'INCORRECT_FORMAT' );

		// Non-EU country.
		$this->assertFalse( $validator->validate( 'US', '123123123' ) );

		// Valid.
		$this->assertTrue( $validator->validate( 'IT', '01231231231' ) );
		$this->assertTrue( $validator->validate( 'ES', 'ESW2858339J' ) );
	}
}
