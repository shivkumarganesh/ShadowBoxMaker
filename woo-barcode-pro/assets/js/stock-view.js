/* global wcbpStockView */
(function () {
	'use strict';

	var cfg       = wcbpStockView;
	var allProducts  = [];
	var activeCat    = 0;   // 0 = All
	var searchQuery  = '';
	var deferTimer   = null;

	// ── DOM refs ──────────────────────────────────────────────────────────────
	var $pills   = document.getElementById('sv-pills');
	var $grid    = document.getElementById('sv-grid');
	var $count   = document.getElementById('sv-count');
	var $search  = document.getElementById('sv-search');
	var $install = document.getElementById('sv-install-btn');

	// ── PWA install prompt ────────────────────────────────────────────────────
	var deferredPrompt = null;

	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault();
		deferredPrompt = e;
		if ($install) { $install.classList.add('visible'); }
	});

	if ($install) {
		$install.addEventListener('click', function () {
			if (!deferredPrompt) { return; }
			deferredPrompt.prompt();
			deferredPrompt.userChoice.then(function () {
				deferredPrompt = null;
				$install.classList.remove('visible');
			});
		});
	}

	// ── Fetch products ────────────────────────────────────────────────────────
	function fetchProducts() {
		showSpinner();
		var fd = new FormData();
		fd.append('action', 'wcbp_sv_get_products');
		fd.append('nonce',  cfg.nonce);

		fetch(cfg.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.success) {
					showEmpty(cfg.strings.error);
					return;
				}
				allProducts = data.data.products || [];
				buildPills(data.data.categories || []);
				renderGrid();
			})
			.catch(function () { showEmpty(cfg.strings.error); });
	}

	// ── Build category pills ──────────────────────────────────────────────────
	function buildPills(categories) {
		if (!$pills) { return; }
		$pills.innerHTML = '';

		var allPill = makePill(0, cfg.strings.all_cat);
		allPill.classList.add('active');
		$pills.appendChild(allPill);

		categories.forEach(function (cat) {
			$pills.appendChild(makePill(cat.id, cat.name));
		});

		if (categories.length === 0) {
			$pills.parentElement.style.display = 'none';
		}
	}

	function makePill(id, label) {
		var btn = document.createElement('button');
		btn.className   = 'sv-pill';
		btn.textContent = label;
		btn.dataset.cat = id;
		btn.type        = 'button';
		btn.addEventListener('click', function () {
			activeCat = parseInt(id, 10);
			document.querySelectorAll('.sv-pill').forEach(function (p) {
				p.classList.toggle('active', parseInt(p.dataset.cat, 10) === activeCat);
			});
			renderGrid();
		});
		return btn;
	}

	// ── Render ────────────────────────────────────────────────────────────────
	function renderGrid() {
		if (!$grid) { return; }

		var query    = searchQuery.toLowerCase();
		var filtered = allProducts.filter(function (p) {
			if (activeCat !== 0 && p.categories.indexOf(activeCat) === -1) { return false; }
			if (query && p.name.toLowerCase().indexOf(query) === -1)       { return false; }
			return true;
		});

		if ($count) {
			$count.textContent = filtered.length + ' ' + cfg.strings.products;
		}

		if (filtered.length === 0) {
			$grid.innerHTML = '';
			showEmpty(cfg.strings.no_products);
			return;
		}

		var html = '';
		filtered.forEach(function (p) {
			html += buildCard(p);
		});
		$grid.innerHTML = html;
	}

	function buildCard(p) {
		var oos   = p.stock !== null && p.stock <= 0;
		var card  = '<div class="sv-card' + (oos ? ' out-of-stock' : '') + '">';

		// Thumbnail or placeholder
		if (p.thumb) {
			card += '<img class="sv-card-img" src="' + escAttr(p.thumb) + '" alt="" loading="lazy">';
		} else {
			card += '<div class="sv-card-img-placeholder">&#128247;</div>';
		}

		card += '<div class="sv-card-body">';
		card += '<div class="sv-card-name">' + escHtml(p.name) + '</div>';

		// Stock badge
		if (p.stock === null) {
			card += '<span class="sv-badge untracked">' + cfg.strings.untracked + '</span>';
		} else if (p.stock <= 0) {
			card += '<span class="sv-badge out-of-stock">' + cfg.strings.out_of_stock + '</span>';
		} else {
			card += '<span class="sv-badge in-stock">' + p.stock + ' ' + cfg.strings.in_stock + '</span>';
		}

		card += '</div></div>';
		return card;
	}

	// ── UI helpers ────────────────────────────────────────────────────────────
	function showSpinner() {
		if (!$grid) { return; }
		$grid.innerHTML =
			'<div class="sv-spinner" style="grid-column:1/-1">' +
			'<div class="sv-spinner-ring"></div>' +
			'<span>' + cfg.strings.loading + '</span>' +
			'</div>';
		if ($count) { $count.textContent = ''; }
	}

	function showEmpty(msg) {
		if (!$grid) { return; }
		$grid.innerHTML =
			'<div class="sv-empty" style="grid-column:1/-1">' +
			'<span class="sv-empty-icon">&#128230;</span>' +
			escHtml(msg) +
			'</div>';
		if ($count) { $count.textContent = ''; }
	}

	function escHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function escAttr(s) {
		return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	// ── Search ────────────────────────────────────────────────────────────────
	if ($search) {
		$search.addEventListener('input', function () {
			clearTimeout(deferTimer);
			deferTimer = setTimeout(function () {
				searchQuery = $search.value.trim();
				renderGrid();
			}, 220);
		});
	}

	// ── Boot ──────────────────────────────────────────────────────────────────
	fetchProducts();
}());
