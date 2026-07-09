<?php
/**
 * Global helper functions.
 *
 * @package WCBarcodePro
 */

namespace WCBarcodePro;

defined( 'ABSPATH' ) || exit;

function wcbp_settings(): array {
	$saved    = get_option( 'wcbp_settings', array() );
	$defaults = Plugin::get_default_settings();
	return wp_parse_args( $saved, $defaults );
}

function wcbp_get_setting( string $key, $default = null ) {
	$settings = wcbp_settings();
	return $settings[ $key ] ?? $default;
}

function wcbp_current_user_can_manage(): bool {
	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

function wcbp_verify_nonce( string $nonce_value, string $action ): void {
	if ( ! wp_verify_nonce( $nonce_value, $action ) ) {
		wp_die( esc_html__( 'Security check failed.', 'woo-barcode-pro' ), 403 );
	}
}

function wcbp_barcode_value( int $product_id, int $variation_id = 0 ): string {
	$id = $variation_id > 0 ? $variation_id : $product_id;

	// 1. EAN meta.
	$ean = get_post_meta( $id, Barcode\EanManager::META_KEY, true );
	if ( $ean && wcbp_get_setting( 'prefer_ean' ) ) {
		return (string) $ean;
	}

	// 2. SKU.
	$sku = get_post_meta( $id, '_sku', true );
	if ( $sku ) {
		return (string) $sku;
	}

	// 3. EAN even without preference (if set and no SKU).
	if ( $ean ) {
		return (string) $ean;
	}

	// 4. Auto-generate SKU.
	if ( wcbp_get_setting( 'auto_sku' ) ) {
		return Barcode\SkuManager::get_instance()->auto_generate_sku( $product_id, $variation_id );
	}

	return '';
}

function wcbp_product_barcode_svg( int $product_id, int $variation_id = 0, array $opts = array() ): string {
	$value = wcbp_barcode_value( $product_id, $variation_id );
	if ( '' === $value ) {
		return '';
	}

	$settings         = wcbp_settings();
	$opts['show_text'] = $opts['show_text'] ?? (bool) $settings['show_text'];
	$opts['height']    = $opts['height']    ?? (int) $settings['barcode_height'];
	$opts['module_width'] = $opts['module_width'] ?? (int) $settings['module_width'];

	$symbology = $opts['symbology'] ?? $settings['symbology'];

	return Barcode\BarcodeGenerator::get_instance()->generate_svg( $value, $symbology, $opts );
}
