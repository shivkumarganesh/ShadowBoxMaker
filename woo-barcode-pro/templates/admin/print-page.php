<?php
/**
 * Full-screen print output page.
 *
 * Available: $html (string — rendered label grid), $label_tpl (array|null).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;

$page_size = $label_tpl['page_size'] ?? 'letter';
$sheet_w   = 'A4' === $page_size ? '210mm' : ( 'A5' === $page_size ? '148mm' : ( 'legal' === $page_size ? '8.5in' : '8.5in' ) );
$sheet_h   = 'A4' === $page_size ? '297mm' : ( 'A5' === $page_size ? '210mm' : ( 'legal' === $page_size ? '14in'  : '11in'  ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php esc_html_e( 'Print Labels — WooBarcode Pro', 'woo-barcode-pro' ); ?></title>
<?php wp_print_styles( 'wcbp-print' ); ?>
<style>
:root {
	--wcbp-page-size: <?php echo esc_attr( $page_size ); ?>;
	--wcbp-sheet-w:   <?php echo esc_attr( $sheet_w ); ?>;
	--wcbp-sheet-h:   <?php echo esc_attr( $sheet_h ); ?>;
}
</style>
</head>
<body class="wcbp-print-body">

<div class="wcbp-print-toolbar">
	<h2>
		<?php esc_html_e( 'WooBarcode Pro — Label Print', 'woo-barcode-pro' ); ?>
		<span style="font-size:12px;font-weight:400;opacity:.7;margin-left:12px;">
			<?php echo esc_html( strtoupper( $page_size ) ); ?>
		</span>
	</h2>
	<button id="wcbp-do-print" onclick="window.print()">🖨️ <?php esc_html_e( 'Print (Ctrl+P)', 'woo-barcode-pro' ); ?></button>
	<button onclick="window.close()" class="button" style="margin-left:4px"><?php esc_html_e( 'Close', 'woo-barcode-pro' ); ?></button>
</div>

<div class="wcbp-label-sheet">
	<?php echo $html; // phpcs:ignore WordPress.Security — rendered internally ?>
</div>

</body>
</html>
