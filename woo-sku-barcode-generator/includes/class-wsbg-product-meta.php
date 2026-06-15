<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds barcode preview + EAN field to the product edit page.
 * Also injects barcodes on the front-end single product / shop pages.
 */
class WSBG_Product_Meta {

	public static function init(): void {
		// Admin metabox
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_metabox' ] );

		// Variation EAN field
		add_action( 'woocommerce_product_after_variable_attributes', [ __CLASS__, 'variation_ean_field' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation',            [ __CLASS__, 'save_variation_ean' ], 10, 2 );

		// Front-end display
		if ( WSBG_Settings::get( 'show_barcode_product' ) ) {
			add_action( 'woocommerce_product_meta_end', [ __CLASS__, 'render_frontend_barcode' ] );
		}
		if ( WSBG_Settings::get( 'show_barcode_shop' ) ) {
			add_action( 'woocommerce_after_shop_loop_item_title', [ __CLASS__, 'render_frontend_barcode' ] );
		}

		// Admin product list column
		add_filter( 'manage_edit-product_columns',         [ __CLASS__, 'add_column' ] );
		add_action( 'manage_product_posts_custom_column',  [ __CLASS__, 'render_column' ], 10, 2 );
	}

	// ── Admin metabox ─────────────────────────────────────────────────────────

	public static function add_metabox(): void {
		add_meta_box(
			'wsbg_barcode',
			__( 'SKU & Barcode', 'wsbg' ),
			[ __CLASS__, 'render_metabox' ],
			'product',
			'side',
			'default'
		);
	}

	public static function render_metabox( \WP_Post $post ): void {
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}
		$sku = $product->get_sku();
		$svg = WSBG_Barcode::svg_for_product( $product );
		echo '<p><strong>' . esc_html__( 'SKU:', 'wsbg' ) . '</strong> ' . esc_html( $sku ?: __( '(none)', 'wsbg' ) ) . '</p>';
		echo '<div class="wsbg-barcode-preview" style="max-width:100%;overflow:hidden;">';
		echo $svg; // SVG is generated server-side; no user input
		echo '</div>';
		$nonce = wp_create_nonce( 'wsbg_queue' );
		printf(
			'<p><a href="#" class="button wsbg-add-to-queue" data-id="%d" data-nonce="%s">%s</a></p>',
			esc_attr( $post->ID ),
			esc_attr( $nonce ),
			esc_html__( 'Add to Print Queue', 'wsbg' )
		);
	}

	// ── Variation EAN ─────────────────────────────────────────────────────────

	public static function variation_ean_field( int $loop, array $variation_data, \WP_Post $variation ): void {
		$ean = get_post_meta( $variation->ID, '_wsbg_ean', true );
		woocommerce_wp_text_input( [
			'id'            => "_wsbg_ean_{$variation->ID}",
			'name'          => "_wsbg_ean[{$variation->ID}]",
			'value'         => esc_attr( $ean ),
			'label'         => __( 'EAN-13', 'wsbg' ),
			'placeholder'   => '0000000000000',
			'desc_tip'      => true,
			'description'   => __( '13-digit EAN barcode (check digit auto-calculated if omitted).', 'wsbg' ),
			'wrapper_class' => 'form-row form-row-full',
		] );
	}

	public static function save_variation_ean( int $variation_id, int $loop ): void {
		if ( isset( $_POST['_wsbg_ean'][ $variation_id ] ) ) {
			$raw = sanitize_text_field( $_POST['_wsbg_ean'][ $variation_id ] );
			update_post_meta( $variation_id, '_wsbg_ean', $raw );
		}
	}

	// ── Front-end barcode ─────────────────────────────────────────────────────

	public static function render_frontend_barcode(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$svg = WSBG_Barcode::svg_for_product( $product );
		echo '<div class="wsbg-barcode" style="margin:8px 0;">' . $svg . '</div>';
	}

	// ── Admin product list column ──────────────────────────────────────────────

	public static function add_column( array $columns ): array {
		$columns['wsbg_barcode'] = __( 'Barcode', 'wsbg' );
		return $columns;
	}

	public static function render_column( string $column, int $post_id ): void {
		if ( $column !== 'wsbg_barcode' ) {
			return;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}
		$svg   = WSBG_Barcode::svg_for_product( $product );
		$nonce = wp_create_nonce( 'wsbg_queue' );
		echo '<div style="max-width:120px;">' . $svg . '</div>';
		printf(
			'<a href="#" class="wsbg-add-to-queue" data-id="%d" data-nonce="%s" title="%s">&#x1F5B6;</a>',
			esc_attr( $post_id ),
			esc_attr( $nonce ),
			esc_attr__( 'Add to Print Queue', 'wsbg' )
		);
	}
}
