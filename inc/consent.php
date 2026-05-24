<?php
/**
 * VeloDisco — mesure d'audience + bandeau de consentement cookies.
 *
 * Stratégie (choix Victor 2026-05-24) :
 *  - Google Analytics 4 : chargé UNIQUEMENT après consentement explicite (CNIL).
 *  - Cloudflare Web Analytics : sans cookie, ne trace pas l'individu → chargé pour
 *    TOUS les visiteurs sans consentement (donne le vrai total de fréquentation).
 *
 * Clés/ID : jamais en dur dans le thème (dépôt public). Résolus via constante
 * wp-config (priorité) ou option saisie dans Réglages → Général.
 *
 * @package VeloDisco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ID de mesure GA4 (ex. « G-XXXXXXXXXX »), ou '' si non configuré. */
function velodisco_ga4_id() {
	if ( defined( 'VELODISCO_GA4_ID' ) && VELODISCO_GA4_ID ) {
		return (string) VELODISCO_GA4_ID;
	}
	return trim( (string) get_option( 'velodisco_ga4_id', '' ) );
}

/** Jeton Cloudflare Web Analytics, ou '' si non configuré. */
function velodisco_cf_analytics_token() {
	if ( defined( 'VELODISCO_CF_ANALYTICS_TOKEN' ) && VELODISCO_CF_ANALYTICS_TOKEN ) {
		return (string) VELODISCO_CF_ANALYTICS_TOKEN;
	}
	return trim( (string) get_option( 'velodisco_cf_analytics_token', '' ) );
}

/* ----- Réglages admin (Réglages → Général) ------------------------------- */

function velodisco_register_analytics_settings() {
	$opts = array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
		'show_in_rest'      => false,
	);
	register_setting( 'general', 'velodisco_ga4_id', $opts );
	register_setting( 'general', 'velodisco_cf_analytics_token', $opts );

	add_settings_section(
		'velodisco_analytics',
		'Mesure d\'audience',
		'velodisco_analytics_section_intro',
		'general'
	);
	add_settings_field(
		'velodisco_ga4_id',
		'Google Analytics 4 — ID de mesure',
		'velodisco_field_ga4',
		'general',
		'velodisco_analytics'
	);
	add_settings_field(
		'velodisco_cf_analytics_token',
		'Cloudflare Web Analytics — jeton',
		'velodisco_field_cf_analytics',
		'general',
		'velodisco_analytics'
	);
}
add_action( 'admin_init', 'velodisco_register_analytics_settings' );

function velodisco_analytics_section_intro() {
	echo '<p>Google Analytics 4 ne se charge qu\'après acceptation du bandeau cookies (conformité CNIL) : laisser vide pour ne pas l\'activer. Cloudflare Web Analytics est sans cookie et compte tous les visiteurs (aucun consentement requis).</p>';
}

function velodisco_field_ga4() {
	printf(
		'<input type="text" class="regular-text" name="velodisco_ga4_id" value="%s" placeholder="G-XXXXXXXXXX" autocomplete="off" spellcheck="false">',
		esc_attr( get_option( 'velodisco_ga4_id', '' ) )
	);
	if ( defined( 'VELODISCO_GA4_ID' ) && VELODISCO_GA4_ID ) {
		echo '<p class="description">Une constante <code>VELODISCO_GA4_ID</code> est définie dans wp-config.php et a la priorité.</p>';
	}
}

function velodisco_field_cf_analytics() {
	printf(
		'<input type="text" class="regular-text" name="velodisco_cf_analytics_token" value="%s" placeholder="jeton du widget Cloudflare" autocomplete="off" spellcheck="false">',
		esc_attr( get_option( 'velodisco_cf_analytics_token', '' ) )
	);
	if ( defined( 'VELODISCO_CF_ANALYTICS_TOKEN' ) && VELODISCO_CF_ANALYTICS_TOKEN ) {
		echo '<p class="description">Une constante <code>VELODISCO_CF_ANALYTICS_TOKEN</code> est définie dans wp-config.php et a la priorité.</p>';
	}
}

/* ----- Sortie front-end -------------------------------------------------- */

/**
 * Imprime, en pied de page :
 *  1) le beacon Cloudflare (toujours, si configuré — sans cookie, sans consentement) ;
 *  2) le bandeau de consentement (si un ID GA4 est configuré). GA4 lui-même n'est
 *     chargé que par le JS, après acceptation.
 */
function velodisco_render_consent_and_analytics() {
	// 1) Cloudflare Web Analytics — sans cookie, pour tous les visiteurs.
	$cf = velodisco_cf_analytics_token();
	if ( '' !== $cf ) {
		$beacon = wp_json_encode( array( 'token' => $cf ) );
		printf(
			'<script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon="%s"></script>' . "\n",
			esc_attr( $beacon )
		);
	}

	// 2) Bandeau de consentement — uniquement s'il y a quelque chose à consentir (GA4).
	$ga4 = velodisco_ga4_id();
	if ( '' === $ga4 ) {
		return;
	}
	?>
	<div class="vd-consent" id="vd-consent" data-ga4="<?php echo esc_attr( $ga4 ); ?>" role="dialog" aria-label="Gestion des cookies" aria-live="polite" hidden>
		<div class="vd-consent__inner">
			<div class="vd-consent__text">
				<p class="vd-consent__title">On utilise des cookies</p>
				<p class="vd-consent__desc">Pour mesurer l'audience, améliorer le site et personnaliser votre expérience. Vous pouvez tout accepter, tout refuser, ou choisir au cas par cas.</p>
			</div>
			<div class="vd-consent__actions">
				<button type="button" class="vd-consent__btn vd-consent__btn--ghost" data-consent="custom">Personnaliser</button>
				<button type="button" class="vd-consent__btn vd-consent__btn--ghost" data-consent="deny">Tout refuser</button>
				<button type="button" class="vd-consent__btn vd-consent__btn--solid" data-consent="accept">Tout accepter</button>
			</div>
		</div>

		<div class="vd-consent__prefs" hidden>
			<label class="vd-consent__pref">
				<span class="vd-consent__pref-txt"><strong>Cookies nécessaires</strong><span>Indispensables au fonctionnement du site. Toujours actifs.</span></span>
				<input type="checkbox" checked disabled aria-label="Cookies nécessaires (toujours actifs)">
			</label>
			<label class="vd-consent__pref">
				<span class="vd-consent__pref-txt"><strong>Mesure d'audience</strong><span>Google Analytics, pour comprendre la fréquentation du site.</span></span>
				<input type="checkbox" id="vd-consent-analytics">
			</label>
			<div class="vd-consent__prefs-actions">
				<button type="button" class="vd-consent__btn vd-consent__btn--solid" data-consent="save">Enregistrer mes choix</button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'velodisco_render_consent_and_analytics' );
