=== WooBarcode Pro ===
Contributors: shadowboxmaker
Tags: woocommerce, barcode, ean13, code128, label, print, quick-add
Requires at least: 6.3
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
WC requires at least: 9.1
WC tested up to: 9.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional barcode generation, label printing, and mobile-optimised product entry for WooCommerce.

== Description ==

WooBarcode Pro turns your WooCommerce store into a barcode powerhouse.

**Key features:**

* **Multiple barcode formats** — Code 128, EAN-13, QR Code, UPC-A, ITF-14. Silent fallback to Code 128 for non-numeric SKUs.
* **Label designer** — Create and save multiple label templates (Avery 5160, A4 65-up, or custom). Control label size, columns, layout, and which fields (name, price, SKU, logo) appear on each label.
* **DB-backed print queue** — Add products individually or in bulk. The queue persists across sessions and supports per-row quantity editing.
* **Mobile Quick Add** — Open the Quick Add page on your Android phone. Scan a Price Template barcode → type a product name → take a photo → tap Save. A new product is live in WooCommerce in under 20 seconds.
* **Price Templates** — Pre-configure price points (e.g. "Small — $25"). Each template gets a unique barcode. Scanning it on the Quick Add page auto-fills the price, category, and label template.
* **Order auto-queue** — Optionally add ordered products to the print queue automatically when an order reaches a chosen status (e.g. "Processing").
* **EAN CSV import/export** — Bulk-import EAN-13 codes from a CSV, or export all current codes.
* **REST API** — `/wcbp/v1/barcode/{id}`, `/wcbp/v1/queue`, `/wcbp/v1/templates/label`, `/wcbp/v1/templates/price`.
* **Onboarding tutorial** — A 6-step wizard guides new users from first install to printing their first batch of labels.
* **HPOS compatible** — Fully compatible with WooCommerce High-Performance Order Storage.

== Installation ==

1. Upload the `woo-barcode-pro` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Navigate to **WooCommerce → Barcode Pro** to start the onboarding tutorial.

== Frequently Asked Questions ==

= Which barcode format should I use? =

Choose **Code 128** if you want barcodes that work with any scanner and any product name. Choose **EAN-13** if you use standard retail scanners and your products have 12-digit numeric SKUs.

= How do I add products quickly from my phone? =

Go to **Barcode Pro → Quick Add** on your phone. Bookmark it on your home screen. Scan a Price Template barcode → enter the product name → take a photo → tap Save.

= Can I print on Avery labels? =

Yes. The default label template is pre-configured for Avery 5160 (2.625" × 1", 30-up). You can create additional templates for any label sheet.

= Does it work with WooCommerce HPOS? =

Yes. The plugin declares `FeaturesUtil::declare_compatibility` for `custom_order_tables`.

== Screenshots ==

1. Print Queue — add, edit, and print labels from one screen.
2. Label Designer — configure size, layout, and fields with a live preview.
3. Quick Add — mobile-optimised product entry with camera capture.
4. Price Templates — one template per price point, each with its own scannable barcode.
5. Tutorial — 6-step onboarding wizard.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First public release.
