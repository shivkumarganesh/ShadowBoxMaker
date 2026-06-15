<?php
defined( 'ABSPATH' ) || exit;

/**
 * Print queue: stores product IDs + quantities and renders a print sheet.
 */
class WSBG_Print_Queue {

	private const OPT = 'wsbg_queue';

	public static function init(): void {
		add_action( 'wp_ajax_wsbg_queue_add',    [ __CLASS__, 'ajax_add' ] );
		add_action( 'wp_ajax_wsbg_queue_remove', [ __CLASS__, 'ajax_remove' ] );
		add_action( 'wp_ajax_wsbg_queue_clear',  [ __CLASS__, 'ajax_clear' ] );

		// Render the print page when ?wsbg_print=1
		add_action( 'admin_init', [ __CLASS__, 'maybe_render_print' ] );

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'wp_enqueue_scripts',    [ __CLASS__, 'enqueue_front' ] );
	}

	// ── Queue helpers ─────────────────────────────────────────────────────────

	private static function get_queue(): array {
		return (array) get_option( self::OPT, [] );
	}

	private static function save_queue( array $q ): void {
		update_option( self::OPT, $q );
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public static function ajax_add(): void {
		check_ajax_referer( 'wsbg_queue', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		$id  = absint( $_POST['id'] ?? 0 );
		$qty = max( 1, absint( $_POST['qty'] ?? 1 ) );
		if ( ! $id ) {
			wp_send_json_error( 'Invalid product ID.' );
		}
		$q       = self::get_queue();
		$q[ $id ] = ( $q[ $id ] ?? 0 ) + $qty;
		self::save_queue( $q );
		wp_send_json_success( [ 'count' => array_sum( $q ) ] );
	}

	public static function ajax_remove(): void {
		check_ajax_referer( 'wsbg_queue', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		$id = absint( $_POST['id'] ?? 0 );
		$q  = self::get_queue();
		unset( $q[ $id ] );
		self::save_queue( $q );
		wp_send_json_success( [ 'count' => array_sum( $q ) ] );
	}

	public static function ajax_clear(): void {
		check_ajax_referer( 'wsbg_queue', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		self::save_queue( [] );
		wp_send_json_success();
	}

	// ── Print page ────────────────────────────────────────────────────────────

	public static function maybe_render_print(): void {
		if ( empty( $_GET['wsbg_print'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Permission denied.' );
		}
		check_admin_referer( 'wsbg_print' );

		$queue   = self::get_queue();
		$preset  = WSBG_Settings::get( 'print_label_preset' );
		$layout  = WSBG_Settings::get( 'print_label_layout' );
		$presets = self::label_presets();
		$cfg     = $presets[ $preset ] ?? $presets['avery5160'];

		include WSBG_DIR . 'templates/print-sheet.php';
		exit;
	}

	public static function label_presets(): array {
		return [
			'avery5160' => [ 'cols' => 3, 'rows' => 10, 'width' => '2.625in', 'height' => '1in',    'label' => 'Avery 5160 / 8160 / 5260' ],
			'a4_65up'   => [ 'cols' => 5, 'rows' => 13, 'width' => '38.1mm',  'height' => '21.2mm', 'label' => 'A4 65-Up' ],
			'custom'    => [ 'cols' => 2, 'rows' => 5,  'width' => '3in',     'height' => '1.5in',  'label' => 'Custom' ],
		];
	}

	// ── Scripts / styles ──────────────────────────────────────────────────────

	public static function enqueue( string $hook ): void {
		wp_enqueue_script(
			'wsbg-admin',
			WSBG_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WSBG_VERSION,
			true
		);
		wp_localize_script( 'wsbg-admin', 'wsbgAdmin', [
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'printUrl'  => wp_nonce_url( admin_url( 'admin.php?wsbg_print=1' ), 'wsbg_print' ),
		] );
		wp_enqueue_style( 'wsbg-admin', WSBG_URL . 'assets/css/admin.css', [], WSBG_VERSION );
	}

	public static function enqueue_front(): void {
		if ( ! is_product() && ! is_shop() ) {
			return;
		}
		wp_enqueue_style( 'wsbg-front', WSBG_URL . 'assets/css/front.css', [], WSBG_VERSION );
	}
}
