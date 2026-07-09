<?php
/**
 * Label templates list / edit page.
 *
 * Available: $templates (array), $presets (array), $action (string), $editing (array|null).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Label Templates', 'woo-barcode-pro' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcbp-label-templates&action=new' ) ); ?>"
	   class="page-title-action"><?php esc_html_e( '+ New Template', 'woo-barcode-pro' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template saved.', 'woo-barcode-pro' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template deleted.', 'woo-barcode-pro' ); ?></p></div>
	<?php endif; ?>

	<?php if ( null !== $editing ) : /* ── Edit / New form ── */ ?>

	<div class="wcbp-designer-wrap">
		<div class="wcbp-designer-form">
		<form id="wcbp-template-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wcbp_save_label_template' ); ?>
			<input type="hidden" name="action" value="wcbp_save_label_template" />
			<?php if ( ! empty( $editing['id'] ) ) : ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $editing['id'] ); ?>" />
			<?php endif; ?>

			<div class="wcbp-card">
				<h2><?php echo empty( $editing['id'] ) ? esc_html__( 'New Label Template', 'woo-barcode-pro' ) : esc_html__( 'Edit Label Template', 'woo-barcode-pro' ); ?></h2>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Name', 'woo-barcode-pro' ); ?></th>
						<td><input type="text" name="name" value="<?php echo esc_attr( $editing['name'] ?? '' ); ?>" class="regular-text" required /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Preset', 'woo-barcode-pro' ); ?></th>
						<td>
							<select name="preset" id="wcbp-preset">
							<?php foreach ( $presets as $key => $p ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $editing['preset'] ?? 'avery_5160', $key ); ?>>
									<?php echo esc_html( $p['name'] ); ?>
								</option>
							<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Width (in)', 'woo-barcode-pro' ); ?></th>
						<td><input type="number" id="wcbp-width-in" name="width_in" value="<?php echo esc_attr( $editing['width_in'] ?? 2.625 ); ?>" step="0.001" min="0.5" max="11" class="small-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Height (in)', 'woo-barcode-pro' ); ?></th>
						<td><input type="number" id="wcbp-height-in" name="height_in" value="<?php echo esc_attr( $editing['height_in'] ?? 1.0 ); ?>" step="0.001" min="0.25" max="11" class="small-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Columns', 'woo-barcode-pro' ); ?></th>
						<td><input type="number" id="wcbp-cols" name="cols" value="<?php echo esc_attr( $editing['cols'] ?? 3 ); ?>" min="1" max="10" class="small-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rows per page', 'woo-barcode-pro' ); ?></th>
						<td><input type="number" id="wcbp-rows-per-page" name="rows_per_page" value="<?php echo esc_attr( $editing['rows_per_page'] ?? 10 ); ?>" min="1" max="50" class="small-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Gap (in)', 'woo-barcode-pro' ); ?></th>
						<td><input type="number" id="wcbp-gap-in" name="gap_in" value="<?php echo esc_attr( $editing['gap_in'] ?? 0 ); ?>" step="0.01" min="0" max="1" class="small-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Page margin (in)', 'woo-barcode-pro' ); ?></th>
						<td><input type="number" id="wcbp-margin-in" name="margin_in" value="<?php echo esc_attr( $editing['margin_in'] ?? 0.5 ); ?>" step="0.01" min="0" max="2" class="small-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Paper size', 'woo-barcode-pro' ); ?></th>
						<td>
							<select name="page_size" id="wcbp-page-size">
								<option value="letter" <?php selected( $editing['page_size'] ?? 'letter', 'letter' ); ?>><?php esc_html_e( 'Letter (8.5 × 11 in)', 'woo-barcode-pro' ); ?></option>
								<option value="A4"     <?php selected( $editing['page_size'] ?? 'letter', 'A4'     ); ?>><?php esc_html_e( 'A4 (210 × 297 mm)',   'woo-barcode-pro' ); ?></option>
								<option value="legal"  <?php selected( $editing['page_size'] ?? 'letter', 'legal'  ); ?>><?php esc_html_e( 'Legal (8.5 × 14 in)', 'woo-barcode-pro' ); ?></option>
								<option value="A5"     <?php selected( $editing['page_size'] ?? 'letter', 'A5'     ); ?>><?php esc_html_e( 'A5 (148 × 210 mm)',   'woo-barcode-pro' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Layout', 'woo-barcode-pro' ); ?></th>
						<td>
							<label><input type="radio" name="layout" value="vertical"   <?php checked( $editing['layout'] ?? 'vertical', 'vertical'   ); ?> /> <?php esc_html_e( 'Vertical (barcode top)', 'woo-barcode-pro' ); ?></label><br/>
							<label><input type="radio" name="layout" value="horizontal" <?php checked( $editing['layout'] ?? 'vertical', 'horizontal' ); ?> /> <?php esc_html_e( 'Horizontal (barcode left)', 'woo-barcode-pro' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Barcode size', 'woo-barcode-pro' ); ?></th>
						<td>
							<div class="wcbp-ratio-wrap">
								<input type="range" id="wcbp-barcode-ratio" name="barcode_ratio" value="<?php echo esc_attr( $editing['barcode_ratio'] ?? 60 ); ?>" min="30" max="80" />
								<span id="wcbp-barcode-ratio-val"><?php echo esc_html( $editing['barcode_ratio'] ?? 60 ); ?>%</span>
							</div>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Fields to show', 'woo-barcode-pro' ); ?></th>
						<td>
							<?php
							$f = $editing['fields'] ?? array();
							$field_labels = array(
								'name'       => __( 'Product name', 'woo-barcode-pro' ),
								'price'      => __( 'Price', 'woo-barcode-pro' ),
								'sku'        => __( 'SKU', 'woo-barcode-pro' ),
								'attributes' => __( 'Attributes', 'woo-barcode-pro' ),
								'logo'       => __( 'Logo', 'woo-barcode-pro' ),
							);
							foreach ( $field_labels as $key => $lbl ) :
							?>
							<div class="wcbp-field-row">
								<label>
									<input type="checkbox" id="wcbp-field-<?php echo esc_attr( $key ); ?>" name="fields[<?php echo esc_attr( $key ); ?>]" value="1"
										<?php checked( ! empty( $f[ $key ] ) ); ?> />
									<?php echo esc_html( $lbl ); ?>
								</label>
							</div>
							<?php endforeach; ?>
							<div class="wcbp-field-row">
								<label><?php esc_html_e( 'Custom meta key', 'woo-barcode-pro' ); ?>:</label>
								<input type="text" name="fields[custom_meta]" value="<?php echo esc_attr( $f['custom_meta'] ?? '' ); ?>" class="regular-text" placeholder="_my_meta" />
							</div>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Logo', 'woo-barcode-pro' ); ?></th>
						<td>
							<input type="hidden" name="logo_id" id="wcbp-logo-id" value="<?php echo esc_attr( $editing['logo_id'] ?? 0 ); ?>" />
							<?php if ( ! empty( $editing['logo_id'] ) ) : ?>
								<img id="wcbp-logo-preview" src="<?php echo esc_url( wp_get_attachment_image_url( $editing['logo_id'], 'thumbnail' ) ); ?>" style="max-width:100px;max-height:40px;" />
							<?php else : ?>
								<img id="wcbp-logo-preview" src="" style="display:none;max-width:100px;max-height:40px;" />
							<?php endif; ?>
							<button type="button" id="wcbp-select-logo" class="button"><?php esc_html_e( 'Select Logo', 'woo-barcode-pro' ); ?></button>
							<button type="button" id="wcbp-remove-logo" class="button" <?php echo empty( $editing['logo_id'] ) ? 'style="display:none"' : ''; ?>><?php esc_html_e( 'Remove', 'woo-barcode-pro' ); ?></button>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Template', 'woo-barcode-pro' ) ); ?>
			</div>
		</form>
		</div><!-- .wcbp-designer-form -->

		<div class="wcbp-designer-preview">
			<div class="wcbp-card">
				<h3><?php esc_html_e( 'Preview', 'woo-barcode-pro' ); ?></h3>
				<div id="wcbp-label-preview"></div>
			</div>
		</div>
	</div>

	<?php else : /* ── List view ── */ ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'woo-barcode-pro' ); ?></th>
				<th><?php esc_html_e( 'Size', 'woo-barcode-pro' ); ?></th>
				<th><?php esc_html_e( 'Columns', 'woo-barcode-pro' ); ?></th>
				<th><?php esc_html_e( 'Default', 'woo-barcode-pro' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'woo-barcode-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $templates ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No label templates found.', 'woo-barcode-pro' ); ?></td></tr>
		<?php else : ?>
		<?php foreach ( $templates as $tpl ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $tpl['name'] ); ?></strong></td>
				<td><?php echo esc_html( $tpl['width_in'] . '" × ' . $tpl['height_in'] . '"' ); ?></td>
				<td><?php echo esc_html( $tpl['cols'] ); ?></td>
				<td>
					<?php if ( $tpl['is_default'] ) : ?>
						<span class="wcbp-badge wcbp-badge-green"><?php esc_html_e( 'Default', 'woo-barcode-pro' ); ?></span>
					<?php else : ?>
						<button class="button-link wcbp-set-default-tpl" data-id="<?php echo esc_attr( $tpl['id'] ); ?>">
							<?php esc_html_e( 'Set as default', 'woo-barcode-pro' ); ?>
						</button>
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcbp-label-templates&action=edit&id=' . $tpl['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'woo-barcode-pro' ); ?></a>
					<?php if ( ! $tpl['is_default'] ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wcbp_delete_label_template&id=' . $tpl['id'] ), 'wcbp_delete_label_template' ) ); ?>"
					   class="button button-small button-link-delete"
					   onclick="return confirm('<?php esc_attr_e( 'Delete this template?', 'woo-barcode-pro' ); ?>')"><?php esc_html_e( 'Delete', 'woo-barcode-pro' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php endif; ?>
</div>
