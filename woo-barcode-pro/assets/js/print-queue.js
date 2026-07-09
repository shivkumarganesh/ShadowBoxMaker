/* global wcbpQueue, jQuery */
(function ($) {
	'use strict';

	// ── Remove a single item ──────────────────────────────────────────────────
	$(document).on('click', '.wcbp-remove-item', function () {
		var $row = $(this).closest('tr');
		var id   = $row.data('id');
		$.post(wcbpQueue.ajax_url, {
			action: 'wcbp_remove_from_queue',
			nonce : wcbpQueue.nonce,
			id    : id,
		}, function (res) {
			if (res.success) {
				$row.fadeOut(200, function () { $(this).remove(); updateCount(res.data.count); });
			}
		});
	});

	// ── Inline quantity update ────────────────────────────────────────────────
	$(document).on('change', '.wcbp-qty-input', function () {
		var $input = $(this);
		var $row   = $input.closest('tr');
		var id     = $row.data('id');
		var qty    = parseInt($input.val(), 10) || 1;
		$.post(wcbpQueue.ajax_url, {
			action: 'wcbp_update_qty',
			nonce : wcbpQueue.nonce,
			id    : id,
			qty   : qty,
		}, function (res) {
			if (res.success && qty <= 0) {
				$row.fadeOut(200, function () { $(this).remove(); });
			}
		});
	});

	// ── Clear queue ───────────────────────────────────────────────────────────
	$('#wcbp-clear-queue').on('click', function () {
		if (!confirm(wcbpQueue.strings.confirm_clear)) return;
		$.post(wcbpQueue.ajax_url, {
			action: 'wcbp_clear_queue',
			nonce : wcbpQueue.nonce,
		}, function (res) {
			if (res.success) location.reload();
		});
	});

	// ── Print selected / all ─────────────────────────────────────────────────
	$('#wcbp-print-btn').on('click', function () {
		var ids = [];
		$('.wcbp-row-check:checked').each(function () { ids.push($(this).closest('tr').data('id')); });
		var templateId = $('#wcbp-template-select').val() || 0;

		var params = 'page=wcbp-print&template_id=' + templateId;
		if (ids.length) params += '&ids[]=' + ids.join('&ids[]=');

		var win = window.open(wcbpQueue.admin_url + '?' + params, '_blank');
		if (!win) { alert(wcbpQueue.strings.popup_blocked); }
	});

	// ── Mark printed ─────────────────────────────────────────────────────────
	$('#wcbp-mark-printed').on('click', function () {
		var ids = [];
		$('.wcbp-row-check:checked').each(function () { ids.push($(this).closest('tr').data('id')); });
		if (!ids.length) {
			$('.wcbp-row-check').each(function () { ids.push($(this).closest('tr').data('id')); });
		}
		$.post(wcbpQueue.ajax_url, {
			action: 'wcbp_mark_printed',
			nonce : wcbpQueue.nonce,
			ids   : ids,
		}, function (res) {
			if (res.success) location.reload();
		});
	});

	// ── Select all checkbox ──────────────────────────────────────────────────
	$('#wcbp-check-all').on('change', function () {
		$('.wcbp-row-check').prop('checked', $(this).is(':checked'));
	});

	function updateCount(n) {
		$('.wcbp-queue-count').text(n);
		$('.wcbp-queue-total').text(n);
	}

}(jQuery));
