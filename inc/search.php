<?php
/**
 * VeloDisco — bloc dynamique « velodisco/search ».
 *
 * Page de résultats de recherche : champ de recherche (prérempli), en-tête
 * « Résultats pour … », grille 3 colonnes des résultats (requête principale,
 * pagination native), message « Aucun résultat » si vide.
 * Réutilise les helpers de cartes de inc/home.php et le CSS de la Page Actu.
 *
 * @package VeloDisco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function velodisco_render_search( $attrs = array(), $content = '' ) {
	$q = get_search_query();

	global $wp_query;
	$total_pages = (int) $wp_query->max_num_pages;
	$paged       = max( 1, (int) get_query_var( 'paged' ) );

	$icon = '<svg class="vd-search__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.5"/><line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

	ob_start();
	?>
	<section class="vd-actu vd-search">

		<form class="vd-search__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Rechercher un article…" aria-label="Rechercher">
		</form>

		<p class="vd-search__head">Résultats pour «&nbsp;<?php echo esc_html( $q ); ?>&nbsp;»</p>

		<?php if ( have_posts() ) : ?>
			<div class="vd-actu__grid">
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
					<nav class="vd-actu__pagination" aria-label="Pagination des résultats">
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
			<div class="vd-search__empty">
				<p class="vd-search__empty-big">Rien trouvé</p>
				<span class="vd-search__empty-img" role="img" aria-label="Dessin d'un vélo"></span>
				<p class="vd-search__empty-sub">Aucun article ne correspond à votre recherche</p>
				<a class="vd-search__empty-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Retour à l'accueil</a>
			</div>
		<?php endif; ?>

	</section>
	<?php
	return ob_get_clean();
}

/** Enregistre le bloc dynamique velodisco/search. */
function velodisco_register_search_block() {
	register_block_type( 'velodisco/search', array(
		'api_version'     => 3,
		'render_callback' => 'velodisco_render_search',
	) );
}
add_action( 'init', 'velodisco_register_search_block' );
