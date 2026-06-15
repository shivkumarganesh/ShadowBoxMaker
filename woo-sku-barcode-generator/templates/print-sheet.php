<?php
/**
 * Print sheet template — loaded with exit; after rendering.
 * Variables available: $queue, $cfg, $layout
 */
defined( 'ABSPATH' ) || exit;

$show_name  = WSBG_Settings::get( 'label_show_name' );
$show_price = WSBG_Settings::get( 'label_show_price' );
$show_sku   = WSBG_Settings::get( 'label_show_sku' );
$is_horiz   = ( $layout === 'horizontal' );
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php esc_html_e( 'Print Labels', 'wsbg' ); ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 9pt; }
  .sheet {
    display: grid;
    grid-template-columns: repeat(<?php echo (int) $cfg['cols']; ?>, <?php echo esc_html( $cfg['width'] ); ?>);
    gap: 0;
  }
  .label {
    width: <?php echo esc_html( $cfg['width'] ); ?>;
    height: <?php echo esc_html( $cfg['height'] ); ?>;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2px;
    flex-direction: <?php echo $is_horiz ? 'row' : 'column'; ?>;
    gap: 3px;
    border: 0.5pt dashed #ccc; /* visible in preview, not on real labels */
  }
  .label svg { flex-shrink: 0; max-height: 80%; }
  .label-text { font-size: 7pt; line-height: 1.3; overflow: hidden; }
  @media print {
    .label { border: none; }
    @page { margin: 0.5in; }
  }
</style>
</head>
<body>
<div class="sheet">
<?php
foreach ( $queue as $id => $qty ) :
	$product = wc_get_product( $id );
	if ( ! $product ) continue;
	$svg = WSBG_Barcode::svg_for_product( $product );
	for ( $i = 0; $i < $qty; $i++ ) :
?>
  <div class="label">
    <?php echo $svg; ?>
    <div class="label-text">
      <?php if ( $show_name ) echo esc_html( $product->get_name() ) . '<br>'; ?>
      <?php if ( $show_sku && $product->get_sku() ) echo esc_html( $product->get_sku() ) . '<br>'; ?>
      <?php if ( $show_price ) echo wp_kses_post( $product->get_price_html() ); ?>
    </div>
  </div>
<?php endfor; endforeach; ?>
</div>
<script>window.onload = function(){ window.print(); }</script>
</body>
</html>
