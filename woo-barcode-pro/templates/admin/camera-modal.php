<?php
/**
 * Live-camera barcode scanner modal.
 * Included by quick-add.php and inventory.php.
 *
 * @package WCBarcodePro
 */
defined( 'ABSPATH' ) || exit;
?>
<div id="wcbp-camera-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:999999;align-items:center;justify-content:center;flex-direction:column;">
	<div style="position:relative;width:min(92vw,480px);background:#000;border-radius:12px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.8);">
		<video id="wcbp-camera-video" style="width:100%;display:block;max-height:60vh;object-fit:cover;" playsinline autoplay muted></video>

		<!-- Scan-line animation -->
		<div id="wcbp-scan-line" style="position:absolute;left:10%;right:10%;height:2px;background:linear-gradient(90deg,transparent,#ef4444,transparent);box-shadow:0 0 8px #ef4444;animation:wcbp-scan 1.6s ease-in-out infinite;top:50%;pointer-events:none;"></div>

		<!-- Viewfinder corners -->
		<div style="position:absolute;inset:0;pointer-events:none;">
			<div style="position:absolute;top:15%;left:10%;width:28px;height:28px;border-top:3px solid #fff;border-left:3px solid #fff;border-radius:2px 0 0 0;"></div>
			<div style="position:absolute;top:15%;right:10%;width:28px;height:28px;border-top:3px solid #fff;border-right:3px solid #fff;border-radius:0 2px 0 0;"></div>
			<div style="position:absolute;bottom:15%;left:10%;width:28px;height:28px;border-bottom:3px solid #fff;border-left:3px solid #fff;border-radius:0 0 0 2px;"></div>
			<div style="position:absolute;bottom:15%;right:10%;width:28px;height:28px;border-bottom:3px solid #fff;border-right:3px solid #fff;border-radius:0 0 2px 0;"></div>
		</div>

		<button id="wcbp-camera-close" style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center;">✕</button>
		<div id="wcbp-camera-status" style="position:absolute;bottom:0;left:0;right:0;padding:10px;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);text-align:center;color:#e5e7eb;font-size:13px;">
			<?php esc_html_e( 'Point camera at barcode…', 'woo-barcode-pro' ); ?>
		</div>
	</div>
	<p style="color:rgba(255,255,255,.5);font-size:12px;margin-top:10px;">
		<?php esc_html_e( 'Tap ✕ to cancel', 'woo-barcode-pro' ); ?>
	</p>
</div>
<style>
@keyframes wcbp-scan {
	0%   { top: 20%; opacity: .4; }
	50%  { opacity: 1; }
	100% { top: 80%; opacity: .4; }
}
</style>
