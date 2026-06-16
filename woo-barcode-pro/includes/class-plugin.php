<?php
/**
 * Main plugin bootstrap.
 *
 * @package WCBarcodePro
 */

namespace WCBarcodePro;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

class Plugin {

	private static ?Plugin $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		load_plugin_textdomain( 'woo-barcode-pro', false, dirname( plugin_basename( WCBP_PLUGIN_FILE ) ) . '/languages' );

		add_action( 'before_woocommerce_init', static function () {
			if ( class_exists( FeaturesUtil::class ) ) {
				FeaturesUtil::declare_compatibility( 'custom_order_tables', WCBP_PLUGIN_FILE, true );
			}
		} );

		// Boot modules.
		Admin\Admin::get_instance()->register_hooks();
		Admin\Settings::get_instance()->register_hooks();
		Admin\Tutorial::get_instance()->register_hooks();
		Admin\LabelTemplates::get_instance()->register_hooks();
		Admin\PriceTemplates::get_instance()->register_hooks();
		Admin\PrintQueue::get_instance()->register_hooks();
		Admin\PrintPage::get_instance()->register_hooks();
		Admin\QuickAdd::get_instance()->register_hooks();
		Admin\BulkActions::get_instance()->register_hooks();
		Barcode\SkuManager::get_instance()->register_hooks();
		Barcode\EanManager::get_instance()->register_hooks();
		Frontend\Frontend::get_instance()->register_hooks();
		REST\RestAPI::get_instance()->register_hooks();
		Integration\OrderQueue::get_instance()->register_hooks();
	}

	public static function get_default_settings(): array {
		return array(
			'symbology'             => 'code128',
			'prefix'                => 'WBP-',
			'auto_sku'              => true,
			'prefer_ean'            => false,
			'show_single'           => true,
			'show_loop'             => false,
			'order_queue_enabled'   => false,
			'order_queue_status'    => 'processing',
			'show_text'             => true,
			'barcode_height'        => 60,
			'module_width'          => 2,
		);
	}
}
