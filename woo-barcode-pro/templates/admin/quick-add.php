<?php
/**
 * Mobile Quick Add page.
 *
 * Available: $settings (array), $price_templates (array), $categories (WP_Term[]).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="theme-color" content="#2271b1" />
<title><?php esc_html_e( 'Quick Add — WooBarcode Pro', 'woo-barcode-pro' ); ?></title>
<link rel="manifest" href="<?php echo esc_url( home_url( '/?wcbp_pwa=manifest' ) ); ?>" />
<link rel="apple-touch-icon" href="<?php echo esc_url( home_url( '/?wcbp_pwa=icon&s=180' ) ); ?>" />
<link rel="stylesheet" href="<?php echo esc_url( WCBP_PLUGIN_URL . 'assets/css/quick-add.css?ver=' . WCBP_VERSION ); ?>" />
</head>
<body class="wcbp-quick-add-body">

<div class="wcbp-qa-wrap">
	<div class="wcbp-qa-header">
		<h1>📦 <?php esc_html_e( 'Quick Add', 'woo-barcode-pro' ); ?></h1>
		<span class="wcbp-queue-count" title="<?php esc_attr_e( 'Items in print queue', 'woo-barcode-pro' ); ?>">
			<?php echo esc_html( \WCBarcodePro\Admin\PrintQueue::get_instance()->get_count() ); ?>
		</span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcbp-print-queue' ) ); ?>" style="margin-left:auto;font-size:13px;color:#2271b1">
			<?php esc_html_e( 'Queue →', 'woo-barcode-pro' ); ?>
		</a>
	</div>

	<!-- Tab navigation -->
	<div class="wcbp-qa-tabs">
		<button class="wcbp-qa-tab-btn wcbp-active" data-tab="scan">📷 <?php esc_html_e( 'Scan &amp; Publish', 'woo-barcode-pro' ); ?></button>
		<button class="wcbp-qa-tab-btn" data-tab="add">➕ <?php esc_html_e( 'Quick Add', 'woo-barcode-pro' ); ?></button>
		<button class="wcbp-qa-tab-btn" data-tab="tally">🔢 <?php esc_html_e( 'Tally', 'woo-barcode-pro' ); ?></button>
	</div>

	<!-- ── Tab 1: Scan & Publish ── -->
	<div id="wcbp-tab-scan" class="wcbp-qa-tab-content wcbp-active">

		<div class="wcbp-qa-scan-row">
			<input id="wcbp-barcode-input" type="text" inputmode="text"
			       placeholder="<?php esc_attr_e( 'Scan or type a barcode…', 'woo-barcode-pro' ); ?>"
			       autocomplete="off" autocorrect="off" spellcheck="false" />
			<button id="wcbp-lookup-btn" type="button" title="<?php esc_attr_e( 'Look up barcode', 'woo-barcode-pro' ); ?>">🔍</button>
			<button id="wcbp-scan-btn" type="button" title="<?php esc_attr_e( 'Scan barcode with camera', 'woo-barcode-pro' ); ?>">📷</button>
		</div>
		<div id="wcbp-scan-status"></div>
		<?php include WCBP_PLUGIN_DIR . 'templates/admin/camera-modal.php'; ?>

		<!-- Draft inventory panel -->
		<div id="wcbp-draft-panel" style="margin-bottom:16px">
			<button type="button" id="wcbp-draft-toggle" class="wcbp-draft-toggle">
				📋 <?php esc_html_e( 'Draft Inventory', 'woo-barcode-pro' ); ?>
				<span id="wcbp-draft-badge" class="wcbp-queue-count" style="display:none"></span>
				<span id="wcbp-draft-chevron" style="margin-left:auto;font-size:12px">▾</span>
			</button>
			<div id="wcbp-draft-list" style="display:none;background:#fff;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;overflow:hidden">
				<div id="wcbp-draft-list-inner" style="padding:8px">
					<p style="margin:8px 4px;font-size:13px;color:#888"><?php esc_html_e( 'Loading…', 'woo-barcode-pro' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Draft product: Scan-to-Publish card -->
		<div id="wcbp-qa-draft-card" style="display:none;margin:16px 0;padding:18px;background:#fff8e1;border:2px solid #ffe082;border-radius:8px;">
			<p style="margin:0 0 4px;font-weight:600;color:#92400e;">⚠️ <?php esc_html_e( 'Draft product scanned — complete to publish', 'woo-barcode-pro' ); ?></p>
			<p style="margin:0 0 14px;font-size:13px;color:#78350f;">SKU: <strong id="wcbp-qa-draft-sku"></strong></p>

			<div class="wcbp-qa-field">
				<label for="wcbp-qa-draft-name"><?php esc_html_e( 'Product Name *', 'woo-barcode-pro' ); ?></label>
				<input id="wcbp-qa-draft-name" type="text" class="wcbp-qa-input"
				       placeholder="<?php esc_attr_e( 'Enter a name for this product…', 'woo-barcode-pro' ); ?>" />
			</div>

			<div class="wcbp-qa-field wcbp-qa-field--inline">
				<label for="wcbp-qa-draft-stock-qty"><?php esc_html_e( 'Units in stock', 'woo-barcode-pro' ); ?></label>
				<input id="wcbp-qa-draft-stock-qty" type="number" min="0" step="1" value="1" class="wcbp-qty-input" />
			</div>

			<?php if ( ! empty( $categories ) ) : ?>
			<div class="wcbp-qa-field">
				<label for="wcbp-qa-draft-categories"><?php esc_html_e( 'Categories', 'woo-barcode-pro' ); ?></label>
				<select id="wcbp-qa-draft-categories" multiple>
					<?php foreach ( (array) $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="wcbp-qa-field">
				<label><?php esc_html_e( 'Photos', 'woo-barcode-pro' ); ?> <span style="font-weight:400;color:#999">(<?php esc_html_e( 'up to 5', 'woo-barcode-pro' ); ?>)</span></label>
				<div class="wcbp-photo-wrap">
					<label class="wcbp-photo-btn" for="wcbp-qa-draft-photo-camera">
						<span class="wcbp-photo-icon">📷</span>
						<?php esc_html_e( 'Take photo', 'woo-barcode-pro' ); ?>
					</label>
					<input id="wcbp-qa-draft-photo-camera" type="file" accept="image/*" capture="environment" style="display:none" />
					<label class="wcbp-photo-btn wcbp-photo-btn--upload" for="wcbp-qa-draft-photo-upload">
						<span class="wcbp-photo-icon">🖼</span>
						<?php esc_html_e( 'Upload', 'woo-barcode-pro' ); ?>
					</label>
					<input id="wcbp-qa-draft-photo-upload" type="file" accept="image/*" style="display:none" />
					<span class="wcbp-photo-count" id="wcbp-qa-draft-photo-count">0 / 5</span>
				</div>
				<div class="wcbp-photo-gallery" id="wcbp-qa-draft-gallery"></div>
				<div id="wcbp-qa-draft-photo-status" style="font-size:13px;margin-top:4px;"></div>
			</div>

			<button id="wcbp-qa-publish-btn" type="button" style="background:#16a34a;color:#fff;border:none;padding:12px 24px;border-radius:6px;font-size:16px;font-weight:600;width:100%;cursor:pointer;margin-top:4px;">
				✓ <?php esc_html_e( 'Publish Product', 'woo-barcode-pro' ); ?>
			</button>
			<div id="wcbp-qa-draft-result" style="margin-top:10px;font-weight:600;"></div>
			<input type="hidden" id="wcbp-qa-draft-product-id" value="" />
		</div>

	</div><!-- /#wcbp-tab-scan -->

	<!-- ── Tab 2: Quick Add ── -->
	<div id="wcbp-tab-add" class="wcbp-qa-tab-content">

		<?php if ( ! empty( $price_templates ) ) : ?>
		<div style="margin-bottom:12px;font-size:13px;color:#555">
			<?php esc_html_e( 'Or tap a template:', 'woo-barcode-pro' ); ?>
			<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
			<?php foreach ( $price_templates as $tpl ) : ?>
				<button type="button" class="button button-secondary"
				        onclick="wcbpLookup('<?php echo esc_js( $tpl['barcode_value'] ); ?>')">
					<?php echo esc_html( $tpl['name'] ); ?>
				</button>
			<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<form id="wcbp-quick-form" class="wcbp-qa-form" autocomplete="off">
			<input type="hidden" id="wcbp-template-id" name="template_id" value="" />

			<div class="wcbp-qa-field">
				<label for="wcbp-name"><?php esc_html_e( 'Product Name *', 'woo-barcode-pro' ); ?></label>
				<input id="wcbp-name" name="name" type="text"
				       placeholder="<?php esc_attr_e( 'Enter product name…', 'woo-barcode-pro' ); ?>" required />
			</div>

			<div class="wcbp-qa-field">
				<label for="wcbp-price"><?php esc_html_e( 'Price', 'woo-barcode-pro' ); ?> (<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>)</label>
				<input id="wcbp-price" name="price" type="number" step="0.01" min="0"
				       placeholder="0.00" />
			</div>

			<?php if ( ! empty( $categories ) ) : ?>
			<div class="wcbp-qa-field">
				<label for="wcbp-categories"><?php esc_html_e( 'Categories', 'woo-barcode-pro' ); ?></label>
				<select id="wcbp-categories" name="category_ids[]" multiple style="height:80px">
					<?php foreach ( (array) $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="wcbp-qa-field">
				<label for="wcbp-sku"><?php esc_html_e( 'SKU', 'woo-barcode-pro' ); ?> <span style="font-weight:400;color:#999">(<?php esc_html_e( 'optional', 'woo-barcode-pro' ); ?>)</span></label>
				<input id="wcbp-sku" name="sku" type="text" placeholder="<?php esc_attr_e( 'Leave blank to auto-generate', 'woo-barcode-pro' ); ?>" />
			</div>

			<div class="wcbp-qa-field wcbp-qa-sizes-toggle">
				<label>
					<input type="checkbox" id="wcbp-has-sizes" />
					<?php esc_html_e( 'Sizes (S – XXL)', 'woo-barcode-pro' ); ?>
					<span style="font-weight:400;color:#999"><?php esc_html_e( '— optional colors per size', 'woo-barcode-pro' ); ?></span>
				</label>
			</div>

			<div class="wcbp-qa-field" id="wcbp-sizes-panel" style="display:none">
				<?php foreach ( \WCBarcodePro\Admin\QuickAdd::SIZES as $size_code ) : ?>
				<div class="wcbp-size-row" data-size="<?php echo esc_attr( $size_code ); ?>">
					<div class="wcbp-size-main">
						<span class="wcbp-size-code"><?php echo esc_html( $size_code ); ?></span>
						<input type="text" class="wcbp-size-colors" autocomplete="off" autocapitalize="words"
						       aria-label="<?php echo esc_attr( sprintf( /* translators: %s: size */ __( 'Colors for %s', 'woo-barcode-pro' ), $size_code ) ); ?>"
						       placeholder="<?php esc_attr_e( 'Colors (optional): Red, Blue', 'woo-barcode-pro' ); ?>" />
					</div>
					<div class="wcbp-size-line wcbp-size-line--plain">
						<span class="wcbp-line-label"></span>
						<span class="wcbp-size-stepper">
							<button type="button" class="wcbp-size-minus" aria-label="<?php esc_attr_e( 'Less', 'woo-barcode-pro' ); ?>">−</button>
							<input type="number" class="wcbp-size-qty" min="0" step="1" value="0" inputmode="numeric" aria-label="<?php echo esc_attr( $size_code ); ?>" />
							<button type="button" class="wcbp-size-plus" aria-label="<?php esc_attr_e( 'More', 'woo-barcode-pro' ); ?>">+</button>
						</span>
						<input type="number" class="wcbp-size-price" step="0.01" min="0" inputmode="decimal" placeholder="<?php esc_attr_e( 'price', 'woo-barcode-pro' ); ?>" />
					</div>
					<div class="wcbp-size-color-lines"></div>
				</div>
				<?php endforeach; ?>
				<p class="wcbp-size-help">
					<span id="wcbp-size-total">0</span> <?php esc_html_e( 'units total. Type colors next to a size (comma-separated) to get a line per color. Lines left at 0 are not created; a blank price uses the main price.', 'woo-barcode-pro' ); ?>
				</p>
			</div>

			<div class="wcbp-qa-field wcbp-qa-field--inline" id="wcbp-stock-qty-field">
				<label for="wcbp-stock-qty"><?php esc_html_e( 'Units in stock', 'woo-barcode-pro' ); ?></label>
				<input id="wcbp-stock-qty" name="stock_qty" type="number" min="0" step="1" value="1" class="wcbp-qty-input" />
			</div>

			<div class="wcbp-qa-field">
				<label><?php esc_html_e( 'Photos', 'woo-barcode-pro' ); ?> <span style="font-weight:400;color:#999">(<?php esc_html_e( 'up to 5', 'woo-barcode-pro' ); ?>)</span></label>
				<div class="wcbp-photo-wrap">
					<label class="wcbp-photo-btn" for="wcbp-photo-camera">
						<span class="wcbp-photo-icon">📷</span>
						<?php esc_html_e( 'Take photo', 'woo-barcode-pro' ); ?>
					</label>
					<input id="wcbp-photo-camera" type="file" accept="image/*" capture="environment" style="display:none" />
					<label class="wcbp-photo-btn wcbp-photo-btn--upload" for="wcbp-photo-upload">
						<span class="wcbp-photo-icon">🖼</span>
						<?php esc_html_e( 'Upload', 'woo-barcode-pro' ); ?>
					</label>
					<input id="wcbp-photo-upload" type="file" accept="image/*" style="display:none" />
					<span class="wcbp-photo-count" id="wcbp-photo-count">0 / 5</span>
				</div>
				<div class="wcbp-photo-gallery" id="wcbp-photo-gallery"></div>
				<div id="wcbp-photo-status"></div>
			</div>

			<button id="wcbp-save-btn" type="submit"><?php esc_html_e( '✓ Save Product', 'woo-barcode-pro' ); ?></button>
			<div id="wcbp-result"></div>
		</form>

	</div><!-- /#wcbp-tab-add -->

	<!-- ── Tab 3: Tally (scan-to-count) ── -->
	<div id="wcbp-tab-tally" class="wcbp-qa-tab-content">
		<form id="wcbp-tally-form" class="wcbp-qa-scan-row" autocomplete="off">
			<input id="wcbp-tally-input" type="text" inputmode="text" enterkeyhint="go"
			       placeholder="<?php esc_attr_e( 'Scan any label…', 'woo-barcode-pro' ); ?>" />
			<button type="submit" id="wcbp-tally-go" class="button"><?php esc_html_e( 'Go', 'woo-barcode-pro' ); ?></button>
			<button type="button" id="wcbp-tally-camera" class="button" title="<?php esc_attr_e( 'Scan with camera', 'woo-barcode-pro' ); ?>">📷</button>
		</form>

		<div id="wcbp-tally-flash" class="wcbp-tally-flash" role="status" aria-live="polite"></div>

		<div id="wcbp-tally-card" style="display:none">
			<div class="wcbp-tally-head">
				<img id="wcbp-tally-img" alt="" style="display:none" />
				<div>
					<div id="wcbp-tally-name" class="wcbp-tally-name"></div>
					<div id="wcbp-tally-summary" class="wcbp-tally-summary"></div>
				</div>
			</div>
			<table class="wcbp-tally-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'woo-barcode-pro' ); ?></th>
						<th><?php esc_html_e( 'Stock', 'woo-barcode-pro' ); ?></th>
						<th><?php esc_html_e( 'Scanned', 'woo-barcode-pro' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="wcbp-tally-rows"></tbody>
			</table>
			<div class="wcbp-tally-actions">
				<button type="button" id="wcbp-tally-queue-missing" class="button button-primary"></button>
				<button type="button" id="wcbp-tally-apply-all" class="button"></button>
				<button type="button" id="wcbp-tally-reset" class="button-link"><?php esc_html_e( 'Reset counts', 'woo-barcode-pro' ); ?></button>
			</div>
			<div id="wcbp-tally-result"></div>
		</div>

		<p class="wcbp-tally-help">
			<?php esc_html_e( 'Scan every item of a product once. Green = all counted. Orange = fewer scanned than in stock (missing labels or missing items). Red = more scanned than in stock. Check that the size shown matches the item\'s tag. ✏️ edits the stock count; 🏷 queues one more label.', 'woo-barcode-pro' ); ?>
		</p>
	</div><!-- /#wcbp-tab-tally -->

</div>

<script>
window.wcbpQuickAdd = <?php echo wp_json_encode( array(
	'ajax_url'   => admin_url( 'admin-ajax.php' ),
	'nonce'      => wp_create_nonce( 'wcbp_quick_add' ),
	'inv_nonce'  => wp_create_nonce( 'wcbp_inventory' ),
	'plugin_url' => WCBP_PLUGIN_URL,
	'strings'    => array(
		'looking_up'      => __( 'Looking up barcode…', 'woo-barcode-pro' ),
		'template_found'  => __( 'Template found', 'woo-barcode-pro' ),
		'product_exists'  => __( 'Product already exists', 'woo-barcode-pro' ),
		'unknown_barcode' => __( 'Unknown barcode — please enter details manually.', 'woo-barcode-pro' ),
		'uploading'       => __( 'Uploading photo…', 'woo-barcode-pro' ),
		'photo_ready'     => __( 'Photo ready ✓', 'woo-barcode-pro' ),
		'upload_failed'   => __( 'Photo upload failed.', 'woo-barcode-pro' ),
		'saving'          => __( 'Saving…', 'woo-barcode-pro' ),
		'save'            => __( '✓ Save Product', 'woo-barcode-pro' ),
		'saved'           => __( 'Product created!', 'woo-barcode-pro' ),
		'sizes_required'  => __( 'Enter units for at least one size.', 'woo-barcode-pro' ),
		/* translators: %s: list of sizes */
		'colors_required' => __( 'Add a color for %s, or remove the colors from every size.', 'woo-barcode-pro' ),
		/* translators: 1: scanned count, 2: stock count */
		't_of'            => __( '%1$d of %2$d', 'woo-barcode-pro' ),
		't_more'          => __( 'more than in stock!', 'woo-barcode-pro' ),
		't_untracked'     => __( 'stock not tracked', 'woo-barcode-pro' ),
		't_parent_code'   => __( 'That is the main product barcode — scan a size label to count it.', 'woo-barcode-pro' ),
		/* translators: 1: scanned total, 2: stock total */
		't_summary'       => __( 'Scanned %1$d of %2$d in stock', 'woo-barcode-pro' ),
		/* translators: %d: number of labels */
		't_queue_missing' => __( '🏷 Queue missing labels (%d)', 'woo-barcode-pro' ),
		't_apply_all'     => __( '✓ Set stock to counted', 'woo-barcode-pro' ),
		/* translators: %d: number of labels */
		't_confirm_queue' => __( 'Add %d labels to the print queue for the items that were not scanned?', 'woo-barcode-pro' ),
		/* translators: %d: number of rows */
		't_confirm_apply' => __( 'Set the stock of %d row(s) to the scanned count?', 'woo-barcode-pro' ),
		't_confirm_reset' => __( 'Clear the scanned counts for this product?', 'woo-barcode-pro' ),
		't_saved'         => __( 'Stock updated.', 'woo-barcode-pro' ),
		't_in_queue'      => __( 'in queue', 'woo-barcode-pro' ),
		't_edit'          => __( 'Edit stock', 'woo-barcode-pro' ),
		't_reprint'       => __( 'Queue one more label', 'woo-barcode-pro' ),
		't_undo_scan'     => __( 'Remove one scan', 'woo-barcode-pro' ),
		't_use_counted'   => __( '= counted', 'woo-barcode-pro' ),
		'save_btn'        => __( 'Save', 'woo-barcode-pro' ),
		'cancel'          => __( 'Cancel', 'woo-barcode-pro' ),
		'less'            => __( 'Less', 'woo-barcode-pro' ),
		'more'            => __( 'More', 'woo-barcode-pro' ),
		'view'            => __( 'Edit →', 'woo-barcode-pro' ),
		'error'           => __( 'Something went wrong.', 'woo-barcode-pro' ),
		'no_camera_api'   => __( 'Live scanning not supported on this browser. Please type the barcode manually.', 'woo-barcode-pro' ),
		'camera_error'    => __( 'Could not access camera:', 'woo-barcode-pro' ),
		'publish_btn'       => __( 'Publish Product', 'woo-barcode-pro' ),
		'publishing'        => __( 'Publishing…', 'woo-barcode-pro' ),
		'published_ok'      => __( 'Product published!', 'woo-barcode-pro' ),
		'no_drafts'         => __( 'No draft products found.', 'woo-barcode-pro' ),
		'tap_to_complete'   => __( 'Tap to complete & publish', 'woo-barcode-pro' ),
		'unit'              => __( 'unit', 'woo-barcode-pro' ),
		'units'             => __( 'units', 'woo-barcode-pro' ),
	),
) ); ?>;
</script>
<script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
<script src="<?php echo esc_url( WCBP_PLUGIN_URL . 'assets/js/quick-add.js?ver=' . WCBP_VERSION ); ?>"></script>
<script>
function wcbpSwitchTab(name) {
	document.querySelectorAll('.wcbp-qa-tab-btn').forEach(function(b){ b.classList.remove('wcbp-active'); });
	document.querySelectorAll('.wcbp-qa-tab-content').forEach(function(c){ c.classList.remove('wcbp-active'); });
	var btn = document.querySelector('.wcbp-qa-tab-btn[data-tab="' + name + '"]');
	var pane = document.getElementById('wcbp-tab-' + name);
	if (btn) btn.classList.add('wcbp-active');
	if (pane) pane.classList.add('wcbp-active');
}
document.querySelectorAll('.wcbp-qa-tab-btn').forEach(function(btn){
	btn.addEventListener('click', function(){ wcbpSwitchTab(btn.dataset.tab); });
});
// Template buttons call lookup; on match the JS will switch to the add tab.
function wcbpLookup(v){ if(typeof lookupBarcode==='function') lookupBarcode(v); }
</script>
</body>
</html>
