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
		// Label content width: label width minus 3px left/right padding (see .wcbp-label in print.css).
		$inner_w_in   = max( 0.25, (float) ( $tpl['width_in'] ?? 2.625 ) - 6 / 96 );
		$raw_name     = $item['product_name'] ?? '';
		if ( $variation_id ) {
			// Variation titles embed the attributes ("Tee - Blue, M"); those print on their own line instead.
			$parent   = wc_get_product( $product_id );
			$raw_name = $parent ? $parent->get_name() : $raw_name;
		}
		$raw_name     = mb_strlen( $raw_name ) > 12 ? mb_substr( $raw_name, 0, 12 ) . '…' : $raw_name;
		$product_name = esc_html( $raw_name );
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

		$variant      = $variation_id && $product ? $this->variation_text( $product ) : '';
		$variant_html = '' !== $variant ? '<span class="wcbp-label-variant">' . esc_html( $variant ) . '</span>' : '';

		// Attributes (product-level; variations get $variant_html instead).
		$attr_html = '';
		if ( ! empty( $fields['attributes'] ) && $product ) {
			$attrs = $product->get_attributes();
			$parts = array();
			foreach ( $attrs as $key => $attr ) {
				// Variations return plain attribute strings, not WC_Product_Attribute objects.
				if ( $attr instanceof \WC_Product_Attribute && $attr->get_visible() ) {
					$parts[] = esc_html( wc_attribute_label( $attr->get_name() ) ) . ': ' . esc_html( implode( ', ', $attr->get_options() ) );
				}
			}
			if ( $parts ) {
				$attr_html = '<span class="wcbp-label-attrs">' . implode( ' | ', $parts ) . '</span>';
			}
		}

		// ── Visual (drag-and-drop) layout ────────────────────────────────────────
		if ( 'visual' === ( $tpl['layout'] ?? '' ) ) {
			$elements = $fields['visual_elements'] ?? array();
			if ( empty( $elements ) ) {
				return '<div class="wcbp-label-visual-empty">No visual layout.</div>';
			}
			// Layouts saved before the Variation element existed: show it under the name (or price, or SKU).
			$variant_host = '';
			if ( '' !== $variant_html && ! in_array( 'variant', array_column( $elements, 'id' ), true ) ) {
				foreach ( array( 'name', 'price', 'sku' ) as $host ) {
					foreach ( $elements as $el ) {
						if ( $host === ( $el['id'] ?? '' ) && ! empty( $el['visible'] ) ) {
							$variant_host = $host;
							break 2;
						}
					}
				}
			}
			$with_variant = static function ( string $html, string $type ) use ( $variant_host, $variant_html ): string {
				// Attributes first: in a small box they matter more than the (truncated) name.
				return $type === $variant_host ? '<div style="width:100%;max-height:100%;overflow:hidden;text-align:inherit">' . $variant_html . $html . '</div>' : $html;
			};

			ob_start();
			echo '<div class="wcbp-label-visual">';
			foreach ( $elements as $el ) {
				if ( empty( $el['visible'] ) ) {
					continue;
				}
				$type  = $el['id'] ?? '';
				$ex    = (float) ( $el['x'] ?? 0 );
				$ey    = (float) ( $el['y'] ?? 0 );
				$ew    = (float) ( $el['w'] ?? 50 );
				$eh    = (float) ( $el['h'] ?? 50 );
				$fs    = max( 6, min( 24, (int) ( $el['fontSize'] ?? 8 ) ) );
				$fw    = ! empty( $el['bold'] ) ? '700' : '400';
				$align = $el['align'] ?? 'left';
				$align = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'left';
				$jc    = $align === 'right' ? 'flex-end' : ( $align === 'center' ? 'center' : 'flex-start' );

				$style = sprintf(
					'position:absolute;left:%.1f%%;top:%.1f%%;width:%.1f%%;height:%.1f%%;overflow:hidden;box-sizing:border-box;',
					$ex, $ey, $ew, $eh
				);

				if ( 'barcode' !== $type ) {
					$style .= "font-size:{$fs}pt;font-weight:{$fw};text-align:{$align};display:flex;align-items:center;justify-content:{$jc};";
				} else {
					$style .= 'display:flex;align-items:center;justify-content:center;';
				}

				echo '<div style="' . esc_attr( $style ) . '">';
				switch ( $type ) {
					case 'barcode':
						echo $this->barcode_html( $product_id, $variation_id, $bc_opts, $inner_w_in * $ew / 100 ); // phpcs:ignore WordPress.Security
						break;
					case 'company':
						echo esc_html( $fields['company_name_text'] ?? '' );
						break;
					case 'name':
						echo $with_variant( $product_name, 'name' ); // phpcs:ignore WordPress.Security
						break;
					case 'variant':
						echo $variant_html; // phpcs:ignore WordPress.Security
						break;
					case 'price':
						echo $with_variant( $price_html, 'price' ); // phpcs:ignore WordPress.Security
						break;
					case 'sku':
						echo $with_variant( $sku, 'sku' ); // phpcs:ignore WordPress.Security
						break;
					case 'logo':
						echo $logo_html; // phpcs:ignore WordPress.Security
						break;
				}
				echo '</div>';
			}
			echo '</div>';
			return ob_get_clean();
		}

		if ( 'horizontal' === ( $tpl['layout'] ?? 'vertical' ) ) {
			$barcode_ratio = $this->scannable_ratio( $product_id, $variation_id, $bc_opts, $inner_w_in, $barcode_ratio );
		}
		$info_ratio  = 100 - $barcode_ratio;
		$bc_avail_in = 'horizontal' === ( $tpl['layout'] ?? 'vertical' ) ? $inner_w_in * $barcode_ratio / 100 : $inner_w_in;
		$barcode_svg = $this->barcode_html( $product_id, $variation_id, $bc_opts, $bc_avail_in );

		// Skip the SKU line when the identical value is already printed under the bars.
		$bc_text_shown = (bool) ( $bc_opts['show_text'] ?? \WCBarcodePro\wcbp_get_setting( 'show_text', true ) );
		if ( $bc_text_shown && '' !== $sku && \WCBarcodePro\wcbp_barcode_value( $product_id, $variation_id ) === ( $item['sku'] ?? '' ) ) {
			$sku = '';
		}

		ob_start();
		?>
		<?php if ( ! empty( $fields['company_name'] ) && ! empty( $fields['company_name_text'] ) ) :
			$co_fs  = max( 6, min( 30, (int) ( $fields['company_name_font_size']      ?? 10 ) ) );
			$co_pb  = max( 0, min( 20, (int) ( $fields['company_name_padding_bottom'] ?? 0  ) ) );
		?>
		<div class="wcbp-label-company" style="font-size:<?php echo esc_attr( $co_fs ); ?>px;padding-bottom:<?php echo esc_attr( $co_pb ); ?>px"><?php echo esc_html( $fields['company_name_text'] ); ?></div>
		<?php endif; ?>
		<div class="wcbp-label-body">
			<?php if ( $logo_html ) : ?>
				<?php echo $logo_html; // phpcs:ignore WordPress.Security ?>
			<?php endif; ?>
			<div class="wcbp-label-barcode" style="flex:<?php echo esc_attr( $barcode_ratio ); ?> 0 0%;min-height:0;min-width:0;overflow:hidden">
				<?php echo $barcode_svg; // phpcs:ignore WordPress.Security ?>
			</div>
			<div class="wcbp-label-info" style="flex:<?php echo esc_attr( $info_ratio ); ?> 0 0%;min-height:0;min-width:0;overflow:hidden">
				<?php if ( ! empty( $fields['name'] ) && $product_name ) : ?>
					<span class="wcbp-label-name"><?php echo $product_name; // phpcs:ignore WordPress.Security ?></span>
				<?php endif; ?>
				<?php echo $variant_html; // phpcs:ignore WordPress.Security ?>
				<?php if ( ! empty( $fields['price'] ) && $price_html ) : ?>
					<span class="wcbp-label-price"><?php echo $price_html; // phpcs:ignore WordPress.Security ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $fields['sku'] ) && $sku ) : ?>
					<span class="wcbp-label-sku"><?php echo $sku; // phpcs:ignore WordPress.Security ?></span>
				<?php endif; ?>
				<?php echo $attr_html; // phpcs:ignore WordPress.Security ?>
				<?php echo $custom_meta_html; // phpcs:ignore WordPress.Security ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Attribute values of a variation, e.g. "Blue / M". "Any …" attributes (empty values) are skipped.
	 */
	private function variation_text( \WC_Product $variation ): string {
		$parts = array();
		foreach ( $variation->get_variation_attributes() as $key => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$taxonomy = substr( $key, strlen( 'attribute_' ) );
			if ( taxonomy_exists( $taxonomy ) ) {
				$term  = get_term_by( 'slug', $value, $taxonomy );
				$value = ( $term && ! is_wp_error( $term ) ) ? $term->name : $value;
			}
			$parts[] = $value;
		}
		return implode( ' / ', $parts );
	}

	/**
	 * Smallest barcode share (%) of the label width at which bars can be 1/150in
	 * (whole dots at 300/600 dpi), never below the template ratio and capped at 72%.
	 */
	private function scannable_ratio( int $product_id, int $variation_id, array $bc_opts, float $inner_w_in, int $ratio ): int {
		$value = \WCBarcodePro\wcbp_barcode_value( $product_id, $variation_id );
		if ( '' === $value ) {
			return $ratio;
		}
		$symbology = (string) ( $bc_opts['symbology'] ?? \WCBarcodePro\wcbp_get_setting( 'symbology', 'code128' ) );
		$modules   = \WCBarcodePro\Barcode\BarcodeGenerator::get_instance()->module_count( $value, $symbology );
		$needed    = (int) ceil( ( $modules / 150 + 0.02 ) / $inner_w_in * 100 );
		return max( $ratio, min( 72, $needed ) );
	}

	/**
	 * Barcode sized for print: fixed physical bar width, bars stretched to the box height,
	 * human-readable text (linear codes) drawn as HTML so it never distorts.
	 */
	private function barcode_html( int $product_id, int $variation_id, array $bc_opts, float $avail_in ): string {
		$symbology = strtolower( $bc_opts['symbology'] ?? (string) \WCBarcodePro\wcbp_get_setting( 'symbology', 'code128' ) );
		$show_text = (bool) ( $bc_opts['show_text'] ?? \WCBarcodePro\wcbp_get_setting( 'show_text', true ) );
		$linear    = ! in_array( $symbology, array( 'ean13', 'upca' ), true );

		$bc_opts['print_width_in'] = max( 0.2, $avail_in - 0.02 );
		if ( $linear ) {
			$bc_opts['show_text'] = false;
		}

		$svg = \WCBarcodePro\wcbp_product_barcode_svg( $product_id, $variation_id, $bc_opts );
		if ( '' === $svg ) {
			return '';
		}

		$text = '';
		if ( $linear && $show_text ) {
			$text = '<div class="wcbp-bc-text">' . esc_html( \WCBarcodePro\wcbp_barcode_value( $product_id, $variation_id ) ) . '</div>';
		}
		return '<div class="wcbp-bc">' . $svg . $text . '</div>';
	}
}
