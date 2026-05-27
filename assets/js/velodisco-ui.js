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
		// Garde-fou : header entièrement visible (transform remis à zéro) pendant l'overlay.
		if (typeof resetHeader === 'function') { resetHeader(); }
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

	/* ------------------------------------------------------------------ */
	/* Bouton RETOUR : animation vélo. À chaque survol on tire un vélo    */
	/* au hasard parmi 10. Le PNG est injecté en absolu dans le bouton,   */
	/* l'animation est portée par le CSS (.vd-bike + .vd-single__retour). */
	/* ------------------------------------------------------------------ */
	var bikeBase = (window.VeloDiscoUI && window.VeloDiscoUI.bikesUrl) || '';
	if (bikeBase) {
		var bikeCount = 10;
		// Préfixe normalisé (s'assure d'un / final).
		if (bikeBase.charAt(bikeBase.length - 1) !== '/') { bikeBase += '/'; }

		function pickBikeSrc() {
			var n = Math.floor(Math.random() * bikeCount) + 1;
			var pad = n < 10 ? '0' + n : '' + n;
			return bikeBase + 'velo-' + pad + '.png';
		}

		var returnBtns = document.querySelectorAll('.vd-single__retour');
		Array.prototype.forEach.call(returnBtns, function (btn) {
			// Évite double-init (HMR / re-render).
			if (btn.querySelector('.vd-bike')) return;

			// Encapsule le texte courant dans un <span> pour pouvoir le superposer
			// au vélo via z-index (cf. .vd-bike-label dans velodisco.css).
			var label = document.createElement('span');
			label.className = 'vd-bike-label';
			while (btn.firstChild) { label.appendChild(btn.firstChild); }
			btn.appendChild(label);

			// Vélo : décoratif (alt vide + aria-hidden), première src au montage.
			var img = document.createElement('img');
			img.className = 'vd-bike';
			img.alt = '';
			img.setAttribute('aria-hidden', 'true');
			img.decoding = 'async';
			img.loading = 'lazy';
			img.src = pickBikeSrc();
			btn.appendChild(img);

			// Re-tire un vélo aléatoire APRÈS chaque survol/focus, pour que la
			// prochaine traversée montre un autre vélo. (On ne change pas la src
			// pendant l'animation pour ne pas voir le vélo se transformer en plein vol.)
			function reroll() { img.src = pickBikeSrc(); }
			btn.addEventListener('mouseleave', reroll);
			btn.addEventListener('blur', reroll, true);
		});
	}

	/* ------------------------------------------------------------------ */
	/* Pastilles footer (surprise sonore) : au clic, on tire une paire    */
	/* son+icône au hasard. L'icône remplace les pastilles le temps que   */
	/* le son joue, puis le visuel revient.                                */
	/* ------------------------------------------------------------------ */
	var fxBase = (window.VeloDiscoUI && window.VeloDiscoUI.footerFxUrl) || '';
	if (fxBase) {
		var fxPairs = ['sonnette', 'klaxon', 'rouearriere', 'velib'];
		if (fxBase.charAt(fxBase.length - 1) !== '/') { fxBase += '/'; }

		var fxPreloaded = {};      // cache d'objets Audio (clé = slug)
		var fxActiveAudio = null;  // l'Audio en cours (pour interruption)

		function fxPreloadAll() {
			fxPairs.forEach(function (slug) {
				if (!fxPreloaded[slug]) {
					var a = new Audio(fxBase + slug + '.mp3');
					a.preload = 'auto';
					fxPreloaded[slug] = a;
				}
			});
		}

		function fxPickSlug() {
			return fxPairs[Math.floor(Math.random() * fxPairs.length)];
		}

		function fxPlay(btn) {
			var slug = fxPickSlug();
			// Interrompt le son précédent (clic rapide) sans attendre la fin.
			if (fxActiveAudio) {
				fxActiveAudio.pause();
				fxActiveAudio.currentTime = 0;
			}
			var img = btn.querySelector('.vd-footer__fx');
			img.src = fxBase + slug + '.png';
			btn.classList.add('is-fx');

			var audio = fxPreloaded[slug];
			if (!audio) {
				audio = new Audio(fxBase + slug + '.mp3');
				fxPreloaded[slug] = audio;
			}
			audio.currentTime = 0;
			fxActiveAudio = audio;

			function done() {
				if (fxActiveAudio === audio) {
					btn.classList.remove('is-fx');
					fxActiveAudio = null;
				}
				audio.removeEventListener('ended', done);
			}
			audio.addEventListener('ended', done);
			var p = audio.play();
			if (p && p.catch) {
				p.catch(function () {
					// Lecture refusée (politique navigateur, son indisponible) : on
					// laisse l'icône visible 1.5s puis on revient à l'état normal.
					setTimeout(done, 1500);
				});
			}
		}

		document.querySelectorAll('button.vd-footer__dots').forEach(function (btn) {
			// Évite double-init.
			if (btn.dataset.fxInit) return;
			btn.dataset.fxInit = '1';

			// Injecte l'élément image qui sera utilisé pour le swap visuel.
			if (!btn.querySelector('.vd-footer__fx')) {
				var img = document.createElement('img');
				img.className = 'vd-footer__fx';
				img.alt = '';
				img.setAttribute('aria-hidden', 'true');
				img.decoding = 'async';
				btn.appendChild(img);
			}

			// Précharge les 4 MP3 au premier hover/focus (lazy : pas au chargement
			// initial pour ne pas alourdir la page, mais avant le clic pour que la
			// lecture démarre instantanément).
			btn.addEventListener('mouseenter', fxPreloadAll, { once: true });
			btn.addEventListener('focus', fxPreloadAll, { once: true });

			btn.addEventListener('click', function () { fxPlay(btn); });
		});
	}

	/* ------------------------------------------------------------------ */
	/* Header : masquer au scroll vers le bas, réafficher vers le haut      */
	/* (mêmes réglages sur desktop ET mobile)                               */
	/* ------------------------------------------------------------------ */
	var vdHeader = document.querySelector('.vd-header');

	function burgerOpen() { return burgerPanel && burgerPanel.classList.contains('is-open'); }
	function scrollY() { return window.pageYOffset || document.documentElement.scrollTop || 0; }

	// Hauteur du header MISE EN CACHE : lire offsetHeight à chaque événement scroll
	// forcerait un reflow synchrone à répétition. On la mesure une fois, puis on la
	// rafraîchit seulement au redimensionnement (c'est le seul moment où elle change).
	var headerHeight = vdHeader ? vdHeader.offsetHeight : 56;
	function refreshHeaderHeight() { if (vdHeader) { headerHeight = vdHeader.offsetHeight; } }
	window.addEventListener('resize', refreshHeaderHeight, { passive: true });

	/* Auto-hide à DEMI-VITESSE et FLUIDE.
	 * - targetOffset = position « voulue » du header (0 visible → H caché), calculée
	 *   au fil du scroll à la moitié de la vitesse de défilement (SPEED = 0.5).
	 * - renderedOffset = position réellement affichée, qui GLISSE vers la cible dans
	 *   une boucle requestAnimationFrame (lissage continu) → pas de saccade liée aux
	 *   à-coups d'événements scroll d'iOS. Pas de transition CSS (la boucle s'en charge).
	 * - Zone morte de 80px (~2 cm) de remontée avant que la réapparition commence. */
	var REVEAL_DEADZONE = 80;
	var SPEED = 0.5;             // le header bouge à la moitié de la vitesse du scroll
	var targetOffset = 0;        // 0 = visible, headerHeight = entièrement caché
	var renderedOffset = 0;      // position affichée (glisse vers la cible)
	var upAccum = 0;             // remontée cumulée (consomme la zone morte)
	var lastY = scrollY();
	var rafRunning = false;

	function paint(off) {
		if (!vdHeader) { return; }
		vdHeader.style.transform = off > 0.5 ? 'translateY(-' + off.toFixed(1) + 'px)' : '';
	}
	function renderLoop() {
		var diff = targetOffset - renderedOffset;
		if (Math.abs(diff) < 0.5) { renderedOffset = targetOffset; }
		else { renderedOffset += diff * 0.14; }   // lissage (plus petit = plus doux)
		paint(renderedOffset);
		if (renderedOffset !== targetOffset) { window.requestAnimationFrame(renderLoop); }
		else { rafRunning = false; }
	}
	function startRender() {
		if (!rafRunning) { rafRunning = true; window.requestAnimationFrame(renderLoop); }
	}
	function resetHeader() { targetOffset = 0; renderedOffset = 0; upAccum = 0; paint(0); }

	function updateHeader() {
		if (!vdHeader) { return; }
		var y = scrollY();
		var H = headerHeight;
		// Menu burger ouvert, ou près du haut → cible = visible.
		if (burgerOpen() || y <= H) {
			targetOffset = 0; upAccum = 0; lastY = y; startRender(); return;
		}
		var delta = y - lastY;
		if (delta > 0) {                            // on descend → masquer (demi-vitesse)
			if (targetOffset === 0) { closeAllPopovers(null); }
			targetOffset = Math.min(H, targetOffset + delta * SPEED);
			upAccum = 0;
		} else if (delta < 0) {                     // on remonte
			upAccum += -delta;
			if (upAccum > REVEAL_DEADZONE) {          // après ~2 cm → réaffiche (demi-vitesse)
				targetOffset = Math.max(0, targetOffset + delta * SPEED); // delta<0 → réduit
			}
		}
		lastY = y;
		startRender();
	}
	// updateHeader est léger (maj de la cible) ; le lissage se fait dans renderLoop.
	window.addEventListener('scroll', updateHeader, { passive: true });

	/* ------------------------------------------------------------------ */
	/* Consentement cookies + chargement conditionnel de Google Analytics  */
	/* ------------------------------------------------------------------ */
	var vdConsent = document.getElementById('vd-consent');
	if (vdConsent) {
		var CONSENT_COOKIE = 'vd_consent';   // 'a' = mesure d'audience acceptée, 'n' = refusée
		var CONSENT_DAYS = 180;

		function vdGetCookie(name) {
			var m = document.cookie.match('(?:^|; )' + name.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)');
			return m ? decodeURIComponent(m[1]) : null;
		}
		function vdSetCookie(name, val, days) {
			var d = new Date();
			d.setTime(d.getTime() + days * 86400000);
			document.cookie = name + '=' + encodeURIComponent(val) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
		}

		var gaLoaded = false;
		function vdLoadGA4() {
			if (gaLoaded) { return; }
			var id = vdConsent.getAttribute('data-ga4');
			if (!id) { return; }
			gaLoaded = true;
			var s = document.createElement('script');
			s.async = true;
			s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
			document.head.appendChild(s);
			window.dataLayer = window.dataLayer || [];
			window.gtag = function () { window.dataLayer.push(arguments); };
			window.gtag('js', new Date());
			window.gtag('config', id);
		}

		var prefsPanel = vdConsent.querySelector('.vd-consent__prefs');
		var analyticsBox = document.getElementById('vd-consent-analytics');

		function vdShowConsent() { vdConsent.hidden = false; }
		function vdHideConsent() {
			vdConsent.hidden = true;
			if (prefsPanel) { prefsPanel.hidden = true; }
		}
		// analytics = true/false ; persist = false pour ne pas réécrire le cookie.
		function vdApplyConsent(analytics, persist) {
			if (persist !== false) { vdSetCookie(CONSENT_COOKIE, analytics ? 'a' : 'n', CONSENT_DAYS); }
			if (analytics) { vdLoadGA4(); }
			vdHideConsent();
		}

		// État initial : on respecte un choix déjà fait, sinon on affiche le bandeau.
		var stored = vdGetCookie(CONSENT_COOKIE);
		if (stored === 'a') { vdLoadGA4(); }
		else if (stored === 'n') { /* refusé : on ne charge rien */ }
		else { vdShowConsent(); }

		// Boutons du bandeau / panneau.
		vdConsent.addEventListener('click', function (e) {
			var btn = e.target.closest && e.target.closest('[data-consent]');
			if (!btn) { return; }
			var act = btn.getAttribute('data-consent');
			if (act === 'accept') { vdApplyConsent(true); }
			else if (act === 'deny') { vdApplyConsent(false); }
			else if (act === 'custom') { if (prefsPanel) { prefsPanel.hidden = !prefsPanel.hidden; } }
			else if (act === 'save') { vdApplyConsent(!!(analyticsBox && analyticsBox.checked)); }
		});

		// Lien « Gérer les cookies » (footer) : ré-ouvre le bandeau et reflète le choix.
		var reopeners = document.querySelectorAll('[data-consent-reopen]');
		for (var i = 0; i < reopeners.length; i++) {
			reopeners[i].hidden = false; // visible seulement quand un bandeau existe
			reopeners[i].addEventListener('click', function (e) {
				e.preventDefault();
				if (analyticsBox) { analyticsBox.checked = ( vdGetCookie(CONSENT_COOKIE) === 'a' ); }
				vdShowConsent();
			});
		}
	}
})();
