<?php
/**
 * Mobile-optimised Quick Add product page.
 *
 * Workflow: scan price-template barcode → product name → camera photo → Save.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class QuickAdd {

	private static ?QuickAdd $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'wp_ajax_wcbp_quick_save_product', array( $this, 'ajax_save_product' ) );
		add_action( 'wp_ajax_wcbp_quick_upload_image',  array( $this, 'ajax_upload_image' ) );
	}

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		$settings        = \WCBarcodePro\wcbp_settings();
		$price_templates = PriceTemplates::get_instance()->get_all();
		$categories      = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		include WCBP_PLUGIN_DIR . 'templates/admin/quick-add.php';
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_save_product(): void {
		check_ajax_referer( 'wcbp_quick_add', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}

		$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$price        = (float) ( $_POST['price'] ?? 0 );
		$category_ids = array_map( 'intval', (array) ( $_POST['category_ids'] ?? array() ) );
		$tag_ids      = array_map( 'intval', (array) ( $_POST['tag_ids']      ?? array() ) );
		$image_id     = (int) ( $_POST['image_id'] ?? 0 );
		$sku          = sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) );
		$attributes   = (array) ( $_POST['attributes'] ?? array() );
		$label_tpl_id = (int) ( $_POST['label_template_id'] ?? 0 );

		if ( ! $name ) {
			wp_send_json_error( array( 'message' => __( 'Product name is required.', 'woo-barcode-pro' ) ) );
		}

		// Create the WooCommerce product.
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( (string) $price );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );

		if ( $sku ) {
			$product->set_sku( $sku );
		}
		if ( $image_id ) {
			$product->set_image_id( $image_id );
		}
		if ( ! empty( $category_ids ) ) {
			$product->set_category_ids( $category_ids );
		}
		if ( ! empty( $tag_ids ) ) {
			$product->set_tag_ids( $tag_ids );
		}

		// Attributes.
		if ( ! empty( $attributes ) ) {
			$wc_attrs = array();
			foreach ( $attributes as $attr_name => $attr_value ) {
				$attr = new \WC_Product_Attribute();
				$attr->set_name( sanitize_text_field( $attr_name ) );
				$attr->set_options( array( sanitize_text_field( $attr_value ) ) );
				$attr->set_visible( true );
				$wc_attrs[] = $attr;
			}
			$product->set_attributes( $wc_attrs );
		}

		$product_id = $product->save();
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create product.', 'woo-barcode-pro' ) ) );
		}

		// Auto-generate SKU if enabled and not provided.
		$settings = \WCBarcodePro\wcbp_settings();
		if ( $settings['auto_sku'] && ! $sku ) {
			\WCBarcodePro\Barcode\SkuManager::get_instance()->auto_generate_sku( $product_id );
		}

		// Add to print queue automatically.
		PrintQueue::get_instance()->add( $product_id, 1, 0, $label_tpl_id );

		wp_send_json_success( array(
			'product_id' => $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'message'    => __( 'Product created and added to print queue.', 'woo-barcode-pro' ),
		) );
	}

	public function ajax_upload_image(): void {
		check_ajax_referer( 'wcbp_quick_add', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}

		if ( empty( $_FILES['image'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No image received.', 'woo-barcode-pro' ) ) );
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$attachment_id = media_handle_upload( 'image', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
		) );
	}
}
