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

	public const SIZES = array( 'S', 'M', 'L', 'XL', 'XXL' );

	// Existing "Size" terms are reused when their name or slug matches one of these.
	private const SIZE_ALIASES = array(
		'S'   => array( 's', 'small', 'sm' ),
		'M'   => array( 'm', 'medium', 'med' ),
		'L'   => array( 'l', 'large', 'lg' ),
		'XL'  => array( 'xl', 'x-large', 'extra-large', 'extra large', 'xlarge' ),
		'XXL' => array( 'xxl', '2xl', 'xx-large', 'xxlarge', '2x-large', 'xx large', '2x' ),
	);

	private static ?QuickAdd $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'init',       array( $this, 'maybe_serve_pwa_asset' ) );
		add_action( 'admin_init', array( $this, 'maybe_intercept_quick_add' ) );
		add_action( 'wp_ajax_wcbp_quick_save_product', array( $this, 'ajax_save_product' ) );
		add_action( 'wp_ajax_wcbp_quick_upload_image',  array( $this, 'ajax_upload_image' ) );
		add_action( 'wp_ajax_wcbp_qa_get_drafts',       array( $this, 'ajax_get_drafts' ) );
	}

	public function maybe_intercept_quick_add(): void {
		if ( ! isset( $_GET['page'] ) || 'wcbp-quick-add' !== $_GET['page'] ) {
			return;
		}
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		$this->render_page();
		exit;
	}

	public function maybe_serve_pwa_asset(): void {
		if ( empty( $_GET['wcbp_pwa'] ) ) {
			return;
		}
		$type = sanitize_key( $_GET['wcbp_pwa'] );
		if ( 'manifest' === $type ) {
			$this->serve_manifest();
		} elseif ( 'icon' === $type ) {
			$size = max( 16, min( 512, (int) ( $_GET['s'] ?? 192 ) ) );
			$this->serve_icon( $size );
		}
	}

	private function serve_manifest(): void {
		$start_url = admin_url( 'admin.php?page=wcbp-quick-add' );
		$icon_base = home_url( '/?wcbp_pwa=icon' );
		$manifest  = array(
			'name'             => 'WooBarcode Quick Add',
			'short_name'       => 'Quick Add',
			'description'      => 'Add products and scan barcodes from your phone.',
			'start_url'        => $start_url,
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'theme_color'      => '#2271b1',
			'background_color' => '#1d2327',
			'icons'            => array(
				array(
					'src'     => $icon_base . '&s=192',
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				),
				array(
					'src'     => $icon_base . '&s=512',
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				),
			),
		);
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: max-age=3600' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		echo json_encode( $manifest );
		exit;
	}

	private function serve_icon( int $size ): void {
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: max-age=86400' );

		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			// GD unavailable — output a 1×1 transparent PNG as fallback.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			echo base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' );
			exit;
		}

		$img   = imagecreatetruecolor( $size, $size );
		$bg    = imagecolorallocate( $img, 0x22, 0x71, 0xb1 ); // #2271b1 blue
		$white = imagecolorallocate( $img, 255, 255, 255 );
		imagefilledrectangle( $img, 0, 0, $size - 1, $size - 1, $bg );

		// Barcode bars — relative widths: bar, gap, bar, gap, ...
		$bars  = array( 18, 10, 28, 10, 18, 10, 36, 10, 18, 28, 10, 18, 34 );
		$gaps  = array(  0,  8,  0,  8,  0,  8,  0,  8,  0,  0,  8,  0,  0 );
		$pad   = (int) round( $size * 0.14 );
		$bh    = (int) round( $size * 0.52 );
		$by    = (int) round( $size * 0.20 );
		$bw    = $size - $pad * 2;

		// Build bar segments: alternating filled and gap, derived from SVG x-positions.
		$svg_bars = array(
			array( 72, 18 ), array( 100, 10 ), array( 120, 28 ), array( 158, 10 ),
			array( 178, 18 ), array( 206, 10 ), array( 226, 36 ), array( 272, 10 ),
			array( 292, 18 ), array( 320, 28 ), array( 358, 10 ), array( 378, 18 ),
			array( 406, 34 ),
		);
		$svg_total = 512.0;
		foreach ( $svg_bars as $bar ) {
			$bx1 = $pad + (int) round( ( $bar[0] / $svg_total ) * $bw );
			$bx2 = $pad + (int) round( ( ( $bar[0] + $bar[1] ) / $svg_total ) * $bw ) - 1;
			imagefilledrectangle( $img, $bx1, $by, $bx2, $by + $bh - 1, $white );
		}

		imagepng( $img );
		imagedestroy( $img );
		exit;
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
		$image_ids    = array_filter( array_map( 'intval', (array) ( $_POST['image_ids'] ?? array() ) ) );
		$sku          = sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) );
		$stock_qty    = max( 0, (int) ( $_POST['stock_qty'] ?? 1 ) );
		$attributes   = (array) ( $_POST['attributes'] ?? array() );
		$label_tpl_id = (int) ( $_POST['label_template_id'] ?? 0 );

		if ( ! $name ) {
			wp_send_json_error( array( 'message' => __( 'Product name is required.', 'woo-barcode-pro' ) ) );
		}

		if ( ! empty( $_POST['has_sizes'] ) ) {
			$this->save_sized_product( $name, $price, $category_ids, $tag_ids, $image_ids, $sku, (array) ( $_POST['sizes'] ?? array() ), $label_tpl_id );
			return;
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
		if ( ! empty( $image_ids ) ) {
			$image_ids = array_values( $image_ids );
			$product->set_image_id( $image_ids[0] );
			if ( count( $image_ids ) > 1 ) {
				$product->set_gallery_image_ids( array_slice( $image_ids, 1 ) );
			}
		}

		// Stock.
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $stock_qty );
		$product->set_stock_status( $stock_qty > 0 ? 'instock' : 'outofstock' );
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

		// Persist label template so future queue additions (buttons, bulk) use the same layout.
		if ( $label_tpl_id ) {
			update_post_meta( $product_id, '_wcbp_label_template_id', $label_tpl_id );
		}

		// Add to print queue — one label per unit. Clear first to avoid phantom merges.
		PrintQueue::get_instance()->remove_by_product( $product_id );
		PrintQueue::get_instance()->add( $product_id, max( 1, $stock_qty ), 0, $label_tpl_id );

		wp_send_json_success( array(
			'product_id' => $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'message'    => __( 'Product created and added to print queue.', 'woo-barcode-pro' ),
		) );
	}

	/**
	 * Variable product with one variation per size — or per size + color — that has units; labels queued per variation.
	 *
	 * @param array $sizes [ 'S' => [ 'qty' => 2, 'price' => '', 'colors' => [ [ 'name' => 'Red', 'qty' => 1, 'price' => '' ] ] ], ... ]
	 */
	private function save_sized_product( string $name, float $price, array $category_ids, array $tag_ids, array $image_ids, string $sku, array $sizes, int $label_tpl_id ): void {
		$lines = $this->size_lines( $sizes, $price );
		if ( is_wp_error( $lines ) ) {
			wp_send_json_error( array( 'message' => $lines->get_error_message() ) );
		}

		$size_wanted  = array();
		$color_wanted = array();
		foreach ( $lines as $line ) {
			$size_wanted[ $line['size'] ] = array( 'name' => $line['size'], 'aliases' => self::SIZE_ALIASES[ $line['size'] ] );
			if ( '' !== $line['color'] && ! isset( $color_wanted[ strtolower( $line['color'] ) ] ) ) {
				$color_wanted[ strtolower( $line['color'] ) ] = array(
					'name'    => $line['color'],
					'aliases' => array( strtolower( $line['color'] ), sanitize_title( $line['color'] ) ),
				);
			}
		}

		$size = $this->global_attribute( array( 'size' ), 'Size', $size_wanted );
		if ( is_wp_error( $size ) ) {
			wp_send_json_error( array( 'message' => $size->get_error_message() ) );
		}
		$color = null;
		if ( $color_wanted ) {
			$color = $this->global_attribute( array( 'color', 'colour' ), 'Color', $color_wanted );
			if ( is_wp_error( $color ) ) {
				wp_send_json_error( array( 'message' => $color->get_error_message() ) );
			}
		}

		$product = new \WC_Product_Variable();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_manage_stock( false ); // Stock lives on each variation.
		if ( $sku ) {
			try {
				$product->set_sku( $sku );
			} catch ( \WC_Data_Exception $e ) {
				wp_send_json_error( array( 'message' => $e->getMessage() ) );
			}
		}
		if ( $image_ids ) {
			$image_ids = array_values( $image_ids );
			$product->set_image_id( $image_ids[0] );
			if ( count( $image_ids ) > 1 ) {
				$product->set_gallery_image_ids( array_slice( $image_ids, 1 ) );
			}
		}
		if ( $category_ids ) {
			$product->set_category_ids( $category_ids );
		}
		if ( $tag_ids ) {
			$product->set_tag_ids( $tag_ids );
		}

		// Size first, so labels read "M / Red" and the size survives on narrow labels.
		$attrs = array( $this->variation_attribute( $size ) );
		if ( $color ) {
			$attrs[] = $this->variation_attribute( $color );
		}
		$product->set_attributes( $attrs );

		$product_id = $product->save();
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create product.', 'woo-barcode-pro' ) ) );
		}

		$settings = \WCBarcodePro\wcbp_settings();
		if ( $settings['auto_sku'] && ! $sku ) {
			\WCBarcodePro\Barcode\SkuManager::get_instance()->auto_generate_sku( $product_id );
		}

		// No SKU here: queuing below gives each variation prefix + ID (e.g. YKS-4141). Longer SKUs such as
		// YKS-4140-M-RED don't fit a scannable barcode on small (1.4") labels.
		foreach ( $lines as $line ) {
			$values = array( $size['taxonomy'] => $size['terms'][ $line['size'] ]['slug'] );
			if ( $color ) {
				$values[ $color['taxonomy'] ] = $color['terms'][ strtolower( $line['color'] ) ]['slug'];
			}
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( $values );
			$variation->set_regular_price( (string) $line['price'] );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( $line['qty'] );
			$variation->set_stock_status( 'instock' );
			$variation->save();
		}
		\WC_Product_Variable::sync( $product_id );
		wc_delete_product_transients( $product_id );

		if ( $label_tpl_id ) {
			update_post_meta( $product_id, '_wcbp_label_template_id', $label_tpl_id );
		}

		$queue = PrintQueue::get_instance();
		$queue->remove_by_product( $product_id );
		$result = $queue->add_variations( $product_id, $queue->get_variations( $product_id ), true, 1, $label_tpl_id );

		wp_send_json_success( array(
			'product_id' => $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'message'    => sprintf(
				/* translators: 1: number of variations, 2: number of labels */
				__( 'Product created with %1$d variations; %2$d labels added to print queue.', 'woo-barcode-pro' ),
				$result['rows'],
				$result['labels']
			),
		) );
	}

	/**
	 * Posted size rows → one line per variation to create: [ size, color, qty, price ].
	 * Lines with 0 units are dropped; a color typed twice for the same size is merged.
	 *
	 * @return array|\WP_Error
	 */
	private function size_lines( array $sizes, float $price ) {
		$lines = array();
		foreach ( self::SIZES as $code ) {
			$row     = (array) ( $sizes[ $code ] ?? array() );
			$colors  = array_filter( (array) ( $row['colors'] ?? array() ), 'is_array' );
			$entries = $colors ? $colors : array( array( 'name' => '', 'qty' => $row['qty'] ?? 0, 'price' => $row['price'] ?? '' ) );
			foreach ( $entries as $entry ) {
				$qty = max( 0, (int) ( $entry['qty'] ?? 0 ) );
				if ( ! $qty ) {
					continue;
				}
				$color = trim( sanitize_text_field( wp_unslash( (string) ( $entry['name'] ?? '' ) ) ) );
				$key   = $code . '|' . strtolower( $color );
				if ( isset( $lines[ $key ] ) ) {
					$lines[ $key ]['qty'] += $qty;
					continue;
				}
				$line_price    = trim( (string) ( $entry['price'] ?? '' ) );
				$lines[ $key ] = array(
					'size'  => $code,
					'color' => $color,
					'qty'   => $qty,
					'price' => '' !== $line_price ? (float) $line_price : $price,
				);
			}
		}
		if ( ! $lines ) {
			return new \WP_Error( 'wcbp_no_units', __( 'Enter units for at least one size.', 'woo-barcode-pro' ) );
		}

		// Mixing colored and uncolored lines would create "any color" variations.
		$uncolored = array_filter( $lines, static fn( $l ) => '' === $l['color'] );
		if ( $uncolored && count( $uncolored ) !== count( $lines ) ) {
			return new \WP_Error(
				'wcbp_color_missing',
				/* translators: %s: list of sizes, e.g. "S, XL" */
				sprintf( __( 'Add a color for %s, or remove the colors from every size.', 'woo-barcode-pro' ), implode( ', ', array_unique( array_column( $uncolored, 'size' ) ) ) )
			);
		}
		return array_values( $lines );
	}

	private function variation_attribute( array $attribute ): \WC_Product_Attribute {
		$attr = new \WC_Product_Attribute();
		$attr->set_id( $attribute['attribute_id'] );
		$attr->set_name( $attribute['taxonomy'] );
		$attr->set_options( array_map( static fn( $t ) => $t['term_id'], array_values( $attribute['terms'] ) ) );
		$attr->set_visible( true );
		$attr->set_variation( true );
		return $attr;
	}

	/**
	 * A global attribute (created if missing) with a term for each wanted value, reusing existing
	 * terms whose name or slug matches (so "Medium" or "red" already in the store are not duplicated).
	 *
	 * @param string[] $names  Attribute slugs/labels that count as this attribute; the first is used when creating it.
	 * @param array    $wanted key => [ 'name' => term name if created, 'aliases' => lowercase names/slugs that match ]
	 * @return array{attribute_id:int,taxonomy:string,terms:array}|\WP_Error
	 */
	private function global_attribute( array $names, string $label, array $wanted ) {
		$attribute_id = 0;
		$slug         = $names[0];
		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			if ( in_array( strtolower( $tax->attribute_name ), $names, true ) || in_array( strtolower( $tax->attribute_label ), $names, true ) ) {
				$attribute_id = (int) $tax->attribute_id;
				$slug         = $tax->attribute_name;
				break;
			}
		}
		if ( ! $attribute_id ) {
			$attribute_id = wc_create_attribute( array( 'name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order' ) );
			if ( is_wp_error( $attribute_id ) ) {
				return $attribute_id;
			}
		}

		$taxonomy = wc_attribute_taxonomy_name( $slug );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			// WooCommerce registers attribute taxonomies on init; one created this request isn't registered yet.
			register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ) );
		}

		$existing = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		$existing = is_wp_error( $existing ) ? array() : $existing;

		$terms = array();
		foreach ( $wanted as $key => $want ) {
			$match = null;
			foreach ( $existing as $term ) {
				if ( in_array( strtolower( $term->name ), $want['aliases'], true ) || in_array( strtolower( $term->slug ), $want['aliases'], true ) ) {
					$match = $term;
					break;
				}
			}
			if ( ! $match ) {
				$inserted = wp_insert_term( $want['name'], $taxonomy, array( 'slug' => sanitize_title( $want['name'] ) ) );
				if ( is_wp_error( $inserted ) ) {
					return $inserted;
				}
				$match    = get_term( (int) $inserted['term_id'], $taxonomy );
				$existing[] = $match;
			}
			$terms[ $key ] = array( 'term_id' => (int) $match->term_id, 'slug' => $match->slug );
		}

		return array( 'attribute_id' => (int) $attribute_id, 'taxonomy' => $taxonomy, 'terms' => $terms );
	}

	public function ajax_get_drafts(): void {
		check_ajax_referer( 'wcbp_quick_add', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}

		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'draft',
			'posts_per_page' => 40,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		) );

		$drafts = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$drafts[] = array(
				'id'    => $id,
				'name'  => $product->get_name(),
				'sku'   => $product->get_sku(),
				'price' => $product->get_regular_price(),
				'stock' => (int) $product->get_stock_quantity(),
			);
		}

		wp_send_json_success( array( 'drafts' => $drafts ) );
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
