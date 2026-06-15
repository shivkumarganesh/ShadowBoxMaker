<?php
defined( 'ABSPATH' ) || exit;

class WSBG_Settings {

	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
	}

	public static function defaults(): array {
		return [
			'sku_prefix'          => 'SKU-',
			'sku_padding'         => 5,          // zero-pad length
			'sku_source'          => 'id',        // 'id' | 'sequential'
			'auto_sku'            => true,
			'barcode_format'      => 'code128',   // 'code128' | 'ean13'
			'show_barcode_product'=> true,
			'show_barcode_shop'   => false,
			'print_label_preset'  => 'avery5160', // preset key
			'print_label_layout'  => 'horizontal',
			'label_show_name'     => true,
			'label_show_price'    => true,
			'label_show_sku'      => true,
		];
	}

	public static function get( string $key = '' ) {
		$opts = get_option( WSBG_OPTION, self::defaults() );
		if ( $key === '' ) {
			return $opts;
		}
		return $opts[ $key ] ?? ( self::defaults()[ $key ] ?? null );
	}

	public static function register(): void {
		register_setting( WSBG_SLUG, WSBG_OPTION, [
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
		] );
	}

	public static function sanitize( array $raw ): array {
		$d = self::defaults();
		return [
			'sku_prefix'           => sanitize_text_field( $raw['sku_prefix']           ?? $d['sku_prefix'] ),
			'sku_padding'          => absint( $raw['sku_padding']                        ?? $d['sku_padding'] ),
			'sku_source'           => in_array( $raw['sku_source'] ?? '', ['id','sequential'], true ) ? $raw['sku_source'] : $d['sku_source'],
			'auto_sku'             => ! empty( $raw['auto_sku'] ),
			'barcode_format'       => in_array( $raw['barcode_format'] ?? '', ['code128','ean13'], true ) ? $raw['barcode_format'] : $d['barcode_format'],
			'show_barcode_product' => ! empty( $raw['show_barcode_product'] ),
			'show_barcode_shop'    => ! empty( $raw['show_barcode_shop'] ),
			'print_label_preset'   => sanitize_key( $raw['print_label_preset']          ?? $d['print_label_preset'] ),
			'print_label_layout'   => in_array( $raw['print_label_layout'] ?? '', ['horizontal','vertical'], true ) ? $raw['print_label_layout'] : $d['print_label_layout'],
			'label_show_name'      => ! empty( $raw['label_show_name'] ),
			'label_show_price'     => ! empty( $raw['label_show_price'] ),
			'label_show_sku'       => ! empty( $raw['label_show_sku'] ),
		];
	}
}
