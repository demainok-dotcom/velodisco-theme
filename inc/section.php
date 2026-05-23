<?php
/**
 * VeloDisco — bloc dynamique « velodisco/section ».
 *
 * Page de section = archive d'une catégorie (Vélos, Accessoires, Composants,
 * Vêtements, Société). Reprend la grille 3 colonnes de la Home, filtrée sur la
 * catégorie : TOUT FRAIS (récents) + DERNIERS ARTICLES (requête principale,
 * paginée) + TENDANCES (plus vus) + teaser GRANDS FORMATS (site-wide).
 * Réutilise les helpers de cartes de inc/home.php.
 *
 * @package VeloDisco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function velodisco_render_section( $attrs = array(), $content = '' ) {
	$term = get_queried_object();
	if ( ! $term || empty( $term->term_id ) ) {
		return '';
	}
	$cat_id = (int) $term->term_id;
	$slug   = sanitize_html_class( $term->slug );
	$name   = strtoupper( str_replace( '-', ' ', $term->name ) );

	// TOUT FRAIS : tous les récents de la catégorie (titres + temps relatif).
	$used  = array();
	$frais = vd_query_ids( array( 'cat' => $cat_id, 'posts_per_page' => -1 ), $used );

	// TENDANCES : les plus vus de la catégorie, complétés par des récents.
	$tused = array();
	$tend  = vd_query_ids( array( 'cat' => $cat_id, 'posts_per_page' => 3, 'meta_key' => 'vd_views', 'orderby' => 'meta_value_num', 'order' => 'DESC' ), $tused );
	if ( count( $tend ) < 3 ) {
		$tend = array_merge( $tend, vd_query_ids( array( 'cat' => $cat_id, 'posts_per_page' => 3 - count( $tend ) ), $tused ) );
	}

	// GRANDS FORMATS : teaser site-wide (catégorie grands-formats), comme la Home.
	$gfused = array();
	$gf     = vd_query_ids( array( 'category_name' => 'grands-formats', 'posts_per_page' => 4 ), $gfused );

	global $wp_query;
	$total_pages = (int) $wp_query->max_num_pages;
	$paged       = max( 1, (int) get_query_var( 'paged' ) );

	ob_start();
	?>
	<div class="vd-home vd-section">

		<!-- Colonne gauche : TOUT FRAIS (récents de la catégorie) -->
		<aside class="vd-col vd-col--frais">
			<h2 class="vd-sectitle">Tout frais</h2>
			<ul class="vd-frais__list">
				<?php foreach ( $frais as $id ) : ?>
					<li class="vd-frais__item">
						<?php echo vd_reltime_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php echo vd_title_html( $id, 'vd-frais__title' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</li>
				<?php endforeach; ?>
				<?php if ( empty( $frais ) ) : ?>
					<li class="vd-empty">Bientôt des articles ici.</li>
				<?php endif; ?>
			</ul>
		</aside>

		<!-- Colonne centre : nom de section + DERNIERS ARTICLES (requête principale, paginée) -->
		<div class="vd-col vd-col--center">
			<div class="vd-section__head">
				<span class="vd-section__name vd-term vd-term--<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></span>
				<span class="vd-section__label">Derniers articles</span>
			</div>

			<?php if ( have_posts() ) : ?>
				<div class="vd-section__grid">
					<?php
					while ( have_posts() ) :
						the_post();
						$id = get_the_ID();
						?>
						<article class="vd-card">
							<?php
							echo vd_thumb_html( $id, 'vd-card__img' );   // phpcs:ignore WordPress.Security.EscapeOutput
							echo vd_tag_html( $id );                     // phpcs:ignore WordPress.Security.EscapeOutput
							echo vd_title_html( $id, 'vd-card__title' ); // phpcs:ignore WordPress.Security.EscapeOutput
							echo vd_excerpt_html( $id );                 // phpcs:ignore WordPress.Security.EscapeOutput
							echo vd_date_html( $id );                    // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						</article>
						<?php
					endwhile;
					?>
				</div>

				<?php
				if ( $total_pages > 1 ) :
					$pages = paginate_links( array(
						'total'     => $total_pages,
						'current'   => $paged,
						'type'      => 'array',
						'prev_next' => true,
						'prev_text' => '&larr;',
						'next_text' => '&rarr;',
						'mid_size'  => 2,
						'end_size'  => 1,
					) );
					if ( $pages ) :
						?>
						<nav class="vd-actu__pagination vd-section__pagination" aria-label="Pagination des articles">
							<?php
							foreach ( $pages as $p ) {
								$p = str_replace( 'page-numbers current', 'vd-page is-active', $p );
								$p = str_replace( 'page-numbers', 'vd-page', $p );
								echo $p; // phpcs:ignore WordPress.Security.EscapeOutput
							}
							?>
						</nav>
						<?php
					endif;
				endif;
				?>
			<?php else : ?>
				<p class="vd-empty">Aucun article dans cette catégorie pour le moment.</p>
			<?php endif; ?>
		</div>

		<!-- Colonne droite : TENDANCES (plus vus de la catégorie) -->
		<aside class="vd-col vd-col--tend">
			<h2 class="vd-sectitle">Tendances</h2>
			<?php foreach ( $tend as $id ) : ?>
				<article class="vd-tcard">
					<?php
					echo vd_thumb_html( $id, 'vd-tcard__img' );    // phpcs:ignore WordPress.Security.EscapeOutput
					echo vd_tag_html( $id );                       // phpcs:ignore WordPress.Security.EscapeOutput
					echo vd_title_html( $id, 'vd-tcard__title' );  // phpcs:ignore WordPress.Security.EscapeOutput
					echo vd_excerpt_html( $id );                   // phpcs:ignore WordPress.Security.EscapeOutput
					echo vd_date_html( $id );                      // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</article>
			<?php endforeach; ?>
		</aside>

	</div>

	<!-- GRANDS FORMATS (teaser pleine largeur, site-wide) -->
	<section class="vd-gf">
		<div class="vd-gf__head">
			<h2 class="vd-sectitle vd-gradient-text">Grands Formats</h2>
			<p class="vd-gf__sub">C'est parfois mieux quand c'est plus long.</p>
			<a class="vd-gf__all vd-gradient-text" href="/category/grands-formats/">Voir tout →</a>
		</div>
		<div class="vd-gf__grid">
			<?php foreach ( $gf as $id ) : ?>
				<article class="vd-gfcard">
					<?php
					echo vd_thumb_html( $id, 'vd-gfcard__img' );   // phpcs:ignore WordPress.Security.EscapeOutput
					?>
					<span class="vd-tag vd-term--grands-formats">GRANDS FORMATS</span>
					<?php
					echo vd_title_html( $id, 'vd-gfcard__title' ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo vd_excerpt_html( $id );                   // phpcs:ignore WordPress.Security.EscapeOutput
					echo vd_date_html( $id );                      // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</article>
			<?php endforeach; ?>
			<?php if ( empty( $gf ) ) : ?>
				<p class="vd-empty">Aucun « Grand Format » publié pour l'instant.</p>
			<?php endif; ?>
		</div>
	</section>
	<?php
	wp_reset_postdata();
	return ob_get_clean();
}

/** Enregistre le bloc dynamique velodisco/section. */
function velodisco_register_section_block() {
	register_block_type( 'velodisco/section', array(
		'api_version'     => 3,
		'render_callback' => 'velodisco_render_section',
	) );
}
add_action( 'init', 'velodisco_register_section_block' );
