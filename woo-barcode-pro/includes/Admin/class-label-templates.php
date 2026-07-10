<?php
/**
 * Label template CRUD.
 *
 * @package WCBarcodePro\Admin
 */

namespace WCBarcodePro\Admin;

defined( 'ABSPATH' ) || exit;

class LabelTemplates {

	private static ?LabelTemplates $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'admin_post_wcbp_save_label_template',   array( $this, 'handle_save' ) );
		add_action( 'admin_post_wcbp_delete_label_template', array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_wcbp_get_label_template',       array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_wcbp_set_default_template',     array( $this, 'ajax_set_default' ) );
		add_action( 'wp_ajax_wcbp_preview_barcode',           array( $this, 'ajax_preview_barcode' ) );
	}

	public function get_all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wcbp_label_templates ORDER BY is_default DESC, name ASC", ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB
	}

	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wcbp_label_templates WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ?: null;
	}

	public function get_default(): ?array {
		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}wcbp_label_templates WHERE is_default = 1 LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $row ) {
			$row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}wcbp_label_templates ORDER BY id ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB
		}
		return $row ?: null;
	}

	public function save( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'wcbp_label_templates';

		$fields_defaults = array( 'name' => true, 'price' => true, 'sku' => true, 'attributes' => false, 'logo' => false, 'custom_meta' => '' );
		$fields_raw = $data['fields'] ?? array();
		$fields = array(
			'company_name'      => ! empty( $fields_raw['company_name'] ),
			'company_name_text' => sanitize_text_field( $fields_raw['company_name_text'] ?? '' ),
			'name'              => ! empty( $fields_raw['name'] ),
			'price'             => ! empty( $fields_raw['price'] ),
			'sku'               => ! empty( $fields_raw['sku'] ),
			'attributes'        => ! empty( $fields_raw['attributes'] ),
			'logo'              => ! empty( $fields_raw['logo'] ),
			'custom_meta'       => sanitize_text_field( $fields_raw['custom_meta'] ?? '' ),
		);

		$allowed_page_sizes   = array( 'letter', 'A4', 'A5', 'legal' );
		$allowed_symbologies  = array( 'code128', 'ean13', 'upca', 'itf14' );
		$bc_color_raw         = $data['bc_color'] ?? '#000000';
		$bc_opts = array(
			'symbology'    => in_array( $data['bc_symbology'] ?? 'code128', $allowed_symbologies, true ) ? $data['bc_symbology'] : 'code128',
			'height'       => max( 20, min( 200, (int) ( $data['bc_height'] ?? 60 ) ) ),
			'module_width' => max( 1, min( 4, (int) ( $data['bc_module_width'] ?? 2 ) ) ),
			'show_text'    => ! empty( $data['bc_show_text'] ),
			'color'        => preg_match( '/^#[0-9a-fA-F]{6}$/', $bc_color_raw ) ? $bc_color_raw : '#000000',
		);
		$row = array(
			'name'            => sanitize_text_field( $data['name'] ?? 'Untitled' ),
			'preset'          => sanitize_key( $data['preset'] ?? 'custom' ),
			'width_in'        => (float) ( $data['width_in']      ?? 2.625 ),
			'height_in'       => (float) ( $data['height_in']     ?? 1.0 ),
			'cols'            => (int)   ( $data['cols']          ?? 3 ),
			'rows_per_page'   => (int)   ( $data['rows_per_page'] ?? 10 ),
			'gap_in'          => (float) ( $data['gap_in']        ?? 0.0 ),
			'margin_in'       => (float) ( $data['margin_in']     ?? 0.5 ),
			'layout'          => in_array( $data['layout'] ?? 'vertical', array( 'vertical', 'horizontal' ), true ) ? $data['layout'] : 'vertical',
			'fields'          => wp_json_encode( $fields ),
			'barcode_ratio'   => max( 30, min( 80, (int) ( $data['barcode_ratio'] ?? 60 ) ) ),
			'logo_id'         => (int) ( $data['logo_id'] ?? 0 ),
			'page_size'       => in_array( $data['page_size'] ?? 'letter', $allowed_page_sizes, true ) ? $data['page_size'] : 'letter',
			'barcode_options' => wp_json_encode( $bc_opts ),
		);
		$fmt = array( '%s','%s','%f','%f','%d','%d','%f','%f','%s','%s','%d','%d','%s','%s' );

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $data['id'] ), $fmt, array( '%d' ) ); // phpcs:ignore WordPress.DB
			return (int) $data['id'];
		}

		$wpdb->insert( $table, $row, $fmt ); // phpcs:ignore WordPress.DB
		return $wpdb->insert_id ?: false;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wcbp_label_templates" ); // phpcs:ignore WordPress.DB
		if ( $count <= 1 ) {
			return false; // Never delete the last template.
		}
		$row = $this->get( $id );
		if ( $row && $row['is_default'] ) {
			// Promote another template as default.
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wcbp_label_templates SET is_default=1 WHERE id != %d LIMIT 1", $id ) ); // phpcs:ignore WordPress.DB
		}
		// Update queue items referencing this template.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wcbp_print_queue SET label_template_id=0 WHERE label_template_id=%d", $id ) ); // phpcs:ignore WordPress.DB
		return (bool) $wpdb->delete( $table = $wpdb->prefix . 'wcbp_label_templates', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	public function set_default( int $id ): bool {
		global $wpdb;
		$wpdb->query( "UPDATE {$wpdb->prefix}wcbp_label_templates SET is_default=0" ); // phpcs:ignore WordPress.DB
		return (bool) $wpdb->update( $wpdb->prefix . 'wcbp_label_templates', array( 'is_default' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	public function get_preset_dimensions(): array {
		return array(
			'avery_5160' => array( 'name' => 'Avery 5160 (Letter)', 'width_in' => 2.625, 'height_in' => 1.0,   'cols' => 3, 'rows_per_page' => 10, 'gap_in' => 0.0,  'margin_in' => 0.5,  'page_size' => 'letter' ),
			'avery_8160' => array( 'name' => 'Avery 8160 (Letter)', 'width_in' => 2.625, 'height_in' => 1.0,   'cols' => 3, 'rows_per_page' => 10, 'gap_in' => 0.0,  'margin_in' => 0.5,  'page_size' => 'letter' ),
			'avery_5260' => array( 'name' => 'Avery 5260 (Letter)', 'width_in' => 2.625, 'height_in' => 1.0,   'cols' => 3, 'rows_per_page' => 10, 'gap_in' => 0.0,  'margin_in' => 0.5,  'page_size' => 'letter' ),
			'a4_65up'    => array( 'name' => 'A4 65-up (A4)',       'width_in' => 1.497, 'height_in' => 0.835, 'cols' => 5, 'rows_per_page' => 13, 'gap_in' => 0.0,  'margin_in' => 0.24, 'page_size' => 'A4'     ),
			'a4_24up'    => array( 'name' => 'A4 24-up (A4)',       'width_in' => 2.48,  'height_in' => 1.35,  'cols' => 3, 'rows_per_page' => 8,  'gap_in' => 0.12, 'margin_in' => 0.24, 'page_size' => 'A4'     ),
			'custom'     => array( 'name' => 'Custom',              'width_in' => 2.0,   'height_in' => 1.0,   'cols' => 3, 'rows_per_page' => 10, 'gap_in' => 0.1,  'margin_in' => 0.5,  'page_size' => 'letter' ),
		);
	}

	public function handle_save(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		\WCBarcodePro\wcbp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'wcbp_save_label_template' );
		$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
		$data['fields'] = $_POST['fields'] ?? array(); // phpcs:ignore WordPress.Security
		$id = $this->save( $data );
		wp_safe_redirect( admin_url( 'admin.php?page=wcbp-label-templates&saved=1&id=' . (int) $id ) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		\WCBarcodePro\wcbp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'wcbp_delete_label_template' );
		$id = (int) ( $_GET['id'] ?? 0 );
		$this->delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=wcbp-label-templates&deleted=1' ) );
		exit;
	}

	public function ajax_get(): void {
		check_ajax_referer( 'wcbp_admin', 'nonce' );
		$id  = (int) ( $_POST['id'] ?? 0 );
		$row = $this->get( $id );
		if ( $row ) {
			$row['fields'] = json_decode( $row['fields'], true );
			wp_send_json_success( $row );
		} else {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'woo-barcode-pro' ) ) );
		}
	}

	public function ajax_set_default(): void {
		check_ajax_referer( 'wcbp_admin', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$id = (int) ( $_POST['id'] ?? 0 );
		wp_send_json_success( array( 'result' => $this->set_default( $id ) ) );
	}

	public function ajax_preview_barcode(): void {
		check_ajax_referer( 'wcbp_admin', 'nonce' );
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_send_json_error();
		}
		$value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
		if ( '' === $value ) {
			wp_send_json_error();
		}
		$allowed_sym = array( 'code128', 'ean13', 'upca', 'itf14' );
		$symbology   = in_array( $_POST['symbology'] ?? 'code128', $allowed_sym, true ) ? sanitize_key( $_POST['symbology'] ) : 'code128'; // phpcs:ignore WordPress.Security
		$show_text   = ! empty( $_POST['show_text'] ) && '1' === $_POST['show_text']; // phpcs:ignore WordPress.Security
		$module_width = max( 1, min( 4, (int) ( $_POST['module_width'] ?? 2 ) ) ); // phpcs:ignore WordPress.Security
		$color_raw   = sanitize_text_field( wp_unslash( $_POST['color'] ?? '#000000' ) );
		$color       = preg_match( '/^#[0-9a-fA-F]{6}$/', $color_raw ) ? $color_raw : '#000000';
		$height      = max( 20, min( 200, (int) ( $_POST['bc_height'] ?? 60 ) ) ); // phpcs:ignore WordPress.Security
		$svg = \WCBarcodePro\Barcode\BarcodeGenerator::get_instance()->generate_svg(
			$value,
			$symbology,
			array(
				'show_text'    => $show_text,
				'height'       => $height,
				'module_width' => $module_width,
				'color'        => $color,
			)
		);
		wp_send_json_success( array( 'svg' => $svg ) );
	}

	public function render_page(): void {
		if ( ! \WCBarcodePro\wcbp_current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'woo-barcode-pro' ) );
		}
		$templates = $this->get_all();
		$presets   = $this->get_preset_dimensions();
		$action    = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security
		$edit_id   = (int) ( $_GET['id'] ?? 0 );
		$editing   = ( 'edit' === $action || 'new' === $action ) ? ( $edit_id ? $this->get( $edit_id ) : array() ) : null;
		if ( $editing && isset( $editing['fields'] ) ) {
			$editing['fields'] = json_decode( $editing['fields'], true );
		}
		if ( $editing && ! empty( $editing['barcode_options'] ) ) {
			$editing['barcode_options'] = json_decode( $editing['barcode_options'], true );
		}
		include WCBP_PLUGIN_DIR . 'templates/admin/label-templates.php';
	}
}
