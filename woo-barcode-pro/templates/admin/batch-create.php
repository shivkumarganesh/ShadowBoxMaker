<?php
/**
 * Batch Create admin page.
 *
 * Available: $price_templates (array), $label_templates (array).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcbp-batch-wrap">
	<h1><?php esc_html_e( 'Batch Create Draft Products', 'woo-barcode-pro' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Scaffold N draft products from a price template. All products are created unpublished with auto-generated SKUs and are added to the print queue instantly. Scan each barcode later to add a name, photo, and publish.', 'woo-barcode-pro' ); ?></p>

	<?php if ( empty( $price_templates ) ) : ?>
		<div class="notice notice-warning inline">
			<p><?php printf(
				/* translators: %s: link to price templates */
				esc_html__( 'No price templates found. %s first.', 'woo-barcode-pro' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wcbp-price-templates&action=new' ) ) . '">' . esc_html__( 'Create a price template', 'woo-barcode-pro' ) . '</a>'
			); ?></p>
		</div>
	<?php else : ?>

	<div class="wcbp-batch-form-wrap">
		<table class="form-table" style="max-width:680px">
			<tr>
				<th><label for="wcbp-batch-template"><?php esc_html_e( 'Price Template', 'woo-barcode-pro' ); ?></label></th>
				<td>
					<select id="wcbp-batch-template" style="min-width:280px">
						<option value=""><?php esc_html_e( '— Select a template —', 'woo-barcode-pro' ); ?></option>
						<?php foreach ( $price_templates as $tpl ) : ?>
							<option value="<?php echo esc_attr( $tpl['id'] ); ?>"
							        data-price="<?php echo esc_attr( $tpl['price'] ); ?>"
							        data-label-tpl="<?php echo esc_attr( $tpl['label_template_id'] ?? 0 ); ?>">
								<?php echo esc_html( $tpl['name'] . ' — ' . get_woocommerce_currency_symbol() . number_format( (float) $tpl['price'], 2 ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="wcbp-batch-qty"><?php esc_html_e( 'Quantity', 'woo-barcode-pro' ); ?></label></th>
				<td>
					<input id="wcbp-batch-qty" type="number" min="1" max="500" value="10" style="width:100px" />
					<span class="description"><?php esc_html_e( 'Max 500 per run.', 'woo-barcode-pro' ); ?></span>
				</td>
			</tr>
			<?php if ( ! empty( $label_templates ) ) : ?>
			<tr>
				<th><label for="wcbp-batch-label-tpl"><?php esc_html_e( 'Label Template', 'woo-barcode-pro' ); ?></label></th>
				<td>
					<select id="wcbp-batch-label-tpl" style="min-width:280px">
						<option value="0"><?php esc_html_e( '— Use template default —', 'woo-barcode-pro' ); ?></option>
						<?php foreach ( $label_templates as $ltpl ) : ?>
							<option value="<?php echo esc_attr( $ltpl['id'] ); ?>">
								<?php echo esc_html( $ltpl['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><label for="wcbp-batch-name"><?php esc_html_e( 'Default Name', 'woo-barcode-pro' ); ?></label></th>
				<td>
					<input id="wcbp-batch-name" type="text" style="min-width:280px"
					       placeholder="<?php esc_attr_e( 'Leave blank to use template name', 'woo-barcode-pro' ); ?>" />
					<p class="description"><?php esc_html_e( 'Printed on the barcode label. All products in this batch will use this name.', 'woo-barcode-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Item Type', 'woo-barcode-pro' ); ?></th>
				<td>
					<fieldset>
						<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer">
							<input id="wcbp-batch-unique" type="checkbox" checked style="margin-top:3px;flex-shrink:0" />
							<span>
								<strong><?php esc_html_e( 'Unique items', 'woo-barcode-pro' ); ?></strong><br>
								<span class="description"><?php esc_html_e( 'Create one product per barcode — scan each to add a photo and publish.', 'woo-barcode-pro' ); ?></span>
							</span>
						</label>
						<div id="wcbp-batch-same-note" style="display:none;margin-top:8px;padding:10px 14px;background:#f0f6fc;border-left:4px solid #2271b1;border-radius:0 4px 4px 0;font-size:13px;line-height:1.5">
							<?php esc_html_e( 'Same items: creates one product with the shared barcode and sets its stock to', 'woo-barcode-pro' ); ?>
							<strong id="wcbp-batch-qty-preview">1</strong>
							<?php esc_html_e( 'units. All labels in the print queue will point to this single product.', 'woo-barcode-pro' ); ?>
						</div>
					</fieldset>
				</td>
			</tr>
		</table>

		<p>
			<button id="wcbp-batch-run" class="button button-primary button-large">
				<?php esc_html_e( 'Create Draft Products', 'woo-barcode-pro' ); ?>
			</button>
		</p>

		<!-- Progress -->
		<div id="wcbp-batch-progress" style="display:none;margin-top:16px">
			<span class="spinner is-active" style="float:none;vertical-align:middle"></span>
			<span id="wcbp-batch-progress-text" style="margin-left:6px"></span>
		</div>

		<!-- Result -->
		<div id="wcbp-batch-result" style="display:none;margin-top:20px"></div>
	</div>

	<script>
	(function () {
		var $unique = document.getElementById('wcbp-batch-unique');
		var $note   = document.getElementById('wcbp-batch-same-note');
		var $preview = document.getElementById('wcbp-batch-qty-preview');
		var $qty    = document.getElementById('wcbp-batch-qty');
		function syncNote() {
			var checked = $unique.checked;
			$note.style.display = checked ? 'none' : 'block';
			if (!checked && $qty) $preview.textContent = $qty.value || '1';
		}
		$unique.addEventListener('change', syncNote);
		if ($qty) {
			$qty.addEventListener('input', function () {
				if (!$unique.checked) $preview.textContent = this.value || '1';
			});
		}
	}());
	</script>

	<?php endif; ?>
</div>
