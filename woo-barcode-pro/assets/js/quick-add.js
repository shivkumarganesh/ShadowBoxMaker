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

	// ── Draft inventory panel ────────────────────────────────────────────────
	var draftListOpen = false;

	$('#wcbp-draft-toggle').on('click', function () {
		draftListOpen = !draftListOpen;
		$('#wcbp-draft-list').toggle(draftListOpen);
		$('#wcbp-draft-chevron').text(draftListOpen ? '▴' : '▾');
	});

	function renderDraftList(drafts) {
		var $inner = $('#wcbp-draft-list-inner').empty();
		if (!drafts || !drafts.length) {
			$inner.html('<p style="margin:8px 4px;font-size:13px;color:#888">' + wcbpQuickAdd.strings.no_drafts + '</p>');
			$('#wcbp-draft-badge').hide();
			return;
		}
		$('#wcbp-draft-badge').text(drafts.length).show();
		$.each(drafts, function (i, d) {
			var units = d.stock === 1
				? d.stock + ' ' + wcbpQuickAdd.strings.unit
				: d.stock + ' ' + wcbpQuickAdd.strings.units;
			var price = d.price ? ' · ' + d.price : '';
			var $item = $('<div class="wcbp-draft-item" role="button" tabindex="0">');
			$item.attr('data-id', d.id).attr('data-sku', d.sku).attr('data-name', d.name).attr('data-price', d.price || '');
			$item.html(
				'<div class="wcbp-draft-item-sku">' + escDraftHtml(d.sku || '#' + d.id) + '</div>' +
				'<div class="wcbp-draft-item-meta">' +
					escDraftHtml(d.name) + price + ' · ' + units +
				'</div>' +
				'<div class="wcbp-draft-item-hint">' + wcbpQuickAdd.strings.tap_to_complete + '</div>'
			);
			$inner.append($item);
		});
	}

	function escDraftHtml(str) {
		return $('<div>').text(String(str || '')).html();
	}

	$(document).on('click keydown', '.wcbp-draft-item', function (e) {
		if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) return;
		var $el  = $(this);
		var data = {
			id    : parseInt($el.attr('data-id'), 10),
			sku   : $el.attr('data-sku') || '',
			name  : $el.attr('data-name') || '',
			price : $el.attr('data-price') || '',
		};
		openDraftCard(data);
		if (typeof wcbpSwitchTab === 'function') wcbpSwitchTab('scan');
	});

	function openDraftCard(data) {
		$('#wcbp-qa-draft-product-id').val(data.id);
		$('#wcbp-qa-draft-sku').text(data.sku || '—');
		$('#wcbp-qa-draft-name').val(data.name || '');
		$('#wcbp-qa-draft-stock-qty').val('1');
		$('#wcbp-qa-draft-photo-status').text('').removeClass('wcbp-error');
		$('#wcbp-qa-draft-result').text('').removeClass('wcbp-success wcbp-error');
		$('#wcbp-qa-publish-btn').prop('disabled', false).text('✓ ' + wcbpQuickAdd.strings.publish_btn);
		draftPhotos.ids = []; draftPhotos.thumbUrls = {};
		draftUploading.count = 0;
		renderGallery(draftPhotos, DRAFT_CFG);
		$('#wcbp-qa-draft-card').show();
		$('#wcbp-qa-draft-name').focus();
	}

	function loadDraftInventory() {
		$.post(wcbpQuickAdd.ajax_url, {
			action : 'wcbp_qa_get_drafts',
			nonce  : wcbpQuickAdd.nonce,
		}, function (res) {
			if (res.success) {
				renderDraftList(res.data.drafts);
			}
		});
	}

	loadDraftInventory();

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
				$('#wcbp-template-id').val(d.template.label_template_id || 0);
				if (d.template.category_ids && d.template.category_ids.length) {
					$('#wcbp-categories').val(d.template.category_ids).trigger('change');
				}
				$('#wcbp-scan-status').text(wcbpQuickAdd.strings.template_found + ' — ' + d.template.name).addClass('wcbp-success');
				if (typeof wcbpSwitchTab === 'function') wcbpSwitchTab('add');
				$('#wcbp-name').focus();
			} else if ('product' === d.type) {
				if (d.product.status === 'draft') {
					$('#wcbp-scan-status').text('').removeClass('wcbp-error wcbp-success');
					openDraftCard({
						id    : d.product.id,
						sku   : d.product.sku,
						name  : '',
						price : '',
					});
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

	// ── Sizes (variable product) ─────────────────────────────────────────────
	$('#wcbp-has-sizes').on('change', function () {
		var on = this.checked;
		$('#wcbp-sizes-panel').toggle(on);
		$('#wcbp-stock-qty-field').toggle(!on);
	});

	$('#wcbp-sizes-panel').on('click', '.wcbp-size-plus, .wcbp-size-minus', function () {
		var $qty = $(this).siblings('.wcbp-size-qty');
		var n = (parseInt($qty.val(), 10) || 0) + ($(this).hasClass('wcbp-size-plus') ? 1 : -1);
		$qty.val(Math.max(0, n));
		updateSizeTotal();
	});
	$('#wcbp-sizes-panel').on('input', '.wcbp-size-qty', updateSizeTotal);

	// Typing colors next to a size adds a line per color, live (saved only on Save).
	$('#wcbp-sizes-panel').on('input', '.wcbp-size-colors', function () {
		syncColorLines($(this).closest('.wcbp-size-row'));
	});

	function textHtml(str) { return $('<span>').text(String(str)).html(); }

	function parseColors(text) {
		var seen = {}, out = [];
		String(text).split(',').forEach(function (c) {
			c = $.trim(c);
			var key = c.toLowerCase();
			if (c && !seen[key]) { seen[key] = true; out.push(c); }
		});
		return out;
	}

	function colorLine(color) {
		var $line = $('#wcbp-sizes-panel .wcbp-size-line--plain').first().clone()
			.removeClass('wcbp-size-line--plain').show();
		$line.find('.wcbp-size-qty').val('0').attr('aria-label', color);
		$line.find('.wcbp-size-price').val('');
		return $line;
	}

	function syncColorLines($row) {
		var colors = parseColors($row.find('.wcbp-size-colors').val());
		var keys   = colors.map(function (c) { return c.toLowerCase(); });
		var $box   = $row.find('.wcbp-size-color-lines');
		var old    = $box.children('.wcbp-size-line').toArray();
		var byKey  = {};
		old.forEach(function (el) { byKey[$(el).attr('data-key')] = el; });

		var lines = colors.map(function (color, i) {
			var key = keys[i];
			var el  = byKey[key];
			// An edited color ("Re" → "Red") keeps the units already entered on that line.
			if (!el && old[i] && keys.indexOf($(old[i]).attr('data-key')) === -1) { el = old[i]; }
			var $line = el ? $(el) : colorLine(color);
			$line.attr('data-key', key).attr('data-color', color);
			$line.find('.wcbp-line-label').html(textHtml(color));
			return $line.get(0);
		});
		$box.empty().append(lines);
		$row.find('.wcbp-size-line--plain').toggle(colors.length === 0);
		updateSizeTotal();
	}

	// Lines that count: the color lines when a size has colors, otherwise the plain line.
	function sizeLines($row) {
		var $colored = $row.find('.wcbp-size-color-lines .wcbp-size-line');
		return $colored.length ? $colored : $row.find('.wcbp-size-line--plain');
	}

	function lineQty($line) {
		return Math.max(0, parseInt($line.find('.wcbp-size-qty').val(), 10) || 0);
	}

	function updateSizeTotal() {
		var total = 0;
		$('.wcbp-size-row').each(function () {
			var rowTotal = 0;
			sizeLines($(this)).each(function () {
				var n = lineQty($(this));
				$(this).toggleClass('has-qty', n > 0);
				rowTotal += n;
			});
			$(this).toggleClass('has-qty', rowTotal > 0);
			total += rowTotal;
		});
		$('#wcbp-size-total').text(total);
		return total;
	}

	// Sizes with units but no color, while another size uses colors.
	function sizesMissingColor() {
		var colored = false, missing = [];
		$('.wcbp-size-row').each(function () {
			var $row = $(this);
			if ($row.find('.wcbp-size-color-lines .wcbp-size-line').length) {
				sizeLines($row).each(function () { if (lineQty($(this)) > 0) { colored = true; } });
			} else if (lineQty($row.find('.wcbp-size-line--plain')) > 0) {
				missing.push($row.data('size'));
			}
		});
		return colored ? missing : [];
	}

	function collectSizes() {
		var sizes = {};
		$('.wcbp-size-row').each(function () {
			var $row   = $(this);
			var $plain = $row.find('.wcbp-size-line--plain');
			var colors = $row.find('.wcbp-size-color-lines .wcbp-size-line').map(function () {
				return { name: $(this).attr('data-color'), qty: lineQty($(this)), price: $(this).find('.wcbp-size-price').val() };
			}).get();
			sizes[$row.data('size')] = { qty: lineQty($plain), price: $plain.find('.wcbp-size-price').val(), colors: colors };
		});
		return sizes;
	}

	// ── Save product ─────────────────────────────────────────────────────────
	$('#wcbp-quick-form').on('submit', function (e) {
		e.preventDefault();
		if (mainUploading.count > 0) return;
		var name = $('#wcbp-name').val().trim();
		if (!name) { $('#wcbp-name').focus(); return; }

		var hasSizes = $('#wcbp-has-sizes').is(':checked');
		if (hasSizes && updateSizeTotal() === 0) {
			$('#wcbp-result').html('<span class="wcbp-error">' + wcbpQuickAdd.strings.sizes_required + '</span>').show();
			$('.wcbp-size-qty').first().focus();
			return;
		}
		var missing = hasSizes ? sizesMissingColor() : [];
		if (missing.length) {
			$('#wcbp-result').html('<span class="wcbp-error">' + textHtml(wcbpQuickAdd.strings.colors_required.replace('%s', missing.join(', '))) + '</span>').show();
			$('.wcbp-size-row[data-size="' + missing[0] + '"] .wcbp-size-colors').focus();
			return;
		}

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
			stock_qty         : parseInt($('#wcbp-stock-qty').val(), 10) || 1,
			label_template_id : $('#wcbp-template-id').val() || 0,
			has_sizes         : hasSizes ? 1 : 0,
			sizes             : hasSizes ? collectSizes() : {},
		}, function (res) {
			$('#wcbp-save-btn').prop('disabled', false).text(wcbpQuickAdd.strings.save);
			if (res.success) {
				$('#wcbp-result').html(
					'<span class="wcbp-success">' + (hasSizes && res.data.message ? res.data.message : wcbpQuickAdd.strings.saved) + ' ' +
					'<a href="' + res.data.edit_url + '" target="_blank">' + wcbpQuickAdd.strings.view + '</a></span>'
				).show();
				resetForm();
				loadDraftInventory();
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
		$('#wcbp-stock-qty').val('1');
		$('.wcbp-size-color-lines').empty();
		$('.wcbp-size-colors').val('');
		$('.wcbp-size-line--plain').show();
		$('.wcbp-size-qty').val('0');
		$('.wcbp-size-price').val('');
		updateSizeTotal();
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
			stock_qty    : parseInt($('#wcbp-qa-draft-stock-qty').val(), 10) || 1,
		}, function (res) {
			$btn.prop('disabled', false).text('✓ ' + wcbpQuickAdd.strings.publish_btn);
			if (res.success) {
				$('#wcbp-qa-draft-result').html(
					wcbpQuickAdd.strings.published_ok + ' <a href="' + res.data.edit_url + '" target="_blank">' + wcbpQuickAdd.strings.view + '</a>'
				).addClass('wcbp-success').removeClass('wcbp-error');
				loadDraftInventory();
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

	// opts.status: element for messages; opts.continuous: keep scanning after each code (Tally).
	function openCameraScanner(onDetected, opts) {
		opts = opts || {};
		var $status = $(opts.status || '#wcbp-scan-status');
		if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			$status.text(wcbpQuickAdd.strings.no_camera_api).addClass('wcbp-error');
			$(opts.status ? '#wcbp-tally-input' : '#wcbp-barcode-input').trigger('focus');
			return;
		}

		var video    = document.getElementById('wcbp-camera-video');
		var $modal   = $('#wcbp-camera-modal');
		// Inside a hidden tab the preview can't show; keep the window directly under <body>.
		if (!$modal.parent().is('body')) { $modal.appendTo(document.body); }
		var $camMsg  = $('#wcbp-camera-status');
		var camText  = $camMsg.data('default') || $camMsg.text();
		$camMsg.data('default', camText).text(camText);
		var scanning = true;
		var lastValue = '', lastSeenAt = 0;

		navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } } })
			.then(function (stream) {
				_cameraStream = stream;
				video.srcObject = stream;
				video.play();
				$modal.css('display', 'flex');

				var detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'qr_code', 'code_39', 'code_93', 'itf'] });

				function tick() {
					if (!scanning) return;
					if (!$modal.is(':visible')) { closeCamera(); return; } // never scan without a visible preview
					if (video.readyState < video.HAVE_ENOUGH_DATA) { requestAnimationFrame(tick); return; }
					detector.detect(video).then(function (codes) {
						if (!scanning) return;
						if (codes.length && opts.continuous) {
							// The same label stays in view for many frames: count it again only after it has left the frame.
							var v = codes[0].rawValue, now = Date.now();
							if (v !== lastValue || now - lastSeenAt > 700) { onDetected(v); }
							lastValue = v; lastSeenAt = now;
							setTimeout(function () { requestAnimationFrame(tick); }, 120);
						} else if (codes.length) {
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
				$status.text(wcbpQuickAdd.strings.camera_error + ' ' + err.message).addClass('wcbp-error');
			});

		function closeCamera() {
			scanning = false;
			if (_cameraStream) { _cameraStream.getTracks().forEach(function (t) { t.stop(); }); _cameraStream = null; }
			if (video) { video.srcObject = null; }
			$(document).off('visibilitychange.wcbpcam');
			$camMsg.text(camText);
			$modal.hide();
		}

		$('#wcbp-camera-close').off('click').on('click', closeCamera);
		$(document).off('visibilitychange.wcbpcam').on('visibilitychange.wcbpcam', function () {
			if (document.hidden) { closeCamera(); }
		});
	}

	// ── Tally (scan-to-count) ────────────────────────────────────────────────
	var TS         = wcbpQuickAdd.strings;
	var COUNTS_KEY = 'wcbp_tally_counts';
	var tally      = { product: null, editing: 0 };
	var tallyCounts = loadTallyCounts();
	var audioCtx   = null;

	// Counts live in this browser so a refresh mid-tally doesn't lose them.
	function loadTallyCounts() {
		try { return JSON.parse(window.localStorage.getItem(COUNTS_KEY) || '{}') || {}; } catch (e) { return {}; }
	}
	function saveTallyCounts() {
		try { window.localStorage.setItem(COUNTS_KEY, JSON.stringify(tallyCounts)); } catch (e) { /* private mode: counts last for this page only */ }
	}
	// Per product: c = scans per item, q = labels already queued from this tally (so they aren't queued twice).
	function tallyState(pid) {
		var st = tallyCounts[pid];
		if (!st || !st.c) { st = tallyCounts[pid] = { c: {}, q: {} }; }
		return st;
	}
	function countsFor(pid) { return tallyState(pid).c; }
	function queuedFor(pid) { return tallyState(pid).q; }
	function missingFor(it, pid) {
		if (it.stock === null) { return 0; }
		return Math.max(0, it.stock - (countsFor(pid)[it.id] || 0) - (queuedFor(pid)[it.id] || 0));
	}

	function fmt(str) {
		var args = arguments, next = 1;
		return String(str).replace(/%(?:(\d+)\$)?d/g, function (m, pos) { return args[pos ? parseInt(pos, 10) : next++]; });
	}

	function beep(ok) {
		try {
			audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
			var osc = audioCtx.createOscillator(), gain = audioCtx.createGain();
			osc.type = ok ? 'sine' : 'square';
			osc.frequency.value = ok ? 1046 : 220;
			gain.gain.value = 0.08;
			osc.connect(gain); gain.connect(audioCtx.destination);
			osc.start(); osc.stop(audioCtx.currentTime + (ok ? 0.09 : 0.3));
		} catch (e) { /* no audio */ }
		if (!ok && navigator.vibrate) { navigator.vibrate(200); }
	}

	function flash(kind, big, small) {
		$('#wcbp-tally-flash').attr('class', 'wcbp-tally-flash is-' + kind)
			.html('<strong>' + textHtml(big) + '</strong>' + (small ? '<span>' + textHtml(small) + '</span>' : ''));
		if ($('#wcbp-camera-modal').is(':visible')) {
			$('#wcbp-camera-status').text(big + (small ? ' — ' + small : ''));
		}
	}

	function itemById(id) {
		var p = tally.product;
		if (!p) { return null; }
		for (var i = 0; i < p.items.length; i++) { if (p.items[i].id === id) { return p.items[i]; } }
		return null;
	}

	function itemByCode(code) {
		var p = tally.product;
		if (!p) { return null; }
		for (var i = 0; i < p.items.length; i++) {
			if (p.items[i].codes.indexOf(code) !== -1) { return p.items[i]; }
		}
		return null;
	}

	function tallyScan(code) {
		code = $.trim(String(code));
		if (!code) { return; }
		// Labels of the product already on screen are counted without a round trip.
		var local = itemByCode(code);
		if (local) { countItem(local); return; }

		flash('info', TS.looking_up, '');
		$.post(wcbpQuickAdd.ajax_url, { action: 'wcbp_tally_lookup', nonce: wcbpQuickAdd.inv_nonce, barcode: code }, function (res) {
			if (!res || !res.success) {
				flash('err', (res && res.data && res.data.message) || TS.error, '');
				beep(false);
				return;
			}
			tally.product = res.data;
			tally.editing = 0;
			var item = itemById(res.data.scanned_id);
			if (item) {
				countItem(item);
			} else {
				renderTally();
				flash('warn', res.data.name, TS.t_parent_code);
				beep(false);
			}
		}).fail(function () { flash('err', TS.error, code); beep(false); });
	}

	function countItem(item) {
		var counts = countsFor(tally.product.product_id);
		var n = (counts[item.id] || 0) + 1;
		counts[item.id] = n;
		saveTallyCounts();
		renderTally();
		if (item.stock === null) {
			flash('info', item.label, n + ' · ' + TS.t_untracked);
			beep(true);
		} else if (n > item.stock) {
			flash('warn', item.label, fmt(TS.t_of, n, item.stock) + ' — ' + TS.t_more);
			beep(false);
		} else {
			flash('ok', item.label, fmt(TS.t_of, n, item.stock));
			beep(true);
		}
	}

	function renderTally() {
		var p = tally.product;
		if (!p) { $('#wcbp-tally-card').hide(); return; }
		var counts = countsFor(p.product_id);
		var totalStock = 0, totalScanned = 0, missing = 0, applyRows = 0, totalQueued = 0, html = '';

		p.items.forEach(function (it) {
			var n = counts[it.id] || 0;
			var st = it.stock;
			totalScanned += n;
			if (st !== null) { totalStock += st; }
			missing += missingFor(it, p.product_id);
			if (st === null ? n > 0 : n !== st) { applyRows++; }
			var cls = st === null ? '' : (n === st ? (st ? 'row-ok' : '') : (n < st ? 'row-short' : 'row-over'));
			var sub = [];
			if (it.sku) { sub.push(textHtml(it.sku)); }
			if (it.queued) {
				sub.push('<span class="t-queued">🏷 ' + it.queued + ' ' + textHtml(TS.t_in_queue) +
					' <button type="button" class="t-unqueue" title="' + textHtml(TS.t_unqueue) + '" aria-label="' + textHtml(TS.t_unqueue) + '">✕</button></span>');
			}
			totalQueued += it.queued;

			html += '<tr class="' + cls + '" data-id="' + it.id + '">' +
				'<td class="t-label">' + textHtml(it.label) + (sub.length ? '<div class="t-sub">' + sub.join(' · ') + '</div>' : '') + '</td>' +
				'<td class="t-stock">' + (st === null ? '—' : st) + '</td>' +
				'<td class="t-scan"><span class="t-n">' + n + '</span>' +
					(n ? '<button type="button" class="t-undo" title="' + textHtml(TS.t_undo_scan) + '" aria-label="' + textHtml(TS.t_undo_scan) + '">−</button>' : '') + '</td>' +
				'<td class="t-actions">' +
					'<button type="button" class="t-edit" title="' + textHtml(TS.t_edit) + '" aria-label="' + textHtml(TS.t_edit) + '">✏️</button>' +
					'<button type="button" class="t-reprint" title="' + textHtml(TS.t_reprint) + '" aria-label="' + textHtml(TS.t_reprint) + '">🏷</button>' +
				'</td></tr>';

			if (tally.editing === it.id) {
				var start = st === null ? n : st;
				html += '<tr class="t-editor" data-id="' + it.id + '"><td colspan="4">' +
					'<div class="t-editor-row">' +
					'<span class="wcbp-size-stepper">' +
						'<button type="button" class="t-minus" aria-label="' + textHtml(TS.less) + '">−</button>' +
						'<input type="number" class="t-qty" min="0" step="1" inputmode="numeric" value="' + start + '" />' +
						'<button type="button" class="t-plus" aria-label="' + textHtml(TS.more) + '">+</button>' +
					'</span>' +
					'<button type="button" class="button t-use-counted">' + textHtml(TS.t_use_counted) + ' (' + n + ')</button>' +
					'<button type="button" class="button button-primary t-save">' + textHtml(TS.save_btn) + '</button>' +
					'<button type="button" class="button-link t-cancel">' + textHtml(TS.cancel) + '</button>' +
					'</div></td></tr>';
			}
		});

		$('#wcbp-tally-rows').html(html);
		$('#wcbp-tally-name').text(p.name);
		$('#wcbp-tally-img').toggle(!!p.image_url).attr('src', p.image_url || '');
		$('#wcbp-tally-summary').text(fmt(TS.t_summary, totalScanned, totalStock));
		$('#wcbp-tally-queue-missing').text(fmt(TS.t_queue_missing, missing)).prop('disabled', missing === 0).data('missing', missing);
		$('#wcbp-tally-apply-all').text(TS.t_apply_all + (applyRows ? ' (' + applyRows + ')' : '')).prop('disabled', applyRows === 0);
		$('#wcbp-tally-unqueue-all').text(fmt(TS.t_unqueue_all, totalQueued)).toggle(totalQueued > 0);
		$('#wcbp-tally-card').show();
	}

	// payload: an items array, or an object of extra fields (e.g. { ids: [...] }).
	function tallyPost(action, payload, onDone) {
		var body = $.extend({ action: action, nonce: wcbpQuickAdd.inv_nonce, product_id: tally.product.product_id }, $.isArray(payload) ? { items: payload } : payload);
		$.post(wcbpQuickAdd.ajax_url, body, function (res) {
			if (!res || !res.success) {
				flash('err', (res && res.data && res.data.message) || TS.error, '');
				beep(false);
				return;
			}
			tally.product = $.extend(res.data, { scanned_id: tally.product.scanned_id });
			if (res.data.queue_count !== undefined) { $('.wcbp-queue-count').text(res.data.queue_count); }
			onDone(res.data);
		}).fail(function () { flash('err', TS.error, ''); beep(false); });
	}

	$('#wcbp-tally-form').on('submit', function (e) {
		e.preventDefault();
		var $in = $('#wcbp-tally-input');
		tallyScan($in.val());
		$in.val('').trigger('focus'); // ready for the next scan from a keyboard-style scanner
	});

	$('#wcbp-tally-camera').on('click', function () {
		openCameraScanner(tallyScan, { continuous: true, status: '#wcbp-tally-flash' });
	});

	$('.wcbp-qa-tab-btn[data-tab="tally"]').on('click', function () {
		setTimeout(function () { $('#wcbp-tally-input').trigger('focus'); }, 50);
	});

	$('#wcbp-tally-rows').on('click', 'button', function () {
		var $btn = $(this);
		var id   = parseInt($btn.closest('tr').data('id'), 10);
		var item = itemById(id);
		if (!item) { return; }
		var counts = countsFor(tally.product.product_id);

		if ($btn.hasClass('t-undo')) {
			counts[id] = Math.max(0, (counts[id] || 0) - 1);
			saveTallyCounts();
			renderTally();
		} else if ($btn.hasClass('t-edit')) {
			tally.editing = tally.editing === id ? 0 : id;
			renderTally();
			$('#wcbp-tally-rows .t-qty').trigger('focus').trigger('select');
		} else if ($btn.hasClass('t-cancel')) {
			tally.editing = 0;
			renderTally();
		} else if ($btn.hasClass('t-minus') || $btn.hasClass('t-plus')) {
			var $q = $btn.siblings('.t-qty');
			$q.val(Math.max(0, (parseInt($q.val(), 10) || 0) + ($btn.hasClass('t-plus') ? 1 : -1)));
		} else if ($btn.hasClass('t-use-counted')) {
			$btn.closest('tr').find('.t-qty').val(counts[id] || 0);
		} else if ($btn.hasClass('t-save')) {
			var qty = Math.max(0, parseInt($btn.closest('tr').find('.t-qty').val(), 10) || 0);
			$btn.prop('disabled', true);
			tallyPost('wcbp_tally_set_stock', [{ id: id, qty: qty }], function () {
				tally.editing = 0;
				renderTally();
				flash('ok', item.label, TS.t_saved);
			});
		} else if ($btn.hasClass('t-unqueue')) {
			if (!window.confirm(TS.t_confirm_unq.replace('%1$d', item.queued).replace('%2$s', item.label))) { return; }
			tallyUnqueue([item]);
		} else if ($btn.hasClass('t-reprint')) {
			tallyPost('wcbp_tally_queue', [{ id: id, qty: 1 }], function (data) {
				renderTally();
				flash('ok', item.label, data.message);
			});
		}
	});

	$('#wcbp-tally-queue-missing').on('click', function () {
		var pid = tally.product.product_id, items = [];
		tally.product.items.forEach(function (it) {
			var miss = missingFor(it, pid);
			if (miss > 0) { items.push({ id: it.id, qty: miss }); }
		});
		var total = items.reduce(function (sum, i) { return sum + i.qty; }, 0);
		if (!total || !window.confirm(fmt(TS.t_confirm_queue, total))) { return; }
		tallyPost('wcbp_tally_queue', items, function (data) {
			var queued = queuedFor(pid);
			items.forEach(function (i) { queued[i.id] = (queued[i.id] || 0) + i.qty; });
			saveTallyCounts();
			renderTally();
			flash('ok', tally.product.name, data.message);
		});
	});

	$('#wcbp-tally-apply-all').on('click', function () {
		var counts = countsFor(tally.product.product_id), items = [], lines = [];
		tally.product.items.forEach(function (it) {
			var n = counts[it.id] || 0;
			if (it.stock === null ? n > 0 : n !== it.stock) {
				items.push({ id: it.id, qty: n });
				lines.push(it.label + ': ' + (it.stock === null ? '—' : it.stock) + ' → ' + n);
			}
		});
		if (!items.length || !window.confirm(fmt(TS.t_confirm_apply, items.length) + '\n\n' + lines.join('\n'))) { return; }
		tallyPost('wcbp_tally_set_stock', items, function () {
			renderTally();
			flash('ok', tally.product.name, TS.t_saved);
		});
	});

	// Removing queued labels also forgets that this tally queued them, so "missing" counts them again.
	function tallyUnqueue(items) {
		var pid = tally.product.product_id;
		var ids = items.length === tally.product.items.length ? [] : items.map(function (i) { return i.id; });
		tallyPost('wcbp_tally_unqueue', { ids: ids }, function (data) {
			var queued = queuedFor(pid);
			items.forEach(function (i) { queued[i.id] = Math.max(0, (queued[i.id] || 0) - i.queued); });
			saveTallyCounts();
			renderTally();
			flash('ok', items.length === 1 ? items[0].label : tally.product.name, data.message);
		});
	}

	$('#wcbp-tally-unqueue-all').on('click', function () {
		var withLabels = tally.product.items.filter(function (it) { return it.queued > 0; });
		var total = withLabels.reduce(function (sum, it) { return sum + it.queued; }, 0);
		if (!total || !window.confirm(TS.t_confirm_unq.replace('%1$d', total).replace('%2$s', tally.product.name))) { return; }
		tallyUnqueue(withLabels.length === tally.product.items.length ? tally.product.items : withLabels);
	});

	$('#wcbp-tally-reset').on('click', function () {
		if (!tally.product || !window.confirm(TS.t_confirm_reset)) { return; }
		delete tallyCounts[tally.product.product_id];
		saveTallyCounts();
		renderTally();
	});

}(jQuery));
