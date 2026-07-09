<?php
/**
 * Inventory management page.
 *
 * Available: $low_stock_threshold (int).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Inventory', 'woo-barcode-pro' ); ?></h1>

	<nav class="nav-tab-wrapper wcbp-inv-tabs">
		<a href="#wcbp-tab-scanner"   class="nav-tab nav-tab-active"><?php esc_html_e( 'Scanner',    'woo-barcode-pro' ); ?></a>
		<a href="#wcbp-tab-lowstock"  class="nav-tab"               ><?php esc_html_e( 'Low Stock',  'woo-barcode-pro' ); ?></a>
		<a href="#wcbp-tab-history"   class="nav-tab"               ><?php esc_html_e( 'History',    'woo-barcode-pro' ); ?></a>
	</nav>

	<!-- ── Scanner ── -->
	<div id="wcbp-tab-scanner" class="wcbp-inv-tab wcbp-card" style="max-width:640px;margin-top:16px;">
		<h2><?php esc_html_e( 'Scan a Barcode', 'woo-barcode-pro' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Point your scanner at a product barcode or type a SKU. Press Enter to look it up.', 'woo-barcode-pro' ); ?></p>

		<div style="display:flex;gap:8px;margin-bottom:16px;">
			<input type="text" id="wcbp-inv-barcode" class="regular-text"
				placeholder="<?php esc_attr_e( 'Scan or type barcode / SKU…', 'woo-barcode-pro' ); ?>"
				autofocus autocomplete="off" />
			<button id="wcbp-inv-camera-btn" class="button" title="<?php esc_attr_e( 'Live camera scan', 'woo-barcode-pro' ); ?>">📷</button>
			<button id="wcbp-inv-lookup-btn" class="button button-primary"><?php esc_html_e( 'Look Up', 'woo-barcode-pro' ); ?></button>
		</div>
		<?php include WCBP_PLUGIN_DIR . 'templates/admin/camera-modal.php'; ?>

		<div id="wcbp-inv-result" style="display:none;">
			<div class="wcbp-inv-product-card">
				<div class="wcbp-inv-product-info">
					<strong id="wcbp-inv-name"></strong>
					<span id="wcbp-inv-sku" style="color:#888;font-size:13px;"></span>
				</div>
				<div class="wcbp-inv-stock-display">
					<span><?php esc_html_e( 'Current Stock:', 'woo-barcode-pro' ); ?></span>
					<span id="wcbp-inv-qty" class="wcbp-inv-qty-badge"></span>
				</div>
			</div>

			<div class="wcbp-inv-actions">
				<div class="wcbp-inv-action-box">
					<h3><?php esc_html_e( 'Adjust Stock', 'woo-barcode-pro' ); ?></h3>
					<div style="display:flex;gap:8px;align-items:center;">
						<input type="number" id="wcbp-inv-new-qty" class="small-text" min="0" placeholder="0" />
						<input type="text" id="wcbp-inv-note" style="flex:1;" placeholder="<?php esc_attr_e( 'Reason / note (optional)', 'woo-barcode-pro' ); ?>" />
						<button id="wcbp-inv-adjust-btn" class="button button-primary"><?php esc_html_e( 'Set Stock', 'woo-barcode-pro' ); ?></button>
					</div>
					<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Enter the correct quantity and save. Use for stock counts and corrections.', 'woo-barcode-pro' ); ?></p>
				</div>

				<div class="wcbp-inv-action-box wcbp-inv-sell-box">
					<h3><?php esc_html_e( 'Sell One (In-Person)', 'woo-barcode-pro' ); ?></h3>
					<button id="wcbp-inv-sell-btn" class="button button-secondary">
						<?php esc_html_e( '− Sell 1 Unit', 'woo-barcode-pro' ); ?>
					</button>
					<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Deducts 1 from stock and logs it as an in-person sale.', 'woo-barcode-pro' ); ?></p>
				</div>
			</div>

			<div id="wcbp-inv-feedback" class="wcbp-inv-feedback" style="display:none;"></div>
		</div>

		<input type="hidden" id="wcbp-inv-product-id" value="" />
		<input type="hidden" id="wcbp-inv-variation-id" value="" />

		<!-- ── Draft product: Scan-to-Publish card ── -->
		<div id="wcbp-inv-draft-card" style="display:none;margin-top:16px;padding:20px;background:#fff8e1;border:1px solid #ffe082;border-radius:4px;">
			<h3 style="margin-top:0;color:#f59e0b;">
				⚠️ <?php esc_html_e( 'Draft Product — Complete to Publish', 'woo-barcode-pro' ); ?>
			</h3>
			<p style="margin-bottom:4px"><strong><?php esc_html_e( 'SKU:', 'woo-barcode-pro' ); ?></strong> <span id="wcbp-publish-sku"></span></p>

			<table class="form-table" style="max-width:500px;margin-top:12px;">
				<tr>
					<th style="width:120px"><label for="wcbp-publish-name"><?php esc_html_e( 'Product Name *', 'woo-barcode-pro' ); ?></label></th>
					<td><input type="text" id="wcbp-publish-name" class="regular-text" placeholder="<?php esc_attr_e( 'Enter a name for this product…', 'woo-barcode-pro' ); ?>" /></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Photo', 'woo-barcode-pro' ); ?></label></th>
					<td>
						<label class="button" for="wcbp-publish-photo-input">📷 <?php esc_html_e( 'Take / Upload Photo', 'woo-barcode-pro' ); ?></label>
						<input id="wcbp-publish-photo-input" type="file" accept="image/*" capture="environment" style="display:none" />
						<div id="wcbp-publish-photo-status" style="margin-top:4px;font-size:13px;"></div>
						<img id="wcbp-publish-photo-preview" src="" alt="" style="display:none;margin-top:8px;max-width:120px;max-height:120px;border-radius:4px;object-fit:cover;" />
					</td>
				</tr>
			</table>

			<p style="margin-top:16px;">
				<button id="wcbp-publish-btn" class="button button-primary button-large">
					✓ <?php esc_html_e( 'Publish Product', 'woo-barcode-pro' ); ?>
				</button>
			</p>
			<div id="wcbp-publish-feedback" style="display:none;margin-top:10px;font-weight:600;"></div>
			<input type="hidden" id="wcbp-publish-product-id" value="" />
		</div>
	</div>

	<!-- ── Low Stock ── -->
	<div id="wcbp-tab-lowstock" class="wcbp-inv-tab" style="display:none;margin-top:16px;">
		<div class="wcbp-card" style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
			<label><strong><?php esc_html_e( 'Show products with stock ≤', 'woo-barcode-pro' ); ?></strong></label>
			<input type="number" id="wcbp-low-threshold" value="<?php echo esc_attr( $low_stock_threshold ); ?>" min="0" max="9999" class="small-text" />
			<button id="wcbp-low-refresh" class="button button-primary"><?php esc_html_e( 'Refresh', 'woo-barcode-pro' ); ?></button>
		</div>

		<div id="wcbp-low-stock-wrap">
			<table class="wp-list-table widefat fixed striped" id="wcbp-low-stock-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'woo-barcode-pro' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'SKU', 'woo-barcode-pro' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Stock', 'woo-barcode-pro' ); ?></th>
						<th style="width:200px"><?php esc_html_e( 'Set New Qty', 'woo-barcode-pro' ); ?></th>
						<th style="width:140px"><?php esc_html_e( 'Actions', 'woo-barcode-pro' ); ?></th>
					</tr>
				</thead>
				<tbody id="wcbp-low-stock-body">
					<tr><td colspan="5"><?php esc_html_e( 'Click Refresh to load.', 'woo-barcode-pro' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- ── History ── -->
	<div id="wcbp-tab-history" class="wcbp-inv-tab" style="display:none;margin-top:16px;">
		<div class="wcbp-card" style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
			<label><strong><?php esc_html_e( 'Filter by product ID (optional)', 'woo-barcode-pro' ); ?></strong></label>
			<input type="number" id="wcbp-log-product-id" value="" min="0" class="small-text" placeholder="0" />
			<button id="wcbp-log-load" class="button button-primary"><?php esc_html_e( 'Load Log', 'woo-barcode-pro' ); ?></button>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:140px"><?php esc_html_e( 'Date', 'woo-barcode-pro' ); ?></th>
					<th><?php esc_html_e( 'Product', 'woo-barcode-pro' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'Before', 'woo-barcode-pro' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'Change', 'woo-barcode-pro' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'After', 'woo-barcode-pro' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Reason', 'woo-barcode-pro' ); ?></th>
					<th><?php esc_html_e( 'Note', 'woo-barcode-pro' ); ?></th>
				</tr>
			</thead>
			<tbody id="wcbp-log-body">
				<tr><td colspan="7"><?php esc_html_e( 'Click Load Log to view history.', 'woo-barcode-pro' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
