<?php
defined( 'ABSPATH' ) || exit;

/**
 * Auto-generates SKU codes for simple products and variations.
 *
 * Generation strategy (configurable):
 *   'id'         → PREFIX + zero-padded post ID   e.g. SKU-00042
 *   'sequential' → PREFIX + zero-padded counter   e.g. SKU-00001
 */
class WSBG_SKU_Generator {

	private const SEQ_OPTION = 'wsbg_sku_counter';

	public static function init(): void {
		add_action( 'woocommerce_new_product',       [ __CLASS__, 'maybe_assign_sku' ], 20 );
		add_action( 'woocommerce_update_product',    [ __CLASS__, 'maybe_assign_sku' ], 20 );
		add_action( 'woocommerce_new_product_variation',    [ __CLASS__, 'maybe_assign_variation_sku' ], 20, 2 );
		add_action( 'woocommerce_update_product_variation', [ __CLASS__, 'maybe_assign_variation_sku' ], 20, 2 );

		// Bulk AJAX regeneration
		add_action( 'wp_ajax_wsbg_bulk_regen_sku', [ __CLASS__, 'ajax_bulk_regen' ] );
	}

	// ── Per-product hook ──────────────────────────────────────────────────────

	public static function maybe_assign_sku( int $product_id ): void {
		if ( ! WSBG_Settings::get( 'auto_sku' ) ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->get_sku() !== '' ) {
			return;
		}
		$sku = self::generate( $product_id );
		$product->set_sku( $sku );
		$product->save();
	}

	public static function maybe_assign_variation_sku( int $variation_id, int $product_id ): void {
		if ( ! WSBG_Settings::get( 'auto_sku' ) ) {
			return;
		}
		$variation = wc_get_product( $variation_id );
		if ( ! $variation || $variation->get_sku() !== '' ) {
			return;
		}
		// Variation SKU = parent SKU + '-V' + variation_id
		$parent  = wc_get_product( $product_id );
		$base    = $parent ? $parent->get_sku() : self::generate( $product_id );
		$sku     = $base . '-V' . $variation_id;
		$variation->set_sku( $sku );
		$variation->save();
	}

	// ── Public generator ──────────────────────────────────────────────────────

	public static function generate( int $post_id ): string {
		$prefix  = WSBG_Settings::get( 'sku_prefix' );
		$padding = (int) WSBG_Settings::get( 'sku_padding' );
		$source  = WSBG_Settings::get( 'sku_source' );

		if ( $source === 'sequential' ) {
			$n = (int) get_option( self::SEQ_OPTION, 0 ) + 1;
			update_option( self::SEQ_OPTION, $n );
			return $prefix . str_pad( (string) $n, $padding, '0', STR_PAD_LEFT );
		}

		return $prefix . str_pad( (string) $post_id, $padding, '0', STR_PAD_LEFT );
	}

	// ── Bulk regeneration ─────────────────────────────────────────────────────

	public static function ajax_bulk_regen(): void {
		check_ajax_referer( 'wsbg_bulk_regen', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$overwrite = ! empty( $_POST['overwrite'] );
		$ids       = wc_get_products( [ 'return' => 'ids', 'limit' => -1, 'status' => 'publish' ] );
		$count     = 0;

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			if ( $product->get_sku() === '' || $overwrite ) {
				$product->set_sku( self::generate( $id ) );
				$product->save();
				$count++;
			}
			// Variations
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v && ( $v->get_sku() === '' || $overwrite ) ) {
						$v->set_sku( $product->get_sku() . '-V' . $vid );
						$v->save();
						$count++;
					}
				}
			}
		}

		wp_send_json_success( [ 'updated' => $count ] );
	}
}
