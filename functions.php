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
	define( 'VELODISCO_VERSION', '0.2.0' );
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

	// Animations au défilement (reveal progressif, respecte prefers-reduced-motion).
	$reveal = '/assets/js/reveal.js';
	wp_enqueue_script(
		'velodisco-reveal',
		$uri . $reveal,
		array(),
		file_exists( $dir . $reveal ) ? filemtime( $dir . $reveal ) : VELODISCO_VERSION,
		true
	);
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
			return '<a' . $attrs . 'href="/category/' . $slug . '/"';
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
	if ( is_singular( 'post' ) && ! is_admin() ) {
		$id = get_queried_object_id();
		if ( $id ) {
			$v = (int) get_post_meta( $id, 'vd_views', true );
			update_post_meta( $id, 'vd_views', $v + 1 );
		}
	}
}
add_action( 'template_redirect', 'velodisco_count_view' );

/**
 * Moteur de la page d'accueil (bloc dynamique velodisco/home).
 */
require_once get_template_directory() . '/inc/home.php';

/**
 * Flux de la Page Actu (bloc dynamique velodisco/actu).
 * Chargé après home.php : réutilise ses helpers de cartes.
 */
require_once get_template_directory() . '/inc/actu.php';
