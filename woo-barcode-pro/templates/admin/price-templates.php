<?php
/**
 * Price templates list / edit page.
 *
 * Available: $templates, $label_templates, $action, $editing, $categories.
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Price Templates', 'woo-barcode-pro' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcbp-price-templates&action=new' ) ); ?>"
	   class="page-title-action"><?php esc_html_e( '+ New Template', 'woo-barcode-pro' ); ?></a>
	<hr class="wp-header-end">

	<p class="description"><?php esc_html_e( 'Create one template per price point. On the Quick Add page, scan a template barcode and the price fills in automatically.', 'woo-barcode-pro' ); ?></p>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template saved.', 'woo-barcode-pro' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template deleted.', 'woo-barcode-pro' ); ?></p></div>
	<?php endif; ?>

	<?php if ( null !== $editing ) : ?>

	<div class="wcbp-card" style="max-width:680px;">
		<h2><?php echo empty( $editing['id'] ) ? esc_html__( 'New Price Template', 'woo-barcode-pro' ) : esc_html__( 'Edit Price Template', 'woo-barcode-pro' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wcbp_save_price_template' ); ?>
			<input type="hidden" name="action" value="wcbp_save_price_template" />
			<?php if ( ! empty( $editing['id'] ) ) : ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $editing['id'] ); ?>" />
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Name', 'woo-barcode-pro' ); ?></th>
					<td><input type="text" name="name" value="<?php echo esc_attr( $editing['name'] ?? '' ); ?>" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. Small — $25', 'woo-barcode-pro' ); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Price', 'woo-barcode-pro' ); ?></th>
					<td>
						<span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
						<input type="number" name="price" value="<?php echo esc_attr( $editing['price'] ?? '' ); ?>" class="small-text" step="0.01" min="0" required />
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Default categories', 'woo-barcode-pro' ); ?></th>
					<td>
						<?php
						$saved_cats = is_string( $editing['category_ids'] ?? '' )
							? json_decode( $editing['category_ids'] ?? '[]', true )
							: ( $editing['category_ids'] ?? array() );
						foreach ( (array) $categories as $cat ) :
						?>
						<label>
							<input type="checkbox" name="category_ids[]" value="<?php echo esc_attr( $cat->term_id ); ?>"
								<?php checked( in_array( $cat->term_id, (array) $saved_cats, false ) ); ?> />
							<?php echo esc_html( $cat->name ); ?>
						</label><br/>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Label template', 'woo-barcode-pro' ); ?></th>
					<td>
						<select name="label_template_id">
							<option value="0"><?php esc_html_e( '— Use default —', 'woo-barcode-pro' ); ?></option>
							<?php foreach ( $label_templates as $lt ) : ?>
								<option value="<?php echo esc_attr( $lt['id'] ); ?>" <?php selected( $editing['label_template_id'] ?? 0, $lt['id'] ); ?>>
									<?php echo esc_html( $lt['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<?php if ( ! empty( $editing['barcode_value'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Barcode:', 'woo-barcode-pro' ); ?></strong>
				<code><?php echo esc_html( $editing['barcode_value'] ); ?></code>
				— <?php esc_html_e( 'scan this on the Quick Add page', 'woo-barcode-pro' ); ?></p>
			<?php endif; ?>

			<?php submit_button( __( 'Save Template', 'woo-barcode-pro' ) ); ?>
		</form>
	</div>

	<?php else : /* list */ ?>

	<?php if ( empty( $templates ) ) : ?>
		<div class="wcbp-empty-state">
			<div class="wcbp-empty-icon">🏷️</div>
			<p><?php esc_html_e( 'No price templates yet. Create your first one!', 'woo-barcode-pro' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcbp-price-templates&action=new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'New Price Template', 'woo-barcode-pro' ); ?></a>
		</div>
	<?php else : ?>
		<?php foreach ( $templates as $tpl ) : ?>
		<div class="wcbp-template-card">
			<div class="wcbp-tpl-price"><?php echo esc_html( get_woocommerce_currency_symbol() . number_format( (float) $tpl['price'], 2 ) ); ?></div>
			<div class="wcbp-tpl-name"><?php echo esc_html( $tpl['name'] ); ?></div>
			<div class="wcbp-tpl-barcode"><?php echo esc_html( $tpl['barcode_value'] ?? '' ); ?></div>
			<div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcbp-price-templates&action=edit&id=' . $tpl['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'woo-barcode-pro' ); ?></a>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wcbp_delete_price_template&id=' . $tpl['id'] ), 'wcbp_delete_price_template' ) ); ?>"
				   class="button button-small button-link-delete"
				   onclick="return confirm('<?php esc_attr_e( 'Delete this template?', 'woo-barcode-pro' ); ?>')"><?php esc_html_e( 'Delete', 'woo-barcode-pro' ); ?></a>
			</div>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php endif; ?>
</div>
