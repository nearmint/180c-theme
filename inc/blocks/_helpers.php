<?php
/**
 * Helpers partagés pour les blocs Gutenberg 180°C.
 *
 * Inclus via require_once dans chaque render.php qui en a besoin.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retourne le HTML d'une card article (post).
 *
 * Tolérante aux deux signatures historiques :
 *  - WP_Post (appelants historiques : articles-rail bloc + part)
 *  - int + variante de taille (appelants : page auteur, wrapper public)
 *
 * Variantes (alignées sur _180c_block_render_recipe_card) :
 *  - 'sm' : rails / grilles denses
 *  - 'md' : grille standard (défaut, rétro-compatible)
 *  - 'lg' : mise en avant
 *
 * Rendu : catégorie + titre + extrait (18 mots, fallback sur post_content).
 * Le card-180c__excerpt reste affiché — c'est ce qui distingue visuellement
 * l'article (texte long) de la recette (méta saison).
 *
 * @param int|WP_Post $post_id       ID du post ou objet WP_Post.
 * @param string      $size          Variante : 'sm' | 'md' | 'lg' (défaut 'md').
 * @param string      $heading_level Niveau du titre : 'h2'|'h3'|'h4' (défaut 'h3').
 * @return string HTML échappé, ou chaîne vide si introuvable.
 */
function _180c_block_render_article_card( $post_id, $size = 'md', $heading_level = 'h3' ) {
	$heading_tag = in_array( $heading_level, array( 'h2', 'h3', 'h4' ), true ) ? $heading_level : 'h3';
	// Guard tolérance : WP_Post → ID, conserve un seul flux ci-dessous.
	if ( $post_id instanceof WP_Post ) {
		$post    = $post_id;
		$post_id = (int) $post->ID;
	} else {
		$post_id = (int) $post_id;
		$post    = $post_id ? get_post( $post_id ) : null;
	}

	if ( ! $post || 'post' !== get_post_type( $post ) || 'publish' !== get_post_status( $post ) ) {
		return '';
	}

	$size       = in_array( $size, array( 'sm', 'md', 'lg' ), true ) ? $size : 'md';
	$url        = esc_url( get_permalink( $post ) );
	$title      = esc_html( $post->post_title );
	$thumb_id   = get_post_thumbnail_id( $post );
	$thumb_html = '';

	if ( $thumb_id ) {
		$img_size   = ( 'lg' === $size ) ? 'large' : 'medium';
		$thumb_html = wp_get_attachment_image(
			$thumb_id,
			$img_size,
			false,
			array(
				'class'    => 'card-180c__image',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
	}

	// Chip catégorie : aligne sur la catégorie principale calculée par le
	// hero (`_180c_article_primary_category`) pour que la carte
	// et la fiche article affichent la même catégorie. Fallback sur le
	// premier terme assigné si le helper n'est pas chargé (contexte hors
	// front-end où inc/article.php pourrait ne pas être require'd).
	$category_label = '';
	if ( function_exists( '_180c_article_primary_category' ) ) {
		$primary = _180c_article_primary_category( (int) $post->ID );
		if ( $primary instanceof WP_Term ) {
			$category_label = esc_html( $primary->name );
		}
	}
	if ( '' === $category_label ) {
		$categories     = get_the_category( $post->ID );
		$category_label = ! empty( $categories ) ? esc_html( $categories[0]->name ) : '';
	}

	$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 18, '…' );

	ob_start();
	?>
	<article class="card-180c card-180c--article card-180c--<?php echo esc_attr( $size ); ?>">
		<a class="card-180c__link" href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<?php if ( $thumb_html ) : ?>
				<div class="card-180c__media">
					<?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
			<div class="card-180c__body">
				<?php if ( $category_label ) : ?>
					<span class="card-180c__category"><?php echo $category_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php endif; ?>
				<<?php echo esc_html( $heading_tag ); ?> class="card-180c__title"><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php if ( $excerpt ) : ?>
					<p class="card-180c__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
			</div>
		</a>
	</article>
	<?php
	return ob_get_clean();
}

/**
 * Retourne le HTML d'une card recette (CPT recipe).
 *
 * Aligné sur le modèle ACF : pas de total_time/difficulty (champs
 * supprimés). La méta affichée provient des taxonomies (catégorie culinaire +
 * saison). Trois variantes de taille pour les différents contextes :
 *  - 'sm' : rails / grilles denses
 *  - 'md' : grille standard (défaut, rétro-compatible)
 *  - 'lg' : mise en avant
 *
 * @param WP_Post $recipe        La recette.
 * @param string  $size          Variante : 'sm' | 'md' | 'lg' (défaut 'md').
 * @param string  $heading_level Niveau du titre : 'h2'|'h3'|'h4' (défaut 'h3').
 * @return string HTML échappé.
 */
function _180c_block_render_recipe_card( WP_Post $recipe, $size = 'md', $heading_level = 'h3' ) {
	$size        = in_array( $size, array( 'sm', 'md', 'lg' ), true ) ? $size : 'md';
	$heading_tag = in_array( $heading_level, array( 'h2', 'h3', 'h4' ), true ) ? $heading_level : 'h3';
	$url         = esc_url( get_permalink( $recipe ) );
	$title       = esc_html( $recipe->post_title );
	$thumb_id    = get_post_thumbnail_id( $recipe );
	$thumb_html  = '';

	if ( $thumb_id ) {
		$img_size   = ( 'lg' === $size ) ? 'large' : 'medium';
		$thumb_html = wp_get_attachment_image(
			$thumb_id,
			$img_size,
			false,
			array(
				'class'    => 'card-180c__image',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
	}

	// Méta issue des taxonomies (catégorie culinaire prioritaire, sinon saison).
	$category_label = '';
	$cats           = get_the_terms( $recipe->ID, 'recipe_category' );
	if ( $cats && ! is_wp_error( $cats ) ) {
		$category_label = $cats[0]->name;
	}

	$season_label = '';
	$seasons      = get_the_terms( $recipe->ID, 'recipe_season' );
	if ( $seasons && ! is_wp_error( $seasons ) ) {
		$season_label = $seasons[0]->name;
	}

	// Bouton favori overlay (hors du <a> : un <button> dans un <a> serait
	// du HTML invalide). Rendu uniquement s'il y a un visuel support et que
	// le module favoris est chargé.
	$favorite_html = '';
	if ( $thumb_html && function_exists( '_180c_render_favorite_button' ) ) {
		$favorite_html = _180c_render_favorite_button( (int) $recipe->ID, 'card' );
	}

	ob_start();
	?>
	<article class="card-180c card-180c--recipe card-180c--<?php echo esc_attr( $size ); ?>">
		<a class="card-180c__link" href="<?php echo $url; ?>">
			<?php if ( $thumb_html ) : ?>
				<div class="card-180c__media">
					<?php echo $thumb_html; ?>
				</div>
			<?php endif; ?>
			<div class="card-180c__body">
				<?php if ( $category_label ) : ?>
					<span class="card-180c__category"><?php echo esc_html( $category_label ); ?></span>
				<?php endif; ?>
				<<?php echo esc_html( $heading_tag ); ?> class="card-180c__title"><?php echo $title; ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php if ( $season_label ) : ?>
					<div class="card-180c__meta">
						<span class="card-180c__season"><?php echo esc_html( $season_label ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		</a>
		<?php
		echo $favorite_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
		?>
	</article>
	<?php
	return ob_get_clean();
}

/**
 * Retourne le HTML d'une card produit WooCommerce.
 *
 * @param WC_Product|WP_Post $product Le produit (WC_Product ou WP_Post).
 * @param array              $args {
 *     Options d'affichage (optionnel).
 *
 *     @type string $variant  'md' (grille, défaut) | 'sm' (rail / cross-sell).
 *     @type bool   $show_cta Afficher le bouton « Ajouter au panier ». Défaut true
 *                            (préserve le comportement des appels existants).
 * }
 * @return string HTML échappé.
 */
function _180c_block_render_product_card( $product, $args = array() ) {
	if ( $product instanceof WP_Post ) {
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product->ID );
		}
	}

	if ( ! $product || ! ( $product instanceof WC_Product ) ) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		array(
			'variant'       => 'md',
			'show_cta'      => true,
			'heading_level' => 'h3',
		)
	);

	$variant     = in_array( $args['variant'], array( 'sm', 'md' ), true ) ? $args['variant'] : 'md';
	$heading_tag = in_array( $args['heading_level'], array( 'h2', 'h3', 'h4' ), true ) ? $args['heading_level'] : 'h3';
	$show_cta    = (bool) $args['show_cta'];
	$in_stock    = $product->is_in_stock();

	// Produit externe (livre/revue vendu en librairie) : non vendable en ligne.
	// On expose un badge de statut « En librairie » + un CTA « En savoir plus »
	// pointant vers la fiche INTERNE (jamais l'URL externe du produit).
	$is_external = $product->is_type( 'external' );

	$url        = esc_url( $product->get_permalink() );
	$title      = esc_html( $product->get_name() );
	$thumb_id   = $product->get_image_id();
	$thumb_html = '';

	if ( $thumb_id ) {
		$thumb_html = wp_get_attachment_image(
			$thumb_id,
			'medium',
			false,
			array(
				'class'    => 'card-180c__image',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
	}

	$price_html = $product->get_price_html();
	$cart_url   = esc_url( $product->add_to_cart_url() );
	$cart_text  = esc_html( $product->add_to_cart_text() );

	/*
	 * Éligibilité AJAX panier : mêmes conditions que WooCommerce
	 * (woocommerce_loop_add_to_cart_link). supports('ajax_add_to_cart') ne
	 * renvoie true que si l'option « Activer l'ajout au panier AJAX » est
	 * active ET le produit est simple → dégradé gracieux automatique (lien
	 * vers la fiche) pour les produits variables / hors stock / option off.
	 */
	$ajax_add = $product->is_purchasable() && $product->is_in_stock() && $product->supports( 'ajax_add_to_cart' );

	ob_start();
	?>
	<article class="card-180c card-180c--product card-180c--<?php echo esc_attr( $variant ); ?>">
		<a class="card-180c__link" href="<?php echo $url; ?>">
			<?php if ( $thumb_html ) : ?>
				<div class="card-180c__media">
					<?php echo $thumb_html; ?>
					<?php if ( ! $in_stock ) : ?>
						<span class="card-180c__badge"><?php esc_html_e( 'Épuisé', '180c' ); ?></span>
					<?php elseif ( $is_external ) : ?>
						<span class="card-180c__badge"><?php esc_html_e( 'En librairie', '180c' ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="card-180c__body">
				<<?php echo esc_html( $heading_tag ); ?> class="card-180c__title"><?php echo $title; ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php if ( $price_html ) : ?>
					<div class="card-180c__price"><?php echo $price_html; ?></div>
				<?php endif; ?>
			</div>
		</a>
		<?php if ( $show_cta && $is_external ) : ?>
			<a class="btn btn--primary card-180c__cta" href="<?php echo $url; ?>">
				<?php esc_html_e( 'En savoir plus', '180c' ); ?>
			</a>
		<?php elseif ( $show_cta && $ajax_add ) : ?>
			<a class="btn btn--primary card-180c__cta add_to_cart_button ajax_add_to_cart"
				href="<?php echo $cart_url; ?>"
				data-quantity="1"
				data-product_id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
				rel="nofollow">
				<?php echo $cart_text; ?>
			</a>
		<?php elseif ( $show_cta ) : ?>
			<a class="btn btn--primary card-180c__cta" href="<?php echo $url; ?>">
				<?php echo $cart_text; ?>
			</a>
		<?php endif; ?>
	</article>
	<?php
	return ob_get_clean();
}

/**
 * Rend un rail horizontal (slider CSS) de cards.
 *
 * @param string $block_class   Classe BEM du bloc parent (ex: 'wp-block-180c-articles-rail').
 * @param string $title         Titre du rail (optionnel).
 * @param array  $items_html    Tableau de chaînes HTML (items).
 * @return string HTML complet.
 */
function _180c_block_render_rail( $block_class, $title, array $items_html ) {
	if ( empty( $items_html ) ) {
		return '';
	}

	ob_start();
	?>
	<section class="<?php echo esc_attr( $block_class ); ?>">
		<?php if ( $title ) : ?>
			<h2 class="<?php echo esc_attr( $block_class ); ?>__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<div class="<?php echo esc_attr( $block_class ); ?>__track" role="list">
			<?php foreach ( $items_html as $item ) : ?>
				<div class="<?php echo esc_attr( $block_class ); ?>__item" role="listitem">
					<?php echo $item; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Rend le hero d'un post/recette/produit (utilisé par hero_editorial).
 *
 * @param WP_Post $post         L'objet post (post, recipe ou product).
 * @param string  $style        Variant d'affichage : 'full' ou 'split'.
 * @param string  $cta_label    Libellé du CTA (optionnel).
 * @param string  $cta_url       URL du CTA (optionnel, fallback permalink).
 * @param string  $heading_level Niveau du titre : 'h1'|'h2' (défaut 'h2'). 'h1'
 *                               réservé au hero de la front-page (titre unique).
 * @return string HTML.
 */
function _180c_render_hero_post( WP_Post $post, $style = 'full', $cta_label = '', $cta_url = '', $heading_level = 'h2' ) {
	$heading_tag = in_array( $heading_level, array( 'h1', 'h2' ), true ) ? $heading_level : 'h2';
	$title       = esc_html( $post->post_title );
	$excerpt     = wp_kses_post( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 30, '&hellip;' ) );
	$permalink   = esc_url( $cta_url ?: get_permalink( $post ) );
	$cta_label   = $cta_label ? esc_html( $cta_label ) : esc_html__( 'Lire la suite', '180c' );
	$thumb_id    = get_post_thumbnail_id( $post );
	$thumb_html  = '';

	if ( $thumb_id ) {
		$size = ( 'split' === $style ) ? 'large' : 'full';
		// `sizes` explicite : le conteneur home plafonne à --container-max 1280px,
		// donc inutile de servir une variante > 1280px sur grand écran (P3,
		// « properly size »). En split l'image n'occupe que la moitié. Le srcset
		// auto de WP reste inchangé : on guide juste le choix de variante. La une
		// garde 'full' en src + fetchpriority pour le LCP.
		$hero_sizes = ( 'split' === $style )
			? '(min-width: 1024px) 50vw, 100vw'
			: '(min-width: 1280px) 1280px, 100vw';
		$thumb_html = wp_get_attachment_image(
			$thumb_id,
			$size,
			false,
			array(
				'class'         => 'hero-180c__image',
				'loading'       => 'eager',
				'decoding'      => 'async',
				'fetchpriority' => 'high',
				'sizes'         => $hero_sizes,
			)
		);
	}

	$modifier = ( 'split' === $style ) ? 'hero-180c--split' : 'hero-180c--full';

	// Bouton favori overlay si la une est une recette (même partial partagé que
	// les cartes — surface « card »). Rendu uniquement avec un visuel support.
	$favorite_html = '';
	if ( $thumb_html
		&& 'recipe' === get_post_type( $post )
		&& function_exists( '_180c_render_favorite_button' ) ) {
		$favorite_html = _180c_render_favorite_button( (int) $post->ID, 'card' );
	}

	ob_start();
	?>
	<div class="hero-180c <?php echo esc_attr( $modifier ); ?>">
		<?php if ( $thumb_html ) : ?>
			<div class="hero-180c__media">
				<?php echo $thumb_html; ?>
			</div>
		<?php endif; ?>
		<?php
		// Le bouton favori est volontairement rendu HORS de .hero-180c__media :
		// cette couche est poussée en z-index:-2 (image de fond), ce qui crée un
		// contexte d'empilement enterrant tout enfant interactif sous l'overlay.
		// Frère direct de .hero-180c (position:relative), son z-index:2 le place
		// au-dessus de l'overlay et du contenu → cliquable.
		echo $favorite_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
		?>
		<div class="hero-180c__content">
			<<?php echo esc_html( $heading_tag ); ?> class="hero-180c__title"><?php echo $title; ?></<?php echo esc_html( $heading_tag ); ?>>
			<?php if ( $excerpt ) : ?>
				<p class="hero-180c__excerpt"><?php echo $excerpt; ?></p>
			<?php endif; ?>
			<a class="btn btn--primary hero-180c__cta" href="<?php echo $permalink; ?>">
				<?php echo $cta_label; ?>
			</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Rend la card d'une publication (livre ou numéro) format XL.
 *
 * @param WC_Product $product      Le produit WooCommerce.
 * @param string     $headline     Titre à afficher (override ou post_title).
 * @param string     $cta_label    Libellé du bouton d'achat.
 * @return string HTML.
 */
function _180c_render_publication_xl( WC_Product $product, $headline = '', $cta_label = '' ) {
	$title      = $headline ? esc_html( $headline ) : esc_html( $product->get_name() );
	$url        = esc_url( $product->get_permalink() );
	$thumb_id   = $product->get_image_id();
	$thumb_html = '';
	$cta_label  = $cta_label ? esc_html( $cta_label ) : esc_html__( 'Acheter', '180c' );

	if ( $thumb_id ) {
		$thumb_html = wp_get_attachment_image(
			$thumb_id,
			'large',
			false,
			array(
				'class'    => 'publication-xl__cover',
				'loading'  => 'eager',
				'decoding' => 'async',
			)
		);
	}

	$excerpt    = wp_kses_post( wp_trim_words( wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ), 40, '&hellip;' ) );
	$price_html = $product->get_price_html();
	$cart_url   = esc_url( $product->add_to_cart_url() );

	ob_start();
	?>
	<div class="publication-xl">
		<?php if ( $thumb_html ) : ?>
			<div class="publication-xl__media">
				<a href="<?php echo $url; ?>" aria-hidden="true" tabindex="-1">
					<?php echo $thumb_html; ?>
				</a>
			</div>
		<?php endif; ?>
		<div class="publication-xl__content">
			<h2 class="publication-xl__title">
				<a href="<?php echo $url; ?>"><?php echo $title; ?></a>
			</h2>
			<?php if ( $excerpt ) : ?>
				<p class="publication-xl__excerpt"><?php echo $excerpt; ?></p>
			<?php endif; ?>
			<?php if ( $price_html ) : ?>
				<div class="publication-xl__price"><?php echo $price_html; ?></div>
			<?php endif; ?>
			<a class="btn btn--primary publication-xl__cta" href="<?php echo $cart_url; ?>">
				<?php echo $cta_label; ?>
			</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Rend une card produit avec badge "Déjà acheté" conditionnel.
 *
 * @param WC_Product $product        Le produit.
 * @param bool       $already_bought Afficher le badge "Déjà acheté".
 * @param string     $heading_level  Niveau du titre : 'h2'|'h3'|'h4' (défaut 'h3').
 * @return string HTML.
 */
function _180c_render_collection_card( WC_Product $product, $already_bought = false, $heading_level = 'h3' ) {
	$heading_tag = in_array( $heading_level, array( 'h2', 'h3', 'h4' ), true ) ? $heading_level : 'h3';
	$url         = esc_url( $product->get_permalink() );
	$title       = esc_html( $product->get_name() );
	$thumb_id    = $product->get_image_id();
	$thumb_html  = '';

	if ( $thumb_id ) {
		$thumb_html = wp_get_attachment_image(
			$thumb_id,
			'medium',
			false,
			array(
				'class'    => 'card-180c__image',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
	}

	$price_html = $already_bought ? '' : $product->get_price_html();
	$cart_url   = $already_bought ? '' : esc_url( $product->add_to_cart_url() );
	$cart_text  = $already_bought ? esc_html__( 'Déjà acheté', '180c' ) : esc_html( $product->add_to_cart_text() );

	ob_start();
	?>
	<article class="card-180c card-180c--product<?php echo $already_bought ? ' card-180c--owned' : ''; ?>">
		<a class="card-180c__link" href="<?php echo $url; ?>">
			<?php if ( $thumb_html ) : ?>
				<div class="card-180c__media">
					<?php echo $thumb_html; ?>
					<?php if ( $already_bought ) : ?>
						<span class="card-180c__badge card-180c__badge--owned" aria-label="<?php esc_attr_e( 'Déjà acheté', '180c' ); ?>">
							<?php esc_html_e( 'Déjà acheté', '180c' ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="card-180c__body">
				<<?php echo esc_html( $heading_tag ); ?> class="card-180c__title"><?php echo $title; ?></<?php echo esc_html( $heading_tag ); ?>>
				<?php if ( $price_html ) : ?>
					<div class="card-180c__price"><?php echo $price_html; ?></div>
				<?php endif; ?>
			</div>
		</a>
		<?php if ( ! $already_bought && $cart_url ) : ?>
			<a class="btn btn--primary card-180c__cta" href="<?php echo $cart_url; ?>">
				<?php echo $cart_text; ?>
			</a>
		<?php else : ?>
			<span class="btn btn--ghost card-180c__cta card-180c__cta--owned">
				<?php esc_html_e( 'Déjà acheté', '180c' ); ?>
			</span>
		<?php endif; ?>
	</article>
	<?php
	return ob_get_clean();
}

/**
 * Retourne les IDs produits déjà achetés par l'utilisateur courant.
 *
 * @return int[] Tableau d'IDs de produits.
 */
function _180c_get_user_purchased_product_ids() {
	if ( ! is_user_logged_in() ) {
		return array();
	}

	$customer_orders = wc_get_orders(
		array(
			'customer_id' => get_current_user_id(),
			'status'      => array( 'wc-completed', 'wc-processing' ),
			'limit'       => -1,
			'return'      => 'ids',
		)
	);

	$product_ids = array();
	foreach ( $customer_orders as $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			continue;
		}
		foreach ( $order->get_items() as $item ) {
			$product_ids[] = (int) $item->get_product_id();
		}
	}

	return array_unique( $product_ids );
}

/**
 * Résout un produit WooCommerce publié à partir d'un id ou d'un sku.
 *
 * Utilisé par le bloc 180c/product-highlight (mise en avant produit). Repli
 * sur le SKU lorsque l'ID est absent ou invalide.
 *
 * @param int|string $id  ID du produit (prioritaire).
 * @param string     $sku SKU de repli.
 * @return WC_Product|null Le produit publié, ou null.
 */
function _180c_block_resolve_product( $id = 0, $sku = '' ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return null;
	}

	$id = (int) $id;

	if ( $id < 1 && '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
		$id = (int) wc_get_product_id_by_sku( $sku );
	}

	if ( $id < 1 || 'publish' !== get_post_status( $id ) ) {
		return null;
	}

	$product = wc_get_product( $id );

	return ( $product instanceof WC_Product ) ? $product : null;
}
