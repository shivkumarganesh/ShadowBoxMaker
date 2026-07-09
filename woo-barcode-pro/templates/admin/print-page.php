<?php
/**
 * Full-screen print output page.
 *
 * Available: $html (string — rendered label grid).
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php esc_html_e( 'Print Labels — WooBarcode Pro', 'woo-barcode-pro' ); ?></title>
<?php wp_print_styles( 'wcbp-print' ); ?>
</head>
<body class="wcbp-print-body">

<div class="wcbp-print-toolbar">
	<h2><?php esc_html_e( 'WooBarcode Pro — Label Print', 'woo-barcode-pro' ); ?></h2>
	<button id="wcbp-do-print" onclick="window.print()">🖨️ <?php esc_html_e( 'Print (Ctrl+P)', 'woo-barcode-pro' ); ?></button>
	<button onclick="window.close()" class="button" style="margin-left:4px"><?php esc_html_e( 'Close', 'woo-barcode-pro' ); ?></button>
</div>

<div class="wcbp-label-sheet">
	<?php echo $html; // phpcs:ignore WordPress.Security — rendered internally ?>
</div>

</body>
</html>
