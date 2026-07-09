<?php
/**
 * EAN meta management, variation field, and CSV import/export.
 *
 * @package WCBarcodePro\Barcode
 */

namespace WCBarcodePro\Barcode;

defined( 'ABSPATH' ) || exit;

class EanManager {

	public const META_KEY = '_wcbp_ean';

	private static ?EanManager $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_hooks(): void {
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_ean_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_ean' ), 10, 2 );
	}

	// ── CRUD ─────────────────────────────────────────────────────────────────

	public function get_ean( int $product_id, int $variation_id = 0 ): string {
		$id = $variation_id > 0 ? $variation_id : $product_id;
		return (string) get_post_meta( $id, self::META_KEY, true );
	}

	public function set_ean( int $product_id, string $ean, int $variation_id = 0 ): bool {
		$ean = sanitize_text_field( $ean );
		$id  = $variation_id > 0 ? $variation_id : $product_id;
		return (bool) update_post_meta( $id, self::META_KEY, $ean );
	}

	public function validate_ean13( string $ean ): bool {
		if ( ! preg_match( '/^\d{13}$/', $ean ) ) {
			return false;
		}
		$expected = BarcodeGenerator::ean13_checksum( substr( $ean, 0, 12 ) );
		return (int) $ean[12] === $expected;
	}

	// ── Variation field ───────────────────────────────────────────────────────

	public function render_variation_ean_field( int $loop, array $variation_data, \WP_Post $variation ): void {
		$ean = $this->get_ean( $variation->ID );
		?>
		<div class="wcbp-variation-ean form-row form-row-full">
			<label for="wcbp_ean_<?php echo esc_attr( (string) $loop ); ?>">
				<?php esc_html_e( 'EAN / Barcode', 'woo-barcode-pro' ); ?>
			</label>
			<input
				type="text"
				id="wcbp_ean_<?php echo esc_attr( (string) $loop ); ?>"
				name="wcbp_ean[<?php echo esc_attr( (string) $loop ); ?>]"
				value="<?php echo esc_attr( $ean ); ?>"
				placeholder="<?php esc_attr_e( 'EAN-13 or custom barcode', 'woo-barcode-pro' ); ?>"
				class="short"
			/>
		</div>
		<?php
	}

	public function save_variation_ean( int $variation_id, int $loop ): void {
		$ean = isset( $_POST['wcbp_ean'][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST['wcbp_ean'][ $loop ] ) ) : ''; // phpcs:ignore WordPress.Security
		if ( '' !== $ean ) {
			update_post_meta( $variation_id, self::META_KEY, $ean );
		} else {
			delete_post_meta( $variation_id, self::META_KEY );
		}
	}

	// ── CSV Import ────────────────────────────────────────────────────────────

	public function import_from_csv( string $csv_content ): array {
		$lines   = array_filter( array_map( 'trim', explode( "\n", $csv_content ) ) );
		$success = 0;
		$errors  = array();

		foreach ( $lines as $i => $line ) {
			if ( 0 === $i && str_contains( strtolower( $line ), 'sku' ) ) {
				continue; // skip header.
			}
			$parts = str_getcsv( $line );
			if ( count( $parts ) < 2 ) {
				$errors[] = sprintf( __( 'Row %d: not enough columns.', 'woo-barcode-pro' ), $i + 1 );
				continue;
			}

			$identifier = sanitize_text_field( trim( $parts[0] ) );
			$ean        = sanitize_text_field( trim( $parts[1] ) );

			// Find product by SKU or ID.
			$product_id = is_numeric( $identifier )
				? (int) $identifier
				: wc_get_product_id_by_sku( $identifier );

			if ( ! $product_id ) {
				$errors[] = sprintf( __( 'Row %d: product not found for "%s".', 'woo-barcode-pro' ), $i + 1, $identifier );
				continue;
			}

			$this->set_ean( $product_id, $ean );
			$success++;
		}

		return compact( 'success', 'errors' );
	}

	// ── CSV Export ────────────────────────────────────────────────────────────

	public function export_to_csv(): string {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			"SELECT p.ID, p.post_title, pm_sku.meta_value AS sku, pm_ean.meta_value AS ean
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm_sku ON pm_sku.post_id = p.ID AND pm_sku.meta_key = '_sku'
			 LEFT JOIN {$wpdb->postmeta} pm_ean ON pm_ean.post_id = p.ID AND pm_ean.meta_key = '" . self::META_KEY . "'
			 WHERE p.post_type IN ('product','product_variation') AND p.post_status != 'trash'
			 ORDER BY p.ID ASC",
			ARRAY_A
		);

		$csv = "ID,SKU,Product Name,EAN\n";
		foreach ( $rows as $row ) {
			$csv .= sprintf(
				"%d,%s,%s,%s\n",
				$row['ID'],
				'"' . str_replace( '"', '""', $row['sku'] ?? '' ) . '"',
				'"' . str_replace( '"', '""', $row['post_title'] ) . '"',
				'"' . str_replace( '"', '""', $row['ean'] ?? '' ) . '"'
			);
		}
		return $csv;
	}
}
