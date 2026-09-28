<?php
/**
 * Design System — lot « Atomes ».
 *
 * Primitives UI : boutons, champs de formulaire, inputs, checkbox, icône.
 * Aperçus rendus avec le vrai markup BEM (composants purement présentationnels).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---- Boutons ----
_180c_ds_component(
	array(
		'name'     => __( 'Bouton', '180c' ),
		'bem'      => '.btn',
		'file'     => 'src/css/components/buttons.css',
		'mode'     => 'live',
		'variants' => array( '--primary', '--secondary', '--ghost', '--danger', '--link', '--sm', '--lg' ),
		'note'     => __( 'Atome bouton. .btn--primary = CTA accent ; --ghost = contour ; --link = lien stylé. Tailles --sm/--lg.', '180c' ),
		'preview'  => '<div class="ds-row">'
			. '<button type="button" class="btn btn--primary">Primary</button>'
			. '<button type="button" class="btn btn--secondary">Secondary</button>'
			. '<button type="button" class="btn btn--ghost">Ghost</button>'
			. '<button type="button" class="btn btn--danger">Danger</button>'
			. '<button type="button" class="btn btn--link">Link</button>'
			. '<button type="button" class="btn btn--primary btn--sm">Small</button>'
			. '<button type="button" class="btn btn--primary btn--lg">Large</button>'
			. '</div>',
	)
);

// ---- Champ de formulaire ----
_180c_ds_component(
	array(
		'name'     => __( 'Champ de formulaire', '180c' ),
		'bem'      => '.field',
		'file'     => 'src/css/components/forms.css',
		'mode'     => 'live',
		'variants' => array( '.field--error', '.field__helper', '.field__error', '.is-required' ),
		'note'     => __( 'Groupe label + input + aide/erreur. .is-required ajoute l’astérisque. .field--error colore le bord + affiche .field__error.', '180c' ),
		'preview'  => '<div class="ds-stack ds-stack--sm">'
			. '<div class="field is-required">'
			. '<label class="label" for="ds-field-1">Adresse e-mail</label>'
			. '<input class="input" type="email" id="ds-field-1" placeholder="vous@example.com">'
			. '<p class="field__helper">Nous ne partageons jamais votre adresse.</p>'
			. '</div>'
			. '<div class="field field--error">'
			. '<label class="label" for="ds-field-2">Mot de passe</label>'
			. '<input class="input" type="password" id="ds-field-2" aria-invalid="true">'
			. '<p class="field__error">Le mot de passe est trop court.</p>'
			. '</div>'
			. '</div>',
	)
);

// ---- Input seul ----
_180c_ds_component(
	array(
		'name'    => __( 'Input', '180c' ),
		'bem'     => '.input',
		'file'    => 'src/css/components/forms.css',
		'mode'    => 'live',
		'note'    => __( 'Champ texte de base. États focus/disabled gérés par le composant forms.', '180c' ),
		'preview' => '<div class="ds-stack ds-stack--sm">'
			. '<input class="input" type="text" value="Texte saisi">'
			. '<input class="input" type="text" placeholder="Placeholder" disabled>'
			. '</div>',
	)
);

// ---- Checkbox ----
_180c_ds_component(
	array(
		'name'     => __( 'Checkbox', '180c' ),
		'bem'      => '.checkbox',
		'file'     => 'src/css/components/forms.css',
		'mode'     => 'live',
		'variants' => array( '.checkbox__input', '.checkbox__label' ),
		'note'     => __( 'Case à cocher accessible (label cliquable). Utilisée pour les consentements (newsletter, RGPD).', '180c' ),
		'preview'  => '<label class="checkbox">'
			. '<input class="checkbox__input" type="checkbox" checked>'
			. '<span class="checkbox__label">J’accepte de recevoir la newsletter.</span>'
			. '</label>',
	)
);

// ---- Icône SVG ----
_180c_ds_component(
	array(
		'name'    => __( 'Icône SVG', '180c' ),
		'bem'     => '<svg> inline',
		'file'    => '_180c_render_svg_icon() (inc/helpers.php)',
		'mode'    => 'snapshot',
		'note'    => __( 'Icônes injectées en inline SVG via _180c_render_svg_icon( $name ). Snapshot : le rendu réel dépend du nom d’icône passé. currentColor hérite de la couleur de texte.', '180c' ),
		'preview' => '<div class="ds-row" style="color: var(--ink);">'
			. '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/></svg>'
			. '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>'
			. '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>'
			. '</div>',
	)
);
