<?php
/**
 * Renders a grid of labels as HTML (for browser print).
 *
 * @package WCBarcodePro\Label
 */

namespace WCBarcodePro\Label;

defined( 'ABSPATH' ) || exit;

class LabelRenderer {

	private static ?LabelRenderer $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Render a full-page grid of labels as an HTML string.
	 *
	 * @param array      $items     Queue rows (with product_name, sku, quantity, etc.)
	 * @param array|null $label_tpl Label template row from DB.
	 */
	public function render_grid( array $items, ?array $label_tpl ): string {
		if ( empty( $items ) || ! $label_tpl ) {
			return '<p>' . esc_html__( 'Nothing to print.', 'woo-barcode-pro' ) . '</p>';
		}

		$fields    = is_string( $label_tpl['fields'] ) ? json_decode( $label_tpl['fields'], true ) : $label_tpl['fields'];
		$fields    = (array) $fields;
		$width_in  = (float) $label_tpl['width_in'];
		$height_in = (float) $label_tpl['height_in'];
		$cols      = max( 1, (int) $label_tpl['cols'] );
		$gap_in    = (float) $label_tpl['gap_in'];
		$margin_in = (float) $label_tpl['margin_in'];
		$layout    = $label_tpl['layout'] ?? 'vertical';
		$logo_id   = (int) ( $label_tpl['logo_id'] ?? 0 );

		// Page CSS vars.
		$css_page = sprintf(
			'--wcbp-label-w:%fin;--wcbp-label-h:%fin;--wcbp-cols:%d;--wcbp-gap:%fin;--wcbp-margin:%fin;',
			$width_in, $height_in, $cols, $gap_in, $margin_in
		);

		// Expand queue items by quantity.
		$label_cells = array();
		foreach ( $items as $item ) {
			$qty = max( 1, (int) $item['quantity'] );
			for ( $q = 0; $q < $qty; $q++ ) {
				$label_cells[] = $item;
			}
		}

		ob_start();
		?>
		<div class="wcbp-label-grid" style="<?php echo esc_attr( $css_page ); ?>">
		<?php foreach ( $label_cells as $cell ) : ?>
			<div class="wcbp-label wcbp-layout-<?php echo esc_attr( $layout ); ?>">
				<?php echo $this->render_label_cell( $cell, $fields, $logo_id, $label_tpl ); // phpcs:ignore WordPress.Security ?>
			</div>
		<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_label_cell( array $item, array $fields, int $logo_id, array $tpl ): string {
		$product_id   = (int) $item['product_id'];
		$variation_id = (int) ( $item['variation_id'] ?? 0 );
		$barcode_ratio = max( 30, min( 80, (int) ( $tpl['barcode_ratio'] ?? 60 ) ) );

		$bc_opts_raw = $tpl['barcode_options'] ?? null;
		$bc_opts     = ( $bc_opts_raw && is_string( $bc_opts_raw ) ) ? (array) json_decode( $bc_opts_raw, true ) : array();
		$barcode_svg = \WCBarcodePro\wcbp_product_barcode_svg( $product_id, $variation_id, $bc_opts );
		$product_name = esc_html( $item['product_name'] ?? '' );
		$sku          = esc_html( $item['sku'] ?? '' );

		// Price — load directly from product.
		$price_html = '';
		$product    = wc_get_product( $variation_id ?: $product_id );
		if ( $product ) {
			$price_html = wc_price( $product->get_price() );
		}

		// Logo.
		$logo_html = '';
		if ( ! empty( $fields['logo'] ) && $logo_id ) {
			$logo_url  = wp_get_attachment_image_url( $logo_id, array( 80, 30 ) );
			if ( $logo_url ) {
				$logo_html = '<img class="wcbp-label-logo" src="' . esc_url( $logo_url ) . '" alt="" />';
			}
		}

		// Custom meta.
		$custom_meta_html = '';
		if ( ! empty( $fields['custom_meta'] ) && $product_id ) {
			$meta_val = get_post_meta( $product_id, sanitize_key( $fields['custom_meta'] ), true );
			if ( $meta_val ) {
				$custom_meta_html = '<span class="wcbp-label-custom-meta">' . esc_html( $meta_val ) . '</span>';
			}
		}

		// Attributes.
		$attr_html = '';
		if ( ! empty( $fields['attributes'] ) && $product ) {
			$attrs = $product->get_attributes();
			$parts = array();
			foreach ( $attrs as $key => $attr ) {
				if ( $attr->get_visible() ) {
					$parts[] = esc_html( wc_attribute_label( $attr->get_name() ) ) . ': ' . esc_html( implode( ', ', $attr->get_options() ) );
				}
			}
			if ( $parts ) {
				$attr_html = '<span class="wcbp-label-attrs">' . implode( ' | ', $parts ) . '</span>';
			}
		}

		ob_start();
		?>
		<?php if ( $logo_html ) : ?>
			<?php echo $logo_html; // phpcs:ignore WordPress.Security ?>
		<?php endif; ?>
		<div class="wcbp-label-barcode" style="height:<?php echo esc_attr( $barcode_ratio ); ?>%">
			<?php echo $barcode_svg; // phpcs:ignore WordPress.Security ?>
		</div>
		<div class="wcbp-label-info">
			<?php if ( ! empty( $fields['name'] ) && $product_name ) : ?>
				<span class="wcbp-label-name"><?php echo $product_name; // phpcs:ignore WordPress.Security ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $fields['price'] ) && $price_html ) : ?>
				<span class="wcbp-label-price"><?php echo $price_html; // phpcs:ignore WordPress.Security ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $fields['sku'] ) && $sku ) : ?>
				<span class="wcbp-label-sku"><?php echo $sku; // phpcs:ignore WordPress.Security ?></span>
			<?php endif; ?>
			<?php echo $attr_html; // phpcs:ignore WordPress.Security ?>
			<?php echo $custom_meta_html; // phpcs:ignore WordPress.Security ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
