<?php
/**
 * Admin menu, enqueue, metabox, and product column.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class Admin {

	private static ?Admin $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'admin_menu',                   array( $this, 'register_menu_pages' ) );
		add_action( 'admin_enqueue_scripts',        array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices',                array( $this, 'show_welcome_notice' ) );
		add_action( 'wp_ajax_wcbp_dismiss_notice',      array( $this, 'ajax_dismiss_notice' ) );
		add_action( 'wp_ajax_wcbp_get_barcode_preview', array( $this, 'ajax_barcode_preview' ) );
		add_action( 'add_meta_boxes',               array( $this, 'add_product_metabox' ) );
		add_action( 'save_post_product',            array( $this, 'save_product_metabox' ), 10, 1 );
		add_filter( 'manage_edit-product_columns',  array( $this, 'add_barcode_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_barcode_column' ), 10, 2 );
		add_filter( 'bulk_actions-edit-product',    array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_bulk_action_add_queue' ), 10, 3 );
	}

	public function register_menu_pages(): void {
		add_menu_page(
			__( 'WooBarcode Pro', 'woo-barcode-pro' ),
			__( 'Barcode Pro', 'woo-barcode-pro' ),
			'manage_woocommerce',
			'wcbp-settings',
			array( Settings::get_instance(), 'render_page' ),
			'dashicons-tag',
			58
		);
		add_submenu_page( 'wcbp-settings', __( 'Settings', 'woo-barcode-pro' ),        __( 'Settings', 'woo-barcode-pro' ),        'manage_woocommerce', 'wcbp-settings',        array( Settings::get_instance(),      'render_page' ) );
		add_submenu_page( 'wcbp-settings', __( 'Label Templates', 'woo-barcode-pro' ),  __( 'Label Templates', 'woo-barcode-pro' ),  'manage_woocommerce', 'wcbp-label-templates',  array( LabelTemplates::get_instance(), 'render_page' ) );
		add_submenu_page( 'wcbp-settings', __( 'Price Templates', 'woo-barcode-pro' ),  __( 'Price Templates', 'woo-barcode-pro' ),  'manage_woocommerce', 'wcbp-price-templates',  array( PriceTemplates::get_instance(), 'render_page' ) );
		add_submenu_page( 'wcbp-settings', __( 'Print Queue', 'woo-barcode-pro' ),      __( 'Print Queue', 'woo-barcode-pro' ),      'manage_woocommerce', 'wcbp-print-queue',      array( PrintQueue::get_instance(),    'render_page' ) );
		add_submenu_page( 'wcbp-settings', __( 'Quick Add', 'woo-barcode-pro' ),        __( '📱 Quick Add', 'woo-barcode-pro' ),    'manage_woocommerce', 'wcbp-quick-add',        array( QuickAdd::get_instance(),      'render_page' ) );
		add_submenu_page( 'wcbp-settings', __( 'Inventory', 'woo-barcode-pro' ),       __( 'Inventory', 'woo-barcode-pro' ),       'manage_woocommerce', 'wcbp-inventory',        array( \WCBarcodePro\Inventory\InventoryManager::get_instance(), 'render_page' ) );
		// Tutorial — hidden from nav but accessible via URL.
		add_submenu_page( null,            __( 'Tutorial', 'woo-barcode-pro' ),         __( 'Tutorial', 'woo-barcode-pro' ),         'manage_woocommerce', 'wcbp-tutorial',         array( Tutorial::get_instance(),      'render_page' ) );
		// Print page — hidden, full-screen.
		add_submenu_page( null,            __( 'Print Labels', 'woo-barcode-pro' ),     __( 'Print Labels', 'woo-barcode-pro' ),     'manage_woocommerce', 'wcbp-print',            array( PrintPage::get_instance(),     'render_page' ) );
	}

	public function enqueue_scripts( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		wp_enqueue_style( 'wcbp-admin', WCBP_PLUGIN_URL . 'assets/css/admin.css', array(), WCBP_VERSION );

		$wcbp_pages  = array( 'wcbp-settings', 'wcbp-label-templates', 'wcbp-price-templates', 'wcbp-print-queue', 'wcbp-quick-add', 'wcbp-tutorial', 'wcbp-print', 'wcbp-inventory' );
		$page        = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security
		$on_product  = 'product' === $screen->post_type && in_array( $screen->base, array( 'post', 'edit' ), true );

		if ( in_array( $page, $wcbp_pages, true ) || $on_product ) {
			wp_enqueue_script( 'wcbp-admin', WCBP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), WCBP_VERSION, true );
			wp_localize_script( 'wcbp-admin', 'wcbpAdmin', array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wcbp_admin' ),
				'queue_nonce'=> wp_create_nonce( 'wcbp_queue' ),
				'plugin_url' => WCBP_PLUGIN_URL,
				'strings'    => array(
					'adding'          => __( 'Adding…', 'woo-barcode-pro' ),
					'added'           => __( 'Added!', 'woo-barcode-pro' ),
					'error'           => __( 'Error', 'woo-barcode-pro' ),
					'select_products' => __( 'Please select products first.', 'woo-barcode-pro' ),
				),
			) );
		}

		if ( 'wcbp-label-templates' === $page ) {
			wp_enqueue_script( 'wcbp-label-designer', WCBP_PLUGIN_URL . 'assets/js/label-designer.js', array( 'jquery' ), WCBP_VERSION, true );
		}

		if ( 'wcbp-print-queue' === $page ) {
			wp_enqueue_script( 'wcbp-print-queue', WCBP_PLUGIN_URL . 'assets/js/print-queue.js', array( 'jquery' ), WCBP_VERSION, true );
			wp_localize_script( 'wcbp-print-queue', 'wcbpQueue', array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'admin_url' => admin_url( 'admin.php' ),
				'nonce'     => wp_create_nonce( 'wcbp_queue' ),
				'strings'   => array(
					'confirm_clear' => __( 'Clear all items from the queue?', 'woo-barcode-pro' ),
					'popup_blocked' => __( 'Please allow popups for this site.', 'woo-barcode-pro' ),
				),
			) );
		}

		if ( 'wcbp-quick-add' === $page ) {
			wp_enqueue_style(  'wcbp-quick-add', WCBP_PLUGIN_URL . 'assets/css/quick-add.css', array(), WCBP_VERSION );
			wp_enqueue_script( 'wcbp-quick-add', WCBP_PLUGIN_URL . 'assets/js/quick-add.js', array(), WCBP_VERSION, true );
			wp_localize_script( 'wcbp-quick-add', 'wcbpQuickAdd', array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wcbp_quick_add' ),
				'plugin_url' => WCBP_PLUGIN_URL,
			) );
		}

		if ( 'wcbp-print' === $page ) {
			wp_enqueue_style( 'wcbp-print', WCBP_PLUGIN_URL . 'assets/css/print.css', array(), WCBP_VERSION );
		}

		if ( 'wcbp-inventory' === $page ) {
			wp_enqueue_script( 'wcbp-inventory', WCBP_PLUGIN_URL . 'assets/js/inventory.js', array( 'jquery' ), WCBP_VERSION, true );
			wp_localize_script( 'wcbp-inventory', 'wcbpInv', array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wcbp_inventory' ),
				'edit_url'  => admin_url( 'post.php?post=' ),
				'order_url' => admin_url( 'post.php?post=' ),
				'strings'   => array(
					'not_found'       => __( 'No product found for that barcode or SKU.', 'woo-barcode-pro' ),
					'loading'         => __( 'Loading…', 'woo-barcode-pro' ),
					'saving'          => __( 'Saving…', 'woo-barcode-pro' ),
					'error'           => __( 'An error occurred.', 'woo-barcode-pro' ),
					'invalid_qty'     => __( 'Please enter a valid quantity (0 or more).', 'woo-barcode-pro' ),
					'set_stock'       => __( 'Set Stock', 'woo-barcode-pro' ),
					'confirm_sell'    => __( 'Deduct 1 unit from stock as an in-person sale?', 'woo-barcode-pro' ),
					'adjusted'        => __( 'Stock updated: %old% → %new% (%change%)', 'woo-barcode-pro' ),
					'sold_one'        => __( 'Sold 1 unit. New stock: %qty%', 'woo-barcode-pro' ),
					'no_low_stock'    => __( 'No products below that threshold.', 'woo-barcode-pro' ),
					'no_log'          => __( 'No history found.', 'woo-barcode-pro' ),
					'reason_order'    => __( 'Order sale', 'woo-barcode-pro' ),
					'reason_manual'   => __( 'Manual adjust', 'woo-barcode-pro' ),
					'reason_scan_sell'=> __( 'In-person sale', 'woo-barcode-pro' ),
				),
			) );
		}

		if ( 'wcbp-tutorial' === $page ) {
			wp_enqueue_style(  'wcbp-tutorial', WCBP_PLUGIN_URL . 'assets/css/tutorial.css', array(), WCBP_VERSION );
			wp_enqueue_script( 'wcbp-tutorial',  WCBP_PLUGIN_URL . 'assets/js/tutorial.js', array( 'jquery' ), WCBP_VERSION, true );
			wp_localize_script( 'wcbp-tutorial', 'wcbpTutorial', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcbp_tutorial' ),
			) );
		}
	}

	public function show_welcome_notice(): void {
		if ( ! get_transient( 'wcbp_just_activated' ) ) {
			return;
		}
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			return;
		}
		$tutorial_url = admin_url( 'admin.php?page=wcbp-tutorial' );
		?>
		<div class="notice notice-info wcbp-welcome-notice is-dismissible" style="padding:12px 15px;display:flex;align-items:center;gap:16px;">
			<span style="font-size:2rem;">🎉</span>
			<div>
				<strong><?php esc_html_e( 'WooBarcode Pro is active!', 'woo-barcode-pro' ); ?></strong>
				<?php esc_html_e( 'Start the 5-minute tour to set up your first barcode labels.', 'woo-barcode-pro' ); ?>
				&nbsp;<a href="<?php echo esc_url( $tutorial_url ); ?>" class="button button-primary" style="margin-left:8px;">
					<?php esc_html_e( 'Start Tutorial →', 'woo-barcode-pro' ); ?>
				</a>
				<a href="#" class="wcbp-dismiss-welcome" style="margin-left:8px;color:#999;">
					<?php esc_html_e( 'Dismiss', 'woo-barcode-pro' ); ?>
				</a>
			</div>
		</div>
		<script>
		document.querySelector('.wcbp-dismiss-welcome')?.addEventListener('click', function(e){
			e.preventDefault();
			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method:'POST',
				headers:{'Content-Type':'application/x-www-form-urlencoded'},
				body:'action=wcbp_dismiss_notice&nonce=<?php echo esc_js( wp_create_nonce( 'wcbp_dismiss' ) ); ?>'
			});
			this.closest('.wcbp-welcome-notice').remove();
		});
		</script>
		<?php
	}

	public function ajax_barcode_preview(): void {
		check_ajax_referer( 'wcbp_admin', 'nonce' );
		$product_id = (int) ( $_POST['id'] ?? 0 );
		$raw_value  = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

		if ( $product_id ) {
			$svg = \WCBarcodePro\wcbp_product_barcode_svg( $product_id, 0, array( 'height' => 50, 'module_width' => 1 ) );
		} elseif ( '' !== $raw_value ) {
			$symbology = \WCBarcodePro\wcbp_get_setting( 'symbology', 'code128' );
			$svg = \WCBarcodePro\Barcode\BarcodeGenerator::get_instance()->generate_svg(
				$raw_value, $symbology, array( 'height' => 50, 'module_width' => 1 )
			);
		} else {
			wp_send_json_error();
			return;
		}

		if ( '' === $svg ) {
			wp_send_json_error( array( 'message' => __( 'Could not generate barcode.', 'woo-barcode-pro' ) ) );
			return;
		}

		wp_send_json_success( array( 'svg' => $svg ) );
	}

	public function ajax_dismiss_notice(): void {
		check_ajax_referer( 'wcbp_dismiss', 'nonce' );
		delete_transient( 'wcbp_just_activated' );
		wp_die( '1' );
	}

	public function add_product_metabox(): void {
		add_meta_box(
			'wcbp_product_barcode',
			__( 'Barcode', 'woo-barcode-pro' ),
			array( $this, 'render_product_metabox' ),
			'product',
			'side',
			'default'
		);
	}

	public function render_product_metabox( \WP_Post $post ): void {
		$product_id = $post->ID;
		$svg        = \WCBarcodePro\wcbp_product_barcode_svg( $product_id, 0, array( 'height' => 50, 'module_width' => 1 ) );
		$value      = \WCBarcodePro\wcbp_barcode_value( $product_id );
		$ean        = \WCBarcodePro\Barcode\EanManager::get_instance()->get_ean( $product_id );
		wp_nonce_field( 'wcbp_metabox', 'wcbp_metabox_nonce' );
		?>
		<div class="wcbp-metabox">
			<div id="wcbp-barcode-preview" class="wcbp-metabox-preview">
				<?php if ( $svg ) : ?>
					<?php echo $svg; // phpcs:ignore WordPress.Security ?>
					<p class="wcbp-metabox-value"><code><?php echo esc_html( $value ); ?></code></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No barcode yet. Save the product or add a SKU.', 'woo-barcode-pro' ); ?></p>
				<?php endif; ?>
			</div>

			<p>
				<label><strong><?php esc_html_e( 'EAN / Custom Barcode', 'woo-barcode-pro' ); ?></strong></label>
				<input type="text" name="wcbp_ean" id="wcbp_ean"
					value="<?php echo esc_attr( $ean ); ?>"
					placeholder="<?php esc_attr_e( 'Optional EAN-13 or custom', 'woo-barcode-pro' ); ?>"
					class="widefat" />
			</p>

			<p>
				<button type="button" class="button wcbp-add-single-queue" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>">
					<?php esc_html_e( '+ Add to Print Queue', 'woo-barcode-pro' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	public function save_product_metabox( int $product_id ): void {
		if ( ! isset( $_POST['wcbp_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcbp_metabox_nonce'] ) ), 'wcbp_metabox' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}
		if ( isset( $_POST['wcbp_ean'] ) ) {
			$ean = sanitize_text_field( wp_unslash( $_POST['wcbp_ean'] ) );
			if ( '' !== $ean ) {
				update_post_meta( $product_id, \WCBarcodePro\Barcode\EanManager::META_KEY, $ean );
			} else {
				delete_post_meta( $product_id, \WCBarcodePro\Barcode\EanManager::META_KEY );
			}
		}
	}

	public function add_barcode_column( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'sku' === $key ) {
				$new['wcbp_barcode'] = __( 'Barcode', 'woo-barcode-pro' );
			}
		}
		return $new;
	}

	public function render_barcode_column( string $column, int $post_id ): void {
		if ( 'wcbp_barcode' !== $column ) {
			return;
		}
		$svg = \WCBarcodePro\wcbp_product_barcode_svg( $post_id, 0, array( 'height' => 32, 'module_width' => 1, 'show_text' => false ) );
		if ( $svg ) {
			echo $svg; // phpcs:ignore WordPress.Security
			echo '<br><button type="button" class="button button-small wcbp-add-single-queue" data-product-id="' . esc_attr( (string) $post_id ) . '">' .
				esc_html__( '+Queue', 'woo-barcode-pro' ) . '</button>';
		} else {
			echo '<span class="wcbp-no-barcode">—</span>';
		}
	}

	public function register_bulk_actions( array $actions ): array {
		$actions['wcbp_add_to_queue'] = __( 'Add to Print Queue', 'woo-barcode-pro' );
		return $actions;
	}

	public function handle_bulk_action_add_queue( string $redirect_to, string $action, array $post_ids ): string {
		if ( 'wcbp_add_to_queue' !== $action ) {
			return $redirect_to;
		}
		$count = PrintQueue::get_instance()->handle_bulk_add( $post_ids );
		return add_query_arg( 'wcbp_queued', $count, $redirect_to );
	}
}
