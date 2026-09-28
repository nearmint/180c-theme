<?php
/**
 * Thankyou page — surcharge 180°C.
 *
 * Page de confirmation de commande avec titre, récap et CTA.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.1.0
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order checkout-thankyou">

	<?php if ( $order ) : ?>

		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<div class="checkout-thankyou__error">
				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed">
					<?php esc_html_e( 'Votre commande n\'a pas pu être traitée : la banque ou le prestataire de paiement a refusé la transaction. Veuillez réessayer.', '180c' ); ?>
				</p>
				<div class="checkout-thankyou__error-actions">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="btn btn--primary">
						<?php esc_html_e( 'Réessayer le paiement', '180c' ); ?>
					</a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="btn btn--secondary">
							<?php esc_html_e( 'Mon compte', '180c' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

		<?php else : ?>

			<?php
			// Nature de la commande (le panier est vidé à ce stade : on lit la commande).
			$has_sub      = function_exists( '_180c_order_has_subscription' ) && _180c_order_has_subscription( $order );
			$has_physical = false;
			foreach ( $order->get_items() as $thankyou_item ) {
				$thankyou_product = $thankyou_item->get_product();
				if ( $thankyou_product && $thankyou_product->needs_shipping() ) {
					$has_physical = true;
					break;
				}
			}

			if ( $has_sub && ! $has_physical ) {
				$thankyou_title    = __( 'Bienvenue chez 180°C !', '180c' );
				$thankyou_subtitle = __( 'Votre abonnement est confirmé, merci de votre confiance. Vous avez désormais accès à toutes les recettes, à tous les reportages et à l\'application mobile. Bonne lecture, et bonne cuisine !', '180c' );
			} elseif ( $has_physical && ! $has_sub ) {
				$thankyou_title    = __( 'Commande confirmée', '180c' );
				$thankyou_subtitle = __( 'Merci ! Votre commande est enregistrée ; vous recevrez un e-mail de confirmation.', '180c' );
			} else {
				$thankyou_title    = __( 'Merci pour votre commande !', '180c' );
				$thankyou_subtitle = __( 'Votre commande a bien été enregistrée. Vous allez recevoir un e-mail de confirmation.', '180c' );
			}
			?>

			<div class="checkout-thankyou__header">
				<div class="checkout-thankyou__icon" aria-hidden="true">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
						<path d="M7.5 12l3 3 6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<h1 class="checkout-thankyou__title"><?php echo esc_html( $thankyou_title ); ?></h1>
				<p class="checkout-thankyou__subtitle"><?php echo esc_html( $thankyou_subtitle ); ?></p>
			</div>

			<?php
			// Note WC core « Merci. Votre commande a été reçue. » volontairement
			// non rendue : le header 180°C ci-dessus porte déjà le message.
			?>

			<div class="checkout-thankyou__recap">
				<?php
				// Liste 100 % custom : on n'applique PAS les classes WC core
				// `order_details` / `woocommerce-order-overview` qui imposent un
				// layout flottant en colonnes (uppercase, bordures pointillées) et
				// cassaient le rendu vertical label/valeur du récap.
				?>
				<ul class="checkout-thankyou__details">

					<li class="woocommerce-order-overview__order">
						<span class="checkout-thankyou__detail-label"><?php esc_html_e( 'Numéro de commande', '180c' ); ?></span>
						<strong class="checkout-thankyou__detail-value"><?php echo $order->get_order_number(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
					</li>

					<li class="woocommerce-order-overview__date">
						<span class="checkout-thankyou__detail-label"><?php esc_html_e( 'Date', '180c' ); ?></span>
						<strong class="checkout-thankyou__detail-value"><?php echo wc_format_datetime( $order->get_date_created() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
					</li>

					<?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
						<li class="woocommerce-order-overview__email">
							<span class="checkout-thankyou__detail-label"><?php esc_html_e( 'Email', '180c' ); ?></span>
							<strong class="checkout-thankyou__detail-value"><?php echo $order->get_billing_email(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
						</li>
					<?php endif; ?>

					<li class="woocommerce-order-overview__total">
						<span class="checkout-thankyou__detail-label"><?php esc_html_e( 'Total', '180c' ); ?></span>
						<strong class="checkout-thankyou__detail-value checkout-thankyou__total"><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
					</li>

					<?php if ( $order->get_payment_method_title() ) : ?>
						<li class="woocommerce-order-overview__payment-method">
							<span class="checkout-thankyou__detail-label"><?php esc_html_e( 'Moyen de paiement', '180c' ); ?></span>
							<strong class="checkout-thankyou__detail-value"><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
						</li>
					<?php endif; ?>

				</ul>
			</div>

			<?php if ( $has_sub ) : ?>
				<?php
				// Bouton de lancement de l'onboarding, sous le récap. Masqué sans
				// JS (l'onboarding est alors rendu empilé juste en dessous) ; le
				// module le révèle et le câble pour ouvrir la modale.
				?>
				<div class="_180c-onboarding-launch" hidden>
					<p class="_180c-onboarding-launch__intro">
						<?php esc_html_e( 'Prenez un instant pour découvrir tout ce que votre abonnement vous réserve.', '180c' ); ?>
					</p>
					<button type="button" class="_180c-onboarding-launch__btn" data-onboarding-launch>
						<?php esc_html_e( 'Découvrir mon abonnement', '180c' ); ?>
					</button>
				</div>

				<?php
				// Onboarding abonné : 5 écrans de bienvenue. Sans JS, rendu empilé
				// ici sous le récap ; avec JS, déplacé en <body> et présenté en
				// modale au clic sur le bouton ci-dessus. Les hooks
				// `woocommerce_thankyou` ci-dessous restent intacts.
				get_template_part(
					'template-parts/onboarding/carousel',
					null,
					array( 'order' => $order )
				);
				?>
			<?php endif; ?>

			<?php // Bloc « suite » : suivi physique et/ou repli accueil. Vide (donc non rendu) pour un abonnement seul, dont la suite passe par l'onboarding. ?>
			<?php if ( $has_physical || ! $has_sub ) : ?>
			<div class="checkout-thankyou__next">

				<?php if ( $has_physical ) : ?>
					<section class="checkout-thankyou__block checkout-thankyou__block--physical" aria-labelledby="thankyou-ship-heading">
						<h2 id="thankyou-ship-heading" class="checkout-thankyou__block-title"><?php esc_html_e( 'Suivi de votre commande', '180c' ); ?></h2>
						<p class="checkout-thankyou__block-text">
							<?php
							printf(
								/* translators: %s : numéro de commande. */
								esc_html__( 'Votre commande %s est en préparation. Vous recevrez un e-mail dès son expédition.', '180c' ),
								'<strong>' . esc_html( $order->get_order_number() ) . '</strong>'
							);
							?>
						</p>
						<div class="checkout-thankyou__cta">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary">
								<?php esc_html_e( 'Retour à l\'accueil', '180c' ); ?>
							</a>
							<?php if ( is_user_logged_in() ) : ?>
								<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="btn btn--secondary">
									<?php esc_html_e( 'Mon compte', '180c' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( ! $has_sub && ! $has_physical ) : ?>
					<div class="checkout-thankyou__cta">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary">
							<?php esc_html_e( 'Retour à l\'accueil', '180c' ); ?>
						</a>
						<?php if ( is_user_logged_in() ) : ?>
							<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="btn btn--secondary">
								<?php esc_html_e( 'Mon compte', '180c' ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			</div>
			<?php endif; ?>

		<?php endif; ?>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

	<?php else : ?>

		<?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>

	<?php endif; ?>

</div>
