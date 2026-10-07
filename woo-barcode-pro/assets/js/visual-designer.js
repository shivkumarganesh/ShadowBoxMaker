/* WooBarcode Pro — Visual Label Designer */
(function () {
	'use strict';

	// ── Element catalogue ─────────────────────────────────────────────────────
	var CATALOGUE = [
		{ id: 'barcode',  label: 'Barcode',       color: '#c2e0ff', x: 2,  y: 4,  w: 52, h: 88, fontSize: 0,  bold: false, align: 'center', visible: true },
		{ id: 'company',  label: 'Company Name',  color: '#ffd6a5', x: 56, y: 2,  w: 42, h: 20, fontSize: 9,  bold: true,  align: 'center', visible: true },
		{ id: 'name',     label: 'Product Name',  color: '#caffbf', x: 56, y: 24, w: 42, h: 28, fontSize: 8,  bold: true,  align: 'left',   visible: true },
		{ id: 'price',    label: 'Price',         color: '#fdffb6', x: 56, y: 54, w: 42, h: 24, fontSize: 10, bold: true,  align: 'right',  visible: true },
		{ id: 'sku',      label: 'SKU',           color: '#e0e0e0', x: 56, y: 80, w: 42, h: 16, fontSize: 7,  bold: false, align: 'right',  visible: true },
	];

	var CANVAS_W = 520; // px — fixed canvas display width
	var SNAP     = 0.5; // % snap resolution

	// ── State ─────────────────────────────────────────────────────────────────
	var S = {
		elements : [],
		selected : null,
		widthIn  : 2.625,
		heightIn : 1.0,
		canvasH  : 200,
	};

	// DOM refs
	var D = {};
	var drag   = null; // { id, ox, oy }
	var rz     = null; // { id, ox, oy, startW, startH }

	// ── Init ──────────────────────────────────────────────────────────────────
	function init() {
		D.overlay   = document.getElementById('wcbp-vd-overlay');
		D.canvas    = document.getElementById('wcbp-vd-canvas');
		D.props     = document.getElementById('wcbp-vd-props');
		D.list      = document.getElementById('wcbp-vd-el-list');
		D.footer    = document.getElementById('wcbp-vd-footer');
		D.openBtn   = document.getElementById('wcbp-vd-open-btn');
		D.applyBtn  = document.getElementById('wcbp-vd-apply');
		D.cancelBtn = document.getElementById('wcbp-vd-cancel');
		D.resetBtn  = document.getElementById('wcbp-vd-reset');
		D.jsonField = document.getElementById('wcbp-visual-layout-json');

		if (!D.overlay) { return; }

		if (D.openBtn)   { D.openBtn.addEventListener('click',   openDesigner);  }
		if (D.applyBtn)  { D.applyBtn.addEventListener('click',  applyLayout);   }
		if (D.cancelBtn) { D.cancelBtn.addEventListener('click', closeDesigner); }
		if (D.resetBtn)  { D.resetBtn.addEventListener('click',  resetDefaults); }

		document.addEventListener('mousemove', onMouseMove);
		document.addEventListener('mouseup',   onMouseUp);

		// Close on backdrop click.
		D.overlay.addEventListener('click', function (e) {
			if (e.target === D.overlay) { closeDesigner(); }
		});
	}

	// ── Open / Close ──────────────────────────────────────────────────────────
	function openDesigner() {
		S.widthIn  = parseFloat(document.getElementById('wcbp-width-in').value)  || 2.625;
		S.heightIn = parseFloat(document.getElementById('wcbp-height-in').value) || 1.0;
		S.canvasH  = Math.round(CANVAS_W * S.heightIn / S.widthIn);

		D.canvas.style.width  = CANVAS_W + 'px';
		D.canvas.style.height = S.canvasH + 'px';

		// Load saved JSON or fall back to defaults.
		var saved = D.jsonField ? D.jsonField.value.trim() : '';
		try {
			var parsed = saved ? JSON.parse(saved) : null;
			S.elements = (Array.isArray(parsed) && parsed.length) ? parsed : defaultElements();
		} catch (e) {
			S.elements = defaultElements();
		}

		S.selected = null;
		renderAll();
		D.overlay.classList.add('open');
	}

	function closeDesigner() {
		D.overlay.classList.remove('open');
	}

	function applyLayout() {
		if (D.jsonField) {
			D.jsonField.value = JSON.stringify(S.elements);
		}
		// Switch the layout radio to "visual".
		var radio = document.querySelector('input[name="layout"][value="visual"]');
		if (radio) { radio.checked = true; }
		closeDesigner();
	}

	function resetDefaults() {
		S.elements = defaultElements();
		S.selected = null;
		renderAll();
	}

	function defaultElements() {
		return CATALOGUE.map(function (d) { return Object.assign({}, d); });
	}

	// ── Render all ────────────────────────────────────────────────────────────
	function renderAll() {
		D.canvas.innerHTML = '';
		S.elements.forEach(renderEl);
		renderList();
		renderProps();
	}

	function renderEl(el) {
		var div        = document.createElement('div');
		div.className  = elClass(el);
		div.dataset.id = el.id;
		div.style.cssText = elCss(el);
		div.style.background = el.color || '#e8e8e8';

		var inner   = document.createElement('div');
		inner.className = 'wcbp-vd-el-inner';
		inner.textContent = el.label;
		div.appendChild(inner);

		var rzHandle   = document.createElement('div');
		rzHandle.className = 'wcbp-vd-rz';
		rzHandle.dataset.id = el.id;
		rzHandle.addEventListener('mousedown', onRzStart);
		div.appendChild(rzHandle);

		div.addEventListener('mousedown', onDragStart);
		D.canvas.appendChild(div);
	}

	function elClass(el) {
		return 'wcbp-vd-el' +
			(el.id === S.selected ? ' selected' : '') +
			(!el.visible ? ' el-hidden' : '');
	}

	function elCss(el) {
		return 'left:' + el.x + '%;top:' + el.y + '%;width:' + el.w + '%;height:' + el.h + '%;';
	}

	function refreshEl(el) {
		var div = D.canvas.querySelector('[data-id="' + el.id + '"]');
		if (!div) { return; }
		div.className = elClass(el);
		div.style.cssText = elCss(el);
		div.style.background = el.color || '#e8e8e8';
	}

	// ── Element list (sidebar) ────────────────────────────────────────────────
	function renderList() {
		if (!D.list) { return; }
		D.list.innerHTML = '';
		S.elements.forEach(function (el) {
			var item = document.createElement('div');
			item.className = 'wcbp-vd-list-item' + (el.id === S.selected ? ' sel' : '');
			item.dataset.id = el.id;

			var sw = document.createElement('div');
			sw.className = 'wcbp-vd-swatch';
			sw.style.background = el.color || '#e8e8e8';
			item.appendChild(sw);

			item.appendChild(document.createTextNode(el.label));
			item.addEventListener('click', function () { selectEl(el.id); });
			D.list.appendChild(item);
		});
	}

	// ── Properties panel ─────────────────────────────────────────────────────
	function renderProps() {
		if (!D.props) { return; }
		var el = getEl(S.selected);
		if (!el) {
			D.props.innerHTML = '<p class="vd-ph">Click an element to edit its properties.</p>';
			return;
		}

		var isBarcode = el.id === 'barcode';
		var frag = document.createDocumentFragment();

		// Title
		var title = document.createElement('div');
		title.style.cssText = 'font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#72aee6;margin-bottom:12px;';
		title.textContent = el.label;
		frag.appendChild(title);

		// Visible
		frag.appendChild(makePropRow('Visible', makeCheckbox('vd-prop-vis', el.visible, function (v) {
			el.visible = v; refreshEl(el); renderList();
		})));

		if (!isBarcode) {
			// Font size
			frag.appendChild(makePropRow('Font size', (function () {
				var wrap = document.createElement('div');
				wrap.style.display = 'flex'; wrap.style.alignItems = 'center'; wrap.style.gap = '4px';
				var inp = document.createElement('input');
				inp.type = 'number'; inp.min = 6; inp.max = 24; inp.value = el.fontSize;
				inp.className = 'vd-prop-row input[type=number]';
				inp.style.cssText = 'width:50px;padding:3px 5px;background:#2c3338;border:1px solid #3c434a;color:#f0f0f1;border-radius:3px;font-size:12px;';
				inp.addEventListener('change', function () {
					el.fontSize = Math.max(6, Math.min(24, parseInt(this.value, 10) || 8));
				});
				var unit = document.createElement('span');
				unit.style.cssText = 'font-size:12px;color:#a7aaad;';
				unit.textContent = 'pt';
				wrap.appendChild(inp); wrap.appendChild(unit);
				return wrap;
			}())));

			// Bold
			frag.appendChild(makePropRow('Bold', makeCheckbox('vd-prop-bold', el.bold, function (v) {
				el.bold = v;
			})));

			// Align
			frag.appendChild(makePropRow('Align', (function () {
				var wrap = document.createElement('div');
				wrap.className = 'vd-align-btns';
				['left','center','right'].forEach(function (a) {
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.dataset.align = a;
					btn.textContent = a === 'left' ? 'L' : (a === 'center' ? 'C' : 'R');
					if (el.align === a) { btn.classList.add('active'); }
					btn.title = a.charAt(0).toUpperCase() + a.slice(1);
					btn.addEventListener('click', function () {
						el.align = a;
						wrap.querySelectorAll('button').forEach(function (b) {
							b.classList.toggle('active', b.dataset.align === a);
						});
					});
					wrap.appendChild(btn);
				});
				return wrap;
			}())));
		}

		// Position/size info
		var posDiv = document.createElement('div');
		posDiv.className = 'vd-pos-info';
		posDiv.id = 'vd-pos-display';
		posDiv.innerHTML = 'X:' + el.x.toFixed(1) + '% Y:' + el.y.toFixed(1) + '%<br>W:' + el.w.toFixed(1) + '% H:' + el.h.toFixed(1) + '%';
		frag.appendChild(posDiv);

		D.props.innerHTML = '';
		D.props.appendChild(frag);
	}

	function makePropRow(labelText, control) {
		var row = document.createElement('div');
		row.className = 'vd-prop-row';
		var lbl = document.createElement('label');
		lbl.style.cssText = 'font-size:12px;color:#a7aaad;min-width:66px;';
		lbl.textContent = labelText;
		row.appendChild(lbl);
		row.appendChild(control);
		return row;
	}

	function makeCheckbox(id, checked, onChange) {
		var cb = document.createElement('input');
		cb.type = 'checkbox'; cb.id = id; cb.checked = checked;
		cb.addEventListener('change', function () { onChange(this.checked); });
		return cb;
	}

	// ── Select ────────────────────────────────────────────────────────────────
	function selectEl(id) {
		S.selected = id;
		D.canvas.querySelectorAll('.wcbp-vd-el').forEach(function (d) {
			var sel = d.dataset.id === id;
			d.classList.toggle('selected', sel);
			d.style.zIndex = sel ? '10' : '';
		});
		renderList();
		renderProps();
	}

	function getEl(id) {
		return S.elements.find(function (e) { return e.id === id; }) || null;
	}

	// ── Drag ──────────────────────────────────────────────────────────────────
	function onDragStart(e) {
		if (e.target.classList.contains('wcbp-vd-rz')) { return; }
		e.preventDefault();
		var id = this.dataset.id;
		selectEl(id);
		var el   = getEl(id);
		if (!el) { return; }
		var rect = D.canvas.getBoundingClientRect();
		drag = {
			id : id,
			ox : e.clientX - rect.left - (el.x / 100) * CANVAS_W,
			oy : e.clientY - rect.top  - (el.y / 100) * S.canvasH,
		};
	}

	// ── Resize ────────────────────────────────────────────────────────────────
	function onRzStart(e) {
		e.preventDefault(); e.stopPropagation();
		var id = this.dataset.id;
		selectEl(id);
		var el = getEl(id);
		if (!el) { return; }
		rz = { id: id, ox: e.clientX, oy: e.clientY, startW: el.w, startH: el.h };
	}

	// ── Mouse move ────────────────────────────────────────────────────────────
	function onMouseMove(e) {
		if (!drag && !rz) { return; }
		var rect = D.canvas.getBoundingClientRect();

		if (drag) {
			var el  = getEl(drag.id);
			if (!el) { drag = null; return; }
			var rawX = ((e.clientX - rect.left - drag.ox) / CANVAS_W)  * 100;
			var rawY = ((e.clientY - rect.top  - drag.oy) / S.canvasH) * 100;
			el.x = snap(clamp(rawX, 0, 100 - el.w));
			el.y = snap(clamp(rawY, 0, 100 - el.h));
			refreshEl(el);
			showCoords(el);
		}

		if (rz) {
			var el  = getEl(rz.id);
			if (!el) { rz = null; return; }
			var dxPct = ((e.clientX - rz.ox) / CANVAS_W)  * 100;
			var dyPct = ((e.clientY - rz.oy) / S.canvasH) * 100;
			el.w = snap(clamp(rz.startW + dxPct, 5, 100 - el.x));
			el.h = snap(clamp(rz.startH + dyPct, 5, 100 - el.y));
			refreshEl(el);
			showCoords(el);
		}
	}

	function onMouseUp() {
		if (drag || rz) { renderProps(); }
		drag = rz = null;
	}

	function showCoords(el) {
		var pos = document.getElementById('vd-pos-display');
		if (pos) {
			pos.innerHTML = 'X:' + el.x.toFixed(1) + '% Y:' + el.y.toFixed(1) + '%<br>W:' + el.w.toFixed(1) + '% H:' + el.h.toFixed(1) + '%';
		}
		if (D.footer) {
			D.footer.textContent = el.label + '  —  X:' + el.x.toFixed(1) + '% Y:' + el.y.toFixed(1) + '% W:' + el.w.toFixed(1) + '% H:' + el.h.toFixed(1) + '%';
		}
	}

	// ── Helpers ───────────────────────────────────────────────────────────────
	function snap(v) { return Math.round(v / SNAP) * SNAP; }
	function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

	// ── Boot ──────────────────────────────────────────────────────────────────
	document.addEventListener('DOMContentLoaded', init);
}());
