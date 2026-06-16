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

	// ── "Add to Queue" button on product list ─────────────────────────────────
	$(document).on('click', '.wcbp-add-single-queue', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).text(wcbpAdmin.strings.adding);
		$.post(wcbpAdmin.ajax_url, {
			action     : 'wcbp_add_to_queue',
			nonce      : wcbpAdmin.queue_nonce,
			product_id : $btn.data('product-id'),
			qty        : 1,
		}, function (res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$btn.text(wcbpAdmin.strings.added);
				$('.wcbp-queue-count').text(res.data.count);
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
