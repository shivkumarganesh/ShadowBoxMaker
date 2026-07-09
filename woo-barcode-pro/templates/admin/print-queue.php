<?php
/**
 * Print queue admin page.
 *
 * Available: $items (array), $label_templates (array), $default_tpl (array|null).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;

$count = count( $items );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Print Queue', 'woo-barcode-pro' ); ?></h1>
	<span class="wcbp-queue-count-pill"><?php printf( esc_html__( '%d pending', 'woo-barcode-pro' ), $count ); ?></span>
	<hr class="wp-header-end">

	<?php if ( $count === 0 ) : ?>
	<div class="wcbp-empty-state">
		<div class="wcbp-empty-icon">🖨️</div>
		<p><?php esc_html_e( 'Your print queue is empty. Add products from the Products list using the bulk action.', 'woo-barcode-pro' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Go to Products', 'woo-barcode-pro' ); ?></a>
	</div>
	<?php else : ?>

	<div class="wcbp-queue-toolbar">
		<label><input type="checkbox" id="wcbp-check-all" /> <?php esc_html_e( 'All', 'woo-barcode-pro' ); ?></label>

		<select id="wcbp-template-select">
			<option value="0"><?php esc_html_e( '— Default label template —', 'woo-barcode-pro' ); ?></option>
			<?php foreach ( $label_templates as $lt ) : ?>
				<option value="<?php echo esc_attr( $lt['id'] ); ?>" <?php selected( ! empty( $default_tpl['id'] ) && $default_tpl['id'] == $lt['id'] ); ?>>
					<?php echo esc_html( $lt['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button id="wcbp-print-btn" class="button button-primary">🖨️ <?php esc_html_e( 'Print Labels', 'woo-barcode-pro' ); ?></button>
		<button id="wcbp-mark-printed" class="button button-secondary"><?php esc_html_e( 'Mark Printed', 'woo-barcode-pro' ); ?></button>
		<button id="wcbp-clear-queue" class="button button-link-delete"><?php esc_html_e( 'Clear Queue', 'woo-barcode-pro' ); ?></button>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th class="check-column"></th>
				<th><?php esc_html_e( 'Product', 'woo-barcode-pro' ); ?></th>
				<th><?php esc_html_e( 'SKU', 'woo-barcode-pro' ); ?></th>
				<th><?php esc_html_e( 'Quantity', 'woo-barcode-pro' ); ?></th>
				<th><?php esc_html_e( 'Added', 'woo-barcode-pro' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'woo-barcode-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $items as $item ) : ?>
			<tr data-id="<?php echo esc_attr( $item['id'] ); ?>">
				<td><input type="checkbox" class="wcbp-row-check" /></td>
				<td>
					<a href="<?php echo esc_url( get_edit_post_link( $item['variation_id'] ?: $item['product_id'] ) ); ?>">
						<?php echo esc_html( $item['product_name'] ); ?>
					</a>
					<?php if ( $item['variation_id'] ) : ?>
						<span class="wcbp-badge wcbp-badge-grey"><?php esc_html_e( 'Variation', 'woo-barcode-pro' ); ?></span>
					<?php endif; ?>
					<?php if ( $item['order_id'] ) : ?>
						<span class="wcbp-badge wcbp-badge-orange"><?php printf( esc_html__( 'Order #%d', 'woo-barcode-pro' ), $item['order_id'] ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $item['sku'] ?? '—' ); ?></td>
				<td>
					<input type="number" class="wcbp-qty-input small-text" value="<?php echo esc_attr( $item['quantity'] ); ?>" min="1" max="999" />
				</td>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $item['added_at'] ) ) ); ?></td>
				<td>
					<button class="button-link wcbp-remove-item" style="color:#d63638"><?php esc_html_e( 'Remove', 'woo-barcode-pro' ); ?></button>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php endif; ?>
</div>
