<?php
/**
 * Plugin settings page.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class Settings {

	private static ?Settings $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting( 'wcbp_settings_group', 'wcbp_settings', array(
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
			'default'           => \WCBarcodePro\Plugin::get_default_settings(),
		) );
	}

	public function sanitize_settings( array $input ): array {
		$valid_symbologies = array( 'code128', 'ean13', 'qr', 'upca', 'itf14' );
		$valid_statuses    = array_keys( wc_get_order_statuses() );

		$clean = array();
		$clean['symbology']           = in_array( $input['symbology'] ?? 'code128', $valid_symbologies, true ) ? $input['symbology'] : 'code128';
		$clean['prefix']              = substr( sanitize_text_field( $input['prefix'] ?? 'WBP-' ), 0, 20 );
		$clean['auto_sku']            = ! empty( $input['auto_sku'] );
		$clean['prefer_ean']          = ! empty( $input['prefer_ean'] );
		$clean['show_single']         = ! empty( $input['show_single'] );
		$clean['show_loop']           = ! empty( $input['show_loop'] );
		$clean['show_text']           = ! empty( $input['show_text'] );
		$clean['order_queue_enabled'] = ! empty( $input['order_queue_enabled'] );
		$clean['barcode_height']      = max( 20, min( 200, (int) ( $input['barcode_height'] ?? 60 ) ) );
		$clean['module_width']        = max( 1, min( 5, (int) ( $input['module_width'] ?? 2 ) ) );

		$raw_status = 'wc-' . ltrim( $input['order_queue_status'] ?? 'processing', 'wc-' );
		$clean['order_queue_status'] = in_array( $raw_status, $valid_statuses, true )
			? ltrim( $raw_status, 'wc-' )
			: 'processing';

		return $clean;
	}

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-barcode-pro' ) );
		}
		$settings = \WCBarcodePro\wcbp_settings();
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'basic'; // phpcs:ignore WordPress.Security
		include WCBP_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	public function get_symbology_options(): array {
		return array(
			'code128' => __( 'Code 128 (recommended — all characters)', 'woo-barcode-pro' ),
			'ean13'   => __( 'EAN-13 (retail barcodes, 12-digit numeric)', 'woo-barcode-pro' ),
			'qr'      => __( 'QR Code (URLs and long text)', 'woo-barcode-pro' ),
			'upca'    => __( 'UPC-A (North American retail, 11-digit numeric)', 'woo-barcode-pro' ),
			'itf14'   => __( 'ITF-14 (shipping/carton, 14-digit numeric)', 'woo-barcode-pro' ),
		);
	}
}
