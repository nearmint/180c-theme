<?php
/**
 * Mon Compte — Tableau de bord single-page (contenu).
 *
 * Rendu DANS le shell mutualisé `.my-account` fourni par l'override
 * woocommerce/myaccount/my-account.php (sidebar = parts/account/sidebar.php).
 * Ce partial n'émet donc QUE les 6 sections ancrées de la colonne contenu.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() || ! function_exists( 'WC' ) ) {
	return;
}

// -----------------------------------------------------------------------
// Données utilisateur (profil/nav/logout → parts/account/sidebar.php).
// -----------------------------------------------------------------------

$user_id      = get_current_user_id();
$current_user = wp_get_current_user();

$subscription = _180c_get_user_subscription_summary( $user_id );
$orders       = _180c_get_user_recent_orders( $user_id, 10 );

$em_dash = '—';

// Abonnement actif (au sens WC Subscriptions) → conditionne l'affichage du
// toggle opt-in « Cahiers de Delphine ».
$has_active_subscription = ( null !== $subscription )
	&& in_array( $subscription['status'], array( 'active', 'pending-cancel' ), true );

// État initial de l'opt-in NL « free » (miroir local de Mailchimp, maintenu par
// l'endpoint /newsletter/account-optin et les routes subscribe/unsubscribe).
$nl_optin = (bool) get_user_meta( $user_id, '_180c_newsletter_free', true );

// -----------------------------------------------------------------------
// Adresse de livraison — lue depuis user_meta via WC_Customer.
// -----------------------------------------------------------------------

$customer     = new WC_Customer( $user_id );
$country_code = $customer->get_shipping_country();
$country_name = '';
if ( '' !== $country_code && isset( WC()->countries ) ) {
	$countries    = WC()->countries->get_countries();
	$country_name = isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_code;
}
$shipping             = array(
	'first_name' => (string) $customer->get_shipping_first_name(),
	'last_name'  => (string) $customer->get_shipping_last_name(),
	'company'    => (string) $customer->get_shipping_company(),
	'address_1'  => (string) $customer->get_shipping_address_1(),
	'address_2'  => (string) $customer->get_shipping_address_2(),
	'postcode'   => (string) $customer->get_shipping_postcode(),
	'city'       => (string) $customer->get_shipping_city(),
	'country'    => (string) $country_name,
);
$has_shipping_address = ( '' !== $shipping['address_1'] || '' !== $shipping['city'] );

// -----------------------------------------------------------------------
// URLs endpoints WC.
// -----------------------------------------------------------------------

$account_url      = wc_get_page_permalink( 'myaccount' );
$edit_account_url = wc_get_endpoint_url( 'edit-account', '', $account_url );
$edit_address_url = wc_get_endpoint_url( 'edit-address', 'shipping', $account_url );

// -----------------------------------------------------------------------
// Abonnement gérable (pause / réactivation) : 1re souscription active OU
// on-hold. Les actions natives WC Subscriptions pilotent l'affichage par
// PRÉSENCE de clé (suspend / reactivate / change_payment_method) — pas de
// lecture de l'option woocommerce_subscriptions_max_customer_suspensions :
// la clé `suspend` n'est exposée que si les suspensions sont autorisées ET
// que le quota n'est pas épuisé.
// -----------------------------------------------------------------------

$managed_sub        = null;
$sub_actions        = array();
$pending_sub        = null;  // Abonnement en attente du 1er paiement (statut pending).
$pending_cancel_sub = null;  // Résiliation en cours (accès maintenu) → bloc à la une.
$has_ended_sub      = false; // Abonnement terminé (cancelled / expired) → alerte en tête.
if ( function_exists( 'wcs_get_users_subscriptions' )
	&& function_exists( 'wcs_get_all_user_actions_for_subscription' )
) {
	$user_subs = wcs_get_users_subscriptions( $user_id );
	foreach ( $user_subs as $sub ) {
		if ( ! $sub instanceof \WC_Subscription ) {
			continue;
		}
		if ( null === $managed_sub && $sub->has_status( array( 'active', 'on-hold' ) ) ) {
			$managed_sub = $sub;
			$sub_actions = wcs_get_all_user_actions_for_subscription( $sub, wp_get_current_user() );
			continue;
		}
		if ( null === $pending_sub && $sub->has_status( 'pending' ) ) {
			$pending_sub = $sub;
			continue;
		}
		if ( null === $pending_cancel_sub && $sub->has_status( 'pending-cancel' ) ) {
			$pending_cancel_sub = $sub;
			continue;
		}
		if ( $sub->has_status( array( 'cancelled', 'expired' ) ) ) {
			$has_ended_sub = true;
		}
	}
}

$has_pending_cancel = $pending_cancel_sub instanceof \WC_Subscription;

// Date de fin d'accès de la résiliation en cours (affichée dans le bloc à la une).
$pending_cancel_end = '';
if ( $has_pending_cancel ) {
	$pc_end_ts          = $pending_cancel_sub->get_time( 'end' ) ?: $pending_cancel_sub->get_time( 'next_payment' );
	$pending_cancel_end = $pc_end_ts ? wp_date( get_option( 'date_format' ), $pc_end_ts ) : '';
}

// URL de finalisation du 1er paiement (source unique : helper partagé).
$pending_pay_url = function_exists( '_180c_get_pending_payment_url' )
	? _180c_get_pending_payment_url( $user_id )
	: '';

$sub_is_active  = $managed_sub instanceof \WC_Subscription && $managed_sub->has_status( 'active' );
$sub_is_on_hold = $managed_sub instanceof \WC_Subscription && $managed_sub->has_status( 'on-hold' );

// Abonnement en pause : renouvellement impayé → régler le paiement ; sinon →
// réactivation. URL de la commande à régler (vide si pause manuelle).
$onhold_pay_url = ( $sub_is_on_hold && function_exists( '_180c_get_subscription_payment_url' ) )
	? _180c_get_subscription_payment_url( $managed_sub )
	: '';

/*
 * Statut « attention requise » promu en bloc à la une (alerte tout en haut, au-
 * dessus de « Vos informations ») : on-hold, pending, résiliation en cours
 * (pending-cancel) et cancelled/expired. Seul un abonnement actif reste géré en
 * position normale dans le bloc « Gérer ». '' = pas d'alerte.
 */
$alert_kind = '';
if ( $sub_is_on_hold ) {
	$alert_kind = 'on_hold';
} elseif ( ! $managed_sub instanceof \WC_Subscription ) {
	if ( $pending_sub instanceof \WC_Subscription ) {
		$alert_kind = 'pending';
	} elseif ( $has_pending_cancel ) {
		$alert_kind = 'pending_cancel';
	} elseif ( $has_ended_sub ) {
		$alert_kind = 'ended';
	}
}
?>

<?php
/*
============================================================
 * 0. Bloc à la une « attention requise » (on-hold / pending / cancelled /
 *    expired). Promu tout en haut, au-dessus de « Vos informations ».
 * ============================================================ */
?>
<?php if ( 'pending' === $alert_kind ) : ?>
	<section class="_180c-account-alert" aria-labelledby="account-alert-title">
		<h2 id="account-alert-title" class="_180c-account-alert__title"><?php esc_html_e( 'Votre abonnement est en attente de paiement', '180c' ); ?></h2>
		<p class="_180c-account-alert__text"><?php esc_html_e( 'Finalisez le règlement pour activer votre accès à toutes nos recettes.', '180c' ); ?></p>
		<?php if ( '' !== $pending_pay_url ) : ?>
			<div class="my-account-actions">
				<a class="my-account-actions__btn my-account-actions__btn--primary" href="<?php echo esc_url( $pending_pay_url ); ?>">
					<?php esc_html_e( 'Finaliser le paiement', '180c' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</section>
<?php elseif ( 'on_hold' === $alert_kind ) : ?>
	<section class="_180c-account-alert" aria-labelledby="account-alert-title">
		<?php if ( '' !== $onhold_pay_url ) : ?>
			<?php /* Pause sur renouvellement impayé → régularisation du paiement. */ ?>
			<h2 id="account-alert-title" class="_180c-account-alert__title"><?php esc_html_e( 'Votre abonnement est suspendu', '180c' ); ?></h2>
			<p class="_180c-account-alert__text"><?php esc_html_e( 'Un paiement est en attente. Régularisez-le pour retrouver l\'accès à toutes nos recettes.', '180c' ); ?></p>
			<div class="my-account-actions">
				<a class="my-account-actions__btn my-account-actions__btn--primary" href="<?php echo esc_url( $onhold_pay_url ); ?>">
					<?php esc_html_e( 'Régler mon paiement', '180c' ); ?>
				</a>
			</div>
		<?php else : ?>
			<?php /* Pause manuelle (rien dû) → réactivation. */ ?>
			<h2 id="account-alert-title" class="_180c-account-alert__title"><?php esc_html_e( 'Votre abonnement est en pause', '180c' ); ?></h2>
			<p class="_180c-account-alert__text"><?php esc_html_e( 'Réactivez-le pour retrouver l\'accès à toutes nos recettes.', '180c' ); ?></p>
			<div class="my-account-actions">
				<button
					type="button"
					class="my-account-actions__btn my-account-actions__btn--primary"
					data-confirm
					data-confirm-post="subscription/reactivate"
					data-confirm-body="<?php echo esc_attr( wp_json_encode( array( 'subscription_id' => (int) $managed_sub->get_id() ) ) ); ?>"
					data-confirm-title="<?php esc_attr_e( 'Réactiver votre abonnement ?', '180c' ); ?>"
					data-confirm-message="<?php esc_attr_e( 'Souhaitez-vous réactiver votre abonnement ? La facturation reprendra à la prochaine échéance.', '180c' ); ?>"
					data-confirm-confirm-label="<?php esc_attr_e( 'Réactiver', '180c' ); ?>"
				>
					<?php esc_html_e( 'Réactiver mon abonnement', '180c' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</section>
<?php elseif ( 'pending_cancel' === $alert_kind ) : ?>
	<?php /* Résiliation en cours : accès maintenu jusqu'à l'échéance ; on propose d'annuler la résiliation (réactivation). */ ?>
	<section class="_180c-account-alert" aria-labelledby="account-alert-title">
		<h2 id="account-alert-title" class="_180c-account-alert__title"><?php esc_html_e( 'Votre abonnement a été annulé', '180c' ); ?></h2>
		<p class="_180c-account-alert__text">
			<?php
			if ( '' !== $pending_cancel_end ) {
				printf(
					/* translators: %s : date de fin d'accès (ex. 15 janvier 2026). */
					esc_html__( 'Vous conservez l\'accès à toutes nos recettes jusqu\'au %s. Vous pouvez encore annuler cette résiliation.', '180c' ),
					esc_html( $pending_cancel_end )
				);
			} else {
				esc_html_e( 'Vous conservez l\'accès à toutes nos recettes jusqu\'à la fin de la période en cours. Vous pouvez encore annuler cette résiliation.', '180c' );
			}
			?>
		</p>
		<div class="my-account-actions">
			<button
				type="button"
				class="my-account-actions__btn my-account-actions__btn--primary"
				data-confirm
				data-confirm-post="subscription/reactivate"
				data-confirm-body="<?php echo esc_attr( wp_json_encode( array( 'subscription_id' => (int) $pending_cancel_sub->get_id() ) ) ); ?>"
				data-confirm-title="<?php esc_attr_e( 'Annuler la résiliation ?', '180c' ); ?>"
				data-confirm-message="<?php esc_attr_e( 'Souhaitez-vous annuler la résiliation de votre abonnement ? Il restera actif et sera renouvelé normalement.', '180c' ); ?>"
				data-confirm-confirm-label="<?php esc_attr_e( 'Annuler la résiliation', '180c' ); ?>"
			>
				<?php esc_html_e( 'Annuler la résiliation', '180c' ); ?>
			</button>
		</div>
	</section>
<?php elseif ( 'ended' === $alert_kind ) : ?>
	<section class="_180c-account-alert" aria-labelledby="account-alert-title">
		<h2 id="account-alert-title" class="_180c-account-alert__title"><?php esc_html_e( 'Votre abonnement a pris fin', '180c' ); ?></h2>
		<p class="_180c-account-alert__text"><?php esc_html_e( 'Réabonnez-vous pour retrouver l\'accès à toutes nos recettes.', '180c' ); ?></p>
		<div class="my-account-actions">
			<a class="my-account-actions__btn my-account-actions__btn--primary" href="<?php echo esc_url( home_url( '/abonnement/' ) ); ?>">
				<?php esc_html_e( 'Reprendre mon abonnement', '180c' ); ?>
			</a>
		</div>
	</section>
<?php endif; ?>

<?php
/*
============================================================
 * 1. Vos informations
 * ============================================================ */
?>
<section id="informations" class="my-account-section" aria-labelledby="informations-title">
	<header class="my-account-section__header">
		<h2 id="informations-title" class="my-account-section__title"><?php esc_html_e( 'Vos informations', '180c' ); ?></h2>
	</header>

	<dl class="my-account-fields">
		<div class="my-account-field">
			<dt class="my-account-field__label"><?php esc_html_e( 'Prénom', '180c' ); ?></dt>
			<dd class="my-account-field__value"><?php echo esc_html( '' !== $current_user->first_name ? $current_user->first_name : $em_dash ); ?></dd>
			<a class="my-account-field__action" href="<?php echo esc_url( $edit_account_url ); ?>"><?php esc_html_e( 'Modifier', '180c' ); ?></a>
		</div>
		<div class="my-account-field">
			<dt class="my-account-field__label"><?php esc_html_e( 'Nom', '180c' ); ?></dt>
			<dd class="my-account-field__value"><?php echo esc_html( '' !== $current_user->last_name ? $current_user->last_name : $em_dash ); ?></dd>
			<a class="my-account-field__action" href="<?php echo esc_url( $edit_account_url ); ?>"><?php esc_html_e( 'Modifier', '180c' ); ?></a>
		</div>
		<div class="my-account-field">
			<dt class="my-account-field__label"><?php esc_html_e( 'E-mail', '180c' ); ?></dt>
			<dd class="my-account-field__value"><?php echo esc_html( $current_user->user_email ); ?></dd>
			<a class="my-account-field__action" href="<?php echo esc_url( $edit_account_url ); ?>"><?php esc_html_e( 'Modifier', '180c' ); ?></a>
		</div>
		<div class="my-account-field">
			<dt class="my-account-field__label"><?php esc_html_e( 'Mot de passe', '180c' ); ?></dt>
			<dd class="my-account-field__value" aria-label="<?php esc_attr_e( 'Mot de passe masqué', '180c' ); ?>">••••••••</dd>
			<a class="my-account-field__action" href="<?php echo esc_url( $edit_account_url ); ?>"><?php esc_html_e( 'Modifier', '180c' ); ?></a>
		</div>
	</dl>
</section>

<?php
/*
============================================================
 * 2. Votre abonnement
 *    Masqué pour un abonnement résilié (cancelled) : les détails (statut,
 *    échéance, paiement) n'ont plus de sens et l'état est déjà signalé par
 *    l'alerte en tête de page.
 * ============================================================ */
?>
<?php if ( null === $subscription || 'cancelled' !== $subscription['status'] ) : ?>
<section id="abonnement" class="my-account-section<?php echo null === $subscription ? ' my-account-section--cta' : ''; ?>" aria-labelledby="abonnement-title">
	<header class="my-account-section__header">
		<h2 id="abonnement-title" class="my-account-section__title"><?php esc_html_e( 'Votre abonnement digital', '180c' ); ?></h2>
	</header>

	<?php if ( null !== $subscription ) : ?>
		<dl class="my-account-fields">
			<div class="my-account-field">
				<dt class="my-account-field__label"><?php esc_html_e( 'Statut', '180c' ); ?></dt>
				<dd class="my-account-field__value">
					<span class="my-account-status my-account-status--<?php echo esc_attr( $subscription['status'] ); ?>">
						<?php echo esc_html( $subscription['status_label'] ); ?>
					</span>
				</dd>
			</div>
			<div class="my-account-field">
				<dt class="my-account-field__label"><?php esc_html_e( 'Tarif', '180c' ); ?></dt>
				<dd class="my-account-field__value"><?php echo esc_html( $subscription['price_formatted'] ); ?></dd>
			</div>
			<div class="my-account-field">
				<dt class="my-account-field__label"><?php esc_html_e( 'Prochaine échéance', '180c' ); ?></dt>
				<dd class="my-account-field__value">
					<?php if ( '' !== $subscription['next_payment_label'] ) : ?>
						<time datetime="<?php echo esc_attr( $subscription['next_payment_iso'] ); ?>">
							<?php echo esc_html( $subscription['next_payment_label'] ); ?>
						</time>
					<?php else : ?>
						<?php echo esc_html( $em_dash ); ?>
					<?php endif; ?>
				</dd>
			</div>
			<div class="my-account-field">
				<dt class="my-account-field__label"><?php esc_html_e( 'Moyen de paiement', '180c' ); ?></dt>
				<dd class="my-account-field__value"><?php echo esc_html( '' !== $subscription['payment_method'] ? $subscription['payment_method'] : $em_dash ); ?></dd>
				<a class="my-account-field__action" href="<?php echo esc_url( $subscription['edit_payment_url'] ); ?>"><?php esc_html_e( 'Modifier', '180c' ); ?></a>
			</div>
		</dl>

		<?php
		/*
		 * Opt-in newsletter « Cahiers de Delphine » — réservé aux abonnés actifs.
		 * Branché sur la même liste Mailchimp que la page Newsletter via
		 * POST /180c/v1/newsletter/account-optin (module account-newsletter-optin.js).
		 */
		?>
		<?php if ( $has_active_subscription ) : ?>
			<div class="my-account-optin" data-account-optin>
				<label class="my-account-optin__control">
					<span class="my-account-optin__text">
						<span class="my-account-optin__eyebrow"><?php esc_html_e( 'Newsletter', '180c' ); ?></span>
						<span class="my-account-optin__title">
							<?php esc_html_e( 'Recevoir Les Cahiers de Delphine tous les vendredis', '180c' ); ?>
						</span>
					</span>
					<input
						type="checkbox"
						class="my-account-switch__input"
						role="switch"
						data-account-optin-input
						<?php checked( $nl_optin ); ?>
						value="1"
					/>
					<span class="my-account-switch" aria-hidden="true">
						<span class="my-account-switch__thumb"></span>
					</span>
				</label>
				<p class="my-account-optin__feedback" role="status" aria-live="polite" aria-atomic="true" hidden></p>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<?php /* Sans abonnement : invitation à s'abonner (le module porte une bordure accent). */ ?>
		<div class="my-account-actions">
			<a class="my-account-actions__btn my-account-actions__btn--primary" href="<?php echo esc_url( home_url( '/abonnement/' ) ); ?>">
				<?php esc_html_e( 'Je m\'abonne', '180c' ); ?>
			</a>
		</div>
	<?php endif; ?>
</section>
<?php endif; ?>

<?php
/*
============================================================
 * 2 bis. Vos notifications
 *
 * SECTION AUTONOME, et c'est le point important : elle ne doit dépendre
 * d'AUCUN état WooCommerce Subscriptions.
 *
 * La section « Votre abonnement digital » ci-dessus est doublement gardée —
 * masquée pour un abonnement résilié, et son corps n'est rendu que si
 * `null !== $subscription`. Y imbriquer le toggle le rendait invisible à
 * exactement la population pour laquelle `_180c_user_is_subscriber()` avait été
 * choisi : les accès nés d'une adhésion sans abonnement (cadeaux, octrois
 * manuels, meta `access_recipes`). Défaut constaté en session authentifiée, pas
 * à la lecture.
 *
 * L'état initial vient de la meta miroir ; le module JS le corrige au
 * chargement en relisant `Notification.permission`, seule vérité.
 * ============================================================
 */
?>
<?php if ( _180c_user_is_subscriber( $user_id ) ) : ?>
<section id="notifications" class="my-account-section" aria-labelledby="notifications-title">
	<header class="my-account-section__header">
		<h2 id="notifications-title" class="my-account-section__title"><?php esc_html_e( 'Vos notifications', '180c' ); ?></h2>
	</header>

	<div class="my-account-optin" data-push-optin>
		<label class="my-account-optin__control">
			<span class="my-account-optin__text">
				<span class="my-account-optin__eyebrow"><?php esc_html_e( 'Notifications', '180c' ); ?></span>
				<span class="my-account-optin__title">
					<?php esc_html_e( 'Recevoir les alertes du site (nouvelles recettes, actualités)', '180c' ); ?>
				</span>
			</span>
			<input
				type="checkbox"
				class="my-account-switch__input"
				role="switch"
				data-push-optin-input
				<?php checked( (bool) get_user_meta( $user_id, '_180c_push_web_optin', true ) ); ?>
				value="1"
			/>
			<span class="my-account-switch" aria-hidden="true">
				<span class="my-account-switch__thumb"></span>
			</span>
		</label>
		<p class="my-account-optin__feedback" role="status" aria-live="polite" aria-atomic="true" hidden></p>
	</div>
</section>
<?php endif; ?>

<?php
/*
============================================================
 * 3. Votre livraison
 * ============================================================ */
?>
<?php if ( $has_shipping_address ) : ?>
<section id="livraison" class="my-account-section" aria-labelledby="livraison-title">
	<header class="my-account-section__header">
		<h2 id="livraison-title" class="my-account-section__title"><?php esc_html_e( 'Adresse de livraison', '180c' ); ?></h2>
	</header>

		<address class="my-account-address">
			<?php
			$name_line = trim( $shipping['first_name'] . ' ' . $shipping['last_name'] );
			if ( '' !== $name_line ) :
				?>
				<p class="my-account-address__line"><?php echo esc_html( $name_line ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $shipping['company'] ) : ?>
				<p class="my-account-address__line"><?php echo esc_html( $shipping['company'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $shipping['address_1'] ) : ?>
				<p class="my-account-address__line"><?php echo esc_html( $shipping['address_1'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $shipping['address_2'] ) : ?>
				<p class="my-account-address__line"><?php echo esc_html( $shipping['address_2'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $shipping['postcode'] || '' !== $shipping['city'] ) : ?>
				<p class="my-account-address__line">
					<?php echo esc_html( trim( $shipping['postcode'] . ' ' . $shipping['city'] ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( '' !== $shipping['country'] ) : ?>
				<p class="my-account-address__line"><?php echo esc_html( $shipping['country'] ); ?></p>
			<?php endif; ?>
		</address>

	<a class="my-account-section__action" href="<?php echo esc_url( $edit_address_url ); ?>">
		<?php esc_html_e( 'Modifier', '180c' ); ?>
	</a>
</section>
<?php endif; ?>

<?php
/*
============================================================
 * 4. Vos commandes
 * ============================================================ */
?>
<section id="factures" class="my-account-section" aria-labelledby="factures-title">
	<header class="my-account-section__header">
		<h2 id="factures-title" class="my-account-section__title"><?php esc_html_e( 'Vos commandes', '180c' ); ?></h2>
	</header>

	<?php if ( empty( $orders ) ) : ?>
		<p class="my-account-empty"><?php esc_html_e( 'Aucune commande pour le moment.', '180c' ); ?></p>
	<?php else : ?>
		<table class="my-account-invoices">
			<thead class="my-account-invoices__head">
				<tr>
					<th scope="col"><?php esc_html_e( 'Date', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( "Type d'achat", '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'N° commande', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Consulter', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Facture PDF', '180c' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order_row ) : ?>
					<tr class="my-account-invoices__row">
						<td class="my-account-invoices__cell my-account-invoices__cell--date" data-label="<?php esc_attr_e( 'Date', '180c' ); ?>">
							<time datetime="<?php echo esc_attr( $order_row['date_iso'] ); ?>">
								<?php echo esc_html( $order_row['date_label'] ); ?>
							</time>
						</td>
						<td class="my-account-invoices__cell my-account-invoices__cell--type" data-label="<?php esc_attr_e( "Type d'achat", '180c' ); ?>">
							<?php echo esc_html( $order_row['type'] ); ?>
						</td>
						<td class="my-account-invoices__cell my-account-invoices__cell--number" data-label="<?php esc_attr_e( 'N° commande', '180c' ); ?>">
							<?php echo esc_html( '#' . $order_row['number'] ); ?>
						</td>
						<td class="my-account-invoices__cell my-account-invoices__cell--view" data-label="<?php esc_attr_e( 'Consulter', '180c' ); ?>">
							<a class="my-account-invoices__link" href="<?php echo esc_url( $order_row['view_url'] ); ?>">
								<?php esc_html_e( 'Voir la commande', '180c' ); ?>
							</a>
						</td>
						<td class="my-account-invoices__cell my-account-invoices__cell--pdf" data-label="<?php esc_attr_e( 'Facture PDF', '180c' ); ?>">
							<?php if ( null !== $order_row['pdf_url'] ) : ?>
								<a class="my-account-invoices__link" href="<?php echo esc_url( $order_row['pdf_url'] ); ?>" target="_blank" rel="noopener">
									<?php esc_html_e( 'Télécharger', '180c' ); ?>
								</a>
							<?php else : ?>
								<span class="my-account-invoices__none" aria-hidden="true"><?php echo esc_html( $em_dash ); ?></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Facture PDF indisponible', '180c' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>

<?php
/*
============================================================
 * 4 bis. Aide et contact
 *    Placé juste après « Vos commandes », donc toujours visible : le module
 *    « Gérer votre abonnement » qui suit est conditionnel (abonnement actif),
 *    et ce bloc ne doit pas dépendre de lui.
 *
 *    La 3e ligne n'apparaît que si un sondage est en cours
 *    (`_180c_survey_url()` rend une chaîne vide sinon) ; les deux premières
 *    sont permanentes.
 *
 *    L'`id` suit le patron des autres sections du tableau de bord, qui en
 *    portent toutes un — il n'est la cible d'aucun lien du thème. Il reste
 *    `nous-contacter` alors que le titre affiché est « Aide et contact » : un
 *    identifiant n'a pas à suivre les renommages d'un libellé, et le changer
 *    casserait tout signet ou lien externe existant pour rien.
 * ============================================================
 */

$survey_url = function_exists( '_180c_survey_url' ) ? _180c_survey_url() : '';
?>
<section id="nous-contacter" class="my-account-section" aria-labelledby="nous-contacter-title">
	<header class="my-account-section__header">
		<h2 id="nous-contacter-title" class="my-account-section__title"><?php esc_html_e( 'Aide et contact', '180c' ); ?></h2>
	</header>

	<ul class="my-account-contact">
		<li class="my-account-contact__row">
			<span class="my-account-contact__text"><?php esc_html_e( 'Vous avez une question ?', '180c' ); ?></span>
			<a class="my-account-contact__link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
				<?php esc_html_e( 'Nous contacter', '180c' ); ?>
			</a>
		</li>
		<li class="my-account-contact__row">
			<span class="my-account-contact__text"><?php esc_html_e( 'Comment pouvons-nous vous aider ?', '180c' ); ?></span>
			<a class="my-account-contact__link" href="<?php echo esc_url( home_url( '/centre-daide/' ) ); ?>">
				<?php esc_html_e( 'Accédez à notre Centre d\'aide', '180c' ); ?>
			</a>
		</li>
		<?php if ( '' !== $survey_url ) : ?>
			<li class="my-account-contact__row">
				<span class="my-account-contact__text"><?php esc_html_e( 'Votre avis sur le site', '180c' ); ?></span>
				<a class="my-account-contact__link" href="<?php echo esc_url( $survey_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Répondre au questionnaire', '180c' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(nouvel onglet)', '180c' ); ?></span>
				</a>
			</li>
		<?php endif; ?>
	</ul>
</section>

<?php
/*
============================================================
 * 5. (supprimé) Gérer votre abonnement
 *    Le module #gerer (ligne « Mettre en pause ») ne rendait que pour un
 *    abonnement actif sans alerte ; il a été retiré du tableau de bord. La
 *    mise en pause reste offerte dans le parcours de rétention du stepper de
 *    résiliation (`parts/account/unsubscribe-stepper.php`, `suspend_url`), et
 *    le lien « Se désabonner » ci-dessous est conservé à l'identique.
 * ============================================================ */

/*
 * Liens de sortie — alignés à gauche, avec un espace supérieur.
 * Volontairement NON accentués (pas jaune).
 *
 *  - « Se désabonner » ouvre le stepper (JS) ; fallback no-JS =
 *    /centre-daide/#resiliation. Affiché uniquement pour un abonnement ACTIF :
 *    en pause (on-hold) le bloc est promu en alerte et le geste attendu est
 *    « Réactiver », pas « Se désabonner ».
 *  - « Supprimer mon compte » mène à l'onglet dédié. Il s'affiche pour les
 *    seuls rôles client, quelle que soit l'éligibilité : un compte encore
 *    bloqué y trouve l'écran qui lui explique pourquoi.
 *
 * Les deux partagent la même classe : ce sont deux gestes de même nature et de
 * même poids visuel.
 */
$show_unsub  = (bool) $sub_is_active;
$show_delete = function_exists( '_180c_account_deletion_menu_visible' ) && _180c_account_deletion_menu_visible();
?>
<?php if ( $show_unsub || $show_delete ) : ?>
	<div class="my-account-exit-links">
		<?php if ( $show_unsub ) : ?>
			<a
				class="my-account-unsub-link"
				href="<?php echo esc_url( home_url( '/centre-daide/' ) . '#resiliation' ); ?>"
				data-unsub-open
			>
				<?php esc_html_e( 'Se désabonner', '180c' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( $show_delete ) : ?>
			<a
				class="my-account-unsub-link"
				href="<?php echo esc_url( wc_get_account_endpoint_url( _180C_ACCOUNT_DELETION_ENDPOINT ) ); ?>"
			>
				<?php esc_html_e( 'Supprimer mon compte', '180c' ); ?>
			</a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php
/*
 * Stepper de résiliation (parcours de rétention) — <dialog> masqué, rendu
 * uniquement pour un abonnement actif (même condition que le lien discret).
 * Markup seul ici ; l'ouverture/bascule/REST est branchée par le JS (Phase 7).
 */
if ( $sub_is_active ) :
	get_template_part(
		'parts/account/unsubscribe-stepper',
		null,
		array(
			'subscription_id' => (int) $managed_sub->get_id(),
			'suspend_url'     => $sub_actions['suspend']['url'] ?? '',
		)
	);
endif;
?>
