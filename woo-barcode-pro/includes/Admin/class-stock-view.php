<?php
/**
 * Mobile-optimised Stock View PWA page.
 *
 * Shows all published products with name, thumbnail, and stock count.
 * Supports category-pill filtering and name search.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class StockView {

	private static ?StockView $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'init',       array( $this, 'maybe_serve_pwa_asset' ) );
		add_action( 'admin_init', array( $this, 'maybe_intercept_stock_view' ) );
		add_action( 'wp_ajax_wcbp_sv_get_products', array( $this, 'ajax_get_products' ) );
	}

	public function maybe_intercept_stock_view(): void {
		if ( ! isset( $_GET['page'] ) || 'wcbp-stock-view' !== $_GET['page'] ) {
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
		if ( 'sv_manifest' === $type ) {
			$this->serve_manifest();
		} elseif ( 'sv_icon' === $type ) {
			$size = max( 16, min( 512, (int) ( $_GET['s'] ?? 192 ) ) );
			$this->serve_icon( $size );
		}
	}

	private function serve_manifest(): void {
		$start_url = admin_url( 'admin.php?page=wcbp-stock-view' );
		$icon_base = home_url( '/?wcbp_pwa=sv_icon' );
		$manifest  = array(
			'name'             => 'WooBarcode Stock View',
			'short_name'       => 'Stock View',
			'description'      => 'Check your product inventory at a glance.',
			'start_url'        => $start_url,
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'theme_color'      => '#1a7f37',
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
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			echo base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' );
			exit;
		}

		$img   = imagecreatetruecolor( $size, $size );
		$bg    = imagecolorallocate( $img, 0x1a, 0x7f, 0x37 ); // #1a7f37 green
		$white = imagecolorallocate( $img, 255, 255, 255 );
		imagefilledrectangle( $img, 0, 0, $size - 1, $size - 1, $bg );

		// Shelf icon: two vertical supports + three horizontal shelves.
		$pad      = (int) round( $size * 0.16 );
		$w        = $size - $pad * 2;
		$sw       = max( 2, (int) round( $size * 0.05 ) ); // support width
		$sh       = max( 2, (int) round( $size * 0.05 ) ); // shelf height
		$top      = (int) round( $size * 0.22 );
		$bottom   = (int) round( $size * 0.82 );

		// Vertical supports.
		imagefilledrectangle( $img, $pad,             $top, $pad + $sw,         $bottom, $white );
		imagefilledrectangle( $img, $pad + $w - $sw,  $top, $pad + $w,          $bottom, $white );

		// Three shelves.
		$shelves = array(
			(int) round( $size * 0.38 ),
			(int) round( $size * 0.56 ),
			(int) round( $size * 0.74 ),
		);
		foreach ( $shelves as $sy ) {
			imagefilledrectangle( $img, $pad, $sy, $pad + $w, $sy + $sh, $white );
		}

		imagepng( $img );
		imagedestroy( $img );
		exit;
	}

	public function ajax_get_products(): void {
		check_ajax_referer( 'wcbp_stock_view', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-barcode-pro' ) ) );
		}

		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		) );

		$products    = array();
		$all_cat_ids = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}

			$cat_ids = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $cat_ids ) ) {
				$cat_ids = array();
			}
			$all_cat_ids = array_merge( $all_cat_ids, $cat_ids );

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $var_id ) {
					$variation = wc_get_product( $var_id );
					if ( ! $variation ) {
						continue;
					}
					$thumb_id = get_post_thumbnail_id( $var_id ) ?: get_post_thumbnail_id( $id );
					$products[] = array(
						'id'         => $var_id,
						'name'       => $product->get_name() . ' — ' . wc_get_formatted_variation( $variation, true ),
						'thumb'      => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
						'stock'      => $variation->get_manage_stock() ? (int) $variation->get_stock_quantity() : null,
						'categories' => $cat_ids,
					);
				}
				continue;
			}

			$thumb_id = get_post_thumbnail_id( $id );
			$products[] = array(
				'id'         => $id,
				'name'       => $product->get_name(),
				'thumb'      => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
				'stock'      => $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null,
				'categories' => $cat_ids,
			);
		}

		// Build category list from those actually used.
		$categories = array();
		$unique_cat_ids = array_unique( $all_cat_ids );
		if ( ! empty( $unique_cat_ids ) ) {
			$terms = get_terms( array(
				'taxonomy'   => 'product_cat',
				'include'    => $unique_cat_ids,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[] = array( 'id' => $term->term_id, 'name' => $term->name );
				}
			}
		}

		wp_send_json_success( array( 'products' => $products, 'categories' => $categories ) );
	}

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		include WCBP_PLUGIN_DIR . 'templates/admin/stock-view.php';
	}
}
