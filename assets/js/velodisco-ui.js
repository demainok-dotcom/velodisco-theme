/**
 * VeloDisco — interface (vanilla JS, sans dépendance).
 * Gère : bascule de thème (sombre / clair / système), menu burger mobile,
 * popover de recherche du header. Tout est défensif : si un élément n'existe
 * pas sur la page, le bloc correspondant ne fait rien (aucune erreur).
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'velodisco-theme';
	var root = document.documentElement;
	var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

	/* ------------------------------------------------------------------ */
	/* Thème : sombre / clair / système                                   */
	/* ------------------------------------------------------------------ */
	function resolve(mode) {
		if (mode === 'system') {
			return (mql && mql.matches) ? 'dark' : 'light';
		}
		return mode;
	}

	function applyTheme(mode) {
		var resolved = resolve(mode);
		root.setAttribute('data-theme', resolved);
		root.setAttribute('data-theme-mode', mode);
		// Met à jour l'état visuel des boutons du menu réglage.
		var btns = document.querySelectorAll('[data-theme-set]');
		for (var i = 0; i < btns.length; i++) {
			btns[i].setAttribute('aria-checked', btns[i].getAttribute('data-theme-set') === mode ? 'true' : 'false');
		}
	}

	function setTheme(mode) {
		try { localStorage.setItem(STORAGE_KEY, mode); } catch (e) {}
		applyTheme(mode);
	}

	function currentMode() {
		try { return localStorage.getItem(STORAGE_KEY) || 'system'; } catch (e) { return 'system'; }
	}

	// Synchronise l'affichage au chargement (le script anti-flash a déjà posé data-theme).
	applyTheme(currentMode());

	// Si l'utilisateur est en mode "système", suivre les changements de l'OS en direct.
	if (mql) {
		var onChange = function () { if (currentMode() === 'system') { applyTheme('system'); } };
		if (mql.addEventListener) { mql.addEventListener('change', onChange); }
		else if (mql.addListener) { mql.addListener(onChange); }
	}

	// Boutons SOMBRE / CLAIR / SYSTÈME.
	document.addEventListener('click', function (e) {
		var setBtn = e.target.closest && e.target.closest('[data-theme-set]');
		if (setBtn) {
			setTheme(setBtn.getAttribute('data-theme-set'));
		}
	});

	/* ------------------------------------------------------------------ */
	/* Popovers (recherche + menu réglage) : ouverture/fermeture           */
	/* ------------------------------------------------------------------ */
	function closeAllPopovers(except) {
		var pops = document.querySelectorAll('.vd-pop.is-open');
		for (var i = 0; i < pops.length; i++) {
			if (pops[i] !== except) {
				pops[i].classList.remove('is-open');
				var ctrl = document.querySelector('[aria-controls="' + pops[i].id + '"]');
				if (ctrl) { ctrl.setAttribute('aria-expanded', 'false'); }
			}
		}
	}

	// Positionne un popover centré horizontalement sous son déclencheur.
	function positionPopover(pop, trigger) {
		var header = (trigger.closest && trigger.closest('.vd-header')) || document.querySelector('.vd-header');
		if (!header) { return; }
		var hr = header.getBoundingClientRect();
		var tr = trigger.getBoundingClientRect();
		var pw = pop.offsetWidth;
		var center = (tr.left - hr.left) + tr.width / 2;
		var left = center - pw / 2;
		left = Math.max(8, Math.min(left, hr.width - pw - 8)); // reste dans l'écran
		pop.style.left = left + 'px';
		pop.style.right = 'auto';
		pop.style.top = (tr.bottom - hr.top + 8) + 'px';
	}

	document.addEventListener('click', function (e) {
		var trigger = e.target.closest && e.target.closest('[data-popover-target]');
		if (trigger) {
			e.preventDefault();
			var id = trigger.getAttribute('data-popover-target');
			var pop = document.getElementById(id);
			if (!pop) { return; }
			var willOpen = !pop.classList.contains('is-open');
			closeAllPopovers(pop);
			pop.classList.toggle('is-open', willOpen);
			trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			if (willOpen) {
				positionPopover(pop, trigger);
				var input = pop.querySelector('input');
				if (input) { setTimeout(function () { input.focus(); }, 60); }
			}
			return;
		}
		// Clic en dehors d'un popover → fermer.
		if (!(e.target.closest && e.target.closest('.vd-pop'))) {
			closeAllPopovers(null);
		}
	});

	// Échap ferme les popovers et le menu burger.
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			closeAllPopovers(null);
			closeBurger();
		}
	});

	/* ------------------------------------------------------------------ */
	/* Menu burger mobile                                                  */
	/* ------------------------------------------------------------------ */
	var burgerPanel = document.getElementById('vd-mobile-nav');

	function openBurger() {
		if (!burgerPanel) { return; }
		burgerPanel.classList.add('is-open');
		burgerPanel.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
		var t = document.querySelector('[data-burger-toggle]');
		if (t) { t.setAttribute('aria-expanded', 'true'); }
	}

	function closeBurger() {
		if (!burgerPanel) { return; }
		burgerPanel.classList.remove('is-open');
		burgerPanel.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
		var t = document.querySelector('[data-burger-toggle]');
		if (t) { t.setAttribute('aria-expanded', 'false'); }
	}

	document.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('[data-burger-toggle]')) {
			e.preventDefault();
			if (burgerPanel && burgerPanel.classList.contains('is-open')) { closeBurger(); }
			else { openBurger(); }
		}
		if (e.target.closest && e.target.closest('[data-burger-close]')) {
			e.preventDefault();
			closeBurger();
		}
	});

	// Bouton RETOUR des articles : revient à la page précédente s'il y a un
	// historique ; sinon laisse le lien (href="/") ramener à l'accueil.
	document.addEventListener('click', function (e) {
		var r = e.target.closest && e.target.closest('.vd-single__retour');
		if (r && window.history.length > 1) {
			e.preventDefault();
			window.history.back();
		}
	});
})();
