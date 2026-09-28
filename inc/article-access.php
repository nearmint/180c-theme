<?php
/**
 * Logique d'accès aux articles premium (paywall éditorial).
 *
 * Pendant de inc/recipe-access.php pour les `post`. Un article marqué
 * `article_premium` (ACF, défaut false) n'est accessible en intégralité qu'aux
 * abonnés du plan canonique `abonne-recettes`. Les non-abonnés reçoivent un
 * aperçu : chapô (post_excerpt, rendu en en-tête) + 1er paragraphe du corps +
 * bloc paywall mutualisé (parts/article-paywall.php).
 *
 * Anti-fuite 100% server-side : le contenu complet d'un article gaté n'est
 * JAMAIS émis. Tronqué après le 1er paragraphe sur toutes les surfaces —
 * the_content (front + flux RSS), REST API (rest_prepare_post) et meta
 * description SEO (cf. inc/seo/meta-tags.php). Le partage d'un article premium
 * par un non-abonné aboutit au paywall, sans fuite.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indique si un article est marqué premium.
 *
 * Lit le champ ACF `article_premium` (défaut false si absent). Filtrable.
 *
 * @param int|null $post_id ID de l'article (par défaut : courant).
 * @return bool True si l'article est premium.
 */
function _180c_article_is_premium( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

	$is_premium = false; // Défaut : accès libre (l'opposé des recettes).

	if ( $post_id && function_exists( 'get_field' ) ) {
		$value = get_field( 'article_premium', $post_id );
		// ACF true_false renvoie un booléen ; null/'' = non renseigné → défaut.
		if ( null !== $value && '' !== $value ) {
			$is_premium = (bool) $value;
		}
	}

	/**
	 * Filtre l'état premium d'un article.
	 *
	 * @param bool $is_premium État premium.
	 * @param int  $post_id    ID de l'article.
	 */
	return (bool) apply_filters( '180c/article_is_premium', $is_premium, $post_id );
}

/**
 * Détermine si l'utilisateur courant a accès au contenu complet d'un article.
 *
 * Server-side, sans dépendance JS. Mêmes mécanismes d'abonnement que les
 * recettes (plan `abonne-recettes`), preview rédaction incluse.
 *
 * @param int|null $post_id ID de l'article (par défaut : courant).
 * @return bool True si l'accès complet est accordé.
 */
function _180c_user_has_article_access( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

	// Article non premium : accès libre pour tout le monde.
	if ( ! _180c_article_is_premium( $post_id ) ) {
		return true;
	}

	// Rédaction (admin / éditeur) : toujours accès (preview).
	if ( current_user_can( 'edit_posts' ) ) {
		return true;
	}

	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();

		// a) WC Memberships — mécanisme canonique 180°C (plan `abonne-recettes`).
		if ( function_exists( '_180c_is_recipe_subscriber' ) && _180c_is_recipe_subscriber() ) {
			return true;
		}

		// b) WC Subscriptions — fallback (abonnement actif sans membership mappé).
		if ( function_exists( 'wcs_user_has_subscription' )
			&& wcs_user_has_subscription( $user_id, '', 'active' ) ) {
			return true;
		}

		// c) Meta utilisateur — fallback (octroi manuel / apps mobiles).
		if ( 'granted' === get_user_meta( $user_id, 'access_recipes', true ) ) {
			return true;
		}
	}

	/**
	 * Filtre final permettant d'accorder l'accès par un mécanisme tiers.
	 *
	 * @param bool $has_access Accès accordé (false à ce stade).
	 * @param int  $post_id    ID de l'article.
	 */
	return (bool) apply_filters( '180c/user_has_article_access', false, $post_id );
}

/**
 * Article gaté = post premium dont l'utilisateur courant n'a pas l'accès.
 *
 * @param int|null $post_id ID de l'article (par défaut : courant).
 * @return bool
 */
function _180c_article_is_gated( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( 'post' !== get_post_type( $post_id ) ) {
		return false;
	}
	return _180c_article_is_premium( $post_id ) && ! _180c_user_has_article_access( $post_id );
}

/**
 * Construit l'aperçu autorisé d'un article gaté : 1er paragraphe seulement.
 *
 * Le 1er bloc paragraphe Gutenberg est rendu via render_block() (sans
 * déclencher le filtre the_content → pas de récursion). Fallback regex sur le
 * 1er <p> auto-paragraphé, puis sur une troncature de mots.
 *
 * @param WP_Post $post Article.
 * @return string HTML de l'aperçu (1er paragraphe), enveloppé.
 */
function _180c_article_first_paragraph_html( WP_Post $post ) {
	$html = '';

	if ( function_exists( 'has_blocks' ) && has_blocks( $post->post_content ) ) {
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			if ( 'core/paragraph' !== ( $block['blockName'] ?? '' ) ) {
				continue;
			}
			$rendered = trim( render_block( $block ) );
			if ( '' !== trim( wp_strip_all_tags( $rendered ) ) ) {
				$html = $rendered;
				break;
			}
		}
	}

	if ( '' === $html ) {
		$auto = wpautop( $post->post_content );
		if ( preg_match( '/<p\b[^>]*>.*?<\/p>/is', $auto, $m ) ) {
			$html = $m[0];
		} else {
			$html = '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '…' ) ) . '</p>';
		}
	}

	return '<div class="article__preview">' . $html . '</div>';
}

/**
 * Rend le bloc paywall article mutualisé (capturé en chaîne).
 *
 * @return string HTML du paywall.
 */
function _180c_article_paywall_html() {
	ob_start();
	get_template_part( 'parts/article-paywall' );
	return (string) ob_get_clean();
}

/**
 * Filtre the_content : tronque les articles premium non accessibles.
 *
 * Couvre le rendu front (single, dans la boucle) et les flux RSS pleins
 * (the_content_feed applique the_content). Le cas REST est traité séparément
 * (rest_prepare_post) pour disposer d'un contexte $post fiable.
 *
 * @param string $content Contenu original (ignoré si gaté).
 * @return string
 */
function _180c_gate_article_content( $content ) {
	if ( is_admin() ) {
		return $content;
	}
	// REST géré par rest_prepare_post : éviter double traitement + contexte
	// $post non fiable hors boucle.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return $content;
	}
	if ( ! in_the_loop() && ! is_feed() ) {
		return $content;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return $content;
	}
	if ( _180c_user_has_article_access( $post->ID ) ) {
		return $content;
	}

	$preview = _180c_article_first_paragraph_html( $post );

	if ( is_feed() ) {
		// Flux : aperçu + lien de lecture, pas le markup visuel complet.
		$url = apply_filters( '180c/paywall_subscribe_url', home_url( '/abonnement/' ) );
		return $preview . "\n<p><a href=\"" . esc_url( $url ) . '">'
			. esc_html__( 'Lire la suite — article réservé aux abonnés.', '180c' )
			. '</a></p>';
	}

	return $preview . _180c_article_paywall_html();
}
add_filter( 'the_content', '_180c_gate_article_content' );

/**
 * Filtre get_the_excerpt : limite l'extrait des articles gatés au chapô.
 *
 * Empêche l'extrait auto-généré (≈ 55 mots du corps) de fuir le contenu
 * premium. Couvre l'affichage front, les listings, les flux et REST
 * (excerpt.rendered passe par get_the_excerpt).
 *
 * @param string  $excerpt Extrait calculé.
 * @param WP_Post $post    Article concerné.
 * @return string
 */
function _180c_gate_article_excerpt( $excerpt, $post = null ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return $excerpt;
	}
	if ( _180c_user_has_article_access( $post->ID ) ) {
		return $excerpt;
	}
	// Gaté : seul le chapô éditorial (post_excerpt) est exposé, jamais le corps.
	return $post->post_excerpt ? wp_strip_all_tags( $post->post_excerpt ) : '';
}
add_filter( 'get_the_excerpt', '_180c_gate_article_excerpt', 10, 2 );

/**
 * Filtre rest_prepare_post : tronque content/excerpt des articles gatés.
 *
 * @param WP_REST_Response $response Réponse REST.
 * @param WP_Post          $post     Article.
 * @return WP_REST_Response
 */
function _180c_gate_article_rest( $response, $post ) {
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return $response;
	}
	if ( _180c_user_has_article_access( $post->ID ) ) {
		return $response;
	}

	$data = $response->get_data();

	if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
		$url                          = apply_filters( '180c/paywall_subscribe_url', home_url( '/abonnement/' ) );
		$data['content']['rendered']  = _180c_article_first_paragraph_html( $post )
			. "\n<p><a href=\"" . esc_url( $url ) . '">'
			. esc_html__( 'Article réservé aux abonnés.', '180c' ) . '</a></p>';
		$data['content']['protected'] = true;
		unset( $data['content']['raw'] );
	}

	if ( isset( $data['excerpt'] ) && is_array( $data['excerpt'] ) ) {
		$chapo                        = $post->post_excerpt ? wp_strip_all_tags( $post->post_excerpt ) : '';
		$data['excerpt']['rendered']  = $chapo ? wpautop( esc_html( $chapo ) ) : '';
		$data['excerpt']['protected'] = true;
		unset( $data['excerpt']['raw'] );
	}

	$response->set_data( $data );
	return $response;
}
add_filter( 'rest_prepare_post', '_180c_gate_article_rest', 10, 2 );
