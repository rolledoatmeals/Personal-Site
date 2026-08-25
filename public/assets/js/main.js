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
			document.body.classList.add('loaded');

			var heroChars = document.querySelectorAll('.hero-title .char');
			var total = heroChars.length || 1;
			heroChars.forEach(function (span, i) {
				var enter = (i * 0.048 + 0.12).toFixed(3);
				var idle  = '-' + (i / total * 5).toFixed(2);
				span.style.animation =
					'hero-char-enter .52s cubic-bezier(.34,1.56,.64,1) ' + enter + 's both,' +
					'color-spin-idle 5s linear ' + idle + 's infinite';

				span.addEventListener('animationend', function cleanup(e) {
					if (e.animationName !== 'hero-char-enter') return;
					span.removeEventListener('animationend', cleanup);
					span.style.animation = '';
					span.style.animationDelay = idle + 's';
				});
			});

			setTimeout(function () {
				if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
			}, 500);
		});
	}

	// Sections are full-height with their content vertically centred, so
	// scrolling to a section's top edge leaves up to 300px of dead space above
	// the heading. Aim at the heading instead and tuck it under the nav.
	function sectionScrollTop(target) {
		const nav = document.querySelector('.site-nav');
		const navH = nav ? nav.getBoundingClientRect().height : 0;
		const anchor = target.querySelector('h2, .about-heading, h1') || target;
		const y = window.scrollY + anchor.getBoundingClientRect().top - navH - 28;
		return Math.max(0, Math.round(y));
	}

	function setupAnchorScroll() {
		document.body.addEventListener('click', function (e) {
			const link = e.target.closest('a[data-scroll]');
			if (!link) return;

			const target = document.getElementById(link.dataset.scroll);
			if (!target) return;

			e.preventDefault();
			window.scrollTo({ top: sectionScrollTop(target), behavior: 'smooth' });
		});
	}

	function setupScrollSpy() {
		const links = Array.from(document.querySelectorAll('a[data-scroll]'));
		if (!links.length) return;

		// Desktop and mobile menus repeat the same ids, and nav order does not
		// match page order, so dedupe and sort by where sections actually sit.
		const ids = links.map(function (a) { return a.dataset.scroll; })
			.filter(function (id, i, all) { return all.indexOf(id) === i; });
		const sections = ids.map(function (id) {
			return { id: id, el: document.getElementById(id) };
		}).filter(function (s) { return s.el; })
			.sort(function (a, b) { return a.el.offsetTop - b.el.offsetTop; });
		if (!sections.length) return;

		let ticking = false;

		function mark() {
			// Read a third of the way down the screen: a section counts as current
			// once it owns most of the view, not the instant its top edge appears.
			const line = window.scrollY + window.innerHeight * 0.34;
			const atBottom = window.scrollY + window.innerHeight >=
				document.documentElement.scrollHeight - 4;

			let currentId = null;
			sections.forEach(function (s) {
				if (s.el.offsetTop <= line) currentId = s.id;
			});
			if (atBottom) currentId = sections[sections.length - 1].id;

			links.forEach(function (a) {
				a.classList.toggle('active', a.dataset.scroll === currentId);
			});
		}

		window.addEventListener('scroll', function () {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(function () { mark(); ticking = false; });
		}, { passive: true });

		window.addEventListener('resize', mark, { passive: true });
		mark();
	}

	function setupMobileNav() {
		const hamburger = document.querySelector('.hamburger');
		const mobileMenu = document.getElementById('mobile-menu');

		if (!hamburger || !mobileMenu) return;

		function closeMenu() {
			hamburger.setAttribute('aria-expanded', 'false');
			hamburger.classList.remove('open');
			mobileMenu.classList.remove('is-open');
		}

		hamburger.addEventListener('click', function () {
			const isOpen = hamburger.getAttribute('aria-expanded') === 'true';
			hamburger.setAttribute('aria-expanded', String(!isOpen));
			if (isOpen) {
				closeMenu();
			} else {
				hamburger.classList.add('open');
				mobileMenu.classList.add('is-open');
			}
		});

		mobileMenu.addEventListener('click', function (e) {
			if (e.target.closest('a')) closeMenu();
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

		el.innerHTML = text.split(' ').map(function (word) {
			return '<span class="word">' + word.split('').map(function (ch) {
				return '<span class="char">' + ch + '</span>';
			}).join('') + '</span>';
		}).join('<span class="char">&nbsp;</span>');

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

	function setupSkillTilt() {
		var cards = document.querySelectorAll('.skill-card');
		cards.forEach(function (card) {
			var lastRipple = 0;

			card.addEventListener('mousemove', function (e) {
				var rect = card.getBoundingClientRect();
				var x = e.clientX - rect.left;
				var y = e.clientY - rect.top;

				var nx = x / rect.width - 0.5;
				var ny = y / rect.height - 0.5;
				card.style.transform = 'translateY(-12px) rotateX(' + (-ny * 12) + 'deg) rotateY(' + (nx * 12) + 'deg)';

				var now = Date.now();
				if (now - lastRipple < 220) return;
				lastRipple = now;

				var ripple = document.createElement('span');
				ripple.className = 'skill-ripple';
				ripple.style.left = x + 'px';
				ripple.style.top  = y + 'px';
				card.appendChild(ripple);
				ripple.addEventListener('animationend', function () {
					ripple.parentNode && ripple.parentNode.removeChild(ripple);
				});
			});

			card.addEventListener('mouseleave', function () {
				card.style.transform = '';
			});
		});
	}

	function setupBackToTop() {
		var btn = document.querySelector('.back-to-top');
		if (!btn) return;
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});
	}

	onReady(function () {
		setupLoader();
		setupAnchorScroll();
		setupScrollSpy();
		setupBackToTop();
		setupMobileNav();
		setupNavScrollState();
		setupSkillCardVars();
		setupSkillTilt();
		setupNavDirection();
		setupNameAnimation();
		setupHeadingAnimations();
	});
})();



// Reveal the Work grid with a stagger the first time it scrolls into view.
(function () {
	const work = document.getElementById('work');
	if (!work || !('IntersectionObserver' in window)) {
		if (work) work.classList.add('in-view');
		return;
	}
	const io = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				work.classList.add('in-view');
				io.disconnect();
			}
		});
	}, { threshold: 0.15 });
	io.observe(work);
})();
