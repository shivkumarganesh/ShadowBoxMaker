/* global wcbpInv, jQuery */
(function ($) {
	'use strict';

	var currentProductId   = 0;
	var currentVariationId = 0;
	var publishImageId     = 0;
	var publishUploading   = false;

	// ── Tab switching ─────────────────────────────────────────────────────────
	$('.wcbp-inv-tabs .nav-tab').on('click', function (e) {
		e.preventDefault();
		$('.wcbp-inv-tabs .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.wcbp-inv-tab').hide();
		$($(this).attr('href')).show();
	});

	// ── Scanner: lookup on Enter or button click ──────────────────────────────
	$('#wcbp-inv-barcode').on('keydown', function (e) {
		if (13 === e.which) {
			e.preventDefault();
			doLookup();
		}
	});
	$('#wcbp-inv-lookup-btn').on('click', doLookup);

	function doLookup() {
		var barcode = $('#wcbp-inv-barcode').val().trim();
		if (!barcode) return;

		$('#wcbp-inv-result').hide();
		hideFeedback();

		$.post(wcbpInv.ajax_url, {
			action  : 'wcbp_inv_lookup',
			nonce   : wcbpInv.nonce,
			barcode : barcode,
		}, function (res) {
			if (!res.success) {
				showFeedback(res.data.message || wcbpInv.strings.not_found, 'error', true);
				return;
			}
			var d = res.data;
			currentProductId   = d.product_id;
			currentVariationId = d.variation_id || 0;

			if (d.status === 'draft') {
				// ── Draft product: show Scan-to-Publish card ──────────────────
				publishImageId   = 0;
				publishUploading = false;
				$('#wcbp-inv-result').hide();
				$('#wcbp-publish-product-id').val(d.product_id);
				$('#wcbp-publish-sku').text(d.sku ? 'SKU: ' + d.sku : '');
				$('#wcbp-publish-price').text(wcbpInv.strings.draft_price_prefix + (d.price || ''));
				$('#wcbp-publish-name').val('');
				$('#wcbp-publish-photo-preview').hide().attr('src', '');
				$('#wcbp-publish-photo-status').text('').removeClass('wcbp-inv-err');
				$('#wcbp-publish-feedback').hide();
				$('#wcbp-publish-btn').prop('disabled', false).text(wcbpInv.strings.publish_btn);
				$('#wcbp-inv-draft-card').show();
				$('#wcbp-publish-name').focus();
			} else {
				// ── Published product: show stock adjustment ──────────────────
				$('#wcbp-inv-draft-card').hide();
				$('#wcbp-inv-product-id').val(d.product_id);
				$('#wcbp-inv-variation-id').val(d.variation_id || 0);
				$('#wcbp-inv-name').text(d.name);
				$('#wcbp-inv-sku').text(d.sku ? 'SKU: ' + d.sku : '');
				$('#wcbp-inv-new-qty').val(d.stock_qty);

				var $badge = $('#wcbp-inv-qty');
				$badge.text(d.stock_qty === null ? '—' : d.stock_qty);
				$badge.removeClass('wcbp-inv-badge-ok wcbp-inv-badge-low wcbp-inv-badge-out');
				if (d.stock_qty <= 0) {
					$badge.addClass('wcbp-inv-badge-out').text('Out of Stock');
				} else if (d.stock_qty <= 5) {
					$badge.addClass('wcbp-inv-badge-low');
				} else {
					$badge.addClass('wcbp-inv-badge-ok');
				}

				$('#wcbp-inv-result').show();
				$('#wcbp-inv-new-qty').trigger('focus');
			}
		});
	}

	// ── Adjust stock ─────────────────────────────────────────────────────────
	$('#wcbp-inv-adjust-btn').on('click', function () {
		if (!currentProductId) return;
		var newQty = parseInt($('#wcbp-inv-new-qty').val(), 10);
		var note   = $('#wcbp-inv-note').val().trim();
		if (isNaN(newQty) || newQty < 0) {
			showFeedback(wcbpInv.strings.invalid_qty, 'error');
			return;
		}
		$(this).prop('disabled', true).text(wcbpInv.strings.saving);
		$.post(wcbpInv.ajax_url, {
			action       : 'wcbp_inv_adjust',
			nonce        : wcbpInv.nonce,
			product_id   : currentProductId,
			variation_id : currentVariationId,
			new_qty      : newQty,
			note         : note,
		}, function (res) {
			$('#wcbp-inv-adjust-btn').prop('disabled', false).text(wcbpInv.strings.set_stock);
			if (res.success) {
				var d = res.data;
				$('#wcbp-inv-qty').text(d.new_qty);
				updateBadge(d.new_qty);
				var sign = d.change >= 0 ? '+' : '';
				showFeedback(
					wcbpInv.strings.adjusted
						.replace('%old%', d.old_qty)
						.replace('%new%', d.new_qty)
						.replace('%change%', sign + d.change),
					'success'
				);
				$('#wcbp-inv-note').val('');
			} else {
				showFeedback(res.data.message || wcbpInv.strings.error, 'error');
			}
		});
	});

	// ── Sell one ─────────────────────────────────────────────────────────────
	$('#wcbp-inv-sell-btn').on('click', function () {
		if (!currentProductId) return;
		if (!confirm(wcbpInv.strings.confirm_sell)) return;
		$(this).prop('disabled', true);
		$.post(wcbpInv.ajax_url, {
			action       : 'wcbp_inv_sell_one',
			nonce        : wcbpInv.nonce,
			product_id   : currentProductId,
			variation_id : currentVariationId,
		}, function (res) {
			$('#wcbp-inv-sell-btn').prop('disabled', false);
			if (res.success) {
				var d = res.data;
				$('#wcbp-inv-qty').text(d.new_qty);
				$('#wcbp-inv-new-qty').val(d.new_qty);
				updateBadge(d.new_qty);
				showFeedback(wcbpInv.strings.sold_one.replace('%qty%', d.new_qty), 'success');
			} else {
				showFeedback(res.data.message || wcbpInv.strings.error, 'error');
			}
		});
	});

	// ── Scan-to-Publish: photo upload ────────────────────────────────────────
	$('#wcbp-publish-photo-input').on('change', function () {
		var file = this.files[0];
		if (!file) return;
		var reader = new FileReader();
		reader.onload = function (e) {
			var img = new Image();
			img.onload = function () {
				var maxW = 1200, maxH = 1200, w = img.width, h = img.height;
				if (w > maxW || h > maxH) {
					var scale = Math.min(maxW / w, maxH / h);
					w = Math.round(w * scale); h = Math.round(h * scale);
				}
				var canvas = document.createElement('canvas');
				canvas.width = w; canvas.height = h;
				canvas.getContext('2d').drawImage(img, 0, 0, w, h);
				canvas.toBlob(function (blob) { uploadPublishPhoto(blob, file.name); }, 'image/jpeg', 0.82);
				$('#wcbp-publish-photo-preview').attr('src', canvas.toDataURL()).show();
			};
			img.src = e.target.result;
		};
		reader.readAsDataURL(file);
	});

	function uploadPublishPhoto(blob, filename) {
		publishUploading = true;
		$('#wcbp-publish-btn').prop('disabled', true);
		$('#wcbp-publish-photo-status').text(wcbpInv.strings.uploading).removeClass('wcbp-inv-err');
		var fd = new FormData();
		fd.append('action', 'wcbp_quick_upload_image');
		fd.append('nonce',  wcbpInv.nonce);
		fd.append('image',  blob, filename);
		$.ajax({
			url: wcbpInv.ajax_url, type: 'POST', data: fd,
			processData: false, contentType: false,
			success: function (res) {
				publishUploading = false;
				$('#wcbp-publish-btn').prop('disabled', false);
				if (res.success) {
					publishImageId = res.data.attachment_id;
					$('#wcbp-publish-photo-status').text(wcbpInv.strings.photo_ready);
				} else {
					$('#wcbp-publish-photo-status').text(wcbpInv.strings.upload_failed).addClass('wcbp-inv-err');
				}
			},
			error: function () {
				publishUploading = false;
				$('#wcbp-publish-btn').prop('disabled', false);
				$('#wcbp-publish-photo-status').text(wcbpInv.strings.upload_failed).addClass('wcbp-inv-err');
			},
		});
	}

	// ── Scan-to-Publish: submit ───────────────────────────────────────────────
	$('#wcbp-publish-btn').on('click', function () {
		var pid  = parseInt($('#wcbp-publish-product-id').val(), 10);
		var name = $('#wcbp-publish-name').val().trim();
		if (!name) {
			$('#wcbp-publish-name').focus();
			return;
		}
		if (publishUploading) return;
		var $btn = $(this).prop('disabled', true).text(wcbpInv.strings.publishing);
		$.post(wcbpInv.ajax_url, {
			action     : 'wcbp_inv_publish_draft',
			nonce      : wcbpInv.nonce,
			product_id : pid,
			name       : name,
			image_id   : publishImageId || 0,
		}, function (res) {
			$btn.prop('disabled', false).text(wcbpInv.strings.publish_btn);
			if (res.success) {
				$('#wcbp-inv-draft-card').hide();
				$('#wcbp-publish-feedback')
					.html(wcbpInv.strings.published_ok + ' <a href="' + res.data.edit_url + '" target="_blank">' + wcbpInv.strings.view_product + '</a>')
					.addClass('wcbp-inv-ok').removeClass('wcbp-inv-err').show();
				$('#wcbp-inv-barcode').val('').focus();
			} else {
				$('#wcbp-publish-feedback')
					.text(res.data.message || wcbpInv.strings.error)
					.addClass('wcbp-inv-err').removeClass('wcbp-inv-ok').show();
			}
		});
	});

	// ── Low stock tab ─────────────────────────────────────────────────────────
	$('#wcbp-low-refresh').on('click', loadLowStock);

	function loadLowStock() {
		var threshold = parseInt($('#wcbp-low-threshold').val(), 10) || 5;
		$('#wcbp-low-stock-body').html('<tr><td colspan="5">' + wcbpInv.strings.loading + '</td></tr>');
		$.post(wcbpInv.ajax_url, {
			action    : 'wcbp_inv_low_stock',
			nonce     : wcbpInv.nonce,
			threshold : threshold,
		}, function (res) {
			if (!res.success || !res.data.items.length) {
				$('#wcbp-low-stock-body').html('<tr><td colspan="5">' + wcbpInv.strings.no_low_stock + '</td></tr>');
				return;
			}
			var html = '';
			$.each(res.data.items, function (i, item) {
				var stock = parseInt(item.stock_qty, 10);
				var cls   = stock <= 0 ? 'wcbp-inv-badge-out' : 'wcbp-inv-badge-low';
				html += '<tr data-pid="' + item.product_id + '">';
				html += '<td><a href="' + wcbpInv.edit_url + item.product_id + '&action=edit" target="_blank">' + escHtml(item.name) + '</a></td>';
				html += '<td>' + escHtml(item.sku || '—') + '</td>';
				html += '<td><span class="wcbp-inv-qty-badge ' + cls + '">' + stock + '</span></td>';
				html += '<td><input type="number" class="wcbp-low-qty small-text" value="' + stock + '" min="0" /></td>';
				html += '<td><button class="button button-small wcbp-low-set-qty">' + wcbpInv.strings.set_stock + '</button></td>';
				html += '</tr>';
			});
			$('#wcbp-low-stock-body').html(html);
		});
	}

	$(document).on('click', '.wcbp-low-set-qty', function () {
		var $row    = $(this).closest('tr');
		var pid     = $row.data('pid');
		var newQty  = parseInt($row.find('.wcbp-low-qty').val(), 10);
		var $btn    = $(this);
		$btn.prop('disabled', true).text(wcbpInv.strings.saving);
		$.post(wcbpInv.ajax_url, {
			action       : 'wcbp_inv_adjust',
			nonce        : wcbpInv.nonce,
			product_id   : pid,
			variation_id : 0,
			new_qty      : newQty,
			note         : 'Low-stock correction',
		}, function (res) {
			$btn.prop('disabled', false).text(wcbpInv.strings.set_stock);
			if (res.success) {
				$btn.text('✓').addClass('button-primary');
				setTimeout(function () { $btn.text(wcbpInv.strings.set_stock).removeClass('button-primary'); }, 2000);
			}
		});
	});

	// ── History tab ──────────────────────────────────────────────────────────
	$('#wcbp-log-load').on('click', loadLog);

	function loadLog() {
		var pid = parseInt($('#wcbp-log-product-id').val(), 10) || 0;
		$('#wcbp-log-body').html('<tr><td colspan="7">' + wcbpInv.strings.loading + '</td></tr>');
		$.post(wcbpInv.ajax_url, {
			action     : 'wcbp_inv_log',
			nonce      : wcbpInv.nonce,
			product_id : pid,
		}, function (res) {
			if (!res.success || !res.data.log.length) {
				$('#wcbp-log-body').html('<tr><td colspan="7">' + wcbpInv.strings.no_log + '</td></tr>');
				return;
			}
			var reasonLabels = {
				order     : wcbpInv.strings.reason_order,
				manual    : wcbpInv.strings.reason_manual,
				scan_sell : wcbpInv.strings.reason_scan_sell,
			};
			var html = '';
			$.each(res.data.log, function (i, row) {
				var change    = parseInt(row.change_qty, 10);
				var changeTxt = (change >= 0 ? '+' : '') + change;
				var changeCls = change < 0 ? 'wcbp-inv-neg' : 'wcbp-inv-pos';
				var reason    = reasonLabels[row.reason] || row.reason;
				html += '<tr>';
				html += '<td>' + escHtml(row.created_at) + '</td>';
				html += '<td>' + escHtml(row.product_name || '—') + '</td>';
				html += '<td>' + row.old_qty + '</td>';
				html += '<td class="' + changeCls + '">' + changeTxt + '</td>';
				html += '<td>' + row.new_qty + '</td>';
				html += '<td><span class="wcbp-badge wcbp-badge-grey">' + escHtml(reason) + '</span></td>';
				html += '<td>' + escHtml(row.note || '') + (row.order_id > 0 ? ' <a href="' + wcbpInv.order_url + row.order_id + '" target="_blank">#' + row.order_id + '</a>' : '') + '</td>';
				html += '</tr>';
			});
			$('#wcbp-log-body').html(html);
		});
	}

	// ── Helpers ───────────────────────────────────────────────────────────────
	function updateBadge(qty) {
		var $badge = $('#wcbp-inv-qty');
		$badge.text(qty);
		$badge.removeClass('wcbp-inv-badge-ok wcbp-inv-badge-low wcbp-inv-badge-out');
		if (qty <= 0)     { $badge.addClass('wcbp-inv-badge-out').text('Out of Stock'); }
		else if (qty <= 5){ $badge.addClass('wcbp-inv-badge-low'); }
		else              { $badge.addClass('wcbp-inv-badge-ok'); }
	}

	function showFeedback(msg, type, sticky) {
		var $el = $('#wcbp-inv-feedback');
		$el.removeClass('wcbp-inv-ok wcbp-inv-err').text(msg)
		   .addClass('error' === type ? 'wcbp-inv-err' : 'wcbp-inv-ok').show();
		if (!sticky) { setTimeout(function () { $el.fadeOut(); }, 3500); }
	}

	function hideFeedback() {
		$('#wcbp-inv-feedback').hide();
	}

	function escHtml(str) {
		return $('<div>').text(str || '').html();
	}

}(jQuery));
