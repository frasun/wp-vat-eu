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
	 * @dataProvider data_vat_numbers
	 *
	 * @param string $country Test number.
	 * @param string $vat_id Test country code.
	 */
	public function test_validate_vat_format( $country, $vat_id ) {
		$this->assertTrue( Chocante_VAT_Validation::validate_vat_format( $country, $vat_id ) );
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

	/**
	 * Test local validator fallback
	 */
	public function test_local_fallback() {
		$validator = $this->getMockBuilder( Testable_VAT_Validation::class )
		->setConstructorArgs(
			array(
				function () {
					throw new Error( 'error' );
				},
			)
		)
		->onlyMethods( array( 'validate_local' ) )
		->getMock();

		$validator->expects( $this->once() )->method( 'validate_local' );

		$validator->validate( 'IT', '00159560366' );
	}


	/**
	 * Test local format validator
	 *
	 * @dataProvider data_vat_numbers
	 *
	 * @param string $country Test number.
	 * @param string $vat_id Test country code.
	 */
	public function test_local_validate( $country, $vat_id ) {
		$validator = new Chocante_VAT_Validation();
		$this->assertTrue( $validator->validate_local( $country, $vat_id ) );
	}

	/**
	 * Test VAT numbers
	 */
	public function data_vat_numbers() {
		return array(
			// Austria.
			array( 'AT', 'U19420008' ),
			// Belgium.
			array( 'BE', '0425258688' ),
			// Bulgaria.
			array( 'BG', '200204065' ),
			// Croatia.
			array( 'HR', '21523879111' ),
			// Cyprus.
			array( 'CY', 'HE165206' ),
			// Czech Republic.
			array( 'CZ', '27081052' ),
			// Denmark.
			array( 'DK', '50574911' ),
			// Estonia.
			array( 'EE', 'EE102125100' ),
			// Finland.
			array( 'FI', '21491726' ),
			// France.
			array( 'FR', 'FR83351745724' ),
			// Germany.
			array( 'DE', 'DE130504827' ),
			// United Kingdom.
			array( 'GB', 'GB527773320' ),
			// Greece.
			array( 'GR', 'EL099757704' ),
			// Hungary.
			array( 'HU', '10731084244' ),
			// Ireland.
			array( 'IE', '6420143R' ),
			// Italy.
			array( 'IT', 'IT02992760963' ),
			// Latvia.
			array( 'LV', 'LV50103951541' ),
			// Lithuania.
			array( 'LT', 'LT100005423711' ),
			// Luxembourg.
			array( 'LU', '26375245' ),
			// Malta.
			array( 'MT', 'MT20511424' ),
			// Monaco.
			array( 'MC', '04636090963' ),
			// Netherlands.
			array( 'NL', 'NL004445879B01' ),
			// Northern Ireland.
			array( 'XI', '527773320' ),
			// Poland.
			array( 'PL', '5270103385' ),
			// Portugal.
			array( 'PT', '505416654' ),
			// Romania.
			array( 'RO', 'RO17547941' ),
			// Slovakia.
			array( 'SK', '2020248538' ),
			// Slovenia.
			array( 'SI', 'SI62033930' ),
			// Spain.
			array( 'ES', 'A28812618' ),
			// Sweden.
			array( 'SE', 'SE556074756901' ),
		);
	}
}
