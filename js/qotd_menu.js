(function () {
	'use strict';

	document.addEventListener('click', function (ev) {
		var btn = ev.target.closest ? ev.target.closest('.qotd-next-btn') : null;
		if (!btn) {
			return;
		}
		ev.preventDefault();

		var wrap = btn.closest('.qotd-widget-wrap');
		if (!wrap || wrap.classList.contains('is-loading')) {
			return;
		}

		var ajaxUrl = wrap.getAttribute('data-qotd-ajax-url') || '/e107_plugins/qotd_menu/qotd_ajax.php';
		var currentId = wrap.getAttribute('data-qotd-current-id') || '0';
		var sep = ajaxUrl.indexOf('?') === -1 ? '?' : '&';
		var url = ajaxUrl + sep + 'exclude_id=' + encodeURIComponent(currentId) + '&_t=' + Date.now();

		wrap.classList.add('is-loading');
		btn.disabled = true;

		fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json' }
		})
			.then(function (resp) {
				if (!resp.ok) {
					throw new Error('HTTP ' + resp.status);
				}
				return resp.json();
			})
			.then(function (data) {
				if (data && data.ok && data.html) {
					wrap.innerHTML = data.html;
					wrap.setAttribute('data-qotd-current-id', String(data.id || 0));
				}
			})
			.catch(function (err) {
				if (window.console && console.warn) {
					console.warn('QotD next quote error:', err);
				}
			})
			.finally(function () {
				wrap.classList.remove('is-loading');
				btn.disabled = false;
			});
	});
})();
