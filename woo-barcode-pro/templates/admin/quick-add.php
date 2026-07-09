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

	<!-- Scan / enter barcode -->
	<div class="wcbp-qa-scan-row">
		<input id="wcbp-barcode-input" type="text" inputmode="none"
		       placeholder="<?php esc_attr_e( 'Scan or type price template barcode…', 'woo-barcode-pro' ); ?>"
		       autocomplete="off" autocorrect="off" spellcheck="false" />
		<button id="wcbp-scan-btn" title="<?php esc_attr_e( 'Scan barcode', 'woo-barcode-pro' ); ?>">📷</button>
	</div>
	<input id="wcbp-barcode-file" type="file" accept="image/*" capture="environment" style="display:none" />
	<div id="wcbp-scan-status"></div>

	<?php if ( ! empty( $price_templates ) ) : ?>
	<div style="margin-bottom:12px;font-size:13px;color:#555">
		<?php esc_html_e( 'Or tap a template:', 'woo-barcode-pro' ); ?>
		<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
		<?php foreach ( $price_templates as $tpl ) : ?>
			<button type="button" class="button button-secondary"
			        onclick="document.getElementById('wcbp-barcode-input').value='<?php echo esc_js( $tpl['barcode_value'] ); ?>';wcbpLookup('<?php echo esc_js( $tpl['barcode_value'] ); ?>')">
				<?php echo esc_html( $tpl['name'] ); ?>
			</button>
		<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Product form -->
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

		<!-- Photo capture -->
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
</div>

<?php
wp_localize_script( 'wcbp-quick-add', 'wcbpQuickAdd', array(
	'ajax_url' => admin_url( 'admin-ajax.php' ),
	'nonce'    => wp_create_nonce( 'wcbp_quick_add' ),
	'strings'  => array(
		'looking_up'      => __( 'Looking up barcode…', 'woo-barcode-pro' ),
		'template_found'  => __( 'Template found', 'woo-barcode-pro' ),
		'product_exists'  => __( 'Product already exists', 'woo-barcode-pro' ),
		'unknown_barcode' => __( 'Unknown barcode — please enter details manually.', 'woo-barcode-pro' ),
		'no_barcode'      => __( 'No barcode detected in image.', 'woo-barcode-pro' ),
		'uploading'       => __( 'Uploading photo…', 'woo-barcode-pro' ),
		'photo_ready'     => __( 'Photo ready ✓', 'woo-barcode-pro' ),
		'upload_failed'   => __( 'Photo upload failed.', 'woo-barcode-pro' ),
		'saving'          => __( 'Saving…', 'woo-barcode-pro' ),
		'save'            => __( '✓ Save Product', 'woo-barcode-pro' ),
		'saved'           => __( 'Product created!', 'woo-barcode-pro' ),
		'view'            => __( 'Edit →', 'woo-barcode-pro' ),
		'error'           => __( 'Something went wrong.', 'woo-barcode-pro' ),
	),
) );
wp_print_scripts( 'wcbp-quick-add' );
?>
<script>
// Allow quick-tap template buttons to trigger lookup
function wcbpLookup(v){ /* handled in quick-add.js lookupBarcode() */ }
</script>
</body>
</html>
