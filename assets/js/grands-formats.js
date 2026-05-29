/**
 * VeloDisco — Animations Grands Formats
 *
 * Deux animations activées sur les articles de la catégorie Grands Formats :
 * 1. Timeline horizontale scroll-progressive (.vd-gf01__hframe)
 *    Les jalons apparaissent gauche → droite au fur et à mesure que le
 *    lecteur scrolle dans la zone du wrapper sticky.
 * 2. Apparition au scroll des cartes pionnières (.vd-gf01__cardmeta)
 *    IntersectionObserver classique avec fade-up.
 *
 * Respecte prefers-reduced-motion (le CSS force opacity:1 dans ce cas).
 */
(function () {
	'use strict';

	function initTimeline() {
		var frame = document.querySelector('.vd-gf01__hframe');
		if (!frame) return;
		var items = frame.querySelectorAll('.vd-gf01__htl-item');
		if (!items.length) return;

		frame.classList.add('is-anim-ready');
		var n = items.length;
		var ticking = false;

		function update() {
			var rect = frame.getBoundingClientRect();
			var winH = window.innerHeight;
			var total = Math.max(1, rect.height - winH);
			var scrolled = Math.max(0, Math.min(total, -rect.top));
			var progress = scrolled / total;
			var visibleCount = Math.ceil(progress * n);
			if (rect.top < winH && visibleCount < 1) visibleCount = 1;
			for (var i = 0; i < items.length; i++) {
				if (i < visibleCount) items[i].classList.add('is-visible');
				else items[i].classList.remove('is-visible');
			}
			ticking = false;
		}

		function onScroll() {
			if (!ticking) {
				window.requestAnimationFrame(update);
				ticking = true;
			}
		}

		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
		update();
	}

	function initCards() {
		var cards = document.querySelectorAll('.vd-gf01__cardmeta');
		if (!cards.length) return;

		if (!('IntersectionObserver' in window)) {
			for (var i = 0; i < cards.length; i++) {
				cards[i].classList.add('is-visible');
			}
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			for (var j = 0; j < entries.length; j++) {
				var entry = entries[j];
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
					observer.unobserve(entry.target);
				}
			}
		}, { threshold: 0.25, rootMargin: '0px 0px -80px 0px' });

		for (var k = 0; k < cards.length; k++) {
			observer.observe(cards[k]);
		}
	}

	function init() {
		initTimeline();
		initCards();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
