<?php
/**
 * Settings page template.
 *
 * Available: $settings (array), $tab (string).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'basic'  => __( 'Barcode', 'woo-barcode-pro' ),
	'print'  => __( 'Print', 'woo-barcode-pro' ),
	'orders' => __( 'Orders', 'woo-barcode-pro' ),
	'tools'  => __( 'Tools', 'woo-barcode-pro' ),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'WooBarcode Pro — Settings', 'woo-barcode-pro' ); ?></h1>

	<nav class="nav-tab-wrapper">
	<?php foreach ( $tabs as $key => $label ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcbp-settings&tab=' . $key ) ); ?>"
		   class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $label ); ?>
		</a>
	<?php endforeach; ?>
	</nav>

	<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'woo-barcode-pro' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['imported'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php printf( esc_html__( 'Imported %d EAN(s) successfully.', 'woo-barcode-pro' ), (int) $_GET['imported'] ); // phpcs:ignore ?>
		</p></div>
	<?php endif; ?>

	<form method="post" action="options.php" enctype="multipart/form-data">
		<?php settings_fields( 'wcbp_settings_group' ); ?>

		<?php if ( 'basic' === $tab ) : ?>

		<div class="wcbp-card">
			<h2><?php esc_html_e( 'Barcode Settings', 'woo-barcode-pro' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Symbology', 'woo-barcode-pro' ); ?></th>
					<td>
						<?php
						$opts = \WCBarcodePro\Admin\Settings::get_instance()->get_symbology_options();
						foreach ( $opts as $val => $label ) :
						?>
						<label>
							<input type="radio" name="wcbp_settings[symbology]" value="<?php echo esc_attr( $val ); ?>"
								<?php checked( $settings['symbology'], $val ); ?> />
							<?php echo esc_html( $label ); ?>
						</label><br/>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SKU Prefix', 'woo-barcode-pro' ); ?></th>
					<td>
						<input type="text" name="wcbp_settings[prefix]" value="<?php echo esc_attr( $settings['prefix'] ); ?>" class="regular-text" maxlength="20" />
						<p class="description"><?php esc_html_e( 'Prepended to auto-generated SKUs. E.g. "SBM-" → SBM-42.', 'woo-barcode-pro' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Auto-generate SKU', 'woo-barcode-pro' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wcbp_settings[auto_sku]" value="1" <?php checked( $settings['auto_sku'] ); ?> />
							<?php esc_html_e( 'Generate a SKU automatically when none is set', 'woo-barcode-pro' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Show barcode on product page', 'woo-barcode-pro' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wcbp_settings[show_single]" value="1" <?php checked( $settings['show_single'] ); ?> />
							<?php esc_html_e( 'Single product page', 'woo-barcode-pro' ); ?>
						</label><br/>
						<label>
							<input type="checkbox" name="wcbp_settings[show_loop]" value="1" <?php checked( $settings['show_loop'] ); ?> />
							<?php esc_html_e( 'Shop / category loop', 'woo-barcode-pro' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Show barcode text', 'woo-barcode-pro' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wcbp_settings[show_text]" value="1" <?php checked( $settings['show_text'] ); ?> />
							<?php esc_html_e( 'Display human-readable barcode value below the bars', 'woo-barcode-pro' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<?php elseif ( 'print' === $tab ) : ?>

		<div class="wcbp-card">
			<h2><?php esc_html_e( 'Print / Display', 'woo-barcode-pro' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Barcode height (px)', 'woo-barcode-pro' ); ?></th>
					<td>
						<input type="number" name="wcbp_settings[barcode_height]" value="<?php echo esc_attr( $settings['barcode_height'] ); ?>" min="20" max="200" class="small-text" /> px
						<p class="description"><?php esc_html_e( 'Height of rendered SVG barcodes.', 'woo-barcode-pro' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Module width (px)', 'woo-barcode-pro' ); ?></th>
					<td>
						<input type="number" name="wcbp_settings[module_width]" value="<?php echo esc_attr( $settings['module_width'] ); ?>" min="1" max="5" class="small-text" /> px
						<p class="description"><?php esc_html_e( 'Width of the narrowest bar. Increase for easier scanning on printed labels.', 'woo-barcode-pro' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php elseif ( 'orders' === $tab ) : ?>

		<div class="wcbp-card">
			<h2><?php esc_html_e( 'Order Auto-Queue', 'woo-barcode-pro' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable auto-queue', 'woo-barcode-pro' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wcbp_settings[order_queue_enabled]" value="1" <?php checked( $settings['order_queue_enabled'] ); ?> />
							<?php esc_html_e( 'Automatically add ordered products to the print queue', 'woo-barcode-pro' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Trigger on status', 'woo-barcode-pro' ); ?></th>
					<td>
						<select name="wcbp_settings[order_queue_status]">
						<?php foreach ( wc_get_order_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( ltrim( $key, 'wc-' ) ); ?>"
								<?php selected( $settings['order_queue_status'], ltrim( $key, 'wc-' ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<?php elseif ( 'tools' === $tab ) : ?>

		<div class="wcbp-card">
			<h2><?php esc_html_e( 'EAN CSV Export', 'woo-barcode-pro' ); ?></h2>
			<p><?php esc_html_e( 'Download all product EAN codes as a CSV file.', 'woo-barcode-pro' ); ?></p>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wcbp_export_ean_csv' ), 'wcbp_export_ean' ) ); ?>"
			   class="button button-secondary"><?php esc_html_e( 'Export EAN CSV', 'woo-barcode-pro' ); ?></a>
		</div>

		<div class="wcbp-card">
			<h2><?php esc_html_e( 'EAN CSV Import', 'woo-barcode-pro' ); ?></h2>
			<p><?php esc_html_e( 'Upload a CSV with columns: product_id, ean. Existing EAN codes will be overwritten.', 'woo-barcode-pro' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wcbp_import_ean' ); ?>
				<input type="hidden" name="action" value="wcbp_import_ean_csv" />
				<input type="file" name="csv_file" accept=".csv" required />
				<?php submit_button( __( 'Import', 'woo-barcode-pro' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<?php endif; ?>

		<?php if ( 'tools' !== $tab ) : ?>
		<?php submit_button(); ?>
		<?php endif; ?>
	</form>
</div>
