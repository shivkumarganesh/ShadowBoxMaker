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
<meta name="theme-color" content="#2271b1" />
<title><?php esc_html_e( 'Quick Add — WooBarcode Pro', 'woo-barcode-pro' ); ?></title>
<?php wp_print_styles( 'wcbp-quick-add' ); ?>
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

		<!-- Draft product: Scan-to-Publish card -->
		<div id="wcbp-qa-draft-card" style="display:none;margin:16px 0;padding:18px;background:#fff8e1;border:2px solid #ffe082;border-radius:8px;">
			<p style="margin:0 0 4px;font-weight:600;color:#92400e;">⚠️ <?php esc_html_e( 'Draft product scanned — complete to publish', 'woo-barcode-pro' ); ?></p>
			<p style="margin:0 0 14px;font-size:13px;color:#78350f;">SKU: <strong id="wcbp-qa-draft-sku"></strong></p>

			<div class="wcbp-qa-field">
				<label for="wcbp-qa-draft-name"><?php esc_html_e( 'Product Name *', 'woo-barcode-pro' ); ?></label>
				<input id="wcbp-qa-draft-name" type="text" class="wcbp-qa-input"
				       placeholder="<?php esc_attr_e( 'Enter a name for this product…', 'woo-barcode-pro' ); ?>" />
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
				<label><?php esc_html_e( 'Photo', 'woo-barcode-pro' ); ?></label>
				<div class="wcbp-photo-wrap">
					<label class="wcbp-photo-btn" for="wcbp-qa-draft-photo">
						<span class="wcbp-photo-icon">📸</span>
						<?php esc_html_e( 'Take photo', 'woo-barcode-pro' ); ?>
					</label>
					<input id="wcbp-qa-draft-photo" type="file" accept="image/*" capture="environment" style="display:none" />
					<img id="wcbp-qa-draft-preview" src="" alt="" style="display:none;max-width:100px;max-height:100px;margin-top:8px;border-radius:4px;object-fit:cover;" />
				</div>
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

			<div class="wcbp-qa-field">
				<label><?php esc_html_e( 'Photo', 'woo-barcode-pro' ); ?></label>
				<div class="wcbp-photo-wrap">
					<label class="wcbp-photo-btn" for="wcbp-photo-input">
						<span class="wcbp-photo-icon">📸</span>
						<?php esc_html_e( 'Take photo', 'woo-barcode-pro' ); ?>
					</label>
					<input id="wcbp-photo-input" type="file" accept="image/*" capture="environment" style="display:none" />
					<img id="wcbp-photo-preview" src="" alt="" />
				</div>
				<div id="wcbp-photo-status"></div>
			</div>

			<button id="wcbp-save-btn" type="submit"><?php esc_html_e( '✓ Save Product', 'woo-barcode-pro' ); ?></button>
			<div id="wcbp-result"></div>
		</form>

	</div><!-- /#wcbp-tab-add -->

</div>

<?php
wp_localize_script( 'wcbp-quick-add', 'wcbpQuickAdd', array(
	'ajax_url'  => admin_url( 'admin-ajax.php' ),
	'nonce'     => wp_create_nonce( 'wcbp_quick_add' ),
	'inv_nonce' => wp_create_nonce( 'wcbp_inventory' ),
	'strings'   => array(
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
		'view'            => __( 'Edit →', 'woo-barcode-pro' ),
		'error'           => __( 'Something went wrong.', 'woo-barcode-pro' ),
		'no_camera_api'   => __( 'Live scanning not supported on this browser. Please type the barcode manually.', 'woo-barcode-pro' ),
		'camera_error'    => __( 'Could not access camera:', 'woo-barcode-pro' ),
		'publish_btn'     => __( 'Publish Product', 'woo-barcode-pro' ),
		'publishing'      => __( 'Publishing…', 'woo-barcode-pro' ),
		'published_ok'    => __( 'Product published!', 'woo-barcode-pro' ),
	),
) );
wp_print_scripts( 'wcbp-quick-add' );
?>
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
