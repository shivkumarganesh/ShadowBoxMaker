<?php
/**
 * Price template CRUD — the heart of the Quick Add workflow.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class PriceTemplates {

	private static ?PriceTemplates $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'admin_post_wcbp_save_price_template',   array( $this, 'handle_save' ) );
		add_action( 'admin_post_wcbp_delete_price_template', array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_wcbp_lookup_price_template',    array( $this, 'ajax_lookup_barcode' ) );
	}

	public function get_all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wcbp_price_templates ORDER BY price ASC, name ASC", ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB
	}

	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wcbp_price_templates WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ?: null;
	}

	public function get_by_barcode( string $barcode ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wcbp_price_templates WHERE barcode_value = %s", $barcode ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ?: null;
	}

	public function save( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'wcbp_price_templates';

		$row = array(
			'name'              => sanitize_text_field( $data['name'] ?? 'Untitled' ),
			'price'             => round( (float) ( $data['price'] ?? 0 ), 2 ),
			'category_ids'      => wp_json_encode( array_map( 'intval', (array) ( $data['category_ids'] ?? array() ) ) ),
			'tag_ids'           => wp_json_encode( array_map( 'intval', (array) ( $data['tag_ids'] ?? array() ) ) ),
			'attributes'        => wp_json_encode( (array) ( $data['attributes'] ?? array() ) ),
			'label_template_id' => (int) ( $data['label_template_id'] ?? 0 ),
		);
		$fmt = array( '%s', '%f', '%s', '%s', '%s', '%d' );

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $data['id'] ), $fmt, array( '%d' ) ); // phpcs:ignore WordPress.DB
			return (int) $data['id'];
		}

		$wpdb->insert( $table, $row, $fmt ); // phpcs:ignore WordPress.DB
		$new_id = $wpdb->insert_id;
		if ( ! $new_id ) {
			return false;
		}

		// Assign the unique internal barcode after we have the ID.
		$barcode = 'WCBP-TPL-' . $new_id;
		$wpdb->update( $table, array( 'barcode_value' => $barcode ), array( 'id' => $new_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB

		return $new_id;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $wpdb->prefix . 'wcbp_price_templates', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	public function handle_save(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		\WCBarcodePro\wcbp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'wcbp_save_price_template' );
		$data                 = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
		$data['category_ids'] = isset( $_POST['category_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['category_ids'] ) ) : array(); // phpcs:ignore WordPress.Security
		$id                   = $this->save( $data );
		wp_safe_redirect( admin_url( 'admin.php?page=wcbp-price-templates&saved=1&id=' . (int) $id ) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		\WCBarcodePro\wcbp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'wcbp_delete_price_template' );
		$this->delete( (int) ( $_GET['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=wcbp-price-templates&deleted=1' ) );
		exit;
	}

	public function ajax_lookup_barcode(): void {
		check_ajax_referer( 'wcbp_quick_add', 'nonce' );
		$barcode = sanitize_text_field( wp_unslash( $_POST['barcode'] ?? '' ) );

		// Check price templates first.
		$tpl = $this->get_by_barcode( $barcode );
		if ( $tpl ) {
			$tpl['category_ids'] = json_decode( $tpl['category_ids'] ?? '[]', true );
			$tpl['tag_ids']      = json_decode( $tpl['tag_ids']      ?? '[]', true );
			$tpl['attributes']   = json_decode( $tpl['attributes']   ?? '{}', true );
			wp_send_json_success( array( 'type' => 'template', 'template' => $tpl ) );
		}

		// Check existing products (by SKU or EAN meta) — query postmeta directly
		// so draft products are found (wc_get_product_id_by_sku uses lookup table
		// which may not include drafts in all WC versions).
		global $wpdb;
		$product_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
			   AND p.post_type IN ('product','product_variation')
			   AND p.post_status NOT IN ('trash','auto-draft')
			 LIMIT 1",
			$barcode
		) );
		if ( ! $product_id ) {
			$product_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				\WCBarcodePro\Barcode\EanManager::META_KEY,
				$barcode
			) );
		}
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				wp_send_json_success( array(
					'type'    => 'product',
					'product' => array(
						'id'    => $product_id,
						'name'  => $product->get_name(),
						'price' => $product->get_price(),
						'sku'   => $product->get_sku(),
						'edit_url' => get_edit_post_link( $product_id, 'raw' ),
					),
				) );
			}
		}

		wp_send_json_success( array( 'type' => 'unknown' ) );
	}

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		$templates      = $this->get_all();
		$label_templates = LabelTemplates::get_instance()->get_all();
		$action         = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security
		$edit_id        = (int) ( $_GET['id'] ?? 0 );
		$editing        = ( 'edit' === $action || 'new' === $action ) ? ( $edit_id ? $this->get( $edit_id ) : array() ) : null;
		$categories     = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		include WCBP_PLUGIN_DIR . 'templates/admin/price-templates.php';
	}
}
