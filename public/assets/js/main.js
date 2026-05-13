// Minimal site script: loader fade-out and smooth anchor scroll
(function () {
	function onReady(fn) {
		if (document.readyState !== 'loading') return fn();
		document.addEventListener('DOMContentLoaded', fn);
	}

	onReady(function () {
		var loader = document.querySelector('.site-loader');
		window.addEventListener('load', function () {
			if (!loader) return;
			loader.classList.add('hidden');
			setTimeout(function () {
				if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
			}, 500);
		});

		document.body.addEventListener('click', function (e) {
			var a = e.target.closest('a');
			if (!a) return;
			var href = a.getAttribute('href');
			if (!href || href.charAt(0) !== '#') return;
			var target = document.querySelector(href);
			if (!target) return;
			e.preventDefault();
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			history.replaceState(null, '', href);
		});
	});


	var nav = document.querySelector('.site-nav');
	var hamb = document.querySelector('.hamburger');
	var mobile = document.getElementById('mobile-menu');
	if (hamb && mobile) {
		hamb.addEventListener('click', function () {
			var open = hamb.getAttribute('aria-expanded') === 'true';
			hamb.setAttribute('aria-expanded', String(!open));
			if (open) {
				mobile.setAttribute('hidden', '');
				hamb.classList.remove('open');
			} else {
				mobile.removeAttribute('hidden');
				hamb.classList.add('open');
			}
		});
	}
	var last = 0;
	function onScroll() {
		var y = window.scrollY || window.pageYOffset;
		if (y > 10) {
			nav.classList.add('scrolled');
		} else {
			nav.classList.remove('scrolled');
		}
		last = y;
	}
	window.addEventListener('scroll', onScroll, { passive: true });
	document.addEventListener('DOMContentLoaded', onScroll);

})();

