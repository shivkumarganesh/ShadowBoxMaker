<?php
/**
 * SKU auto-generation manager.
 *
 * @package WCBarcodePro\Barcode
 */

namespace WCBarcodePro\Barcode;

defined( 'ABSPATH' ) || exit;

class SkuManager {

	private static ?SkuManager $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'save_post_product', array( $this, 'maybe_auto_sku' ), 20, 1 );
	}

	public function maybe_auto_sku( int $product_id ): void {
		if ( ! \WCBarcodePro\wcbp_get_setting( 'auto_sku' ) ) {
			return;
		}
		$existing = get_post_meta( $product_id, '_sku', true );
		if ( empty( $existing ) ) {
			$this->auto_generate_sku( $product_id );
		}
	}

	public function auto_generate_sku( int $product_id, int $variation_id = 0 ): string {
		$id     = $variation_id > 0 ? $variation_id : $product_id;
		$prefix = (string) \WCBarcodePro\wcbp_get_setting( 'prefix', 'WBP-' );
		$sku    = $prefix . $id;
		update_post_meta( $id, '_sku', $sku );
		return $sku;
	}

	public function get_or_create_sku( int $product_id, int $variation_id = 0 ): string {
		$id  = $variation_id > 0 ? $variation_id : $product_id;
		$sku = get_post_meta( $id, '_sku', true );
		if ( ! empty( $sku ) ) {
			return (string) $sku;
		}
		return $this->auto_generate_sku( $product_id, $variation_id );
	}

	public function bulk_regenerate( array $product_ids = array() ): int {
		if ( empty( $product_ids ) ) {
			global $wpdb;
			$product_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
			);
		}
		$count  = 0;
		$prefix = (string) \WCBarcodePro\wcbp_get_setting( 'prefix', 'WBP-' );
		foreach ( $product_ids as $pid ) {
			$sku = $prefix . (int) $pid;
			update_post_meta( (int) $pid, '_sku', $sku );
			$count++;
		}
		return $count;
	}
}
