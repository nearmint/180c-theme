<?php
/**
 * Edit address form — surcharge 180°C (DS).
 *
 * Rend DANS le shell .my-account (sidebar + content) via my-account.php.
 * Le formulaire WC est conservé à l'identique (hooks, champs générés par
 * `woocommerce_form_field()`, nonce, action) et enveloppé dans une section DS ;
 * la mise au design system (labels, inputs, bouton) est portée par
 * my-account.css (.my-account-form / .my-account-actions__btn).
 *
 * Variables fournies par WooCommerce : $load_address, $address, $page_title.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$page_title = ( 'billing' === $load_address )
	? esc_html__( 'Adresse de facturation', '180c' )
	: esc_html__( 'Adresse de livraison', '180c' );

do_action( 'woocommerce_before_edit_account_address_form' );
?>

<a class="account-back-link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) . '#livraison' ); ?>">
	<span class="account-back-link__icon" aria-hidden="true">&larr;</span>
	<span class="account-back-link__label"><?php esc_html_e( 'Retour à mon compte', '180c' ); ?></span>
</a>

<?php if ( ! $load_address ) : ?>
	<?php wc_get_template( 'myaccount/my-address.php' ); ?>
<?php else : ?>

	<section class="my-account-section" aria-labelledby="edit-address-title">
		<header class="my-account-section__header">
			<h2 id="edit-address-title" class="my-account-section__title"><?php echo esc_html( apply_filters( 'woocommerce_my_account_edit_address_title', $page_title, $load_address ) ); ?></h2>
		</header>

		<form method="post" class="my-account-form my-account-form--address" novalidate>

			<div class="woocommerce-address-fields">
				<?php do_action( "woocommerce_before_edit_address_form_{$load_address}" ); ?>

				<div class="woocommerce-address-fields__field-wrapper">
					<?php
					foreach ( $address as $key => $field ) {
						woocommerce_form_field( $key, $field, wc_get_post_data_by_key( $key, $field['value'] ) );
					}
					?>
				</div>

				<?php do_action( "woocommerce_after_edit_address_form_{$load_address}" ); ?>

				<p class="my-account-form__actions">
					<button type="submit" class="woocommerce-Button button my-account-actions__btn my-account-actions__btn--primary" name="save_address" value="<?php esc_attr_e( "Enregistrer l'adresse", '180c' ); ?>"><?php esc_html_e( "Enregistrer l'adresse", '180c' ); ?></button>
					<?php wp_nonce_field( 'woocommerce-edit_address', 'woocommerce-edit-address-nonce' ); ?>
					<input type="hidden" name="action" value="edit_address" />
				</p>
			</div>

		</form>

	</section>

<?php endif; ?>

<?php do_action( 'woocommerce_after_edit_account_address_form' ); ?>
