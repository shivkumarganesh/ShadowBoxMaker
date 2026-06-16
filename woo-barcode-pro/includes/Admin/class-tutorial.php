<?php
/**
 * Step-by-step onboarding tutorial.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class Tutorial {

	private static ?Tutorial $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'wp_ajax_wcbp_save_tutorial_step', array( $this, 'ajax_save_step' ) );
		add_action( 'wp_ajax_wcbp_complete_tutorial',  array( $this, 'ajax_complete' ) );
	}

	private function get_steps(): array {
		$quick_add_url = admin_url( 'admin.php?page=wcbp-quick-add' );
		return array(
			1 => array(
				'title'        => __( 'Welcome to WooBarcode Pro', 'woo-barcode-pro' ),
				'icon'         => '🎉',
				'content'      => __( 'WooBarcode Pro turns your WooCommerce store into a barcode powerhouse. In this quick tour you will: set up your barcode format, create price templates for fast product entry, design label templates, and print your first batch of labels. Let\'s go!', 'woo-barcode-pro' ),
				'action_label' => __( "Let's go →", 'woo-barcode-pro' ),
				'action_url'   => null,
			),
			2 => array(
				'title'        => __( 'Step 1 of 5 — Basic Settings', 'woo-barcode-pro' ),
				'icon'         => '⚙️',
				'content'      => __( 'Configure your barcode settings. Choose Code 128 if you want barcodes that work with any scanner and any product name. Choose EAN-13 if you use standard retail scanners and your products have 12-digit numeric codes. Set a SKU prefix — for example "SBM-" so all auto-generated SKUs look like SBM-42.', 'woo-barcode-pro' ),
				'action_label' => __( 'Go to Settings →', 'woo-barcode-pro' ),
				'action_url'   => admin_url( 'admin.php?page=wcbp-settings' ),
			),
			3 => array(
				'title'        => __( 'Step 2 of 5 — Price Templates', 'woo-barcode-pro' ),
				'icon'         => '🏷️',
				'content'      => __( 'Price Templates are the key to adding products fast. Create one template per price point — for example "Small $25", "Medium $50", "Large $75". Each template gets its own barcode. Scan that barcode on the Quick Add page and the price fills in automatically. You only need to type the product name and take a photo.', 'woo-barcode-pro' ),
				'action_label' => __( 'Create Price Templates →', 'woo-barcode-pro' ),
				'action_url'   => admin_url( 'admin.php?page=wcbp-price-templates&action=new' ),
			),
			4 => array(
				'title'        => __( 'Step 3 of 5 — Label Design', 'woo-barcode-pro' ),
				'icon'         => '🖨️',
				'content'      => __( 'Design your label template. A default Avery 5160 template is already created for you (1" × 2.625", 30 labels per sheet — the most common size). You can customise what appears on each label: product name, price, SKU, your store logo. The live preview updates as you change settings.', 'woo-barcode-pro' ),
				'action_label' => __( 'Design Label Template →', 'woo-barcode-pro' ),
				'action_url'   => admin_url( 'admin.php?page=wcbp-label-templates' ),
			),
			5 => array(
				'title'        => __( 'Step 4 of 5 — Quick Add on Mobile', 'woo-barcode-pro' ),
				'icon'         => '📱',
				/* translators: %s = Quick Add page URL */
				'content'      => sprintf( __( 'Open Chrome on your Android phone and go to: %s — bookmark it on your home screen. To add a product: scan a Price Template barcode → type the product name → tap the camera to take a photo → tap Save. Done in under 20 seconds. The product is live in WooCommerce instantly.', 'woo-barcode-pro' ), '<code>' . esc_html( $quick_add_url ) . '</code>' ),
				'action_label' => __( 'Open Quick Add →', 'woo-barcode-pro' ),
				'action_url'   => $quick_add_url,
			),
			6 => array(
				'title'        => __( 'Step 5 of 5 — Print Your Labels', 'woo-barcode-pro' ),
				'icon'         => '✅',
				'content'      => __( 'Your products have barcodes. Now print the labels. Go to Products → select the ones you want → use the "Add to Print Queue" bulk action → then go to Barcode Pro → Print Queue → click Print. Labels appear on screen ready to print. Press Ctrl+P (or Cmd+P on Mac) to send to your printer. You\'re all set — happy labelling!', 'woo-barcode-pro' ),
				'action_label' => __( 'Go to Print Queue →', 'woo-barcode-pro' ),
				'action_url'   => admin_url( 'admin.php?page=wcbp-print-queue' ),
			),
		);
	}

	public function get_current_step(): int {
		if ( $this->is_complete() ) {
			return 0;
		}
		return (int) get_user_meta( get_current_user_id(), 'wcbp_tutorial_step', true ) ?: 1;
	}

	public function is_complete(): bool {
		return (bool) get_user_meta( get_current_user_id(), 'wcbp_tutorial_complete', true );
	}

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-barcode-pro' ) );
		}
		delete_transient( 'wcbp_just_activated' );
		$steps        = $this->get_steps();
		$current_step = $this->get_current_step() ?: 1;
		$total_steps  = count( $steps ) - 1; // step 1 is welcome, not counted.
		$step         = $steps[ $current_step ] ?? $steps[1];
		$is_complete  = $this->is_complete();
		include WCBP_PLUGIN_DIR . 'templates/admin/tutorial.php';
	}

	public function ajax_save_step(): void {
		check_ajax_referer( 'wcbp_tutorial', 'nonce' );
		$step = max( 1, min( 6, (int) ( $_POST['step'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security
		update_user_meta( get_current_user_id(), 'wcbp_tutorial_step', $step );
		wp_send_json_success( array( 'step' => $step ) );
	}

	public function ajax_complete(): void {
		check_ajax_referer( 'wcbp_tutorial', 'nonce' );
		update_user_meta( get_current_user_id(), 'wcbp_tutorial_complete', 1 );
		delete_user_meta( get_current_user_id(), 'wcbp_tutorial_step' );
		wp_send_json_success( array( 'redirect' => admin_url( 'admin.php?page=wcbp-print-queue' ) ) );
	}
}
