<?php
/**
 * Plugin Name: VAT EU
 * Description: Validate European Union VAT number.
 * Version: 1.0.0
 * Author: Chocante
 * Text Domain: chocante-vat-eu
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Chocante_VAT_EU
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAIN_PLUGIN_FILE' ) ) {
	define( 'MAIN_PLUGIN_FILE', __FILE__ );
}

/**
 * Current plugin version.
 */
define( 'CHOCANTE_VAT_EU_VERSION', '1.0.0' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-chocante-vat-eu.php';
add_action( 'plugins_loaded', 'chocante_vat_eu_init', 10 );

/**
 * Load text domain
 */
function chocante_vat_eu_init() {
	load_plugin_textdomain( 'chocante-vat-eu', false, plugin_basename( __DIR__ ) . '/languages' );

	Chocante_VAT_EU::instance();
}

register_activation_hook( __FILE__, 'chocante_vat_eu_activate' );

/**
 * Activation hook
 */
function chocante_vat_eu_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'chocante_vat_eu_missing_wc_notice' );
		return;
	}
}

/**
 * WooCommerce fallback notice
 */
function chocante_vat_eu_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'VAT EU requires WooCommerce to be installed and active. You can download %s here.', 'chocante-vat-eu' ), '<a href="https://woo.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}
