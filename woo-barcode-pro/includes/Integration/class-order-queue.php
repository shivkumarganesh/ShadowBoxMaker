<?php
/**
 * Automatically adds ordered products to the print queue when an order reaches
 * the configured status (e.g. "processing").
 *
 * @package WCBarcodePro\Integration
 */

namespace WCBarcodePro\Integration;

defined( 'ABSPATH' ) || exit;

class OrderQueue {

	private static ?OrderQueue $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		$settings = \WCBarcodePro\wcbp_settings();
		if ( empty( $settings['order_queue_enabled'] ) ) {
			return;
		}

		$status = 'wc-' . ltrim( $settings['order_queue_status'] ?? 'processing', 'wc-' );
		add_action( 'woocommerce_order_status_' . ltrim( $status, 'wc-' ), array( $this, 'handle_order' ), 10, 2 );
	}

	public function handle_order( int $order_id, \WC_Order $order ): void {
		$settings  = \WCBarcodePro\wcbp_settings();
		$label_id  = (int) ( \WCBarcodePro\Admin\LabelTemplates::get_instance()->get_default()['id'] ?? 0 );
		$queue     = \WCBarcodePro\Admin\PrintQueue::get_instance();

		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product_id   = (int) $item->get_product_id();
			$variation_id = (int) $item->get_variation_id();
			$qty          = max( 1, (int) $item->get_quantity() );

			if ( ! $product_id ) {
				continue;
			}
			$queue->add( $product_id, $qty, $variation_id, $label_id, $order_id );
		}
	}
}
