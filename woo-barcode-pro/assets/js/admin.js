/* global wcbpAdmin, jQuery */
(function ($) {
	'use strict';

	// ── Product list column barcode preview ──────────────────────────────────
	$(document).on('click', '.wcbp-preview-barcode', function () {
		var $btn = $(this);
		var productId = $btn.data('product-id');
		if (!productId) return;

		var $wrap = $btn.closest('.wcbp-barcode-col');
		var $svg  = $wrap.find('.wcbp-barcode-svg');
		if ($svg.length && $svg.data('loaded')) {
			$svg.toggle();
			return;
		}
		$.post(wcbpAdmin.ajax_url, {
			action : 'wcbp_get_barcode_preview',
			nonce  : wcbpAdmin.nonce,
			id     : productId,
		}, function (res) {
			if (res.success) {
				if (!$svg.length) {
					$wrap.append('<div class="wcbp-barcode-svg" />');
					$svg = $wrap.find('.wcbp-barcode-svg');
				}
				$svg.html(res.data.svg).data('loaded', true).show();
			}
		});
	});

	// ── "Add Stock To Print" — replaces queue entry with current stock qty ────
	$(document).on('click', '.wcbp-add-stock-queue', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).text(wcbpAdmin.strings.adding);
		var stockQty = parseInt($btn.data('stock-qty'), 10) || 1;
		$.post(wcbpAdmin.ajax_url, {
			action     : 'wcbp_add_to_queue',
			nonce      : wcbpAdmin.queue_nonce,
			product_id : $btn.data('product-id'),
			qty        : stockQty,
			replace    : 1,
		}, function (res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$btn.text(res.data.button_text || wcbpAdmin.strings.added);
				$('.wcbp-queue-count').text(res.data.count);
				updateRemoveButton($btn.data('product-id'), res.data.pending);
			} else {
				$btn.text(wcbpAdmin.strings.error);
			}
		});
	});

	// ── "Add to Queue +1" — increments existing queue entry by 1 ─────────────
	$(document).on('click', '.wcbp-add-one-queue', function () {
		var $btn = $(this);
		var origText = $btn.text();
		$btn.prop('disabled', true).text(wcbpAdmin.strings.adding);
		$.post(wcbpAdmin.ajax_url, {
			action     : 'wcbp_add_to_queue',
			nonce      : wcbpAdmin.queue_nonce,
			product_id : $btn.data('product-id'),
			qty        : 1,
		}, function (res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$btn.text(res.data.button_text || wcbpAdmin.strings.added_one);
				$('.wcbp-queue-count').text(res.data.count);
				updateRemoveButton($btn.data('product-id'), res.data.pending);
				setTimeout(function () { $btn.text(origText); }, res.data.button_text ? 3000 : 2000);
			} else {
				$btn.text(wcbpAdmin.strings.error);
				setTimeout(function () { $btn.text(origText); }, 2000);
			}
		});
	});

	// ── "Remove from queue (n)" — drops this product's unprinted labels ───────
	function updateRemoveButton(productId, pending) {
		if (pending === undefined) { return; }
		var $rm = $('.wcbp-remove-queue[data-product-id="' + productId + '"]');
		$rm.data('pending', pending).text(wcbpAdmin.strings.remove_queue.replace('%d', pending)).toggle(pending > 0);
	}

	$('.wcbp-remove-queue').each(function () {
		$(this).data('pending', parseInt(($(this).text().match(/\d+/) || [0])[0], 10));
	});

	$(document).on('click', '.wcbp-remove-queue', function () {
		var $btn = $(this);
		var pending = $btn.data('pending') || 0;
		if (!window.confirm(wcbpAdmin.strings.confirm_remove.replace('%d', pending))) { return; }
		$btn.prop('disabled', true).text(wcbpAdmin.strings.removing);
		$.post(wcbpAdmin.ajax_url, {
			action     : 'wcbp_remove_product_from_queue',
			nonce      : wcbpAdmin.queue_nonce,
			product_id : $btn.data('product-id'),
		}, function (res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$btn.text(res.data.button_text);
				$('.wcbp-queue-count').text(res.data.count);
				setTimeout(function () { updateRemoveButton($btn.data('product-id'), 0); }, 2000);
			} else {
				$btn.text(wcbpAdmin.strings.error);
			}
		});
	});

	// ── Metabox: live barcode preview when EAN field changes ──────────────────
	$('#wcbp_ean').on('input', function () {
		var val = $(this).val().trim();
		var $preview = $('#wcbp-barcode-preview');
		if (!val || !$preview.length) return;
		// Debounce
		clearTimeout(window._wcbpEanTimer);
		window._wcbpEanTimer = setTimeout(function () {
			$.post(wcbpAdmin.ajax_url, {
				action : 'wcbp_get_barcode_preview',
				nonce  : wcbpAdmin.nonce,
				value  : val,
			}, function (res) {
				if (res.success) $preview.html(res.data.svg);
			});
		}, 400);
	});

	// ── Bulk action: "Add to Print Queue" ────────────────────────────────────
	$(document).on('submit', '#posts-filter', function (e) {
		var action = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();
		if ('wcbp_add_to_queue' !== action) return;
		e.preventDefault();
		var ids = [];
		$('input[name="post[]"]:checked').each(function () { ids.push($(this).val()); });
		if (!ids.length) {
			alert(wcbpAdmin.strings.select_products);
			return;
		}
		$.post(wcbpAdmin.ajax_url, {
			action : 'wcbp_bulk_add_to_queue',
			nonce  : wcbpAdmin.queue_nonce,
			ids    : ids,
		}, function (res) {
			if (res.success) {
				$('.wcbp-queue-count').text(res.data.total);
				alert(res.data.message);
			}
		});
	});

}(jQuery));
