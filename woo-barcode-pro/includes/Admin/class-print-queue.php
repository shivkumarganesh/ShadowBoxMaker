<?php
/**
 * DB-backed print queue management.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class PrintQueue {

	private static ?PrintQueue $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'wp_ajax_wcbp_add_to_queue',       array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_wcbp_bulk_add_to_queue', array( $this, 'ajax_bulk_add' ) );
		add_action( 'wp_ajax_wcbp_remove_from_queue', array( $this, 'ajax_remove' ) );
		add_action( 'wp_ajax_wcbp_update_qty',        array( $this, 'ajax_update_qty' ) );
		add_action( 'wp_ajax_wcbp_clear_queue',       array( $this, 'ajax_clear' ) );
		add_action( 'wp_ajax_wcbp_mark_printed',      array( $this, 'ajax_mark_printed' ) );

		// Keep queue in sync with product lifecycle.
		add_action( 'wp_trash_post',          array( $this, 'on_product_trashed_or_deleted' ) );
		add_action( 'before_delete_post',     array( $this, 'on_product_trashed_or_deleted' ) );
		add_action( 'transition_post_status', array( $this, 'on_product_published' ), 10, 3 );
	}

	// ── Data methods ──────────────────────────────────────────────────────────

	public function add( int $product_id, int $qty = 1, int $variation_id = 0, int $label_template_id = 0, int $order_id = 0 ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wcbp_print_queue';

		// Merge if same product+variation already pending.
		$existing = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT id FROM $table WHERE product_id=%d AND variation_id=%d AND status='pending'",
			$product_id, $variation_id
		) );

		if ( $existing ) {
			return (bool) $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB
				"UPDATE $table SET quantity = quantity + %d WHERE id = %d",
				$qty, $existing
			) );
		}

		return (bool) $wpdb->insert( $table, array(
			'product_id'        => $product_id,
			'variation_id'      => $variation_id,
			'quantity'          => $qty,
			'label_template_id' => $label_template_id,
			'added_by'          => get_current_user_id(),
			'order_id'          => $order_id,
			'status'            => 'pending',
		), array( '%d','%d','%d','%d','%d','%d','%s' ) ); // phpcs:ignore WordPress.DB
	}

	public function remove( int $queue_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $wpdb->prefix . 'wcbp_print_queue', array( 'id' => $queue_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	public function update_quantity( int $queue_id, int $qty ): bool {
		global $wpdb;
		if ( $qty <= 0 ) {
			return $this->remove( $queue_id );
		}
		return (bool) $wpdb->update( $wpdb->prefix . 'wcbp_print_queue', array( 'quantity' => $qty ), array( 'id' => $queue_id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	public function clear(): bool {
		global $wpdb;
		return (bool) $wpdb->query( "DELETE FROM {$wpdb->prefix}wcbp_print_queue WHERE status='pending'" ); // phpcs:ignore WordPress.DB
	}

	public function get_all( string $status = 'pending' ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT q.*, p.post_title AS product_name, pm.meta_value AS sku
			 FROM {$wpdb->prefix}wcbp_print_queue q
			 LEFT JOIN {$wpdb->posts} p ON p.ID = IF(q.variation_id > 0, q.variation_id, q.product_id)
			 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = IF(q.variation_id > 0, q.variation_id, q.product_id) AND pm.meta_key = '_sku'
			 WHERE q.status = %s
			 ORDER BY q.added_at ASC",
			$status
		), ARRAY_A ) ?: array();
	}

	public function get_count( string $status = 'pending' ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wcbp_print_queue WHERE status=%s", $status ) ); // phpcs:ignore WordPress.DB
	}

	public function mark_printed( array $ids ): bool {
		global $wpdb;
		if ( empty( $ids ) ) {
			return false;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return (bool) $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"UPDATE {$wpdb->prefix}wcbp_print_queue SET status='printed', printed_at=NOW() WHERE id IN ($placeholders)",
			...$ids
		) );
	}

	public function handle_bulk_add( array $product_ids ): int {
		$count    = 0;
		$label_id = (int) ( LabelTemplates::get_instance()->get_default()['id'] ?? 0 );
		foreach ( $product_ids as $pid ) {
			if ( $this->add( (int) $pid, 1, 0, $label_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	public function remove_by_product( int $product_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB
			$wpdb->prefix . 'wcbp_print_queue',
			array( 'product_id' => $product_id ),
			array( '%d' )
		);
	}

	public function on_product_trashed_or_deleted( int $post_id ): void {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		$this->remove_by_product( $post_id );
	}

	public function on_product_published( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' !== $new_status || $new_status === $old_status || 'product' !== $post->post_type ) {
			return;
		}
		$this->remove_by_product( $post->ID );
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_add(): void {
		check_ajax_referer( 'wcbp_queue', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}
		$product_id   = (int) ( $_POST['product_id']   ?? 0 );
		$qty          = max( 1, (int) ( $_POST['qty'] ?? 1 ) );
		$variation_id = (int) ( $_POST['variation_id'] ?? 0 );
		$label_id     = (int) ( $_POST['label_template_id'] ?? 0 );

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'woo-barcode-pro' ) ) );
		}

		$this->add( $product_id, $qty, $variation_id, $label_id );
		wp_send_json_success( array(
			'count'   => $this->get_count(),
			'message' => __( 'Added to print queue.', 'woo-barcode-pro' ),
		) );
	}

	public function ajax_bulk_add(): void {
		check_ajax_referer( 'wcbp_queue', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}
		$ids   = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
		$count = $this->handle_bulk_add( $ids );
		wp_send_json_success( array(
			'total'   => $this->get_count(),
			'added'   => $count,
			'message' => sprintf(
				/* translators: %d: number of products added */
				_n( '%d product added to print queue.', '%d products added to print queue.', $count, 'woo-barcode-pro' ),
				$count
			),
		) );
	}

	public function ajax_remove(): void {
		check_ajax_referer( 'wcbp_queue', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$id = (int) ( $_POST['id'] ?? 0 );
		wp_send_json_success( array( 'removed' => $this->remove( $id ), 'count' => $this->get_count() ) );
	}

	public function ajax_update_qty(): void {
		check_ajax_referer( 'wcbp_queue', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$id  = (int) ( $_POST['id']  ?? 0 );
		$qty = (int) ( $_POST['qty'] ?? 1 );
		wp_send_json_success( array( 'updated' => $this->update_quantity( $id, $qty ) ) );
	}

	public function ajax_clear(): void {
		check_ajax_referer( 'wcbp_queue', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		wp_send_json_success( array( 'cleared' => $this->clear() ) );
	}

	public function ajax_mark_printed(): void {
		check_ajax_referer( 'wcbp_queue', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$ids = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
		wp_send_json_success( array( 'marked' => $this->mark_printed( $ids ) ) );
	}

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		$items           = $this->get_all();
		$label_templates = LabelTemplates::get_instance()->get_all();
		$default_tpl     = LabelTemplates::get_instance()->get_default();
		include WCBP_PLUGIN_DIR . 'templates/admin/print-queue.php';
	}
}
