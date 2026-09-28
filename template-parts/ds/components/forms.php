<?php
/**
 * Design System — lot « Formulaires & auth ».
 *
 * Documente les formulaires applicatifs du thème :
 *   - Bloc newsletter-form (.block-180c-newsletter-form / .newsletter-form)
 *   - Formulaires d'authentification (.auth-form) : connexion, inscription,
 *     réinitialisation de mot de passe, suppression de compte
 *   - Formulaire de contact (.contact-form)
 *   - Widget Turnstile (Cloudflare, injecté par auth.js)
 *
 * Tous les composants sont en mode « snapshot » : leur rendu réel dépend d'un
 * contexte WP (session utilisateur, nonces, ACF, REST) inaccessible ici.
 * Les placeholders image utilisent <span class="ds-ph ds-ph--wide"> (sans photo
 * recadrée ni overlay) conformément à la règle client.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// 1. Newsletter form — .block-180c-newsletter-form / .newsletter-form
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Newsletter form', '180c' ),
		'bem'      => '.block-180c-newsletter-form',
		'file'     => 'inc/blocks/newsletter-form/render.php + src/css/components/newsletter-form.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'--with-image',
			'__media',
			'__image',
			'__content',
			'__title',
			'__description',
			'__form',
			'__field',
			'__label',
			'__input-group',
			'__input',
			'__submit',
			'__consent',
			'__feedback',
			'.newsletter-form__feedback--success',
			'.newsletter-form__feedback--error',
		),
		'note'     => __( 'Bloc Gutenberg server-rendered. Le contrat CSS de bas niveau est .newsletter-form (footer + partials) ; le bloc émet .block-180c-newsletter-form avec les mêmes éléments. Variante --with-image : panneau image à gauche. Soumission via fetch REST 180c/v1/newsletter/subscribe. La zone de feedback (.newsletter-form__feedback) est pilotée par aria-live="polite" — snapshot HTML figé ici. Reuse des atomes .btn, .label, .input du forms.css.', '180c' ),
		'preview'  => '<div style="max-width:600px;">'
			. '<section class="block-180c-newsletter-form" style="border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;">'
			. '<div class="block-180c-newsletter-form__content" style="padding:var(--space-xl);">'
			. '<h2 class="block-180c-newsletter-form__title" style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;margin:0 0 var(--space-xs);">Restez aux fourneaux</h2>'
			. '<p class="block-180c-newsletter-form__description" style="font-size:var(--text-sm);color:var(--color-fg-muted);margin:0 0 var(--space-md);">Recettes, astuces et actus culinaires — chaque semaine dans votre boîte.</p>'
			. '<form class="block-180c-newsletter-form__form" novalidate>'
			. '<div class="block-180c-newsletter-form__field" style="margin-bottom:var(--space-xs);">'
			. '<label class="block-180c-newsletter-form__label" for="ds-nl-email" style="display:block;font-size:var(--text-sm);margin-bottom:var(--space-3xs);">Adresse e-mail</label>'
			. '<div class="block-180c-newsletter-form__input-group" style="display:flex;gap:0;">'
			. '<input id="ds-nl-email" class="block-180c-newsletter-form__input input" type="email" name="email" placeholder="vous@example.com" autocomplete="email" required aria-required="true" style="flex:1;min-width:0;border-top-right-radius:0;border-bottom-right-radius:0;border-right:0;">'
			. '<button class="btn btn--primary block-180c-newsletter-form__submit" type="submit" style="border-top-left-radius:0;border-bottom-left-radius:0;white-space:nowrap;">S\'inscrire</button>'
			. '</div>'
			. '</div>'
			. '<p class="block-180c-newsletter-form__consent newsletter-form__consent">En vous inscrivant, vous recevez un e-mail de confirmation (double opt-in). Désabonnement possible à tout moment.</p>'
			. '<p class="newsletter-form__feedback newsletter-form__feedback--success" role="status" aria-live="polite" style="margin-top:var(--space-xs);padding:var(--space-2xs) var(--space-xs);border-radius:var(--radius-sm);background:color-mix(in srgb,var(--color-success) 12%,transparent);color:var(--color-success);">Parfait ! Consultez votre boîte mail pour confirmer votre inscription.</p>'
			. '</form>'
			. '</div>'
			. '</section>'
			. '<p style="margin-top:var(--space-sm);font-size:var(--text-xs);color:var(--color-fg-muted);">Variante --with-image : ajoute un <code>.block-180c-newsletter-form__media</code> avec placeholder ci-dessous.</p>'
			. '<div style="border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;display:grid;grid-template-columns:1fr 1fr;margin-top:var(--space-xs);">'
			. '<span class="ds-ph ds-ph--wide" aria-hidden="true" style="height:180px;"></span>'
			. '<div style="padding:var(--space-md);"><p style="font-size:var(--text-sm);color:var(--color-fg-muted);">Même formulaire dans le volet droit.</p></div>'
			. '</div>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 2. Auth — connexion — .auth-form
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Auth — Connexion', '180c' ),
		'bem'      => '.auth-form',
		'file'     => 'parts/auth/login-form.php + src/css/components/auth.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__header',
			'__title',
			'__header-link',
			'__alt-link',
			'__body',
			'__field',
			'__row',
			'__submit',
			'__submit-btn',
			'__footer',
			'.auth-message--error',
			'.auth-message--success',
			'.auth-message--info',
		),
		'note'     => __( 'Formulaire de connexion : e-mail / identifiant + mot de passe + case « Se souvenir de moi ». Lien vers réinitialisation et création de compte. .auth-message--error illustre un identifiant incorrect. .auth-message--success illustre la confirmation post-réinitialisation. Les .auth-form__field héritent des atomes .field / .input / .label / .checkbox. Turnstile conditionnel (voir composant dédié). Snapshot : nonces et session WP inaccessibles.', '180c' ),
		'preview'  => '<div style="max-width:480px;">'
			. '<div class="auth-form" role="main" aria-labelledby="ds-login-title">'
			. '<div class="auth-form__header">'
			. '<h2 class="auth-form__title" id="ds-login-title" style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;margin:0 0 var(--space-2xs);text-align:center;">Connexion</h2>'
			. '<p class="auth-form__header-link" style="text-align:center;font-size:var(--text-sm);color:var(--color-fg-muted);margin:0;">Pas encore de compte ? <a class="auth-form__alt-link" href="#">Créer un compte</a></p>'
			. '</div>'
			. '<div class="auth-message auth-message--success" role="status" aria-live="polite" style="margin-top:var(--space-sm);">Mot de passe mis à jour. Vous pouvez vous connecter.</div>'
			. '<div class="auth-message auth-message--error" role="alert" aria-live="polite">Identifiant ou mot de passe incorrect.</div>'
			. '<form class="auth-form__body" novalidate>'
			. '<div class="auth-form__field field field--error" style="margin-bottom:var(--space-sm);">'
			. '<label class="label is-required" for="ds-log">Adresse e-mail</label>'
			. '<input class="input" type="text" id="ds-log" name="log" autocomplete="username" aria-invalid="true">'
			. '</div>'
			. '<div class="auth-form__field field field--error" style="margin-bottom:var(--space-xs);">'
			. '<label class="label is-required" for="ds-pwd">Mot de passe</label>'
			. '<input class="input" type="password" id="ds-pwd" name="pwd" autocomplete="current-password" aria-invalid="true">'
			. '<span class="field__error" role="alert">Identifiant ou mot de passe incorrect.</span>'
			. '</div>'
			. '<div class="auth-form__field field" style="margin-bottom:var(--space-md);">'
			. '<label class="checkbox"><input class="checkbox__input" type="checkbox"> <span class="checkbox__label">Se souvenir de moi</span></label>'
			. '</div>'
			. '<div class="auth-form__submit">'
			. '<button type="submit" class="btn btn--primary auth-form__submit-btn" style="width:100%;justify-content:center;">Se connecter</button>'
			. '</div>'
			. '<div class="auth-form__footer" style="text-align:center;margin-top:var(--space-sm);">'
			. '<a class="auth-form__alt-link" href="#">Mot de passe oublié ?</a>'
			. '</div>'
			. '</form>'
			. '</div>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 3. Auth — inscription — .auth-form
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Auth — Inscription', '180c' ),
		'bem'      => '.auth-form',
		'file'     => 'parts/auth/register-form.php + src/css/components/auth.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__row (prénom / nom sur 2 colonnes)',
			'__cgu (case CGU obligatoire)',
			'.field__helper (indice mot de passe)',
			'.checkbox (newsletter opt-in)',
		),
		'note'     => __( 'Formulaire d\'inscription avec prénom, nom, e-mail et mot de passe (min. 10 car.). La rangée __row passe sur 2 colonnes ≥361px. Double opt-in newsletter (checkbox pré-cochée). Case CGU obligatoire. Snapshot : nonces et session inaccessibles.', '180c' ),
		'preview'  => '<div style="max-width:480px;">'
			. '<div class="auth-form" role="main" aria-labelledby="ds-register-title">'
			. '<div class="auth-form__header">'
			. '<h2 class="auth-form__title" id="ds-register-title" style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;margin:0 0 var(--space-2xs);text-align:center;">Créer un compte</h2>'
			. '<p class="auth-form__header-link" style="text-align:center;font-size:var(--text-sm);color:var(--color-fg-muted);margin:0;">Déjà inscrit ? <a class="auth-form__alt-link" href="#">Se connecter</a></p>'
			. '</div>'
			. '<form class="auth-form__body" novalidate style="margin-top:var(--space-md);">'
			. '<div class="auth-form__row" style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-sm);margin-bottom:var(--space-sm);">'
			. '<div class="auth-form__field field">'
			. '<label class="label is-required" for="ds-firstname">Prénom</label>'
			. '<input class="input" type="text" id="ds-firstname" name="firstname" autocomplete="given-name" required aria-required="true">'
			. '</div>'
			. '<div class="auth-form__field field">'
			. '<label class="label is-required" for="ds-lastname">Nom</label>'
			. '<input class="input" type="text" id="ds-lastname" name="lastname" autocomplete="family-name" required aria-required="true">'
			. '</div>'
			. '</div>'
			. '<div class="auth-form__field field" style="margin-bottom:var(--space-sm);">'
			. '<label class="label is-required" for="ds-email-reg">Adresse e-mail</label>'
			. '<input class="input" type="email" id="ds-email-reg" name="email" autocomplete="email" required aria-required="true">'
			. '</div>'
			. '<div class="auth-form__field field" style="margin-bottom:var(--space-sm);">'
			. '<label class="label is-required" for="ds-password-reg">Mot de passe</label>'
			. '<input class="input" type="password" id="ds-password-reg" name="password" autocomplete="new-password" required aria-required="true" aria-describedby="ds-pwd-hint">'
			. '<span class="field__helper" id="ds-pwd-hint">Minimum 10 caractères.</span>'
			. '</div>'
			. '<div class="auth-form__field field" style="margin-bottom:var(--space-2xs);">'
			. '<label class="checkbox"><input class="checkbox__input" type="checkbox" checked> <span class="checkbox__label">Je m\'inscris à la newsletter gratuite de 180°C</span></label>'
			. '</div>'
			. '<div class="auth-form__cgu field" style="margin-bottom:var(--space-md);">'
			. '<label class="checkbox"><input class="checkbox__input" type="checkbox" required aria-required="true"> <span class="checkbox__label">J\'accepte les <a href="#">Conditions Générales d\'Utilisation</a></span></label>'
			. '</div>'
			. '<div class="auth-form__submit">'
			. '<button type="submit" class="btn btn--primary auth-form__submit-btn" style="width:100%;justify-content:center;">Créer mon compte</button>'
			. '</div>'
			. '</form>'
			. '</div>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 4. Auth — réinitialisation de mot de passe — .auth-form
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Auth — Réinitialisation de mot de passe', '180c' ),
		'bem'      => '.auth-form',
		'file'     => 'parts/auth/password-reset-form.php + src/css/components/auth.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__header-desc (texte d\'explication)',
			'.auth-message--info (lien envoyé)',
			'.auth-message--error (e-mail inconnu)',
		),
		'note'     => __( 'Formulaire « mot de passe oublié ». Un seul champ e-mail. Après soumission réussie, le formulaire est masqué et .auth-message--info confirme l\'envoi. .auth-message--error signale une adresse inconnue. Lien de retour vers la connexion. Snapshot : nonces inaccessibles.', '180c' ),
		'preview'  => '<div style="max-width:480px;">'
			. '<div class="auth-form" role="main" aria-labelledby="ds-forgot-title">'
			. '<div class="auth-form__header">'
			. '<h2 class="auth-form__title" id="ds-forgot-title" style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;margin:0 0 var(--space-2xs);text-align:center;">Mot de passe oublié</h2>'
			. '<p class="auth-form__header-desc" style="text-align:center;font-size:var(--text-sm);color:var(--color-fg-muted);margin:0;">Saisissez votre adresse e-mail, nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>'
			. '</div>'
			. '<div class="auth-message auth-message--info" role="status" aria-live="polite" style="margin-top:var(--space-sm);">Un lien de réinitialisation a été envoyé à votre adresse.</div>'
			. '<form class="auth-form__body" novalidate style="margin-top:var(--space-md);">'
			. '<div class="auth-form__field field" style="margin-bottom:var(--space-md);">'
			. '<label class="label is-required" for="ds-email-forgot">Adresse e-mail</label>'
			. '<input class="input" type="email" id="ds-email-forgot" name="email" autocomplete="email" required aria-required="true">'
			. '</div>'
			. '<div class="auth-form__submit">'
			. '<button type="submit" class="btn btn--primary auth-form__submit-btn" style="width:100%;justify-content:center;">Envoyer le lien de réinitialisation</button>'
			. '</div>'
			. '</form>'
			. '<div class="auth-form__footer" style="text-align:center;margin-top:var(--space-sm);">'
			. '<a class="auth-form__alt-link" href="#">Retour à la connexion</a>'
			. '</div>'
			. '</div>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 5. Auth — suppression de compte — .account-danger-zone
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Auth — Suppression de compte', '180c' ),
		'bem'      => '.account-danger-zone',
		'file'     => 'parts/auth/delete-account.php + src/css/components/auth.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__title',
			'__desc',
			'__form',
			'.btn--danger (CTA de confirmation)',
			'.auth-message--error (abonnement actif / échec mail)',
			'.auth-message--info (demande envoyée)',
		),
		'note'     => __( 'Section RGPD incluse dans /mon-compte/profil/. Affichée uniquement pour un utilisateur connecté. Le bouton .btn--danger déclenche une confirmation JS avant soumission. En cas d\'abonnement actif un .auth-message--error avec lien de gestion est affiché. Après soumission : .auth-message--info confirme l\'envoi de l\'e-mail de confirmation. Snapshot : is_user_logged_in() retournerait false ici.', '180c' ),
		'preview'  => '<div style="max-width:560px;">'
			. '<section class="account-danger-zone" aria-labelledby="ds-danger-title" style="margin-top:0;padding-top:var(--space-xl);border-top:1px solid var(--color-border);">'
			. '<h2 class="account-danger-zone__title" id="ds-danger-title">Zone de danger</h2>'
			. '<div class="auth-message auth-message--error" role="alert" style="margin-bottom:var(--space-md);">Vous avez un abonnement actif. Veuillez le résilier avant de supprimer votre compte. <a href="#" class="btn btn--secondary btn--sm" style="margin-left:var(--space-xs);">Gérer mon abonnement</a></div>'
			. '<p class="account-danger-zone__desc">La suppression de votre compte est définitive et irréversible. Toutes vos données personnelles, vos favoris et votre historique seront supprimés.</p>'
			. '<form class="account-danger-zone__form">'
			. '<button type="submit" class="btn btn--danger">Supprimer mon compte</button>'
			. '</form>'
			. '</section>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 6. Formulaire de contact — .contact-form
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Formulaire de contact', '180c' ),
		'bem'      => '.contact-form',
		'file'     => 'page-contact.php + src/css/components/contact.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__field',
			'__label',
			'__input',
			'__select',
			'__textarea',
			'__helper',
			'__counter',
			'__error',
			'__submit',
			'__rgpd',
			'__status (--success / --error)',
			'__gotcha (honeypot, invisible)',
		),
		'note'     => __( 'Template page-contact.php. Soumission via fetch vers l\'endpoint natif POST 180c/v1/contact (inc/rest/contact.php), qui achemine le message par wp_mail() : nonce cf_nonce rafraîchi via GET 180c/v1/contact/nonce, honeypot vérifié côté serveur, rate-limit 3 requêtes / 5 min par IP. Le routage est porté par la clé data-objet de l\'option choisie, recopiée par le JS (src/js/modules/contact.js) dans le champ caché objet. Honeypot .contact-form__gotcha hors flux (position:absolute left:-9999px). Compteur de caractères sur le textarea (20 min). La zone .contact-form__status est pilotée en aria-live par contact.js. Snapshot : nonce et endpoint non disponibles ici.', '180c' ),
		'preview'  => '<div style="max-width:520px;">'
			. '<form class="contact-form" novalidate style="display:flex;flex-direction:column;gap:var(--space-sm);">'
			. '<div class="contact-form__field">'
			. '<label class="contact-form__label" for="ds-cf-name">Prénom et nom</label>'
			. '<input class="contact-form__input" type="text" id="ds-cf-name" name="name" placeholder="Prénom et nom" autocomplete="name" required aria-required="true">'
			. '<p class="contact-form__error" aria-live="polite"></p>'
			. '</div>'
			. '<div class="contact-form__field">'
			. '<label class="contact-form__label" for="ds-cf-email">Adresse e-mail</label>'
			. '<input class="contact-form__input" type="email" id="ds-cf-email" name="email" placeholder="vous@mail.com" autocomplete="email" required aria-required="true">'
			. '<p class="contact-form__helper">Nous vous répondrons à cette adresse.</p>'
			. '<p class="contact-form__error" aria-live="polite"></p>'
			. '</div>'
			. '<div class="contact-form__field">'
			. '<label class="contact-form__label" for="ds-cf-subject">Objet de votre message</label>'
			. '<select class="contact-form__select" id="ds-cf-subject" name="_subject" required aria-required="true">'
			. '<option value="" selected disabled>— Choisissez l\'objet —</option>'
			. '<option value="abonnement">Abonnement</option>'
			. '<option value="commande">Commande</option>'
			. '<option value="redaction">Rédaction</option>'
			. '<option value="autre">Autre</option>'
			. '</select>'
			. '<p class="contact-form__error" aria-live="polite"></p>'
			. '</div>'
			. '<div class="contact-form__field">'
			. '<label class="contact-form__label" for="ds-cf-message">Votre message</label>'
			. '<textarea class="contact-form__textarea" id="ds-cf-message" name="message" rows="6" placeholder="Décrivez votre demande en quelques lignes." required aria-required="true" minlength="20"></textarea>'
			. '<p class="contact-form__counter" aria-live="polite">0 / 20 caractères minimum</p>'
			. '<p class="contact-form__error" aria-live="polite"></p>'
			. '</div>'
			. '<button class="contact-form__submit" type="submit">Envoyer le message</button>'
			. '<p class="contact-form__rgpd">Vos données sont utilisées uniquement pour traiter votre demande. Elles ne sont ni cédées, ni utilisées à des fins commerciales. <a href="#">Politique de confidentialité</a>.</p>'
			. '<p class="contact-form__status contact-form__status--success" role="status" aria-live="polite" style="margin-top:var(--space-xs);">Votre message a bien été envoyé. Nous vous répondrons sous 48 h.</p>'
			. '</form>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 7. Widget Turnstile (Cloudflare)
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Turnstile — widget anti-bot', '180c' ),
		'bem'      => '.turnstile-container',
		'file'     => 'inc/auth/rate-limit.php → _180c_should_show_turnstile() / _180c_turnstile_site_key()',
		'mode'     => 'snapshot',
		'variants' => array(),
		'note'     => __( 'Widget Cloudflare Turnstile injecté conditionnellement dans les formulaires d\'authentification (login + register). _180c_should_show_turnstile( $ip ) retourne true lorsque le seuil de tentatives est dépassé ou que la clé de site est configurée. Le conteneur .turnstile-container (min-height:65px) reçoit data-sitekey et est peuplé par auth.js via l\'API Turnstile côté client (window.turnstile.render). Snapshot : le widget JS externe n\'est pas disponible dans l\'aperçu du Design System.', '180c' ),
		'preview'  => '<div style="max-width:340px;">'
			. '<p style="font-size:var(--text-xs);color:var(--color-fg-muted);margin:0 0 var(--space-xs);">Rendu conditionnel — affiché après seuil de tentatives ou si clé Turnstile configurée.</p>'
			. '<div class="turnstile-container" data-sitekey="[SITE_KEY]" style="background:var(--color-bg-elevated);border:1px dashed var(--color-border);border-radius:var(--radius-md);min-height:65px;display:flex;align-items:center;justify-content:center;padding:var(--space-xs);">'
			. '<span style="font-size:var(--text-xs);color:var(--color-fg-muted);font-family:var(--font-display);letter-spacing:0.04em;text-transform:uppercase;">Widget Cloudflare Turnstile — injecté par auth.js</span>'
			. '</div>'
			. '<p style="font-size:var(--text-xs);color:var(--color-fg-muted);margin:var(--space-xs) 0 0;">Fonctions associées : <code>_180c_turnstile_site_key()</code>, <code>_180c_validate_turnstile()</code>, <code>_180c_render_turnstile_site_key_field()</code> (réglages WP Admin).</p>'
			. '</div>',
	)
);
