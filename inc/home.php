<?php
/**
 * VeloDisco — Home (front page).
 * Bloc dynamique rendu côté serveur : 3 colonnes (Tout frais / centre À la une +
 * Derniers articles / Tendances) + section Grands Formats pleine largeur.
 * Relevé pixel depuis le Figma « Home - Ordi ».
 *
 * @package VeloDisco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ----- Helpers ---------------------------------------------------------- */

/** Catégorie principale d'un article (1er terme). */
function vd_primary_cat( $id ) {
	$cats = get_the_category( $id );
	return ! empty( $cats ) ? $cats[0] : null;
}

/** Étiquette de catégorie colorée (réutilise les classes vd-term--{slug}). */
function vd_tag_html( $id ) {
	$c = vd_primary_cat( $id );
	if ( ! $c ) {
		return '';
	}
	$slug = sanitize_html_class( $c->slug );
	// Affichage : tirets → espaces (ex. catégorie nommée « grands-formats » → « GRANDS FORMATS »).
	$label = strtoupper( str_replace( '-', ' ', $c->name ) );
	return '<a class="vd-tag vd-term vd-term--' . $slug . '" href="' . esc_url( get_category_link( $c ) ) . '">' . esc_html( $label ) . '</a>';
}

/** Image mise en avant, liée, avec tracé catégorie 0.5px. */
function vd_thumb_html( $id, $imgclass ) {
	$c    = vd_primary_cat( $id );
	$slug = $c ? sanitize_html_class( $c->slug ) : '';
	$href = esc_url( get_permalink( $id ) );
	$stroke = $slug ? ' vd-img-stroke vd-img-stroke--' . $slug : '';
	$inner = has_post_thumbnail( $id )
		? get_the_post_thumbnail( $id, 'large', array( 'loading' => 'lazy', 'alt' => '', 'class' => 'vd-thumb__img' ) )
		: '<span class="vd-thumb__ph" aria-hidden="true"></span>';
	return '<a class="' . esc_attr( $imgclass . ' vd-hover-zoom' . $stroke ) . '" href="' . $href . '" aria-label="' . esc_attr( get_the_title( $id ) ) . '">' . $inner . '</a>';
}

/** Date « 26 MARS 2026 ». */
function vd_date_html( $id ) {
	return '<span class="vd-card__date">' . esc_html( get_the_date( 'j F Y', $id ) ) . '</span>';
}

/** Temps relatif court : « 6H », « 2J ». */
function vd_reltime_html( $id ) {
	$diff = time() - get_post_time( 'U', true, $id );
	if ( $diff < 0 ) { $diff = 0; }
	$h = floor( $diff / 3600 );
	if ( $h < 1 ) {
		$txt = max( 1, floor( $diff / 60 ) ) . 'MIN';
	} elseif ( $h < 24 ) {
		$txt = $h . 'H';
	} else {
		$txt = floor( $h / 24 ) . 'J';
	}
	return '<span class="vd-frais__time">' . esc_html( $txt ) . '</span>';
}

/** Lien titre en serif. */
function vd_title_html( $id, $class = '' ) {
	return '<a class="vd-serif ' . esc_attr( $class ) . '" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
}

/** Extrait COMPLET (plus de troncature : on affiche tout l'extrait saisi).
 * Le 2e paramètre est conservé pour compat. mais n'est plus utilisé. */
function vd_excerpt_html( $id, $words = 0 ) {
	$ex = trim( wp_strip_all_tags( get_the_excerpt( $id ) ) );
	return '<p class="vd-card__excerpt">' . esc_html( $ex ) . '</p>';
}

/** Requête → IDs, en excluant ceux déjà utilisés (passés par référence). */
function vd_query_ids( $args, &$used ) {
	$defaults = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => 1,
		'no_found_rows'       => true,
		'fields'              => 'ids',
	);
	$args = array_merge( $defaults, $args );
	if ( ! empty( $used ) ) {
		$args['post__not_in'] = $used;
	}
	$q   = new WP_Query( $args );
	$ids = $q->posts;
	$used = array_merge( $used, $ids );
	return $ids;
}

/* ----- Rendu du bloc Home ----------------------------------------------- */

function velodisco_render_home() {
	$used = array();

	// À LA UNE = article épinglé (sinon le plus récent).
	$aune     = 0;
	$stickies = get_option( 'sticky_posts' );
	if ( ! empty( $stickies ) ) {
		$ids = vd_query_ids( array( 'post__in' => $stickies, 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC' ), $used );
		$aune = ! empty( $ids ) ? $ids[0] : 0;
	}
	if ( ! $aune ) {
		$ids  = vd_query_ids( array( 'posts_per_page' => 1 ), $used );
		$aune = ! empty( $ids ) ? $ids[0] : 0;
	}

	// DERNIERS ARTICLES = 5 récents (desktop n'en montre que 4 en grille 2×2 ;
	// le 5e n'apparaît qu'en mobile, où la section est une pile de cartes).
	$derniers = vd_query_ids( array( 'posts_per_page' => 5 ), $used );

	// TENDANCES = les plus vus (compteur maison) ; complété par des récents.
	$tend = vd_query_ids( array( 'posts_per_page' => 3, 'meta_key' => 'vd_views', 'orderby' => 'meta_value_num', 'order' => 'DESC' ), $used );
	if ( count( $tend ) < 3 ) {
		$tend = array_merge( $tend, vd_query_ids( array( 'posts_per_page' => 3 - count( $tend ) ), $used ) );
	}

	// TOUT FRAIS = TOUS les articles récents (indépendant : on ne retire pas ceux
	// déjà affichés en À la une / Derniers / Tendances → liste complète comme au Figma).
	$usedfrais = array();
	$frais     = vd_query_ids( array( 'posts_per_page' => -1, 'no_found_rows' => false ), $usedfrais );

	// GRANDS FORMATS = catégorie dédiée (indépendant).
	$usedgf = array();
	$gf     = vd_query_ids( array( 'posts_per_page' => 4, 'category_name' => 'grands-formats' ), $usedgf );

	ob_start();
	?>
	<div class="vd-home">

		<!-- Colonne gauche : TOUT FRAIS -->
		<aside class="vd-col vd-col--frais">
			<h2 class="vd-sectitle">Tout frais</h2>
			<ul class="vd-frais__list">
				<?php foreach ( $frais as $id ) : ?>
					<li class="vd-frais__item">
						<?php echo vd_reltime_html( $id ); ?>
						<?php echo vd_title_html( $id, 'vd-frais__title' ); ?>
					</li>
				<?php endforeach; ?>
				<?php if ( empty( $frais ) ) : ?>
					<li class="vd-empty">Bientôt des articles ici.</li>
				<?php endif; ?>
			</ul>
		</aside>

		<!-- Colonne centre : À LA UNE + DERNIERS ARTICLES -->
		<div class="vd-col vd-col--center">
			<?php if ( $aune ) : ?>
				<section class="vd-aune">
					<h2 class="vd-sectitle">À la une</h2>
					<?php echo vd_tag_html( $aune ); ?>
					<?php echo vd_title_html( $aune, 'vd-aune__title' ); ?>
					<?php echo vd_thumb_html( $aune, 'vd-aune__img' ); ?>
					<div class="vd-aune__foot">
						<?php echo vd_excerpt_html( $aune, 28 ); ?>
						<?php echo vd_date_html( $aune ); ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="vd-derniers">
				<h2 class="vd-sectitle">Derniers articles</h2>
				<div class="vd-derniers__grid">
					<?php foreach ( $derniers as $id ) : ?>
						<article class="vd-card">
							<?php echo vd_thumb_html( $id, 'vd-card__img' ); ?>
							<?php echo vd_tag_html( $id ); ?>
							<?php echo vd_title_html( $id, 'vd-card__title' ); ?>
							<?php echo vd_excerpt_html( $id, 16 ); ?>
							<?php echo vd_date_html( $id ); ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		</div>

		<!-- Colonne droite : TENDANCES -->
		<aside class="vd-col vd-col--tend">
			<h2 class="vd-sectitle">Tendances</h2>
			<?php foreach ( $tend as $id ) : ?>
				<article class="vd-tcard">
					<?php echo vd_thumb_html( $id, 'vd-tcard__img' ); ?>
					<?php echo vd_tag_html( $id ); ?>
					<?php echo vd_title_html( $id, 'vd-tcard__title' ); ?>
					<?php echo vd_excerpt_html( $id, 18 ); ?>
					<?php echo vd_date_html( $id ); ?>
				</article>
			<?php endforeach; ?>
		</aside>

	</div>

	<!-- GRANDS FORMATS (pleine largeur) -->
	<section class="vd-gf">
		<div class="vd-gf__head">
			<h2 class="vd-sectitle vd-gradient-text">Grands Formats</h2>
			<p class="vd-gf__sub">C'est parfois mieux quand c'est plus long.</p>
			<a class="vd-gf__all vd-gradient-text" href="/category/grands-formats/">Voir tout →</a>
		</div>
		<div class="vd-gf__grid">
			<?php foreach ( $gf as $id ) : ?>
				<article class="vd-gfcard">
					<?php echo vd_thumb_html( $id, 'vd-gfcard__img' ); ?>
					<span class="vd-tag vd-term--grands-formats">GRANDS FORMATS</span>
					<?php echo vd_title_html( $id, 'vd-gfcard__title' ); ?>
					<?php echo vd_excerpt_html( $id, 16 ); ?>
					<?php echo vd_date_html( $id ); ?>
				</article>
			<?php endforeach; ?>
			<?php if ( empty( $gf ) ) : ?>
				<p class="vd-empty">Aucun « Grand Format » publié pour l'instant (catégorie « grands-formats »).</p>
			<?php endif; ?>
		</div>
	</section>

	<!-- Lien Mentions légales — affiché uniquement en mobile (cf. Figma Home Mobile) -->
	<a class="vd-home-mentions" href="/mentions-legales/">Mentions légales</a>
	<?php
	return ob_get_clean();
}

/** Enregistre le bloc dynamique velodisco/home. */
function velodisco_register_home_block() {
	register_block_type( 'velodisco/home', array(
		'api_version'     => 3,
		'render_callback' => 'velodisco_render_home',
	) );
}
add_action( 'init', 'velodisco_register_home_block' );
