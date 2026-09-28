/**
 * Configuration Stylelint du thème 180°C.
 *
 * Base : stylelint-config-standard. On désactive ce qui entre en conflit avec
 * Tailwind v4 (at-rules custom : @theme, @apply, @layer, @utility, @variant…)
 * et avec l'approche CSS-first du thème (variables CSS partout, pas de SCSS).
 *
 * Lint : `npm run lint:css`
 */

/** At-rules introduites par Tailwind v4 (moteur CSS-first). */
const tailwindAtRules = [
	'tailwind',
	'apply',
	'layer',
	'theme',
	'variant',
	'custom-variant',
	'source',
	'utility',
	'reference',
	'config',
	'plugin',
	'screen',
	'responsive',
];

/** @type {import('stylelint').Config} */
export default {
	extends: [ 'stylelint-config-standard' ],
	ignoreFiles: [ 'dist/**', 'node_modules/**', 'vendor/**' ],
	rules: {
		// Tailwind v4 : autoriser ses at-rules custom.
		'at-rule-no-unknown': [
			true,
			{ ignoreAtRules: tailwindAtRules },
		],
		// Le thème assemble son CSS via @import dans main.css (entrée Vite/Tailwind).
		'no-invalid-position-at-import-rule': null,
		// Tokens & nommage : on tolère les conventions maison (BEM, casse mixte
		// dans les valeurs hex, etc.) sans imposer de réécriture du CSS existant.
		'custom-property-empty-line-before': null,
		'declaration-empty-line-before': null,
		'comment-empty-line-before': null,
		'color-hex-length': null,
		'color-function-notation': null,
		'alpha-value-notation': null,
		'value-keyword-case': null,
		'selector-class-pattern': null,
		// Certains sélecteurs ciblent des ID natifs WooCommerce en snake_case
		// (`#order_review`, `#place_order`) qu'on ne peut pas renommer sans casser
		// le markup natif WC — même tolérance que pour les classes BEM maison.
		'selector-id-pattern': null,
		'keyframes-name-pattern': null,
		'media-feature-range-notation': null,
		'property-no-vendor-prefix': null,
		'shorthand-property-no-redundant-values': null,
		'declaration-block-no-redundant-longhand-properties': null,
		// `composes`, vars dynamiques, et valeurs Tailwind ne doivent pas casser le lint.
		'function-no-unknown': null,
		'no-descending-specificity': null,
		// Le thème utilise la forme `@import "fichier.css"` (résolue par Vite/
		// Tailwind), pas `url(...)`.
		'import-notation': null,
		// main.css déclare volontairement `html` en plusieurs blocs (font-size,
		// scroll-behavior) — l'ordre/lisibilité prime sur la fusion.
		'no-duplicate-selectors': null,
		// Conventions de mise en forme tolérées (CSS écrit à la main, déjà cohérent).
		'rule-empty-line-before': null,
		'length-zero-no-unit': null,
		'declaration-block-single-line-max-declarations': null,
		// Le thème met systématiquement les noms de polices entre guillemets
		// (cohérent avec les tokens `--font-display`/`--font-body`).
		'font-family-name-quotes': null,
	},
};
