<?php
/**
 * Inventory management: stock log, scan-to-adjust, scan-to-sell, low stock.
 *
 * @package WCBarcodePro\Inventory
 */

namespace WCBarcodePro\Inventory;

defined( 'ABSPATH' ) || exit;

class InventoryManager {

	private static ?InventoryManager $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'log_order_stock_reduction' ) );
		add_action( 'wp_ajax_wcbp_inv_lookup',        array( $this, 'ajax_lookup' ) );
		add_action( 'wp_ajax_wcbp_inv_adjust',        array( $this, 'ajax_adjust' ) );
		add_action( 'wp_ajax_wcbp_inv_sell_one',      array( $this, 'ajax_sell_one' ) );
		add_action( 'wp_ajax_wcbp_inv_low_stock',     array( $this, 'ajax_low_stock' ) );
		add_action( 'wp_ajax_wcbp_inv_log',           array( $this, 'ajax_get_log' ) );
		add_action( 'wp_ajax_wcbp_inv_publish_draft', array( $this, 'ajax_publish_draft' ) );
	}

	// ── Core methods ─────────────────────────────────────────────────────────

	public function lookup_by_barcode( string $barcode ): ?array {
		$barcode = trim( $barcode );
		if ( '' === $barcode ) {
			return null;
		}

		global $wpdb;

		// Query _sku directly — works for all post statuses including draft.
		// wc_get_product_id_by_sku() uses wc_product_meta_lookup which may miss drafts.
		$product_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
			   AND p.post_type IN ('product','product_variation')
			   AND p.post_status NOT IN ('trash','auto-draft')
			 LIMIT 1",
			$barcode
		) );

		// Fallback: EAN meta.
		if ( ! $product_id ) {
			$product_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value=%s LIMIT 1",
				\WCBarcodePro\Barcode\EanManager::META_KEY,
				$barcode
			) );
		}

		if ( ! $product_id ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		return array(
			'product_id'    => $product_id,
			'variation_id'  => 0,
			'name'          => $product->get_name(),
			'sku'           => $product->get_sku(),
			'status'        => $product->get_status(),
			'stock_qty'     => (int) $product->get_stock_quantity(),
			'stock_status'  => $product->get_stock_status(),
			'manage_stock'  => (bool) $product->get_manage_stock(),
			'edit_url'      => get_edit_post_link( $product_id, 'raw' ),
			'image_url'     => get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ?: '',
		);
	}

	public function adjust_stock( int $product_id, int $variation_id, int $new_qty, string $reason = 'manual', string $note = '' ): array {
		$id      = $variation_id > 0 ? $variation_id : $product_id;
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return array( 'success' => false, 'message' => __( 'Product not found.', 'woo-barcode-pro' ) );
		}

		$old_qty = (int) $product->get_stock_quantity();
		wc_update_product_stock( $product, $new_qty, 'set' );
		$this->log_change( $product_id, $variation_id, $old_qty, $new_qty, $reason, 0, $note );

		return array(
			'success' => true,
			'old_qty' => $old_qty,
			'new_qty' => $new_qty,
			'change'  => $new_qty - $old_qty,
			'status'  => $new_qty <= 0 ? 'outofstock' : 'instock',
		);
	}

	public function sell_one( int $product_id, int $variation_id = 0 ): array {
		$id      = $variation_id > 0 ? $variation_id : $product_id;
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return array( 'success' => false, 'message' => __( 'Product not found.', 'woo-barcode-pro' ) );
		}
		if ( ! $product->get_manage_stock() ) {
			return array( 'success' => false, 'message' => __( 'Stock tracking is not enabled for this product.', 'woo-barcode-pro' ) );
		}

		$old_qty = (int) $product->get_stock_quantity();
		if ( $old_qty <= 0 ) {
			return array( 'success' => false, 'message' => __( 'Already out of stock.', 'woo-barcode-pro' ) );
		}

		$new_qty = $old_qty - 1;
		wc_update_product_stock( $product, $new_qty, 'set' );
		$this->log_change(
			$product_id, $variation_id, $old_qty, $new_qty, 'scan_sell', 0,
			__( 'In-person sale via barcode scan', 'woo-barcode-pro' )
		);

		return array(
			'success' => true,
			'old_qty' => $old_qty,
			'new_qty' => $new_qty,
			'status'  => $new_qty <= 0 ? 'outofstock' : 'instock',
		);
	}

	public function log_change( int $product_id, int $variation_id, int $old_qty, int $new_qty, string $reason, int $order_id = 0, string $note = '' ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$wpdb->prefix . 'wcbp_stock_log',
			array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'old_qty'      => $old_qty,
				'new_qty'      => $new_qty,
				'change_qty'   => $new_qty - $old_qty,
				'reason'       => sanitize_key( $reason ),
				'order_id'     => $order_id,
				'user_id'      => get_current_user_id(),
				'note'         => sanitize_text_field( $note ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s' )
		);
	}

	public function log_order_stock_reduction( \WC_Order $order ): void {
		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( ! $product || ! $product->get_manage_stock() ) {
				continue;
			}
			$product_id   = $item->get_product_id();
			$variation_id = $item->get_variation_id();
			$qty_sold     = (int) $item->get_quantity();
			$new_qty      = (int) $product->get_stock_quantity();
			$old_qty      = $new_qty + $qty_sold;

			$this->log_change(
				$product_id, $variation_id, $old_qty, $new_qty, 'order',
				$order->get_id(),
				sprintf(
					/* translators: 1: order ID, 2: qty sold */
					__( 'Order #%1$d — sold %2$d unit(s)', 'woo-barcode-pro' ),
					$order->get_id(), $qty_sold
				)
			);
		}
	}

	public function get_low_stock( int $threshold = 5 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT p.ID AS product_id, p.post_title AS name,
			        pm_sku.meta_value    AS sku,
			        CAST(pm_stock.meta_value AS SIGNED) AS stock_qty
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm_sku    ON pm_sku.post_id    = p.ID AND pm_sku.meta_key    = '_sku'
			 LEFT JOIN {$wpdb->postmeta} pm_stock   ON pm_stock.post_id   = p.ID AND pm_stock.meta_key   = '_stock'
			 LEFT JOIN {$wpdb->postmeta} pm_manage  ON pm_manage.post_id  = p.ID AND pm_manage.meta_key  = '_manage_stock'
			 WHERE p.post_type    = 'product'
			   AND p.post_status  = 'publish'
			   AND pm_manage.meta_value = 'yes'
			   AND CAST(pm_stock.meta_value AS SIGNED) <= %d
			 ORDER BY CAST(pm_stock.meta_value AS SIGNED) ASC
			 LIMIT 200",
			$threshold
		), ARRAY_A ) ?: array();
	}

	public function get_log( int $product_id = 0, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		if ( $product_id > 0 ) {
			return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
				"SELECT l.*, p.post_title AS product_name
				 FROM {$wpdb->prefix}wcbp_stock_log l
				 LEFT JOIN {$wpdb->posts} p ON p.ID = IF(l.variation_id > 0, l.variation_id, l.product_id)
				 WHERE l.product_id = %d
				 ORDER BY l.created_at DESC
				 LIMIT %d OFFSET %d",
				$product_id, $limit, $offset
			), ARRAY_A ) ?: array();
		}

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT l.*, p.post_title AS product_name
			 FROM {$wpdb->prefix}wcbp_stock_log l
			 LEFT JOIN {$wpdb->posts} p ON p.ID = IF(l.variation_id > 0, l.variation_id, l.product_id)
			 ORDER BY l.created_at DESC
			 LIMIT %d OFFSET %d",
			$limit, $offset
		), ARRAY_A ) ?: array();
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_lookup(): void {
		check_ajax_referer( 'wcbp_inventory', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}
		$barcode = sanitize_text_field( wp_unslash( $_POST['barcode'] ?? '' ) );
		$result  = $this->lookup_by_barcode( $barcode );
		if ( $result ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: scanned barcode value */
					__( 'No product found for "%s". Check the barcode printed on the label matches this value.', 'woo-barcode-pro' ),
					$barcode
				),
			) );
		}
	}

	public function ajax_adjust(): void {
		check_ajax_referer( 'wcbp_inventory', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$product_id   = (int) ( $_POST['product_id']   ?? 0 );
		$variation_id = (int) ( $_POST['variation_id'] ?? 0 );
		$new_qty      = (int) ( $_POST['new_qty']      ?? 0 );
		$note         = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) );

		wp_send_json_success( $this->adjust_stock( $product_id, $variation_id, $new_qty, 'manual', $note ) );
	}

	public function ajax_sell_one(): void {
		check_ajax_referer( 'wcbp_inventory', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$product_id   = (int) ( $_POST['product_id']   ?? 0 );
		$variation_id = (int) ( $_POST['variation_id'] ?? 0 );
		$result       = $this->sell_one( $product_id, $variation_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	public function ajax_low_stock(): void {
		check_ajax_referer( 'wcbp_inventory', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$threshold = max( 0, (int) ( $_POST['threshold'] ?? 5 ) );
		wp_send_json_success( array( 'items' => $this->get_low_stock( $threshold ) ) );
	}

	public function ajax_get_log(): void {
		check_ajax_referer( 'wcbp_inventory', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$product_id = (int) ( $_POST['product_id'] ?? 0 );
		$page       = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$limit      = 50;
		$offset     = ( $page - 1 ) * $limit;
		wp_send_json_success( array( 'log' => $this->get_log( $product_id, $limit, $offset ) ) );
	}

	public function ajax_publish_draft(): void {
		check_ajax_referer( 'wcbp_inventory', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}

		$product_id = (int) ( $_POST['product_id'] ?? 0 );
		$name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$image_id   = (int) ( $_POST['image_id'] ?? 0 );

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'woo-barcode-pro' ) ) );
		}
		if ( ! $name ) {
			wp_send_json_error( array( 'message' => __( 'Product name is required.', 'woo-barcode-pro' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'woo-barcode-pro' ) ) );
		}

		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );

		if ( $image_id ) {
			$product->set_image_id( $image_id );
		}

		$product->save();

		wp_send_json_success( array(
			'product_id' => $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'name'       => $name,
		) );
	}

	// ── Admin page ────────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		$low_stock_threshold = (int) get_option( 'wcbp_low_stock_threshold', 5 );
		include WCBP_PLUGIN_DIR . 'templates/admin/inventory.php';
	}
}
