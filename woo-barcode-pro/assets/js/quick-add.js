/* global wcbpQuickAdd, jQuery */
(function ($) {
	'use strict';

	var MAX_PHOTOS = 5;

	// Photo state — one group per UI section
	var mainPhotos  = { ids: [], thumbUrls: {} };
	var draftPhotos = { ids: [], thumbUrls: {} };
	var mainUploading  = { count: 0 };
	var draftUploading = { count: 0 };

	var barcodeScanned = '';
	var priceFromTpl   = 0;

	// ── UI config objects ────────────────────────────────────────────────────
	var MAIN_CFG = {
		gallery      : '#wcbp-photo-gallery',
		count        : '#wcbp-photo-count',
		cameraInput  : '#wcbp-photo-camera',
		uploadInput  : '#wcbp-photo-upload',
		cameraLabel  : 'label[for="wcbp-photo-camera"]',
		uploadLabel  : 'label[for="wcbp-photo-upload"]',
		statusEl     : '#wcbp-photo-status',
		blockBtn     : '#wcbp-save-btn',
	};
	var DRAFT_CFG = {
		gallery      : '#wcbp-qa-draft-gallery',
		count        : '#wcbp-qa-draft-photo-count',
		cameraInput  : '#wcbp-qa-draft-photo-camera',
		uploadInput  : '#wcbp-qa-draft-photo-upload',
		cameraLabel  : 'label[for="wcbp-qa-draft-photo-camera"]',
		uploadLabel  : 'label[for="wcbp-qa-draft-photo-upload"]',
		statusEl     : '#wcbp-qa-draft-photo-status',
		blockBtn     : '#wcbp-qa-publish-btn',
	};

	// ── Gallery render ───────────────────────────────────────────────────────
	function renderGallery(photos, cfg) {
		var $g = $(cfg.gallery).empty();
		$.each(photos.ids, function (i, id) {
			var url  = photos.thumbUrls[id] || '';
			var $th  = $('<div class="wcbp-thumb">').attr('data-id', id);
			if (url) {
				$th.append($('<img>').attr({ src: url, alt: '' }));
			} else {
				$th.append('<div class="wcbp-thumb-loading">⏳</div>');
			}
			$th.append($('<button type="button" class="wcbp-thumb-remove" aria-label="Remove">').text('×'));
			$g.append($th);
		});

		var n    = photos.ids.length;
		var full = n >= MAX_PHOTOS;
		$(cfg.count).text(n + ' / ' + MAX_PHOTOS).toggleClass('wcbp-photo-count--full', full);
		$(cfg.cameraLabel + ', ' + cfg.uploadLabel).toggleClass('wcbp-photo-btn--disabled', full);
		$(cfg.cameraInput + ', ' + cfg.uploadInput).prop('disabled', full);
	}

	// ── Remove a photo thumbnail ─────────────────────────────────────────────
	$(document).on('click', '.wcbp-thumb-remove', function () {
		var $thumb = $(this).closest('.wcbp-thumb');
		var id     = $thumb.data('id');
		var inMain = $thumb.closest('#wcbp-photo-gallery').length > 0;
		var photos = inMain ? mainPhotos : draftPhotos;
		var cfg    = inMain ? MAIN_CFG   : DRAFT_CFG;
		var idx    = photos.ids.indexOf(id);
		if (idx !== -1) photos.ids.splice(idx, 1);
		delete photos.thumbUrls[id];
		renderGallery(photos, cfg);
	});

	// ── Resize + upload helper ───────────────────────────────────────────────
	function processFile(file, photos, cfg, uploadingRef) {
		if (photos.ids.length >= MAX_PHOTOS) return;

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
				canvas.width = w; canvas.height = h;
				canvas.getContext('2d').drawImage(img, 0, 0, w, h);

				// Show local preview immediately while uploading
				var localUrl = canvas.toDataURL('image/jpeg', 0.82);
				var tempId   = 'tmp-' + Date.now() + '-' + Math.floor(Math.random() * 1e6);
				photos.ids.push(tempId);
				photos.thumbUrls[tempId] = localUrl;
				renderGallery(photos, cfg);

				canvas.toBlob(function (blob) {
					uploadingRef.count++;
					$(cfg.blockBtn).prop('disabled', true);
					$(cfg.statusEl).text(wcbpQuickAdd.strings.uploading).removeClass('wcbp-error');

					var fd = new FormData();
					fd.append('action', 'wcbp_quick_upload_image');
					fd.append('nonce',  wcbpQuickAdd.nonce);
					fd.append('image',  blob, file.name);

					$.ajax({
						url         : wcbpQuickAdd.ajax_url,
						type        : 'POST',
						data        : fd,
						processData : false,
						contentType : false,
						success     : function (res) {
							uploadingRef.count--;
							if (uploadingRef.count === 0) $(cfg.blockBtn).prop('disabled', false);
							var idx = photos.ids.indexOf(tempId);
							if (res.success) {
								var realId = res.data.attachment_id;
								if (idx !== -1) photos.ids[idx] = realId;
								delete photos.thumbUrls[tempId];
								photos.thumbUrls[realId] = res.data.url || localUrl;
								var n = photos.ids.length;
								$(cfg.statusEl).text(n + (n === 1 ? ' photo' : ' photos') + ' ready ✓').removeClass('wcbp-error');
							} else {
								if (idx !== -1) photos.ids.splice(idx, 1);
								delete photos.thumbUrls[tempId];
								$(cfg.statusEl).text(wcbpQuickAdd.strings.upload_failed).addClass('wcbp-error');
							}
							renderGallery(photos, cfg);
						},
						error       : function () {
							uploadingRef.count--;
							if (uploadingRef.count === 0) $(cfg.blockBtn).prop('disabled', false);
							var idx = photos.ids.indexOf(tempId);
							if (idx !== -1) photos.ids.splice(idx, 1);
							delete photos.thumbUrls[tempId];
							$(cfg.statusEl).text(wcbpQuickAdd.strings.upload_failed).addClass('wcbp-error');
							renderGallery(photos, cfg);
						},
					});
				}, 'image/jpeg', 0.82);
			};
			img.src = e.target.result;
		};
		reader.readAsDataURL(file);
	}

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
				if (d.template.category_ids && d.template.category_ids.length) {
					$('#wcbp-categories').val(d.template.category_ids).trigger('change');
				}
				$('#wcbp-scan-status').text(wcbpQuickAdd.strings.template_found + ' — ' + d.template.name).addClass('wcbp-success');
				if (typeof wcbpSwitchTab === 'function') wcbpSwitchTab('add');
				$('#wcbp-name').focus();
			} else if ('product' === d.type) {
				if (d.product.status === 'draft') {
					$('#wcbp-scan-status').text('').removeClass('wcbp-error wcbp-success');
					$('#wcbp-qa-draft-product-id').val(d.product.id);
					$('#wcbp-qa-draft-sku').text(d.product.sku || '—');
					$('#wcbp-qa-draft-name').val('');
					$('#wcbp-qa-draft-photo-status').text('').removeClass('wcbp-error');
					$('#wcbp-qa-draft-result').text('').removeClass('wcbp-success wcbp-error');
					$('#wcbp-qa-publish-btn').prop('disabled', false).text('✓ ' + wcbpQuickAdd.strings.publish_btn);
					draftPhotos.ids = []; draftPhotos.thumbUrls = {};
					draftUploading.count = 0;
					renderGallery(draftPhotos, DRAFT_CFG);
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

	window.lookupBarcode = lookupBarcode;

	$('#wcbp-barcode-input').on('keydown', function (e) {
		if (13 === e.which) { e.preventDefault(); lookupBarcode($(this).val().trim()); }
	});

	$('#wcbp-lookup-btn').on('click', function () {
		var val = $('#wcbp-barcode-input').val().trim();
		if (val) { lookupBarcode(val); } else { $('#wcbp-barcode-input').focus(); }
	});

	$('#wcbp-scan-btn').on('click', function () {
		openCameraScanner(function (value) {
			$('#wcbp-barcode-input').val(value);
			lookupBarcode(value);
		});
	});

	// ── Photo inputs ─────────────────────────────────────────────────────────
	$('#wcbp-photo-camera, #wcbp-photo-upload').on('change', function () {
		var file = this.files[0];
		if (!file) return;
		$(this).val(''); // reset so same file can be re-selected
		processFile(file, mainPhotos, MAIN_CFG, mainUploading);
	});

	$('#wcbp-qa-draft-photo-camera, #wcbp-qa-draft-photo-upload').on('change', function () {
		var file = this.files[0];
		if (!file) return;
		$(this).val('');
		processFile(file, draftPhotos, DRAFT_CFG, draftUploading);
	});

	// ── Save product ─────────────────────────────────────────────────────────
	$('#wcbp-quick-form').on('submit', function (e) {
		e.preventDefault();
		if (mainUploading.count > 0) return;
		var name = $('#wcbp-name').val().trim();
		if (!name) { $('#wcbp-name').focus(); return; }

		var categories = [];
		$('#wcbp-categories option:selected').each(function () { categories.push($(this).val()); });

		$('#wcbp-save-btn').prop('disabled', true).text(wcbpQuickAdd.strings.saving);

		$.post(wcbpQuickAdd.ajax_url, {
			action            : 'wcbp_quick_save_product',
			nonce             : wcbpQuickAdd.nonce,
			name              : name,
			price             : $('#wcbp-price').val(),
			category_ids      : categories,
			image_ids         : mainPhotos.ids,
			sku               : $('#wcbp-sku').val(),
			label_template_id : $('#wcbp-template-id').val() || 0,
		}, function (res) {
			$('#wcbp-save-btn').prop('disabled', false).text(wcbpQuickAdd.strings.save);
			if (res.success) {
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
		mainPhotos.ids = []; mainPhotos.thumbUrls = {};
		mainUploading.count = 0;
		renderGallery(mainPhotos, MAIN_CFG);
		barcodeScanned = '';
		$('#wcbp-qa-draft-card').hide();
		$('#wcbp-qa-draft-categories').val(null);
		$('#wcbp-barcode-input').val('');
		$('#wcbp-name, #wcbp-sku, #wcbp-price, #wcbp-template-id').val('');
		$('#wcbp-scan-status, #wcbp-photo-status').text('').removeClass('wcbp-error wcbp-success');
		setTimeout(function () { $('#wcbp-result').hide(); }, 3000);
	}

	// ── Draft publish card ────────────────────────────────────────────────────
	$('#wcbp-qa-publish-btn').on('click', function () {
		if (draftUploading.count > 0) return;
		var pid  = parseInt($('#wcbp-qa-draft-product-id').val(), 10);
		var name = $('#wcbp-qa-draft-name').val().trim();
		if (!name) { $('#wcbp-qa-draft-name').focus(); return; }
		var $btn = $(this).prop('disabled', true).text(wcbpQuickAdd.strings.publishing);
		var draftCats = [];
		$('#wcbp-qa-draft-categories option:selected').each(function () { draftCats.push($(this).val()); });
		$.post(wcbpQuickAdd.ajax_url, {
			action       : 'wcbp_inv_publish_draft',
			nonce        : wcbpQuickAdd.inv_nonce,
			product_id   : pid,
			name         : name,
			image_ids    : draftPhotos.ids,
			category_ids : draftCats,
		}, function (res) {
			$btn.prop('disabled', false).text('✓ ' + wcbpQuickAdd.strings.publish_btn);
			if (res.success) {
				$('#wcbp-qa-draft-result').html(
					wcbpQuickAdd.strings.published_ok + ' <a href="' + res.data.edit_url + '" target="_blank">' + wcbpQuickAdd.strings.view + '</a>'
				).addClass('wcbp-success').removeClass('wcbp-error');
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
