/* global wcbpBatch, jQuery */
(function ($) {
	'use strict';

	$('#wcbp-batch-run').on('click', function () {
		var templateId = $('#wcbp-batch-template').val();
		var qty        = parseInt($('#wcbp-batch-qty').val(), 10);
		var labelTplId = $('#wcbp-batch-label-tpl').val() || 0;

		if (!templateId) {
			alert(wcbpBatch.strings.select_template);
			$('#wcbp-batch-template').focus();
			return;
		}
		if (!qty || qty < 1) {
			alert(wcbpBatch.strings.invalid_qty);
			$('#wcbp-batch-qty').focus();
			return;
		}

		var $btn = $(this).prop('disabled', true).text(wcbpBatch.strings.creating);
		$('#wcbp-batch-result').hide();
		$('#wcbp-batch-progress')
			.show()
			.find('#wcbp-batch-progress-text')
			.text(wcbpBatch.strings.creating_n.replace('%n%', qty));

		$.post(wcbpBatch.ajax_url, {
			action            : 'wcbp_batch_create',
			nonce             : wcbpBatch.nonce,
			template_id       : templateId,
			quantity          : qty,
			label_template_id : labelTplId,
		}, function (res) {
			$('#wcbp-batch-progress').hide();
			$btn.prop('disabled', false).text(wcbpBatch.strings.create_btn);

			if (!res.success) {
				$('#wcbp-batch-result').html(
					'<div class="notice notice-error inline"><p>' + escHtml(res.data.message || wcbpBatch.strings.error) + '</p></div>'
				).show();
				return;
			}

			var d = res.data;
			var html = '<div class="notice notice-success inline"><p>' +
				wcbpBatch.strings.success.replace('%n%', d.created) +
				'</p></div>' +
				'<p style="margin-top:12px">' +
				'<a href="' + escHtml(d.print_url) + '" class="button button-primary">' +
				wcbpBatch.strings.go_to_queue + '</a>' +
				'&nbsp;&nbsp;<a href="' + escHtml(d.print_url) + '" class="button">' +
				wcbpBatch.strings.print_now + '</a>' +
				'</p>' +
				'<details style="margin-top:12px"><summary style="cursor:pointer;color:#646970">' +
				wcbpBatch.strings.show_skus + '</summary>' +
				'<ul style="margin:.5em 0 0 1.5em;column-count:3;column-gap:1em">';

			$.each(d.products, function (i, p) {
				html += '<li style="font-family:monospace;font-size:12px">#' + p.id + ' — ' + escHtml(p.sku) + '</li>';
			});
			html += '</ul></details>';

			$('#wcbp-batch-result').html(html).show();
		}).fail(function () {
			$('#wcbp-batch-progress').hide();
			$btn.prop('disabled', false).text(wcbpBatch.strings.create_btn);
			$('#wcbp-batch-result').html(
				'<div class="notice notice-error inline"><p>' + wcbpBatch.strings.error + '</p></div>'
			).show();
		});
	});

	function escHtml(str) {
		return $('<div>').text(String(str)).html();
	}

}(jQuery));
