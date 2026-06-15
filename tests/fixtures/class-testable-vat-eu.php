<?php
/**
 * EU VAT (mock)
 *
 * @package Chocante_VAT_EU
 */

/**
 * Modified class
 */
class Testable_VAT_EU extends Chocante_VAT_EU {
	/**
	 * Add ability to modify instance
	 *
	 * @param callable $validator Modified class.
	 */
	public function __construct( $validator ) {
		$this->validator = $validator;
	}
}
