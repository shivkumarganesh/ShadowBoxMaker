/* global wcbpQuickAdd, jQuery */
(function ($) {
	'use strict';

	var uploadedImageId   = 0;
	var imageUploading    = false;
	var draftImageId      = 0;
	var draftImageUploading = false;
	var barcodeScanned    = '';
	var priceFromTpl      = 0;

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
				if (typeof wcbpSwitchTab === 'function') wcbpSwitchTab('add');
				$('#wcbp-name').focus();
			} else if ('product' === d.type) {
				if (d.product.status === 'draft') {
					// Show inline Scan-to-Publish card
					$('#wcbp-scan-status').text('').removeClass('wcbp-error wcbp-success');
					$('#wcbp-qa-draft-product-id').val(d.product.id);
					$('#wcbp-qa-draft-sku').text(d.product.sku || '—');
					$('#wcbp-qa-draft-name').val('');
					$('#wcbp-qa-draft-preview').hide().attr('src', '');
					$('#wcbp-qa-draft-photo-status').text('').removeClass('wcbp-error');
					$('#wcbp-qa-draft-result').text('').removeClass('wcbp-success wcbp-error');
					$('#wcbp-qa-publish-btn').prop('disabled', false).text('✓ ' + wcbpQuickAdd.strings.publish_btn);
					draftImageId = 0; draftImageUploading = false;
					$('#wcbp-qa-draft-card').show();
					$('#wcbp-qa-draft-name').focus();
				} else {
					$('#wcbp-scan-status').text(wcbpQuickAdd.strings.product_exists + ': ' + d.product.name).addClass('wcbp-error');
				}
			} else {
				$('#wcbp-scan-status').text(wcbpQuickAdd.strings.unknown_barcode).addClass('wcbp-error');
			}
		});
	}

	// Expose lookup for template buttons in the page markup
	window.lookupBarcode = lookupBarcode;

	// Enter key in the barcode input
	$('#wcbp-barcode-input').on('keydown', function (e) {
		if (13 === e.which) {
			e.preventDefault();
			lookupBarcode($(this).val().trim());
		}
	});

	// Dedicated look-up button
	$('#wcbp-lookup-btn').on('click', function () {
		var val = $('#wcbp-barcode-input').val().trim();
		if (val) {
			lookupBarcode(val);
		} else {
			$('#wcbp-barcode-input').focus();
		}
	});

	// Camera button — live camera scan
	$('#wcbp-scan-btn').on('click', function () {
		openCameraScanner(function (value) {
			$('#wcbp-barcode-input').val(value);
			lookupBarcode(value);
		});
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
		uploadedImageId     = 0;
		imageUploading      = false;
		draftImageId        = 0;
		draftImageUploading = false;
		barcodeScanned      = '';
		$('#wcbp-qa-draft-card').hide();
		$('#wcbp-qa-draft-categories').val(null);
		$('#wcbp-barcode-input').val('');
		$('#wcbp-name').val('');
		$('#wcbp-sku').val('');
		$('#wcbp-price').val('');
		$('#wcbp-template-id').val('');
		$('#wcbp-photo-preview').hide().attr('src', '');
		$('#wcbp-scan-status, #wcbp-photo-status').text('').removeClass('wcbp-error wcbp-success');
		setTimeout(function () { $('#wcbp-result').hide(); }, 3000);
	}

	// ── Draft publish card ────────────────────────────────────────────────────
	$('#wcbp-qa-draft-photo').on('change', function () {
		var file = this.files[0];
		if (!file) return;
		var reader = new FileReader();
		reader.onload = function (e) {
			var img = new Image();
			img.onload = function () {
				var maxW = 1200, maxH = 1200, w = img.width, h = img.height;
				if (w > maxW || h > maxH) { var s = Math.min(maxW/w, maxH/h); w = Math.round(w*s); h = Math.round(h*s); }
				var cv = document.createElement('canvas'); cv.width = w; cv.height = h;
				cv.getContext('2d').drawImage(img, 0, 0, w, h);
				cv.toBlob(function (blob) {
					draftImageUploading = true;
					$('#wcbp-qa-publish-btn').prop('disabled', true);
					$('#wcbp-qa-draft-photo-status').text(wcbpQuickAdd.strings.uploading).removeClass('wcbp-error');
					var fd = new FormData();
					fd.append('action', 'wcbp_quick_upload_image');
					fd.append('nonce', wcbpQuickAdd.nonce);
					fd.append('image', blob, file.name);
					$.ajax({ url: wcbpQuickAdd.ajax_url, type: 'POST', data: fd, processData: false, contentType: false,
						success: function (res) {
							draftImageUploading = false;
							$('#wcbp-qa-publish-btn').prop('disabled', false);
							if (res.success) { draftImageId = res.data.attachment_id; $('#wcbp-qa-draft-photo-status').text(wcbpQuickAdd.strings.photo_ready); }
							else { $('#wcbp-qa-draft-photo-status').text(wcbpQuickAdd.strings.upload_failed).addClass('wcbp-error'); }
						},
						error: function () {
							draftImageUploading = false;
							$('#wcbp-qa-publish-btn').prop('disabled', false);
							$('#wcbp-qa-draft-photo-status').text(wcbpQuickAdd.strings.upload_failed).addClass('wcbp-error');
						}
					});
				}, 'image/jpeg', 0.82);
				$('#wcbp-qa-draft-preview').attr('src', cv.toDataURL()).show();
			};
			img.src = e.target.result;
		};
		reader.readAsDataURL(file);
	});

	$('#wcbp-qa-publish-btn').on('click', function () {
		var pid  = parseInt($('#wcbp-qa-draft-product-id').val(), 10);
		var name = $('#wcbp-qa-draft-name').val().trim();
		if (!name) { $('#wcbp-qa-draft-name').focus(); return; }
		if (draftImageUploading) return;
		var $btn = $(this).prop('disabled', true).text(wcbpQuickAdd.strings.publishing);
		var draftCats = [];
		$('#wcbp-qa-draft-categories option:selected').each(function () { draftCats.push($(this).val()); });
		$.post(wcbpQuickAdd.ajax_url, {
			action       : 'wcbp_inv_publish_draft',
			nonce        : wcbpQuickAdd.inv_nonce,
			product_id   : pid,
			name         : name,
			image_id     : draftImageId || 0,
			category_ids : draftCats,
		}, function (res) {
			$btn.prop('disabled', false).text('✓ ' + wcbpQuickAdd.strings.publish_btn);
			if (res.success) {
				$('#wcbp-qa-draft-result').html(
					wcbpQuickAdd.strings.published_ok + ' <a href="' + res.data.edit_url + '" target="_blank">' + wcbpQuickAdd.strings.view + '</a>'
				).addClass('wcbp-success').removeClass('wcbp-error');
				// Reset for next scan
				setTimeout(function () {
					$('#wcbp-qa-draft-card').hide();
					$('#wcbp-barcode-input').val('').focus();
				}, 2500);
			} else {
				$('#wcbp-qa-draft-result').text(res.data.message || wcbpQuickAdd.strings.error).addClass('wcbp-error').removeClass('wcbp-success');
			}
		});
	});

	// ── Live camera scanner ───────────────────────────────────────────────────
	var _cameraStream = null;

	function openCameraScanner(onDetected) {
		if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			$('#wcbp-scan-status').text(wcbpQuickAdd.strings.no_camera_api).addClass('wcbp-error');
			$('#wcbp-barcode-input').focus();
			return;
		}

		var video   = document.getElementById('wcbp-camera-video');
		var $modal  = $('#wcbp-camera-modal');
		var scanning = true;

		navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } } })
			.then(function (stream) {
				_cameraStream = stream;
				video.srcObject = stream;
				video.play();
				$modal.css('display', 'flex');

				var detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'qr_code', 'code_39', 'code_93', 'itf'] });

				function tick() {
					if (!scanning) return;
					if (video.readyState < video.HAVE_ENOUGH_DATA) { requestAnimationFrame(tick); return; }
					detector.detect(video).then(function (codes) {
						if (codes.length && scanning) {
							scanning = false;
							closeCamera();
							onDetected(codes[0].rawValue);
						} else {
							requestAnimationFrame(tick);
						}
					}).catch(function () { if (scanning) requestAnimationFrame(tick); });
				}
				requestAnimationFrame(tick);
			})
			.catch(function (err) {
				$('#wcbp-scan-status').text(wcbpQuickAdd.strings.camera_error + ' ' + err.message).addClass('wcbp-error');
			});

		function closeCamera() {
			scanning = false;
			if (_cameraStream) { _cameraStream.getTracks().forEach(function (t) { t.stop(); }); _cameraStream = null; }
			if (video) { video.srcObject = null; }
			$modal.hide();
		}

		$('#wcbp-camera-close').off('click').on('click', closeCamera);
	}

}(jQuery));
