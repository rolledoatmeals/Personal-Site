(function () {
	function onReady(fn) {
		if (document.readyState !== 'loading') return fn();
		document.addEventListener('DOMContentLoaded', fn);
	}

	function setupLoader() {
		const loader = document.querySelector('.site-loader');

		window.addEventListener('load', function () {
			if (!loader) return;
			loader.classList.add('hidden');
			setTimeout(function () {
				if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
			}, 500);
		});
	}

	function setupAnchorScroll() {
		document.body.addEventListener('click', function (e) {
			const link = e.target.closest('a[data-scroll]');
			if (!link) return;

			const target = document.getElementById(link.dataset.scroll);
			if (!target) return;

			e.preventDefault();
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	}

	function setupMobileNav() {
		const hamburger = document.querySelector('.hamburger');
		const mobileMenu = document.getElementById('mobile-menu');

		if (!hamburger || !mobileMenu) return;

		hamburger.addEventListener('click', function () {
			const isOpen = hamburger.getAttribute('aria-expanded') === 'true';

			hamburger.setAttribute('aria-expanded', String(!isOpen));
			if (isOpen) {
				mobileMenu.setAttribute('hidden', '');
				hamburger.classList.remove('open');
				return;
			}

			mobileMenu.removeAttribute('hidden');
			hamburger.classList.add('open');
		});
	}

	function setupNavScrollState() {
		const nav = document.querySelector('.site-nav');

		if (!nav) return;

		function onScroll() {
			const y = window.scrollY || window.pageYOffset;

			nav.classList.toggle('scrolled', y > 10);
		}

		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	var CHAR_GRADIENT = 'linear-gradient(90deg,#fff 0%,#00e676 45%,#00c8ff 100%)';

	function applyCharAnimation(el) {
		if (!el) return;
		var text = el.textContent;

		el.innerHTML = text.split('').map(function (ch) {
			if (ch === ' ') return '&nbsp;';
			return '<span class="char">' + ch + '</span>';
		}).join('');

		var rect = el.getBoundingClientRect();
		var totalW = rect.width || 1;
		var chars = Array.prototype.slice.call(el.querySelectorAll('.char'));

		chars.forEach(function (span, i) {
			var offsetX = span.getBoundingClientRect().left - rect.left;
			span.style.backgroundImage = CHAR_GRADIENT;
			span.style.backgroundSize = totalW + 'px 100%';
			span.style.backgroundPositionX = '-' + offsetX + 'px';
			span.style.webkitBackgroundClip = 'text';
			span.style.backgroundClip = 'text';
			span.style.webkitTextFillColor = 'transparent';
			span.style.animationDelay = '-' + (i / chars.length * 5).toFixed(2) + 's';
		});

		el.style.backgroundImage = 'none';
		el.style.webkitTextFillColor = 'initial';
		el.style.webkitBackgroundClip = 'initial';
		el.style.backgroundClip = 'initial';

		chars.forEach(function (span, idx) {
			span.addEventListener('mouseenter', function () {
				chars.forEach(function (c, j) {
					var d = Math.abs(j - idx);
					c.dataset.wave = d <= 2 ? String(d) : '3';
				});
			});
			span.addEventListener('mouseleave', function () {
				chars.forEach(function (c) { delete c.dataset.wave; });
			});
		});
	}

	function setupNameAnimation() {
		applyCharAnimation(document.querySelector('.hero-title'));
	}

	function setupHeadingAnimations() {
		[
			document.querySelector('.about-heading'),
			document.querySelector('.skills h2'),
			document.querySelector('.contact-inner h2'),
		].forEach(applyCharAnimation);
	}

	function setupNavDirection() {
		const links = document.querySelectorAll('.nav-links a');
		links.forEach(function (link) {
			link.addEventListener('mouseenter', function (e) {
				const rect = link.getBoundingClientRect();
				const dir = e.clientX < rect.left + rect.width / 2 ? 'left' : 'right';
				link.style.setProperty('--underline-origin', dir);
			});
		});
	}

	function setupSkillCardVars() {
		const cards = document.querySelectorAll('.skill-card[data-i]');
		if (!cards || !cards.length) return;
		cards.forEach(function (c) {
			const idx = c.getAttribute('data-i') || '0';
			c.style.setProperty('--i', idx);
		});
	}

	onReady(function () {
		setupLoader();
		setupAnchorScroll();
		setupMobileNav();
		setupNavScrollState();
		setupSkillCardVars();
		setupNavDirection();
		setupNameAnimation();
		setupHeadingAnimations();
	});
})();

