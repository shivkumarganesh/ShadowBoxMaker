<?php
/**
 * Plugin activation: creates DB tables and seeds defaults.
 *
 * @package WCBarcodePro
 */

namespace WCBarcodePro;

defined( 'ABSPATH' ) || exit;

class Activator {

	public static function activate(): void {
		global $wpdb;
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset = $wpdb->get_charset_collate();

		$sql_label = "CREATE TABLE {$wpdb->prefix}wcbp_label_templates (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			preset varchar(50) DEFAULT 'custom',
			width_in decimal(6,3) DEFAULT 2.625,
			height_in decimal(6,3) DEFAULT 1.000,
			cols tinyint(3) UNSIGNED DEFAULT 3,
			rows_per_page tinyint(3) UNSIGNED DEFAULT 10,
			gap_in decimal(6,3) DEFAULT 0.100,
			margin_in decimal(6,3) DEFAULT 0.500,
			layout varchar(20) DEFAULT 'vertical',
			fields longtext,
			barcode_ratio tinyint(3) UNSIGNED DEFAULT 60,
			logo_id bigint(20) UNSIGNED DEFAULT 0,
			is_default tinyint(1) DEFAULT 0,
			page_size varchar(10) NOT NULL DEFAULT 'letter',
			barcode_options text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;";

		$sql_price = "CREATE TABLE {$wpdb->prefix}wcbp_price_templates (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			price decimal(10,2) NOT NULL DEFAULT 0.00,
			category_ids longtext,
			tag_ids longtext,
			attributes longtext,
			label_template_id bigint(20) UNSIGNED DEFAULT 0,
			barcode_value varchar(50) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY barcode_value (barcode_value)
		) $charset;";

		$sql_queue = "CREATE TABLE {$wpdb->prefix}wcbp_print_queue (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id bigint(20) UNSIGNED NOT NULL,
			variation_id bigint(20) UNSIGNED DEFAULT 0,
			quantity int(11) UNSIGNED DEFAULT 1,
			label_template_id bigint(20) UNSIGNED DEFAULT 0,
			added_by bigint(20) UNSIGNED DEFAULT 0,
			added_at datetime DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'pending',
			printed_at datetime DEFAULT NULL,
			order_id bigint(20) UNSIGNED DEFAULT 0,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY status (status),
			KEY order_id (order_id)
		) $charset;";

		$sql_stock_log = "CREATE TABLE {$wpdb->prefix}wcbp_stock_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id bigint(20) UNSIGNED NOT NULL,
			variation_id bigint(20) UNSIGNED DEFAULT 0,
			old_qty int(11) NOT NULL DEFAULT 0,
			new_qty int(11) NOT NULL DEFAULT 0,
			change_qty int(11) NOT NULL DEFAULT 0,
			reason varchar(50) NOT NULL DEFAULT 'manual',
			order_id bigint(20) UNSIGNED DEFAULT 0,
			user_id bigint(20) UNSIGNED DEFAULT 0,
			note varchar(255) DEFAULT '',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY reason (reason),
			KEY created_at (created_at)
		) $charset;";

		dbDelta( $sql_label );
		dbDelta( $sql_price );
		dbDelta( $sql_queue );
		dbDelta( $sql_stock_log );

		// Seed a default label template if none exists.
		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wcbp_label_templates" ); // phpcs:ignore WordPress.DB
		if ( '0' === $existing || 0 === (int) $existing ) {
			$wpdb->insert(
				$wpdb->prefix . 'wcbp_label_templates',
				array(
					'name'         => 'Avery 5160 (Default)',
					'preset'       => 'avery_5160',
					'width_in'     => 2.625,
					'height_in'    => 1.0,
					'cols'         => 3,
					'rows_per_page'=> 10,
					'gap_in'       => 0.0,
					'margin_in'    => 0.5,
					'layout'       => 'vertical',
					'fields'       => wp_json_encode( array(
						'name'       => true,
						'price'      => true,
						'sku'        => true,
						'attributes' => false,
						'logo'       => false,
						'custom_meta'=> '',
					) ),
					'barcode_ratio'=> 60,
					'logo_id'      => 0,
					'is_default'   => 1,
					'page_size'    => 'letter',
				),
				array( '%s', '%s', '%f', '%f', '%d', '%d', '%f', '%f', '%s', '%s', '%d', '%d', '%d', '%s' )
			);
		}

		update_option( 'wcbp_db_version', WCBP_DB_VERSION );
		set_transient( 'wcbp_just_activated', 1, 30 );

		// Ensure default plugin settings exist.
		if ( ! get_option( 'wcbp_settings' ) ) {
			require_once WCBP_PLUGIN_DIR . 'includes/class-plugin.php';
			add_option( 'wcbp_settings', \WCBarcodePro\Plugin::get_default_settings() );
		}
	}
}
