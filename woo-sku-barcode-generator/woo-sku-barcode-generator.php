<?php
/**
 * Plugin Name:  Woo SKU & Barcode Generator
 * Plugin URI:   https://github.com/shivkumarganesh/shadowboxmaker
 * Description:  Auto-generates SKU codes for every WooCommerce product/variation and renders Code 128 / EAN-13 barcodes with a label print queue.
 * Version:      1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:       Shiv Kumar Ganesh
 * License:      GPL-2.0+
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  wsbg
 * WC requires at least: 7.0
 * WC tested up to: 9.1
 */

defined( 'ABSPATH' ) || exit;

define( 'WSBG_VERSION',  '1.0.0' );
define( 'WSBG_FILE',     __FILE__ );
define( 'WSBG_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WSBG_URL',      plugin_dir_url( __FILE__ ) );
define( 'WSBG_OPTION',   'wsbg_settings' );
define( 'WSBG_SLUG',     'wsbg' );

// ── HPOS compatibility ────────────────────────────────────────────────────────
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables', WSBG_FILE, true
		);
	}
} );

// ── Boot ──────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Woo SKU & Barcode Generator requires WooCommerce to be active.', 'wsbg' )
				. '</p></div>';
		} );
		return;
	}

	require_once WSBG_DIR . 'includes/class-wsbg-settings.php';
	require_once WSBG_DIR . 'includes/class-wsbg-sku-generator.php';
	require_once WSBG_DIR . 'includes/class-wsbg-barcode.php';
	require_once WSBG_DIR . 'includes/class-wsbg-product-meta.php';
	require_once WSBG_DIR . 'includes/class-wsbg-admin.php';
	require_once WSBG_DIR . 'includes/class-wsbg-print-queue.php';

	WSBG_Settings::init();
	WSBG_SKU_Generator::init();
	WSBG_Product_Meta::init();
	WSBG_Admin::init();
	WSBG_Print_Queue::init();
} );

// ── Activation / deactivation ─────────────────────────────────────────────────
register_activation_hook( WSBG_FILE, function () {
	add_option( WSBG_OPTION, WSBG_Settings::defaults() );
} );

register_deactivation_hook( WSBG_FILE, '__return_false' );
