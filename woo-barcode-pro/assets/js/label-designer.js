/* global wcbpDesigner, jQuery */
(function ($) {
	'use strict';

	var presets = wcbpDesigner.presets || {};

	// Apply preset values when user selects a preset
	$('#wcbp-preset').on('change', function () {
		var key = $(this).val();
		var p   = presets[key];
		if (!p) return;
		$('#wcbp-width-in').val(p.width_in);
		$('#wcbp-height-in').val(p.height_in);
		$('#wcbp-cols').val(p.cols);
		$('#wcbp-rows-per-page').val(p.rows_per_page);
		$('#wcbp-gap-in').val(p.gap_in);
		$('#wcbp-margin-in').val(p.margin_in);
		updatePreview();
	});

	// Live preview update
	function updatePreview() {
		var w  = parseFloat($('#wcbp-width-in').val())  || 2.625;
		var h  = parseFloat($('#wcbp-height-in').val()) || 1.0;
		var c  = parseInt($('#wcbp-cols').val(), 10)     || 3;
		var g  = parseFloat($('#wcbp-gap-in').val())    || 0;
		var m  = parseFloat($('#wcbp-margin-in').val()) || 0.5;
		var layout = $('input[name="layout"]:checked').val() || 'vertical';
		var ratio  = parseInt($('#wcbp-barcode-ratio').val(), 10) || 60;
		var showName  = $('#wcbp-field-name').is(':checked');
		var showPrice = $('#wcbp-field-price').is(':checked');
		var showSku   = $('#wcbp-field-sku').is(':checked');

		var $prev = $('#wcbp-label-preview');
		$prev.css({
			width  : (w * 96) + 'px',
			height : (h * 96) + 'px',
			display: 'flex',
			flexDirection: 'vertical' === layout ? 'column' : 'row',
			alignItems: 'center',
			justifyContent: 'center',
			border : '1px dashed #ccc',
			padding: '4px',
			boxSizing: 'border-box',
			overflow: 'hidden',
			fontSize: '10px',
		});

		var html = '';
		html += '<div style="height:' + ratio + '%;width:100%;background:#f5f5f5;display:flex;align-items:center;justify-content:center;font-size:9px;color:#999">▊▌▋▊ barcode ▊▋▌▊</div>';
		html += '<div style="width:100%;text-align:center;">';
		if (showName)  html += '<div><strong>Product Name</strong></div>';
		if (showPrice) html += '<div>$0.00</div>';
		if (showSku)   html += '<div><small>SKU-001</small></div>';
		html += '</div>';

		$prev.html(html);
	}

	$('#wcbp-template-form input, #wcbp-template-form select').on('change input', updatePreview);

	// Ratio slider label
	$('#wcbp-barcode-ratio').on('input', function () {
		$('#wcbp-barcode-ratio-val').text($(this).val() + '%');
		updatePreview();
	});

	// Media library for logo
	$('#wcbp-select-logo').on('click', function (e) {
		e.preventDefault();
		var frame = wp.media({
			title   : wcbpDesigner.strings.select_logo,
			button  : { text: wcbpDesigner.strings.use_image },
			multiple: false,
			library : { type: 'image' },
		});
		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			$('#wcbp-logo-id').val(att.id);
			$('#wcbp-logo-preview').attr('src', att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url).show();
			$('#wcbp-remove-logo').show();
		});
		frame.open();
	});

	$('#wcbp-remove-logo').on('click', function () {
		$('#wcbp-logo-id').val('');
		$('#wcbp-logo-preview').attr('src', '').hide();
		$(this).hide();
	});

	// Set default via AJAX
	$(document).on('click', '.wcbp-set-default-tpl', function (e) {
		e.preventDefault();
		var $btn = $(this);
		$.post(wcbpDesigner.ajax_url, {
			action: 'wcbp_set_default_template',
			nonce : wcbpDesigner.nonce,
			id    : $btn.data('id'),
		}, function (res) {
			if (res.success) location.reload();
		});
	});

	// Init preview on load
	updatePreview();

}(jQuery));
