<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin pages: Settings + Print Queue manager.
 */
class WSBG_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'SKU & Barcode Settings', 'wsbg' ),
			__( 'SKU & Barcodes', 'wsbg' ),
			'manage_woocommerce',
			WSBG_SLUG,
			[ __CLASS__, 'render_settings_page' ]
		);
		add_submenu_page(
			'woocommerce',
			__( 'Print Queue', 'wsbg' ),
			__( 'Print Queue', 'wsbg' ),
			'manage_woocommerce',
			WSBG_SLUG . '-queue',
			[ __CLASS__, 'render_queue_page' ]
		);
	}

	// ── Settings page ─────────────────────────────────────────────────────────

	public static function render_settings_page(): void {
		$opts    = WSBG_Settings::get();
		$presets = WSBG_Print_Queue::label_presets();
		$tab     = sanitize_key( $_GET['tab'] ?? 'general' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SKU & Barcode Generator', 'wsbg' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( [ 'general' => __( 'General', 'wsbg' ), 'labels' => __( 'Labels', 'wsbg' ), 'tools' => __( 'Tools', 'wsbg' ) ] as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WSBG_SLUG . '&tab=' . $slug ) ); ?>"
					   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" style="margin-top:1em;">
				<?php settings_fields( WSBG_SLUG ); ?>

				<?php if ( $tab === 'general' ) : ?>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Auto-generate SKU', 'wsbg' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo WSBG_OPTION; ?>[auto_sku]" value="1" <?php checked( $opts['auto_sku'] ); ?>> <?php esc_html_e( 'Assign SKU automatically when a product is saved without one', 'wsbg' ); ?></label></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'SKU Prefix', 'wsbg' ); ?></th>
							<td><input type="text" name="<?php echo WSBG_OPTION; ?>[sku_prefix]" value="<?php echo esc_attr( $opts['sku_prefix'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'SKU Number Padding', 'wsbg' ); ?></th>
							<td><input type="number" min="1" max="12" name="<?php echo WSBG_OPTION; ?>[sku_padding]" value="<?php echo esc_attr( $opts['sku_padding'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Zero-pad the numeric portion to this many digits.', 'wsbg' ); ?></p></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'SKU Source', 'wsbg' ); ?></th>
							<td>
								<select name="<?php echo WSBG_OPTION; ?>[sku_source]">
									<option value="id" <?php selected( $opts['sku_source'], 'id' ); ?>><?php esc_html_e( 'Product ID (stable)', 'wsbg' ); ?></option>
									<option value="sequential" <?php selected( $opts['sku_source'], 'sequential' ); ?>><?php esc_html_e( 'Sequential counter', 'wsbg' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Barcode Format', 'wsbg' ); ?></th>
							<td>
								<select name="<?php echo WSBG_OPTION; ?>[barcode_format]">
									<option value="code128" <?php selected( $opts['barcode_format'], 'code128' ); ?>>Code 128</option>
									<option value="ean13"   <?php selected( $opts['barcode_format'], 'ean13' ); ?>>EAN-13</option>
								</select>
								<p class="description"><?php esc_html_e( 'EAN-13 requires a 12-digit numeric SKU. Falls back to Code 128 if invalid.', 'wsbg' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Show Barcode on Product Page', 'wsbg' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo WSBG_OPTION; ?>[show_barcode_product]" value="1" <?php checked( $opts['show_barcode_product'] ); ?>></label></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Show Barcode in Shop/Archive', 'wsbg' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo WSBG_OPTION; ?>[show_barcode_shop]" value="1" <?php checked( $opts['show_barcode_shop'] ); ?>></label></td>
						</tr>
					</table>

				<?php elseif ( $tab === 'labels' ) : ?>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Label Preset', 'wsbg' ); ?></th>
							<td>
								<select name="<?php echo WSBG_OPTION; ?>[print_label_preset]">
									<?php foreach ( $presets as $key => $p ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $opts['print_label_preset'], $key ); ?>><?php echo esc_html( $p['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Layout', 'wsbg' ); ?></th>
							<td>
								<select name="<?php echo WSBG_OPTION; ?>[print_label_layout]">
									<option value="horizontal" <?php selected( $opts['print_label_layout'], 'horizontal' ); ?>><?php esc_html_e( 'Horizontal', 'wsbg' ); ?></option>
									<option value="vertical"   <?php selected( $opts['print_label_layout'], 'vertical' ); ?>><?php esc_html_e( 'Vertical', 'wsbg' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Label Content', 'wsbg' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo WSBG_OPTION; ?>[label_show_name]" value="1" <?php checked( $opts['label_show_name'] ); ?>> <?php esc_html_e( 'Product Name', 'wsbg' ); ?></label><br>
								<label><input type="checkbox" name="<?php echo WSBG_OPTION; ?>[label_show_price]" value="1" <?php checked( $opts['label_show_price'] ); ?>> <?php esc_html_e( 'Price', 'wsbg' ); ?></label><br>
								<label><input type="checkbox" name="<?php echo WSBG_OPTION; ?>[label_show_sku]" value="1" <?php checked( $opts['label_show_sku'] ); ?>> <?php esc_html_e( 'SKU', 'wsbg' ); ?></label>
							</td>
						</tr>
					</table>

				<?php elseif ( $tab === 'tools' ) : ?>
					<h2><?php esc_html_e( 'Bulk SKU Regeneration', 'wsbg' ); ?></h2>
					<p><?php esc_html_e( 'Re-generate SKUs for all products using the current settings.', 'wsbg' ); ?></p>
					<label>
						<input type="checkbox" id="wsbg_overwrite"> <?php esc_html_e( 'Overwrite existing SKUs', 'wsbg' ); ?>
					</label><br><br>
					<button type="button" id="wsbg_bulk_regen" class="button button-secondary">
						<?php esc_html_e( 'Run Bulk SKU Generation', 'wsbg' ); ?>
					</button>
					<span id="wsbg_regen_result" style="margin-left:10px;"></span>

					<script>
					jQuery(function($){
						$('#wsbg_bulk_regen').on('click', function(){
							var btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Working…', 'wsbg' ) ); ?>');
							$.post(ajaxurl, {
								action: 'wsbg_bulk_regen_sku',
								nonce:  '<?php echo esc_js( wp_create_nonce( 'wsbg_bulk_regen' ) ); ?>',
								overwrite: $('#wsbg_overwrite').is(':checked') ? 1 : 0
							}, function(res){
								btn.prop('disabled', false).text('<?php echo esc_js( __( 'Run Bulk SKU Generation', 'wsbg' ) ); ?>');
								if (res.success) {
									$('#wsbg_regen_result').text('<?php echo esc_js( __( 'Updated:', 'wsbg' ) ); ?> ' + res.data.updated);
								}
							});
						});
					});
					</script>

					<hr>
					<h2><?php esc_html_e( 'EAN Import', 'wsbg' ); ?></h2>
					<p><?php esc_html_e( 'Paste one entry per line: SKU,EAN13 or ProductID,EAN13', 'wsbg' ); ?></p>
					<textarea id="wsbg_ean_import" rows="8" class="large-text" placeholder="SKU-00001,5901234123457"></textarea><br><br>
					<button type="button" id="wsbg_ean_import_btn" class="button button-secondary">
						<?php esc_html_e( 'Import EANs', 'wsbg' ); ?>
					</button>
					<span id="wsbg_ean_import_result" style="margin-left:10px;"></span>

					<script>
					jQuery(function($){
						$('#wsbg_ean_import_btn').on('click', function(){
							var btn = $(this).prop('disabled', true);
							$.post(ajaxurl, {
								action: 'wsbg_ean_import',
								nonce:  '<?php echo esc_js( wp_create_nonce( 'wsbg_ean_import' ) ); ?>',
								data:   $('#wsbg_ean_import').val()
							}, function(res){
								btn.prop('disabled', false);
								if (res.success) {
									$('#wsbg_ean_import_result').text('<?php echo esc_js( __( 'Imported:', 'wsbg' ) ); ?> ' + res.data.imported);
								}
							});
						});
					});
					</script>
				<?php endif; ?>

				<?php if ( $tab !== 'tools' ) : ?>
					<?php submit_button(); ?>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	// ── Print queue management page ───────────────────────────────────────────

	public static function render_queue_page(): void {
		$queue    = (array) get_option( 'wsbg_queue', [] );
		$print_url = wp_nonce_url( admin_url( 'admin.php?wsbg_print=1' ), 'wsbg_print' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Print Queue', 'wsbg' ); ?></h1>

			<?php if ( empty( $queue ) ) : ?>
				<p><?php esc_html_e( 'The print queue is empty. Add products from the product list or product edit page.', 'wsbg' ); ?></p>
			<?php else : ?>
				<p>
					<a href="<?php echo esc_url( $print_url ); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e( 'Print Labels', 'wsbg' ); ?>
					</a>
					&nbsp;
					<button type="button" id="wsbg_clear_queue" class="button button-secondary">
						<?php esc_html_e( 'Clear Queue', 'wsbg' ); ?>
					</button>
				</p>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Product', 'wsbg' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'wsbg' ); ?></th>
						<th><?php esc_html_e( 'Qty', 'wsbg' ); ?></th>
						<th><?php esc_html_e( 'Barcode', 'wsbg' ); ?></th>
						<th></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $queue as $id => $qty ) :
						$p = wc_get_product( $id );
						if ( ! $p ) continue;
					?>
						<tr data-id="<?php echo esc_attr( $id ); ?>">
							<td><?php echo esc_html( $p->get_name() ); ?></td>
							<td><?php echo esc_html( $p->get_sku() ); ?></td>
							<td><input type="number" min="1" value="<?php echo esc_attr( $qty ); ?>" class="wsbg-qty small-text" style="width:55px;"></td>
							<td style="max-width:120px;"><?php echo WSBG_Barcode::svg_for_product( $p ); ?></td>
							<td><button type="button" class="button wsbg-remove-from-queue" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wsbg_queue' ) ); ?>"><?php esc_html_e( 'Remove', 'wsbg' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<script>
		jQuery(function($){
			$('#wsbg_clear_queue').on('click', function(){
				$.post(ajaxurl, { action:'wsbg_queue_clear', nonce:'<?php echo esc_js( wp_create_nonce( 'wsbg_queue' ) ); ?>' }, function(){ location.reload(); });
			});
			$('.wsbg-remove-from-queue').on('click', function(){
				var $tr = $(this).closest('tr');
				$.post(ajaxurl, { action:'wsbg_queue_remove', nonce:$(this).data('nonce'), id:$tr.data('id') }, function(){ $tr.remove(); });
			});
		});
		</script>
		<?php
	}
}
