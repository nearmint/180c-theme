/**
 * Configuration ESLint (flat config, ESLint 9+) du thème 180°C.
 *
 * Le JS du thème est du vanilla ES modules exécuté dans le navigateur.
 * On garde un jeu de règles léger : `eslint:recommended` + quelques garde-fous,
 * sans framework ni TypeScript.
 *
 * Lint : `npm run lint:js`
 */

import js from '@eslint/js';
import globals from 'globals';

export default [
	{
		// Fichiers/dossiers générés ou tiers : jamais lintés.
		ignores: [ 'dist/**', 'node_modules/**', 'vendor/**' ],
	},

	js.configs.recommended,

	{
		// JS front du thème — modules ES exécutés dans le navigateur.
		// inc/blocks/**: JS des blocs Gutenberg, servi tel quel (aucune étape de
		// build) — il échappait jusqu'ici à tout contrôle.
		files: [ 'src/js/**/*.js', 'inc/blocks/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2024,
			sourceType: 'module',
			globals: {
				...globals.browser,
				// Globals injectés par WordPress / le thème / les tiers.
				_180c: 'readonly', // window._180c (enqueue.php).
				gtag: 'readonly', // GA4.
				dataLayer: 'writable', // GA4 / GTM.
				wp: 'readonly', // API éditeur Gutenberg (entrée admin).
			},
		},
		rules: {
			// Les `catch ( e )` / `catch ( _ )` vides (erreurs volontairement
			// ignorées) et les vars préfixées `_` ne sont pas signalées.
			'no-unused-vars': [
				'error',
				{
					args: 'none',
					caughtErrors: 'none',
					varsIgnorePattern: '^_',
					ignoreRestSiblings: true,
				},
			],
			// Le code suppresse ces règles ligne par ligne quand c'est volontaire
			// (debug log, confirm() d'auth, reflow forcé). On les garde donc ON
			// pour que les directives `eslint-disable` restent significatives.
			'no-console': 'warn',
			'no-alert': 'error',
			'no-unused-expressions': 'error',
			'prefer-const': 'error',
			'no-var': 'error',
			eqeqeq: [ 'error', 'smart' ],
		},
	},

	{
		// Outils Node (config Vite/PostCSS, scripts, tests).
		files: [
			'*.config.js',
			'tests/**/*.js',
			'scripts/**/*.js',
		],
		languageOptions: {
			ecmaVersion: 2024,
			sourceType: 'module',
			globals: {
				...globals.node,
			},
		},
		rules: {
			'no-console': 'off',
		},
	},
];
