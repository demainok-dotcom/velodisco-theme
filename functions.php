<?php
/**
 * VeloDisco — fonctions du thème.
 *
 * @package VeloDisco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Sécurité : pas d'accès direct.
}

if ( ! defined( 'VELODISCO_VERSION' ) ) {
	// Version lue dynamiquement dans l'en-tête de style.css (source unique de vérité)
	// → ne se désynchronise plus. Sert uniquement de repli au cache-busting des assets,
	// qui repose d'abord sur filemtime().
	$vd_theme = wp_get_theme();
	define( 'VELODISCO_VERSION', $vd_theme->exists() ? $vd_theme->get( 'Version' ) : '1.0.0' );
}

/**
 * Réglages du thème.
 * (La majorité des capacités sont déclarées dans theme.json ; on ajoute ici
 * les supports qui ne s'y trouvent pas.)
 */
function velodisco_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );

	// Styles de l'éditeur (mêmes règles que le front pour une vraie parité WYSIWYG).
	add_editor_style( 'assets/css/velodisco.css' );

	load_theme_textdomain( 'velodisco', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'velodisco_setup' );

/**
 * Styles et scripts du front-end.
 */
function velodisco_assets() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	// Feuille de style principale (base, dark mode, header/footer, animations).
	$css = '/assets/css/velodisco.css';
	wp_enqueue_style(
		'velodisco-main',
		$uri . $css,
		array(),
		file_exists( $dir . $css ) ? filemtime( $dir . $css ) : VELODISCO_VERSION
	);

	// Interface : bascule de thème (sombre/clair/système), menu burger mobile, popover recherche.
	$ui = '/assets/js/velodisco-ui.js';
	wp_enqueue_script(
		'velodisco-ui',
		$uri . $ui,
		array(),
		file_exists( $dir . $ui ) ? filemtime( $dir . $ui ) : VELODISCO_VERSION,
		true
	);
	// Variables runtime du JS d'interface :
	// - bikesUrl    : PNG des 10 vélos (animation du bouton RETOUR)
	// - footerFxUrl : MP3 + PNG des 4 paires son/icône (pastilles footer cliquables)
	wp_localize_script( 'velodisco-ui', 'VeloDiscoUI', array(
		'bikesUrl'    => $uri . '/assets/img/bikes/',
		'footerFxUrl' => $uri . '/assets/footer-fx/',
	) );

	// Animations au défilement (reveal progressif, respecte prefers-reduced-motion).
	$reveal = '/assets/js/reveal.js';
	wp_enqueue_script(
		'velodisco-reveal',
		$uri . $reveal,
		array(),
		file_exists( $dir . $reveal ) ? filemtime( $dir . $reveal ) : VELODISCO_VERSION,
		true
	);

	// Animations spécifiques aux articles de la catégorie Grands Formats
	// (timeline horizontale scroll-progressive + apparition cartes pionnières).
	// Le JS est chargé depuis le thème pour échapper au filtre WP wptexturize
	// qui altère les apostrophes/guillemets dans le contenu d'un post.
	if ( is_singular( 'post' ) && has_category( 'grands-formats' ) ) {
		$gf = '/assets/js/grands-formats.js';
		wp_enqueue_script(
			'velodisco-grands-formats',
			$uri . $gf,
			array(),
			file_exists( $dir . $gf ) ? filemtime( $dir . $gf ) : VELODISCO_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'velodisco_assets' );

/**
 * Script anti-flash : applique le thème (sombre/clair) sur <html> AVANT le
 * premier rendu, pour ne jamais afficher un flash de la mauvaise couleur.
 * Imprimé très tôt dans <head>, en amont du CSS.
 */
function velodisco_no_flash_script() {
	?>
<script>
(function () {
	try {
		var stored = localStorage.getItem('velodisco-theme'); // 'dark' | 'light' | 'system' | null
		var mode = stored || 'system';
		var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
		var resolved = (mode === 'system') ? (prefersDark ? 'dark' : 'light') : mode;
		var el = document.documentElement;
		el.setAttribute('data-theme', resolved);
		el.setAttribute('data-theme-mode', mode);
	} catch (e) {}
})();
</script>
	<?php
}
add_action( 'wp_head', 'velodisco_no_flash_script', 1 );

/**
 * Précharge la police principale (poids variable, sous-ensemble latin) pour
 * accélérer le premier rendu du texte.
 */
function velodisco_preload_font() {
	$uri = get_template_directory_uri();
	echo '<link rel="preload" href="' . esc_url( $uri . '/assets/fonts/inter-latin-wght-normal.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}
add_action( 'wp_head', 'velodisco_preload_font', 2 );

/**
 * Styles de blocs personnalisés.
 */
function velodisco_block_styles() {
	register_block_style( 'core/post-terms', array(
		'name'  => 'vd-tag',
		'label' => __( 'Étiquette VeloDisco', 'velodisco' ),
	) );
	register_block_style( 'core/image', array(
		'name'  => 'vd-stroke',
		'label' => __( 'Tracé catégorie', 'velodisco' ),
	) );
}
add_action( 'init', 'velodisco_block_styles' );

/**
 * Ajoute la couleur de catégorie sur les listes de termes (core/post-terms),
 * afin que l'étiquette d'un article prenne automatiquement la couleur de sa
 * catégorie (Vélos rouge, Composants orange, etc.).
 * On ajoute une classe vd-term--{slug} sur chaque lien de terme.
 */
function velodisco_colorize_post_terms( $block_content, $block ) {
	if ( empty( $block_content ) ) {
		return $block_content;
	}
	$block_content = preg_replace_callback(
		'/<a([^>]*?)href="[^"]*\/category\/([^\/"]+)\/?"/i',
		function ( $m ) {
			$slug = sanitize_html_class( $m[2] );
			if ( false !== strpos( $m[1], 'class="' ) ) {
				$attrs = preg_replace( '/class="/', 'class="vd-term vd-term--' . $slug . ' ', $m[1], 1 );
			} else {
				$attrs = $m[1] . ' class="vd-term vd-term--' . $slug . '"';
			}
			return '<a' . $attrs . 'href="' . esc_url( home_url( '/category/' . $slug . '/' ) ) . '"';
		},
		$block_content
	);
	return $block_content;
}
add_filter( 'render_block_core/post-terms', 'velodisco_colorize_post_terms', 10, 2 );

/**
 * Ajoute une classe vd-cat--{slug} sur le <body> des articles, d'après leur
 * catégorie principale, pour appliquer le fond dégradé subtil par catégorie
 * (Grands Formats = arc-en-ciel, Vélos = rouge→blanc, etc.).
 */
function velodisco_body_category_class( $classes ) {
	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			$classes[] = 'vd-cat--' . sanitize_html_class( $cats[0]->slug );
		}
	} elseif ( is_category() ) {
		// Pages de section : même fond dégradé par catégorie que l'article.
		$term = get_queried_object();
		if ( $term && ! empty( $term->slug ) ) {
			$classes[] = 'vd-cat--' . sanitize_html_class( $term->slug );
		}
	}
	return $classes;
}
add_filter( 'body_class', 'velodisco_body_category_class' );

/**
 * Compteur de vues maison (sans plugin) : incrémente la meta vd_views à chaque
 * lecture d'un article. Sert au tri « Tendances » (les plus populaires).
 * NB : si une page article est servie depuis le cache Varnish, le PHP ne tourne
 * pas → la vue n'est pas comptée. C'est un compteur « best effort », suffisant
 * pour ordonner les tendances.
 */
function velodisco_count_view() {
	// On ne compte pas : back-office et utilisateurs connectés (previews, rédaction).
	if ( ! is_singular( 'post' ) || is_admin() || is_user_logged_in() ) {
		return;
	}
	$id = get_queried_object_id();
	if ( ! $id ) {
		return;
	}
	// Garde anti-amplification : au plus 1 écriture par article et par minute, quelle
	// que soit la provenance. Sans cela, un acteur malveillant qui martèle l'URL d'un
	// article avec un paramètre bidon (?x=123) contourne le cache Varnish et provoque
	// une écriture BDD à CHAQUE requête. Ici les écritures sont bornées ; le compteur
	// reste « best effort », ce qui suffit largement à ordonner les Tendances.
	$lock = 'vd_vlock_' . $id;
	if ( get_transient( $lock ) ) {
		return;
	}
	set_transient( $lock, 1, MINUTE_IN_SECONDS );

	$views = (int) get_post_meta( $id, 'vd_views', true );
	update_post_meta( $id, 'vd_views', $views + 1 );
}
add_action( 'template_redirect', 'velodisco_count_view' );

/**
 * Meilleure estimation de l'IP cliente. Derrière Varnish/Nginx, REMOTE_ADDR peut être
 * l'IP du proxy ; on lit alors le 1er maillon de X-Forwarded-For. Utilisé pour limiter
 * le débit du formulaire de contact.
 * NB : X-Forwarded-For est falsifiable → défense de premier niveau (stoppe le flood
 * basique), à compléter par un CAPTCHA si le spam ciblé devient un problème.
 *
 * @return string IP valide, ou '0.0.0.0' en dernier recours.
 */
function velodisco_client_ip() {
	$candidates = array();
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$xff          = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		$candidates[] = trim( $xff[0] );
	}
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	foreach ( $candidates as $cand ) {
		$valid = filter_var( $cand, FILTER_VALIDATE_IP );
		if ( $valid ) {
			return $valid;
		}
	}
	return '0.0.0.0';
}

/* ------------------------------------------------------------------------- *
 * Anti-spam Cloudflare Turnstile — résolution des clés + réglages admin.
 *
 * Les clés peuvent venir de DEUX sources, par ordre de priorité :
 *   1) constantes wp-config (VELODISCO_TURNSTILE_SITEKEY / _SECRET) — voie « pro » ;
 *   2) options WordPress saisies dans Réglages → Général — voie simple, sans fichier.
 * La clé secrète n'est jamais imprimée côté public ; seuls les admins la voient.
 * ------------------------------------------------------------------------- */

/** Clé de site (publique) Turnstile, ou '' si non configurée. */
function velodisco_turnstile_sitekey() {
	if ( defined( 'VELODISCO_TURNSTILE_SITEKEY' ) && VELODISCO_TURNSTILE_SITEKEY ) {
		return (string) VELODISCO_TURNSTILE_SITEKEY;
	}
	return trim( (string) get_option( 'velodisco_turnstile_sitekey', '' ) );
}

/** Clé secrète (serveur) Turnstile, ou '' si non configurée. */
function velodisco_turnstile_secret() {
	if ( defined( 'VELODISCO_TURNSTILE_SECRET' ) && VELODISCO_TURNSTILE_SECRET ) {
		return (string) VELODISCO_TURNSTILE_SECRET;
	}
	return trim( (string) get_option( 'velodisco_turnstile_secret', '' ) );
}

/**
 * Enregistre 2 champs (clé site + clé secrète) dans Réglages → Général.
 * Stockés en options, sanitisés ; visibles uniquement par un admin (manage_options).
 */
function velodisco_register_turnstile_settings() {
	$opts = array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
		'show_in_rest'      => false,
	);
	register_setting( 'general', 'velodisco_turnstile_sitekey', $opts );
	register_setting( 'general', 'velodisco_turnstile_secret', $opts );

	add_settings_section(
		'velodisco_turnstile',
		'Anti-spam Cloudflare Turnstile (formulaire de contact)',
		'velodisco_turnstile_section_intro',
		'general'
	);
	add_settings_field(
		'velodisco_turnstile_sitekey',
		'Turnstile — Clé de site',
		'velodisco_turnstile_field_sitekey',
		'general',
		'velodisco_turnstile'
	);
	add_settings_field(
		'velodisco_turnstile_secret',
		'Turnstile — Clé secrète',
		'velodisco_turnstile_field_secret',
		'general',
		'velodisco_turnstile'
	);
}
add_action( 'admin_init', 'velodisco_register_turnstile_settings' );

/** Texte d'intro de la section de réglages Turnstile. */
function velodisco_turnstile_section_intro() {
	echo '<p>Collez vos clés Cloudflare Turnstile (gratuit, sur dash.cloudflare.com → Turnstile) pour activer la protection anti-robot du formulaire de contact. Laissez les deux champs vides pour la désactiver. La protection ne s\'active que si les <strong>deux</strong> clés sont renseignées.</p>';
}

/** Champ « clé de site » (publique). */
function velodisco_turnstile_field_sitekey() {
	printf(
		'<input type="text" class="regular-text" name="velodisco_turnstile_sitekey" value="%s" autocomplete="off" spellcheck="false" placeholder="0x4AAAAAAA...">',
		esc_attr( get_option( 'velodisco_turnstile_sitekey', '' ) )
	);
	if ( defined( 'VELODISCO_TURNSTILE_SITEKEY' ) && VELODISCO_TURNSTILE_SITEKEY ) {
		echo '<p class="description">Une constante <code>VELODISCO_TURNSTILE_SITEKEY</code> est définie dans wp-config.php et a la priorité sur ce champ.</p>';
	}
}

/** Champ « clé secrète » (serveur). */
function velodisco_turnstile_field_secret() {
	printf(
		'<input type="text" class="regular-text" name="velodisco_turnstile_secret" value="%s" autocomplete="off" spellcheck="false" placeholder="0x4AAAAAAA...">',
		esc_attr( get_option( 'velodisco_turnstile_secret', '' ) )
	);
	if ( defined( 'VELODISCO_TURNSTILE_SECRET' ) && VELODISCO_TURNSTILE_SECRET ) {
		echo '<p class="description">Une constante <code>VELODISCO_TURNSTILE_SECRET</code> est définie dans wp-config.php et a la priorité sur ce champ.</p>';
	}
}

/**
 * Moteur de la page d'accueil (bloc dynamique velodisco/home).
 */
require_once get_template_directory() . '/inc/home.php';

/**
 * Flux de la Page Actu (bloc dynamique velodisco/actu).
 * Chargé après home.php : réutilise ses helpers de cartes.
 */
require_once get_template_directory() . '/inc/actu.php';

/**
 * Pages de section / archives de catégorie (bloc dynamique velodisco/section).
 * Chargé après home.php : réutilise ses helpers de cartes.
 */
require_once get_template_directory() . '/inc/section.php';

/**
 * Page de recherche (bloc dynamique velodisco/search).
 * Chargé après home.php : réutilise ses helpers de cartes.
 */
require_once get_template_directory() . '/inc/search.php';

/**
 * Page de contact (bloc dynamique velodisco/contact).
 * Formulaire autonome : validation + wp_mail() + anti-spam.
 */
require_once get_template_directory() . '/inc/contact.php';

/**
 * Mesure d'audience + bandeau de consentement cookies (GA4 consenti + Cloudflare).
 */
require_once get_template_directory() . '/inc/consent.php';
