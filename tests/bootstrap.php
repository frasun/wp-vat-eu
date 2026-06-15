<?php
/**
 * PHPUnit bootstrap
 *
 * @package Chocante_VAT_EU
 */

// Tests env location.
$tests = getenv( 'WP_TESTS_DIR' );

// Include polyfills.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php' );

// Include WP test functions.
require_once $tests . '/includes/functions.php';

// Activate plugins.
tests_add_filter(
	'muplugins_loaded',
	function () {
		require WP_PLUGIN_DIR . '/woocommerce.latest-stable/woocommerce.php';
		require WP_PLUGIN_DIR . '/wp-vat-eu/chocante-vat-eu.php';
	}
);

require $tests . '/includes/bootstrap.php';
