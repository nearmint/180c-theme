/**
 * Configuration PostCSS du thème 180°C.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Pourquoi ce fichier est (volontairement) sans plugins
 * ─────────────────────────────────────────────────────────────────────────
 * Le thème utilise Tailwind CSS v4 via le plugin officiel `@tailwindcss/vite`
 * (déclaré dans `vite.config.js`). Ce plugin embarque le moteur Tailwind v4,
 * lui-même basé sur Lightning CSS, qui prend en charge nativement :
 *
 *   - la résolution des `@import "..."` (plus besoin de `postcss-import`) ;
 *   - le préfixage vendeur automatique (plus besoin d'`autoprefixer`) —
 *     vérifié sur le bundle de prod (`-webkit-`, `-moz-`, `-webkit-user-select`,
 *     `-webkit-backdrop-filter` injectés automatiquement) ;
 *   - le nesting CSS et diverses transformations de CSS moderne.
 *
 * C'est la recommandation explicite de Tailwind v4 : ne PAS ajouter
 * `autoprefixer` ni `postcss-import` par-dessus, sous peine de double
 * traitement et d'avertissements.
 *
 * Ce fichier reste néanmoins présent car :
 *   1. il matérialise le point d'extension PostCSS de la pipeline
 *      (`.github/workflows/deploy.yml` l'exclut déjà du déploiement FTP) ;
 *   2. il documente la décision ci-dessus à l'endroit où un dev s'attendrait
 *      à la trouver ;
 *   3. il offre un emplacement prêt à l'emploi si un besoin PostCSS NON couvert
 *      par Tailwind v4 émerge un jour (ex. un plugin custom de post-traitement).
 *
 * Pour ajouter un plugin : `plugins: { 'mon-plugin': {} }`.
 */

export default {
	plugins: {},
};
