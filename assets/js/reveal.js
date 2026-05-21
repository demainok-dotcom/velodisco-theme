/**
 * VeloDisco — animations au défilement.
 * Tout élément portant la classe .vd-reveal apparaît en fondu + glissement
 * quand il entre dans le viewport. Léger (IntersectionObserver natif), et
 * désactivé automatiquement si l'utilisateur préfère réduire les animations.
 */
(function () {
	'use strict';

	var prefersReduced = window.matchMedia &&
		window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	var els = document.querySelectorAll('.vd-reveal');
	if (!els.length) { return; }

	// Pas d'animation souhaitée, ou pas de support → tout afficher immédiatement.
	if (prefersReduced || !('IntersectionObserver' in window)) {
		for (var i = 0; i < els.length; i++) { els[i].classList.add('is-in'); }
		return;
	}

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (entry.isIntersecting) {
				entry.target.classList.add('is-in');
				observer.unobserve(entry.target);
			}
		});
	}, {
		root: null,
		rootMargin: '0px 0px -10% 0px',
		threshold: 0.08
	});

	for (var j = 0; j < els.length; j++) { observer.observe(els[j]); }
})();
