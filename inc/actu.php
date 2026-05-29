<?php
/**
 * VeloDisco — bloc dynamique « velodisco/actu ».
 *
 * Flux de la Page Actu (/actu/) : les derniers articles, toutes catégories
 * confondues, en grille 3 colonnes, paginé. Réutilise les helpers de cartes
 * définis dans inc/home.php (vd_thumb_html / vd_tag_html / vd_title_html /
 * vd_excerpt_html / vd_date_html) pour des cartes identiques à la Home.
 *
 * @package VeloDisco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendu du flux Actu.
 *
 * Pagination via le paramètre ?ap=N : une Page WordPress ne pagine pas sa
 * requête principale (et /actu/page/2/ n'est pas une URL native pour une Page),
 * on gère donc une requête secondaire avec notre propre variable.
 */
function velodisco_render_actu( $attrs = array(), $content = '' ) {
	$paged = isset( $_GET['ap'] ) ? max( 1, (int) $_GET['ap'] ) : 1;
	return vd_render_cached( 'actu_p' . $paged, function () use ( $paged ) {
		return velodisco_render_actu_inner( $paged );
	} );
}
function velodisco_render_actu_inner( $paged ) {
	$per = 15; // 3 colonnes × 5 rangées (cf. Figma « Page Actu - Ordi »).

	$q = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $per,
		'paged'               => $paged,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
	) );

	ob_start();
	?>
	<h1 class="vd-sr-only">Actu — Tout frais sur VeloDisco</h1>
	<section class="vd-actu">

		<div class="vd-actu__head">
			<span class="vd-actu__crumb">Actu</span>
			<span class="vd-actu__tab">Tout frais</span>
			<span class="vd-actu__tab vd-actu__tab--right">Derniers articles</span>
			<span class="vd-actu__m-right">Tout est là</span>
		</div>

		<?php if ( $q->have_posts() ) : ?>

			<div class="vd-actu__grid">
				<?php
				while ( $q->have_posts() ) :
					$q->the_post();
					$id = get_the_ID();
					?>
					<article class="vd-card">
						<?php
						echo vd_thumb_html( $id, 'vd-card__img' ); // phpcs:ignore WordPress.Security.EscapeOutput
						echo vd_tag_html( $id );                   // phpcs:ignore WordPress.Security.EscapeOutput
						echo vd_title_html( $id, 'vd-card__title' ); // phpcs:ignore WordPress.Security.EscapeOutput
						echo vd_excerpt_html( $id );               // phpcs:ignore WordPress.Security.EscapeOutput
						echo vd_date_html( $id );                  // phpcs:ignore WordPress.Security.EscapeOutput
						?>
					</article>
					<?php
				endwhile;
				?>
			</div>

			<?php
			$total = (int) $q->max_num_pages;
			if ( $total > 1 ) :
				$base = remove_query_arg( 'ap' );
				?>
				<nav class="vd-actu__pagination" aria-label="Pagination des articles">
					<?php
					// Pagination FENÊTRÉE : on n'affiche que les 1res pages, les dernières
					// et une fenêtre autour de la page courante, avec « … » pour combler.
					// Sans cela, le DOM contiendrait un lien par page (illimité à terme).
					$window     = 2;
					$last_shown = 0;
					for ( $i = 1; $i <= $total; $i++ ) :
						$in_window = ( $i <= 1 + $window ) || ( $i > $total - $window ) || ( abs( $i - $paged ) <= $window );
						if ( ! $in_window ) {
							continue;
						}
						if ( $last_shown && $i > $last_shown + 1 ) :
							?><span class="vd-page vd-page--gap" aria-hidden="true">…</span><?php
						endif;
						$url = ( 1 === $i ) ? $base : add_query_arg( 'ap', $i, $base );
						$cls = 'vd-page' . ( $i === $paged ? ' is-active' : '' );
						?>
						<a class="<?php echo esc_attr( $cls ); ?>"<?php echo ( $i === $paged ) ? ' aria-current="page"' : ''; ?> href="<?php echo esc_url( $url ); ?>"><?php echo (int) $i; ?></a>
						<?php
						$last_shown = $i;
					endfor;
					if ( $paged < $total ) :
						?>
						<a class="vd-page vd-page--next" href="<?php echo esc_url( add_query_arg( 'ap', $paged + 1, $base ) ); ?>" aria-label="Page suivante">&rarr;</a>
						<?php
					endif;
					?>
				</nav>
			<?php endif; ?>

		<?php else : ?>
			<p class="vd-empty">Aucun article pour le moment.</p>
		<?php endif; ?>

		<?php wp_reset_postdata(); ?>

	</section>
	<?php
	return ob_get_clean();
}

/** Enregistre le bloc dynamique velodisco/actu. */
function velodisco_register_actu_block() {
	register_block_type( 'velodisco/actu', array(
		'api_version'     => 3,
		'render_callback' => 'velodisco_render_actu',
	) );
}
add_action( 'init', 'velodisco_register_actu_block' );
