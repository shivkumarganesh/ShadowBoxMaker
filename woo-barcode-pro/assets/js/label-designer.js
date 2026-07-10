/* global wcbpDesigner, jQuery */
(function ($) {
	'use strict';

	var presets       = wcbpDesigner.presets || {};
	var _barcodeCache = {};
	var _barcodeTimer = null;

	// ── Preset selector ──────────────────────────────────────────────────────
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
		schedulePreview();
	});

	// ── Barcode AJAX (cached + debounced) ────────────────────────────────────
	function fetchBarcode(value, callback) {
		if (!value) { callback(''); return; }
		if (_barcodeCache[value] !== undefined) { callback(_barcodeCache[value]); return; }
		$.post(wcbpDesigner.ajax_url, {
			action: 'wcbp_preview_barcode',
			nonce : wcbpDesigner.nonce,
			value : value,
		}, function (res) {
			var svg = (res.success && res.data && res.data.svg) ? res.data.svg : '';
			_barcodeCache[value] = svg;
			callback(svg);
		}).fail(function () {
			_barcodeCache[value] = '';
			callback('');
		});
	}

	// Make the SVG fill its container while keeping aspect ratio.
	function responsiveSvg(svg) {
		return svg
			.replace(/(<svg[^>]*)\swidth="\d+(\.\d+)?"/, '$1 width="100%"')
			.replace(/(<svg[^>]*)\sheight="\d+(\.\d+)?"/, '$1 height="100%"');
	}

	// Minimal XSS escape for text inserted as innerHTML.
	function esc(str) {
		return $('<span>').text(String(str)).html();
	}

	// ── Preview renderer ─────────────────────────────────────────────────────
	function renderPreview(barcodeSvg) {
		var w      = parseFloat($('#wcbp-width-in').val())        || 2.625;
		var h      = parseFloat($('#wcbp-height-in').val())       || 1.0;
		var layout = $('input[name="layout"]:checked').val()      || 'vertical';
		var ratio  = parseInt($('#wcbp-barcode-ratio').val(), 10) || 60;
		var rest   = 100 - ratio;
		var isHoriz = (layout === 'horizontal');

		var showName  = $('#wcbp-field-name').is(':checked');
		var showPrice = $('#wcbp-field-price').is(':checked');
		var showSku   = $('#wcbp-field-sku').is(':checked');

		var mockName  = $('#wcbp-mock-name').val()  || 'Sample Product';
		var mockPrice = $('#wcbp-mock-price').val() || '0.00';
		var mockSku   = $('#wcbp-mock-sku').val()   || 'SKU-001';

		var pxW = Math.round(w * 96);
		var pxH = Math.round(h * 96);

		var $prev = $('#wcbp-label-preview');
		$prev.css({
			width        : pxW + 'px',
			height       : pxH + 'px',
			display      : 'flex',
			flexDirection: isHoriz ? 'row' : 'column',
			background   : '#fff',
			border       : '1px solid #c3c4c7',
			padding      : '4px',
			boxSizing    : 'border-box',
			overflow     : 'hidden',
			gap          : '3px',
		});

		// Barcode block
		var barcodeContent;
		if (barcodeSvg) {
			barcodeContent = responsiveSvg(barcodeSvg);
		} else {
			barcodeContent = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f5f5f5;color:#bbb;font-size:9px;font-family:monospace">▊▋▊▌▋▊▋</div>';
		}
		var bcFlex = isHoriz
			? 'flex:' + ratio + ' 0 0%;min-width:0;height:100%;'
			: 'flex:' + ratio + ' 0 0%;min-height:0;width:100%;';
		var barcodeHtml = '<div style="' + bcFlex + 'overflow:hidden;display:flex;align-items:center;justify-content:center;">' + barcodeContent + '</div>';

		// Info block
		var infoLines = '';
		if (showName)  infoLines += '<div style="font-weight:700;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + esc(mockName) + '</div>';
		if (showPrice) infoLines += '<div>' + esc(mockPrice) + '</div>';
		if (showSku)   infoLines += '<div style="color:#888;font-size:8px">' + esc(mockSku) + '</div>';

		var infoHtml = '';
		if (infoLines) {
			var inFlex = isHoriz
				? 'flex:' + rest + ' 0 0%;min-width:0;height:100%;'
				: 'flex:' + rest + ' 0 0%;min-height:0;width:100%;';
			infoHtml = '<div style="' + inFlex + 'display:flex;flex-direction:column;justify-content:center;overflow:hidden;line-height:1.4;font-size:9px;font-family:sans-serif">' + infoLines + '</div>';
		}

		$prev.html(barcodeHtml + infoHtml);
	}

	// ── Main entry: debounce + fetch ─────────────────────────────────────────
	function schedulePreview() {
		var val = $('#wcbp-mock-barcode').val() || $('#wcbp-mock-sku').val() || 'SKU-001';
		clearTimeout(_barcodeTimer);
		_barcodeTimer = setTimeout(function () {
			fetchBarcode(val, renderPreview);
		}, 350);
	}

	// ── Event bindings ───────────────────────────────────────────────────────

	// All template form controls
	$('#wcbp-template-form input, #wcbp-template-form select').on('change input', schedulePreview);

	// Barcode ratio slider — also update the % label
	$('#wcbp-barcode-ratio').on('input', function () {
		$('#wcbp-barcode-ratio-val').text($(this).val() + '%');
		schedulePreview();
	});

	// Mock name and price — redraw immediately using cached barcode
	$('#wcbp-mock-name, #wcbp-mock-price').on('input', function () {
		clearTimeout(_barcodeTimer);
		_barcodeTimer = setTimeout(function () {
			var val = $('#wcbp-mock-barcode').val() || $('#wcbp-mock-sku').val() || 'SKU-001';
			var cached = _barcodeCache[val];
			renderPreview(cached !== undefined ? cached : '');
		}, 150);
	});

	// Mock SKU — mirror to barcode field unless user has customised it separately
	$('#wcbp-mock-sku').on('input', function () {
		if (!$('#wcbp-mock-barcode').data('custom')) {
			$('#wcbp-mock-barcode').val($(this).val());
		}
		schedulePreview();
	});

	// Mock barcode — mark as custom so SKU mirroring stops, clear cache, fetch fresh
	$('#wcbp-mock-barcode').on('input', function () {
		$(this).data('custom', true);
		delete _barcodeCache[$(this).val()];
		schedulePreview();
	});

	// ── Media library for logo ───────────────────────────────────────────────
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

	// ── Set default template ─────────────────────────────────────────────────
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

	// ── Init ─────────────────────────────────────────────────────────────────
	schedulePreview();

}(jQuery));
