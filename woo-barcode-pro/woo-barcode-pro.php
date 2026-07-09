<?php
/**
 * Plugin Name:       WooBarcode Pro
 * Plugin URI:        https://shadowboxmaker.com
 * Description:       Auto-generate barcodes, design label templates, manage a print queue, and add products in seconds with the mobile Quick Add workflow.
 * Version:           1.1.2
 * Author:            ShadowBoxMaker
 * Author URI:        https://shadowboxmaker.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-barcode-pro
 * Domain Path:       /languages
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * WC requires at least: 9.1
 * WC tested up to:   9.5
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;

define( 'WCBP_VERSION',     '1.1.2' );
define( 'WCBP_PLUGIN_FILE', __FILE__ );
define( 'WCBP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WCBP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WCBP_DB_VERSION',  '1.2' );

register_activation_hook( __FILE__, static function () {
	require_once WCBP_PLUGIN_DIR . 'includes/class-activator.php';
	\WCBarcodePro\Activator::activate();
} );

register_deactivation_hook( __FILE__, static function () {
	require_once WCBP_PLUGIN_DIR . 'includes/class-deactivator.php';
	\WCBarcodePro\Deactivator::deactivate();
} );

add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'WooBarcode Pro requires WooCommerce to be installed and active.', 'woo-barcode-pro' ) .
				'</p></div>';
		} );
		return;
	}

	$autoload = WCBP_PLUGIN_DIR . 'vendor/autoload.php';
	if ( file_exists( $autoload ) ) {
		require_once $autoload;
	} else {
		// Fallback: manual includes when composer vendor not installed.
		require_once WCBP_PLUGIN_DIR . 'includes/class-plugin.php';
		require_once WCBP_PLUGIN_DIR . 'includes/class-activator.php';
		require_once WCBP_PLUGIN_DIR . 'includes/class-deactivator.php';
		require_once WCBP_PLUGIN_DIR . 'includes/helpers.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Updater/class-plugin-updater.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Barcode/class-barcode-generator.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Barcode/class-sku-manager.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Barcode/class-ean-manager.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-admin.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-settings.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-tutorial.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-label-templates.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-price-templates.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-print-queue.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-print-page.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-quick-add.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Admin/class-bulk-actions.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Label/class-label-renderer.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Frontend/class-frontend.php';
		require_once WCBP_PLUGIN_DIR . 'includes/REST/class-rest-api.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Integration/class-order-queue.php';
		require_once WCBP_PLUGIN_DIR . 'includes/Inventory/class-inventory-manager.php';
	}

	\WCBarcodePro\Plugin::get_instance()->init();
}, 10 );
