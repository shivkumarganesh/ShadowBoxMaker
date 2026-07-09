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

	<?php endif; ?>
</div>
