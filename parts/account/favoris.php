<?php
/**
 * Mon Compte — Mes recettes favorites.
 *
 * Grille des recettes favorites du user via table wp_user_favorites.
 * Empty state avec CTA "Explorer les recettes".
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$user_id    = get_current_user_id();
$recipe_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"SELECT recipe_id FROM {$wpdb->prefix}user_favorites WHERE user_id = %d ORDER BY created_at DESC",
		$user_id
	)
);
?>

<div class="account-favoris">

	<h1 class="account-favoris__title"><?php esc_html_e( 'Mes recettes favorites', '180c' ); ?></h1>

	<?php if ( ! empty( $recipe_ids ) ) : ?>

		<ul class="account-favoris__grid" role="list">
			<?php foreach ( $recipe_ids as $recipe_id ) : ?>
				<?php
				// $fav_post et non $post : get_template_part() passe par load_template(),
				// qui déclare `global $post`. Assigner $post ici écraserait durablement
				// la globale — après ce partial, tout ce qui la lit (footer, widgets,
				// get_the_ID()) verrait la dernière recette favorite au lieu du contenu
				// de la page. Même raison pour $title.
				$recipe_id = absint( $recipe_id );
				$fav_post  = get_post( $recipe_id );

				if ( ! $fav_post || 'publish' !== $fav_post->post_status ) {
					continue;
				}

				$permalink     = get_permalink( $fav_post );
				$fav_title     = get_the_title( $fav_post );
				$thumbnail_url = get_the_post_thumbnail_url( $fav_post, 'medium' );
				?>
				<li class="account-favoris__item card-recipe">
					<a href="<?php echo esc_url( $permalink ); ?>" class="card-recipe__link">
						<?php if ( $thumbnail_url ) : ?>
							<div class="card-recipe__thumb">
								<img
									src="<?php echo esc_url( $thumbnail_url ); ?>"
									alt="<?php echo esc_attr( $fav_title ); ?>"
									loading="lazy"
									width="300"
									height="200"
									class="card-recipe__img"
								/>
							</div>
						<?php endif; ?>
						<div class="card-recipe__body">
							<h2 class="card-recipe__title"><?php echo esc_html( $fav_title ); ?></h2>
						</div>
					</a>

					<?php /* Bouton retirer des favoris — action REST Phase 6 */ ?>
					<button
						type="button"
						class="btn btn--ghost btn--sm account-favoris__remove"
						data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>"
						aria-label="<?php echo esc_attr( sprintf( __( 'Retirer %s des favoris', '180c' ), $fav_title ) ); ?>"
					>
						<?php esc_html_e( 'Retirer', '180c' ); ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php else : ?>

		<div class="account-favoris__empty">
			<p><?php esc_html_e( 'Vous n\'avez pas encore de recette favorite.', '180c' ); ?></p>
			<p><?php esc_html_e( 'Ajoutez des recettes à vos favoris en cliquant sur le cœur depuis n\'importe quelle fiche recette.', '180c' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/recettes/' ) ); ?>" class="btn btn--primary">
				<?php esc_html_e( 'Explorer les recettes', '180c' ); ?>
			</a>
		</div>

	<?php endif; ?>

</div>
