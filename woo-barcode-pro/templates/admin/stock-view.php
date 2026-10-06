<?php
/**
 * Stock View PWA — standalone full-screen template.
 *
 * @package WCBarcodePro
 */

defined( 'ABSPATH' ) || exit;

$manifest_url = home_url( '/?wcbp_pwa=sv_manifest' );
$icon_url_192 = home_url( '/?wcbp_pwa=sv_icon&s=192' );
$ajax_url     = admin_url( 'admin-ajax.php' );
$nonce        = wp_create_nonce( 'wcbp_stock_view' );
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1a7f37">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Stock View">
<link rel="manifest" href="<?php echo esc_url( $manifest_url ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $icon_url_192 ); ?>">
<title><?php esc_html_e( 'Stock View', 'woo-barcode-pro' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( WCBP_PLUGIN_URL . 'assets/css/stock-view.css?v=' . WCBP_VERSION ); ?>">
</head>
<body>

<header class="sv-header">
	<div class="sv-header-row">
		<h1 class="sv-title"><?php esc_html_e( 'Stock View', 'woo-barcode-pro' ); ?></h1>
		<button id="sv-install-btn" class="sv-install-btn" type="button">
			<?php esc_html_e( '⬇ Install App', 'woo-barcode-pro' ); ?>
		</button>
	</div>
	<div class="sv-search-wrap">
		<span class="sv-search-icon">&#128269;</span>
		<input
			id="sv-search"
			class="sv-search"
			type="search"
			placeholder="<?php esc_attr_e( 'Search by name…', 'woo-barcode-pro' ); ?>"
			autocomplete="off"
			spellcheck="false"
		>
	</div>
</header>

<div id="sv-pills" class="sv-pills" role="list" aria-label="<?php esc_attr_e( 'Filter by category', 'woo-barcode-pro' ); ?>"></div>

<main class="sv-content">
	<div id="sv-count" class="sv-count"></div>
	<div id="sv-grid" class="sv-grid" role="list"></div>
</main>

<script>
var wcbpStockView = {
	ajax_url : <?php echo wp_json_encode( $ajax_url ); ?>,
	nonce    : <?php echo wp_json_encode( $nonce ); ?>,
	strings  : {
		all_cat     : <?php echo wp_json_encode( __( 'All', 'woo-barcode-pro' ) ); ?>,
		loading     : <?php echo wp_json_encode( __( 'Loading…', 'woo-barcode-pro' ) ); ?>,
		products    : <?php echo wp_json_encode( __( 'products', 'woo-barcode-pro' ) ); ?>,
		no_products : <?php echo wp_json_encode( __( 'No products found.', 'woo-barcode-pro' ) ); ?>,
		in_stock    : <?php echo wp_json_encode( __( 'left', 'woo-barcode-pro' ) ); ?>,
		out_of_stock: <?php echo wp_json_encode( __( '✕ Out of stock', 'woo-barcode-pro' ) ); ?>,
		untracked   : <?php echo wp_json_encode( __( '— Not tracked', 'woo-barcode-pro' ) ); ?>,
		error       : <?php echo wp_json_encode( __( 'Failed to load products.', 'woo-barcode-pro' ) ); ?>
	}
};
</script>
<script src="<?php echo esc_url( WCBP_PLUGIN_URL . 'assets/js/stock-view.js?v=' . WCBP_VERSION ); ?>"></script>
</body>
</html>
