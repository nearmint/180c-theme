<?php
/**
 * Edit account form — surcharge 180°C (DS).
 *
 * Rend DANS le shell .my-account (sidebar + content) via my-account.php.
 * Le formulaire WC est conservé à l'identique (hooks, champs, nonce, action)
 * et enveloppé dans une section DS ; la mise au design system (labels, inputs,
 * fieldset, bouton) est portée par my-account.css (.my-account-form).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook - woocommerce_before_edit_account_form.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_before_edit_account_form' );
?>

<a class="account-back-link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) . '#informations' ); ?>">
	<span class="account-back-link__icon" aria-hidden="true">&larr;</span>
	<span class="account-back-link__label"><?php esc_html_e( 'Retour à mon compte', '180c' ); ?></span>
</a>

<section class="my-account-section" aria-labelledby="edit-account-title">
	<header class="my-account-section__header">
		<h2 id="edit-account-title" class="my-account-section__title"><?php esc_html_e( 'Mon profil', '180c' ); ?></h2>
	</header>

	<form class="woocommerce-EditAccountForm edit-account my-account-form" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

		<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

		<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
			<label for="account_first_name"><?php esc_html_e( 'Prénom', '180c' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
			<label for="account_last_name"><?php esc_html_e( 'Nom', '180c' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
		</p>
		<div class="clear"></div>

		<?php
		/*
		 * Champ « Nom affiché » masqué (DS) : WooCommerce exige une valeur non
		 * vide à l'enregistrement (save_account_details). On conserve donc le
		 * champ dans le DOM, pré-rempli avec la valeur courante, et on masque
		 * sa ligne en CSS (.my-account-form__row--hidden) plutôt que de le
		 * retirer — ce qui déclencherait l'erreur « Nom affiché obligatoire ».
		 */
		?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide my-account-form__row--hidden">
			<label for="account_display_name"><?php esc_html_e( 'Nom affiché', '180c' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" /> <span id="account_display_name_description"><em><?php esc_html_e( 'Nom affiché dans votre espace compte et dans les avis.', '180c' ); ?></em></span>
		</p>
		<div class="clear"></div>

		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="account_email"><?php esc_html_e( 'Adresse e-mail', '180c' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
		</p>

		<?php
			/**
			 * Hook where additional fields should be rendered.
			 *
			 * @since 8.7.0
			 */
			do_action( 'woocommerce_edit_account_form_fields' );
		?>

		<fieldset class="my-account-form__fieldset">
			<legend><?php esc_html_e( 'Changer de mot de passe', '180c' ); ?></legend>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password_current"><?php esc_html_e( 'Mot de passe actuel (laisser vide pour ne pas changer)', '180c' ); ?></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" />
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password_1"><?php esc_html_e( 'Nouveau mot de passe (laisser vide pour ne pas changer)', '180c' ); ?></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" />
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password_2"><?php esc_html_e( 'Confirmer le nouveau mot de passe', '180c' ); ?></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" />
			</p>
		</fieldset>
		<div class="clear"></div>

		<?php
			/**
			 * My Account edit account form.
			 *
			 * @since 2.6.0
			 */
			do_action( 'woocommerce_edit_account_form' );
		?>

		<p class="my-account-form__actions">
			<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
			<button type="submit" class="woocommerce-Button button my-account-actions__btn my-account-actions__btn--primary" name="save_account_details" value="<?php esc_attr_e( 'Enregistrer', '180c' ); ?>"><?php esc_html_e( 'Enregistrer', '180c' ); ?></button>
			<input type="hidden" name="action" value="save_account_details" />
		</p>

		<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
	</form>
</section>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
