<?php
/**
 * Full-screen, print-ready label output.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class PrintPage {

	private static ?PrintPage $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'wp_ajax_wcbp_render_labels', array( $this, 'ajax_render_labels' ) );
	}

	/**
	 * Render the print-preview page (full-screen iframe target).
	 */
	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}

		$queue_ids   = array_map( 'intval', (array) ( $_GET['ids'] ?? array() ) ); // phpcs:ignore WordPress.Security
		$template_id = (int) ( $_GET['template_id'] ?? 0 );

		$queue    = PrintQueue::get_instance();
		$renderer = \WCBarcodePro\Label\LabelRenderer::get_instance();

		if ( ! empty( $queue_ids ) ) {
			// Specific queue items selected for printing.
			$items = array_filter(
				$queue->get_all(),
				fn( $item ) => in_array( (int) $item['id'], $queue_ids, true )
			);
		} else {
			$items = $queue->get_all();
		}

		$label_tpl = $template_id
			? LabelTemplates::get_instance()->get( $template_id )
			: LabelTemplates::get_instance()->get_default();

		$html = $renderer->render_grid( array_values( $items ), $label_tpl );
		include WCBP_PLUGIN_DIR . 'templates/admin/print-page.php';
	}

	/**
	 * AJAX: return rendered label HTML so JS can display in the iframe.
	 */
	public function ajax_render_labels(): void {
		check_ajax_referer( 'wcbp_queue', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}

		$ids         = array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) );
		$template_id = (int) ( $_POST['template_id'] ?? 0 );

		$queue    = PrintQueue::get_instance();
		$renderer = \WCBarcodePro\Label\LabelRenderer::get_instance();

		$all_items = $queue->get_all();
		if ( ! empty( $ids ) ) {
			$all_items = array_filter( $all_items, fn( $i ) => in_array( (int) $i['id'], $ids, true ) );
		}

		$label_tpl = $template_id
			? LabelTemplates::get_instance()->get( $template_id )
			: LabelTemplates::get_instance()->get_default();

		wp_send_json_success( array(
			'html'  => $renderer->render_grid( array_values( $all_items ), $label_tpl ),
			'count' => count( $all_items ),
		) );
	}
}
