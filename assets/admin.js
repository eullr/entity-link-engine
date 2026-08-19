/* Entity Link Engine — meta box admin script. */
(function () {
	'use strict';

	var data = window.elinkData || {};
	if (!data.restUrl) {
		return;
	}

	var box = document.getElementById('elink-meta');
	if (!box) {
		return;
	}

	var results = box.querySelector('.elink-results');
	var btnSuggest = box.querySelector('.elink-suggest');
	var btnApply = box.querySelector('.elink-apply');
	var btnUndo = box.querySelector('.elink-undo');
	var current = null;

	function post(route, body) {
		return fetch(data.restUrl + route, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': data.nonce
			},
			body: JSON.stringify(body || {})
		}).then(function (r) {
			return r.json().catch(function () {
				return { error: data.i18n.error };
			});
		});
	}

	function renderCandidates(candidates) {
		if (!candidates || !candidates.length) {
			results.innerHTML = '<p><em>' + data.i18n.noResults + '</em></p>';
			btnApply.style.display = 'none';
			return;
		}
		var html = '<ul class="elink-candidates">';
		candidates.forEach(function (c) {
			var reasons = (c.reasons || []).join(', ');
			// c.url is escaped for the attribute; title/reasons escaped for text.
			html += '<li><a href="' + escapeAttr(c.url) + '" target="_blank" rel="noopener">' + escapeHtml(c.title) + '</a> ' +
				'<span class="elink-score">' + Number(c.score).toFixed(2) + '</span>' +
				(reasons ? '<br /><small>' + escapeHtml(reasons) + '</small>' : '') +
				(c.already_linked ? ' <small>(' + escapeHtml(data.i18n.already) + ')</small>' : '') +
				'</li>';
		});
		html += '</ul>';
		results.innerHTML = html;
		btnApply.style.display = '';
	}

	function escapeAttr(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	btnSuggest.addEventListener('click', function () {
		btnSuggest.disabled = true;
		btnSuggest.textContent = data.i18n.running;
		post('/suggest', { post_id: data.postId }).then(function (res) {
			btnSuggest.disabled = false;
			btnSuggest.textContent = data.i18n.suggest;
			if (res.error) {
				results.innerHTML = '<p><em>' + escapeHtml(res.error) + '</em></p>';
				return;
			}
			current = res;
			renderCandidates(res.candidates);
			btnUndo.style.display = (res.inserted && res.inserted.length) ? '' : 'none';
		});
	});

	btnApply.addEventListener('click', function () {
		btnApply.disabled = true;
		post('/run', { post_id: data.postId }).then(function (res) {
			btnApply.disabled = false;
			if (res.error) {
				results.innerHTML = '<p><em>' + escapeHtml(res.error) + '</em></p>';
				return;
			}
			results.innerHTML = '<p><em>' + data.i18n.applied + ' ' + (res.inserted || []).length + '</em></p>';
			btnApply.style.display = 'none';
			btnUndo.style.display = (res.inserted && res.inserted.length) ? '' : 'none';
			if (window.location.reload) {
				// Refresh the editor content preview without a full reload when possible.
				window.location.reload();
			}
		});
	});

	btnUndo.addEventListener('click', function () {
		btnUndo.disabled = true;
		post('/undo', { post_id: data.postId }).then(function (res) {
			btnUndo.disabled = false;
			results.innerHTML = '<p><em>' + data.i18n.undone + '</em></p>';
			btnUndo.style.display = 'none';
			btnApply.style.display = 'none';
			window.location.reload();
		});
	});
})();
