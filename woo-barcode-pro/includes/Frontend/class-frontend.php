<?php
/**
 * Storefront barcode display on product pages and loop.
 *
 * @package WCBarcodePro\Frontend
 */

namespace WCBarcodePro\Frontend;

defined( 'ABSPATH' ) || exit;

class Frontend {

	private static ?Frontend $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		$settings = \WCBarcodePro\wcbp_settings();

		if ( $settings['show_single'] ) {
			add_action( 'woocommerce_product_meta_end', array( $this, 'render_single_barcode' ) );
		}
		if ( $settings['show_loop'] ) {
			add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_loop_barcode' ) );
		}
	}

	public function render_single_barcode(): void {
		global $post;
		if ( ! $post ) {
			return;
		}
		$settings = \WCBarcodePro\wcbp_settings();
		$svg      = \WCBarcodePro\wcbp_product_barcode_svg( $post->ID, 0, array(
			'height'       => $settings['barcode_height'],
			'module_width' => $settings['module_width'],
			'show_text'    => $settings['show_text'],
		) );
		if ( ! $svg ) {
			return;
		}
		include WCBP_PLUGIN_DIR . 'templates/frontend/single-barcode.php';
	}

	public function render_loop_barcode(): void {
		global $post;
		if ( ! $post ) {
			return;
		}
		$settings = \WCBarcodePro\wcbp_settings();
		$svg      = \WCBarcodePro\wcbp_product_barcode_svg( $post->ID, 0, array(
			'height'       => max( 30, (int) ( $settings['barcode_height'] * 0.7 ) ),
			'module_width' => $settings['module_width'],
			'show_text'    => false,
		) );
		if ( ! $svg ) {
			return;
		}
		include WCBP_PLUGIN_DIR . 'templates/frontend/loop-barcode.php';
	}
}
