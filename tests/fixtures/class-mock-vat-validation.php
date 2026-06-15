<?php
/**
 * EU VAT Validation (mock)
 *
 * @package Chocante_VAT_EU
 */

/**
 * Validator class (mock)
 */
class Mock_VAT_Validation extends Chocante_VAT_Validation {
	const MOCK_BAD_TAX_ID = '123';
	const MOCK_TAX_ID     = 'OK123';

	/**
	 * Validate EU VAT ID
	 *
	 * @param string $country Country code.
	 * @param string $vat_id VAT number.
	 * @throws Error API error.
	 * @return bool
	 */
	public function validate( $country, $vat_id ) {
		if ( empty( $vat_id ) ) {
			$this->error = self::ERROR_MISSING_VAT_ID;
			return false;
		}

		if ( self::MOCK_BAD_TAX_ID === $vat_id ) {
			$this->error = self::ERROR_INCORRECT_FORMAT;
			return false;
		}

		return self::MOCK_TAX_ID;
	}
}
