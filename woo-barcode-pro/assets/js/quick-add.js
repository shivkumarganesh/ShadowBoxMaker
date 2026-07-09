/* global wcbpQuickAdd, jQuery */
(function ($) {
	'use strict';

	var uploadedImageId  = 0;
	var imageUploading   = false;
	var barcodeScanned   = '';
	var priceFromTpl     = 0;

	// ── Barcode lookup ────────────────────────────────────────────────────────
	function lookupBarcode(value) {
		if (!value) return;
		$('#wcbp-scan-status').text(wcbpQuickAdd.strings.looking_up).removeClass('wcbp-error wcbp-success');

		$.post(wcbpQuickAdd.ajax_url, {
			action : 'wcbp_lookup_price_template',
			nonce  : wcbpQuickAdd.nonce,
			barcode: value,
		}, function (res) {
			if (!res.success) return;
			var d = res.data;

			if ('template' === d.type) {
				barcodeScanned = value;
				priceFromTpl   = parseFloat(d.template.price) || 0;
				$('#wcbp-price').val(priceFromTpl.toFixed(2));
				$('#wcbp-template-id').val(d.template.id);

				// Pre-fill categories.
				if (d.template.category_ids && d.template.category_ids.length) {
					$('#wcbp-categories').val(d.template.category_ids).trigger('change');
				}

				$('#wcbp-scan-status').text(wcbpQuickAdd.strings.template_found + ' — ' + d.template.name).addClass('wcbp-success');
				$('#wcbp-name').focus();
			} else if ('product' === d.type) {
				$('#wcbp-scan-status').text(wcbpQuickAdd.strings.product_exists + ': ' + d.product.name).addClass('wcbp-error');
			} else {
				$('#wcbp-scan-status').text(wcbpQuickAdd.strings.unknown_barcode).addClass('wcbp-error');
			}
		});
	}

	// Camera icon tap → trigger native camera input
	$('#wcbp-barcode-input').on('keydown', function (e) {
		if (13 === e.which) {
			e.preventDefault();
			lookupBarcode($(this).val().trim());
		}
	});

	$('#wcbp-scan-btn').on('click', function () {
		$('#wcbp-barcode-file').trigger('click');
	});

	// BarcodeDetector API (Android Chrome)
	$('#wcbp-barcode-file').on('change', function () {
		var file = this.files[0];
		if (!file) return;
		if ('BarcodeDetector' in window) {
			var img = new Image();
			img.onload = function () {
				var bd = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'upc_a', 'qr_code'] });
				bd.detect(img).then(function (codes) {
					if (codes.length) lookupBarcode(codes[0].rawValue);
					else $('#wcbp-scan-status').text(wcbpQuickAdd.strings.no_barcode).addClass('wcbp-error');
				});
			};
			img.src = URL.createObjectURL(file);
		} else {
			// Fallback: user types barcode manually
			$('#wcbp-barcode-input').focus();
		}
	});

	// ── Camera image upload ───────────────────────────────────────────────────
	$('#wcbp-photo-input').on('change', function () {
		var file = this.files[0];
		if (!file) return;

		// Client-side resize via Canvas before upload
		var reader = new FileReader();
		reader.onload = function (e) {
			var img = new Image();
			img.onload = function () {
				var maxW = 1200, maxH = 1200;
				var w = img.width, h = img.height;
				if (w > maxW || h > maxH) {
					var scale = Math.min(maxW / w, maxH / h);
					w = Math.round(w * scale);
					h = Math.round(h * scale);
				}
				var canvas = document.createElement('canvas');
				canvas.width  = w;
				canvas.height = h;
				canvas.getContext('2d').drawImage(img, 0, 0, w, h);
				canvas.toBlob(function (blob) {
					uploadImage(blob, file.name);
				}, 'image/jpeg', 0.82);
				$('#wcbp-photo-preview').attr('src', canvas.toDataURL()).show();
			};
			img.src = e.target.result;
		};
		reader.readAsDataURL(file);
	});

	function uploadImage(blob, filename) {
		imageUploading = true;
		$('#wcbp-save-btn').prop('disabled', true);
		$('#wcbp-photo-status').text(wcbpQuickAdd.strings.uploading).removeClass('wcbp-error');
		var fd = new FormData();
		fd.append('action', 'wcbp_quick_upload_image');
		fd.append('nonce',  wcbpQuickAdd.nonce);
		fd.append('image',  blob, filename);

		$.ajax({
			url        : wcbpQuickAdd.ajax_url,
			type       : 'POST',
			data       : fd,
			processData: false,
			contentType: false,
			success    : function (res) {
				imageUploading = false;
				$('#wcbp-save-btn').prop('disabled', false);
				if (res.success) {
					uploadedImageId = res.data.attachment_id;
					$('#wcbp-photo-status').text(wcbpQuickAdd.strings.photo_ready);
				} else {
					$('#wcbp-photo-status').text(wcbpQuickAdd.strings.upload_failed).addClass('wcbp-error');
				}
			},
			error: function () {
				imageUploading = false;
				$('#wcbp-save-btn').prop('disabled', false);
				$('#wcbp-photo-status').text(wcbpQuickAdd.strings.upload_failed).addClass('wcbp-error');
			},
		});
	}

	// ── Save product ─────────────────────────────────────────────────────────
	$('#wcbp-quick-form').on('submit', function (e) {
		e.preventDefault();
		var name = $('#wcbp-name').val().trim();
		if (!name) {
			$('#wcbp-name').focus();
			return;
		}

		var categories = [];
		$('#wcbp-categories option:selected').each(function () { categories.push($(this).val()); });

		$('#wcbp-save-btn').prop('disabled', true).text(wcbpQuickAdd.strings.saving);

		$.post(wcbpQuickAdd.ajax_url, {
			action             : 'wcbp_quick_save_product',
			nonce              : wcbpQuickAdd.nonce,
			name               : name,
			price              : $('#wcbp-price').val(),
			category_ids       : categories,
			image_id           : uploadedImageId || 0,
			sku                : $('#wcbp-sku').val(),
			label_template_id  : $('#wcbp-template-id').val() || 0,
		}, function (res) {
			$('#wcbp-save-btn').prop('disabled', false).text(wcbpQuickAdd.strings.save);
			if (res.success) {
				// Show success then reset
				$('#wcbp-result').html(
					'<span class="wcbp-success">' + wcbpQuickAdd.strings.saved + ' ' +
					'<a href="' + res.data.edit_url + '" target="_blank">' + wcbpQuickAdd.strings.view + '</a></span>'
				).show();
				resetForm();
			} else {
				$('#wcbp-result').html('<span class="wcbp-error">' + (res.data.message || wcbpQuickAdd.strings.error) + '</span>').show();
			}
		});
	});

	function resetForm() {
		uploadedImageId = 0;
		imageUploading  = false;
		barcodeScanned  = '';
		$('#wcbp-barcode-input').val('');
		$('#wcbp-name').val('');
		$('#wcbp-sku').val('');
		$('#wcbp-price').val('');
		$('#wcbp-template-id').val('');
		$('#wcbp-photo-preview').hide().attr('src', '');
		$('#wcbp-scan-status, #wcbp-photo-status').text('').removeClass('wcbp-error wcbp-success');
		setTimeout(function () { $('#wcbp-result').hide(); }, 3000);
	}

}(jQuery));
