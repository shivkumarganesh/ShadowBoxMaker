<?php
/**
 * CSV import/export and bulk SKU regeneration.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class BulkActions {

	private static ?BulkActions $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'admin_post_wcbp_export_ean_csv',    array( $this, 'handle_export' ) );
		add_action( 'admin_post_wcbp_import_ean_csv',    array( $this, 'handle_import' ) );
		add_action( 'wp_ajax_wcbp_bulk_regen_skus',      array( $this, 'ajax_regen_skus' ) );
		add_action( 'wp_ajax_wcbp_bulk_add_to_queue',    array( $this, 'ajax_bulk_queue' ) );
	}

	// ── Export ────────────────────────────────────────────────────────────────

	public function handle_export(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		\WCBarcodePro\wcbp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'wcbp_export_ean' );

		$csv = \WCBarcodePro\Barcode\EanManager::get_instance()->export_to_csv();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="woobarcode-ean-export-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		// phpcs:ignore WordPress.Security
		echo $csv;
		exit;
	}

	// ── Import ────────────────────────────────────────────────────────────────

	public function handle_import(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		\WCBarcodePro\wcbp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'wcbp_import_ean' );

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wcbp-settings&tab=tools&import_error=no_file' ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$file = $_FILES['csv_file']['tmp_name'];
		$result = \WCBarcodePro\Barcode\EanManager::get_instance()->import_from_csv( $file );

		wp_safe_redirect( admin_url(
			'admin.php?page=wcbp-settings&tab=tools&imported=' . $result['success'] . '&import_errors=' . count( $result['errors'] )
		) );
		exit;
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_regen_skus(): void {
		check_ajax_referer( 'wcbp_admin', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}

		$ids   = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
		$count = \WCBarcodePro\Barcode\SkuManager::get_instance()->bulk_regenerate( $ids );
		wp_send_json_success( array( 'regenerated' => $count ) );
	}

	public function ajax_bulk_queue(): void {
		check_ajax_referer( 'wcbp_queue', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}

		$ids   = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
		$count = PrintQueue::get_instance()->handle_bulk_add( $ids );
		wp_send_json_success( array(
			'added'   => $count,
			'total'   => PrintQueue::get_instance()->get_count(),
			'message' => sprintf(
				/* translators: %d = number of products added */
				__( '%d product(s) added to print queue.', 'woo-barcode-pro' ),
				$count
			),
		) );
	}
}
