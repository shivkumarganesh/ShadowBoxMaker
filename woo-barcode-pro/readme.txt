=== WooBarcode Pro ===
Contributors: shadowboxmaker
Tags: woocommerce, barcode, ean13, code128, label, print, quick-add, pwa
Requires at least: 6.3
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.2.15
WC requires at least: 9.1
WC tested up to: 9.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional barcode generation, label printing, and mobile-optimised product entry for WooCommerce.

== Description ==

WooBarcode Pro turns your WooCommerce store into a barcode powerhouse — from generating barcodes and designing label templates to adding new products in seconds from your phone.

**Barcode generation**

* **Multiple formats** — Code 128, EAN-13, UPC-A, ITF-14. Silent fallback to Code 128 for non-numeric SKUs.
* **Per-template Barcode Designer** — choose symbology, bar height, bar width, text visibility, and bar colour independently for each label template.
* **EAN CSV import/export** — bulk-import EAN-13 codes from a CSV, or export all current codes.

**Label designer & printing**

* **Multiple label templates** — Avery 5160 (2.625″ × 1″, 30-up), A4 65-up, or fully custom sizes. Control columns, gap, margin, layout (vertical/horizontal), and which fields appear.
* **Live preview** — the label designer shows a real-time preview with editable test data (name, price, SKU, barcode value) and fetches the actual barcode SVG as you type.
* **Company name banner** — optional company name printed at the top of every label; each template can have its own name or none at all.
* **Logo support** — pick an image from the WordPress media library to print on labels.
* **Clean print page** — opens as a standalone page (no admin sidebar or toolbar). Includes a Save as PDF button that triggers the browser print dialog.
* **DB-backed print queue** — add products individually or in bulk. The queue persists across sessions and supports per-row quantity editing. Trashing or publishing a product removes it from the queue automatically.

**Mobile Quick Add — add products from your phone in under 20 seconds**

* **PWA (Progressive Web App)** — add the Quick Add page to your phone's home screen on iOS (Safari → Share → Add to Home Screen) or Android (Chrome menu → Add to Home Screen). It opens full-screen with no browser chrome, just like a native app.
* **Scan & Publish tab** — scan a draft product's barcode with the live camera, complete the name and category, add photos, and publish — all from one card.
* **Quick Add tab** — scan a Price Template barcode → auto-fill price and category → type a name → add photos → tap Save.
* **Live camera barcode scanner** — uses the `BarcodeDetector` API with the rear camera. Falls back gracefully with a typed-input prompt on unsupported browsers.
* **Multi-photo capture** — add up to 5 photos per product. Choose between taking a new photo (camera) or uploading from your library. Photos preview instantly from the local canvas; the first becomes the product's main image, the rest go to the WooCommerce gallery. Tap × on any thumbnail to remove it before saving.
* **Price Templates** — pre-configure price points (e.g. "Small — $25"). Each template gets a unique barcode. Scanning it auto-fills price, category, and label template.

**Batch Create**

* Generate multiple draft products in one go from a CSV or manual list.
* Scan the resulting draft barcodes with Quick Add → Scan & Publish to complete and publish each product.

**Inventory management**

* Scan or type a barcode/SKU to look up any product, adjust stock, log a manual sale, and view stock history.

**Other**

* **Order auto-queue** — optionally add ordered products to the print queue when an order reaches a chosen status (e.g. Processing).
* **REST API** — `/wcbp/v1/barcode/{id}`, `/wcbp/v1/queue`, `/wcbp/v1/templates/label`, `/wcbp/v1/templates/price`.
* **Onboarding tutorial** — a 6-step wizard guides new users from install to their first printed batch.
* **HPOS compatible** — declares `FeaturesUtil::declare_compatibility` for `custom_order_tables`.

== Installation ==

1. Upload the `woo-barcode-pro` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Navigate to **WooCommerce → Barcode Pro** to start the onboarding tutorial.

== Frequently Asked Questions ==

= Which barcode format should I use? =

Choose **Code 128** for maximum scanner compatibility. Choose **EAN-13** if your products have 12-digit numeric SKUs and you use standard retail scanners. Each label template can use a different symbology — configure it in the Barcode Design section of the Label Template editor.

= How do I add products quickly from my phone? =

Go to **Barcode Pro → Quick Add** on your phone. Add it to your home screen (iOS: Safari → Share → Add to Home Screen; Android: Chrome menu → Add to Home Screen) for instant one-tap access. Scan a Price Template barcode with the live camera, enter a product name, add up to 5 photos, and tap Save.

= Can I print on Avery labels? =

Yes. The default template is pre-configured for Avery 5160 (2.625″ × 1″, 30-up). You can create additional templates for any label sheet size.

= How do I save labels as a PDF? =

Open the print queue and click **Print Labels**. On the print page, click the green **Save as PDF** button — this opens the browser print dialog where you can choose "Save as PDF" as the printer.

= Can I add a company name or logo to my labels? =

Yes. In the Label Template editor, enable the **Company name** checkbox and enter your company name — it prints as a bold banner at the top of every label. For a logo, click the logo picker to choose an image from the WordPress media library.

= Does it work with WooCommerce HPOS? =

Yes. The plugin declares compatibility with WooCommerce High-Performance Order Storage (custom_order_tables).

== Screenshots ==

1. Print Queue — add, edit, and print labels from one screen.
2. Label Designer — configure size, layout, and fields with a live preview.
3. Quick Add — mobile-optimised product entry with camera capture and multi-photo support.
4. Price Templates — one template per price point, each with its own scannable barcode.
5. Tutorial — 6-step onboarding wizard.
6. Inventory — scan to look up stock, adjust quantities, and view history.

== Changelog ==

= 1.2.15 =
* Docs: Updated readme with all features through v1.2.14, PWA instructions, multi-photo FAQ, and full changelog.

= 1.2.14 =
* New: Quick Add and Scan-to-Publish now support up to 5 photos per product. The first photo becomes the main product image; additional photos are saved as WooCommerce gallery images. Photos preview instantly from the local canvas. Each thumbnail has a × remove button.

= 1.2.13 =
* New: Photo field on Quick Add now has two buttons — Take photo (opens camera directly) and Upload (opens photo library). Works in both the Quick Add form and the Scan-to-Publish draft card.

= 1.2.12 =
* New: Quick Add page is now a Progressive Web App (PWA). Add it to your phone's home screen on iOS or Android for a native app-like experience with no browser chrome and a custom icon.

= 1.2.11 =
* New: Company Name field on label templates — enable the checkbox and enter your company name to have it printed at the top of every label. Each template can have its own company name or none at all.

= 1.2.10 =
* Fixed: Print page no longer shows the WordPress admin sidebar and toolbar — it now opens as a clean standalone page.
* New: Save as PDF button added to the print toolbar.

= 1.2.9 =
* New: Per-template Barcode Designer — choose symbology (Code 128, EAN-13, UPC-A, ITF-14), bar height, bar width, text visibility, and bar colour independently for each label template.

= 1.2.8 =
* New: Label Template editor now has a live preview with editable test data (Name, Price, SKU, Barcode) — the real barcode SVG is fetched and rendered as you type.

= 1.2.7 =
* New: Trashing or permanently deleting a product now automatically removes it from the print queue.
* New: Publishing a product (including via Scan-to-Publish) automatically removes it from the print queue.

= 1.2.6 =
* New: Quick Add page now has two tabs — Scan & Publish (draft barcode scan flow) and Quick Add (price template new product form).
* New: Category selector added to the draft Scan-to-Publish card.

= 1.2.5 =
* Maintenance release.

= 1.2.4 =
* Fixed: Scanning a draft product barcode in Quick Add now shows the inline Complete & Publish card correctly.

= 1.2.3 =
* Fixed: Barcode lookup missed draft products.

= 1.2.2 =
* New: Live camera barcode scanner using the BarcodeDetector API.

= 1.2.0 =
* New: Batch Create and Scan-to-Publish workflow.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.15 =
Documentation update — no code changes. Safe to update.

= 1.2.14 =
Adds multi-photo support (up to 5) on Quick Add and Scan-to-Publish. Safe to update.
