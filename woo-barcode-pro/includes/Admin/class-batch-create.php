<?php
/**
 * Batch Create — scaffold N draft products from a price template.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class BatchCreate {

	private static ?BatchCreate $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'wp_ajax_wcbp_batch_create', array( $this, 'ajax_batch_create' ) );
	}

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		$price_templates = PriceTemplates::get_instance()->get_all();
		$label_templates = LabelTemplates::get_instance()->get_all();
		include WCBP_PLUGIN_DIR . 'templates/admin/batch-create.php';
	}

	public function ajax_batch_create(): void {
		check_ajax_referer( 'wcbp_batch_create', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}

		$template_id      = (int) ( $_POST['template_id']      ?? 0 );
		$quantity         = min( max( (int) ( $_POST['quantity'] ?? 1 ), 1 ), 500 );
		$label_tpl_id     = (int) ( $_POST['label_template_id'] ?? 0 );

		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select a price template.', 'woo-barcode-pro' ) ) );
		}

		$template = PriceTemplates::get_instance()->get( $template_id );
		if ( ! $template ) {
			wp_send_json_error( array( 'message' => __( 'Price template not found.', 'woo-barcode-pro' ) ) );
		}

		$category_ids  = json_decode( $template['category_ids'] ?? '[]', true ) ?: array();
		$tag_ids       = json_decode( $template['tag_ids']      ?? '[]', true ) ?: array();
		$price         = (float) $template['price'];
		$label_tpl_id  = $label_tpl_id ?: (int) ( $template['label_template_id'] ?? 0 );
		$settings      = \WCBarcodePro\wcbp_settings();

		$created = array();
		for ( $i = 0; $i < $quantity; $i++ ) {
			$product = new \WC_Product_Simple();
			$product->set_name( sprintf( __( 'Draft — %s', 'woo-barcode-pro' ), $template['name'] ) );
			$product->set_regular_price( (string) $price );
			$product->set_status( 'draft' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( 1 );
			$product->set_stock_status( 'instock' );

			if ( ! empty( $category_ids ) ) {
				$product->set_category_ids( $category_ids );
			}
			if ( ! empty( $tag_ids ) ) {
				$product->set_tag_ids( $tag_ids );
			}

			$product_id = $product->save();
			if ( ! $product_id ) {
				continue;
			}

			$sku = '';
			if ( $settings['auto_sku'] ) {
				$sku = \WCBarcodePro\Barcode\SkuManager::get_instance()->auto_generate_sku( $product_id );
			}

			PrintQueue::get_instance()->add( $product_id, 1, 0, $label_tpl_id );

			$created[] = array(
				'id'  => $product_id,
				'sku' => $sku,
			);
		}

		wp_send_json_success( array(
			'created'   => count( $created ),
			'products'  => $created,
			'print_url' => admin_url( 'admin.php?page=wcbp-print-queue' ),
		) );
	}
}
