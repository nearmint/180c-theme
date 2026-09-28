---
description: Orchestre la construction de la v1 complète du thème 180c-theme en déléguant aux sous-agents spécialisés.
---

# Build theme v1 — orchestrateur

Tu es le coordinateur principal pour la construction du thème 180c-theme v1.

## Documents de référence

Avant toute chose, lis :

1. `CLAUDE.md` à la racine du repo
2. Les pages Notion référencées dans CLAUDE.md (Brief produit, Architecture technique, Modèle de contenu, Design System)

## Plan d'exécution

Exécute ce plan dans l'ordre. Entre chaque étape : commit atomique conventionnel, validation que ce qui a été produit compile/lint.

### Phase 1 — Foundations

1. `wp-architect` — Valider la structure du repo, créer/compléter les fichiers PHP structurels manquants
2. `wp-architect` — Implémenter les groupes ACF (versionner dans `acf-json/`) selon Notion `📊 Modèle de contenu`
3. `frontend-blocks` — Compléter `src/css/main.css` et `src/css/tokens.css` selon le Design System Notion
4. `frontend-blocks` — Compiler le build Vite (`npm run build`) et vérifier que le manifest est lu correctement

### Phase 2 — Modules de la home

5. `frontend-blocks` + `wp-architect` — Implémenter les 11 blocs Gutenberg custom (cf. Brief produit, section 5)
6. `wp-architect` — Implémenter `front-page.php` et les `parts/modules/*.php` correspondants

### Phase 3 — CPT Recipe

7. `wp-architect` — Compléter `inc/cpts/recipe.php` (déjà initialisé)
8. `frontend-blocks` — Implémenter le template `single-recipe.php` et `archive-recipe.php`
9. `frontend-blocks` + `php-backend` — Implémenter le paywall côté serveur

### Phase 4 — E-commerce et Mon Compte

10. `woo-specialist` — Surcharger les templates Woo nécessaires (`templates/woocommerce/*`)
11. `woo-specialist` — Implémenter Apple Pay + Google Pay via Stripe Express Checkout
12. `woo-specialist` — Refondre les emails transactionnels (alignés Design System)
13. `woo-specialist` — Implémenter le custom Mon Compte (6 endpoints : profil, abo, factures, NL, commandes, favoris)

### Phase 5 — Auth

14. `php-backend` — Implémenter le rate limiting + Cloudflare Turnstile (`inc/auth/rate-limit.php`)
15. `php-backend` — Implémenter les routes `/connexion/`, `/inscription/`, `/mot-de-passe-oublie/`

### Phase 6 — REST + Favoris

16. `php-backend` — Implémenter les endpoints `/wp-json/180c/v1/favorites/*` complets
17. `php-backend` — Implémenter `/wp-json/180c/v1/newsletter/*` (proxy Mailchimp API)
18. `php-backend` — Tester la sync app→site en mode local

### Phase 7 — SEO

19. `seo-guardian` — Implémenter `inc/seo/meta-tags.php` complet
20. `seo-guardian` — Implémenter Schema.org JSON-LD (Recipe, Article, Product, Organization)
21. `seo-guardian` — Optimiser le sitemap natif WP

### Phase 8 — Analytics

22. `tracking-ga4` — Implémenter `inc/analytics/ga4.php` avec les 17 events MUST
23. `tracking-ga4` — Implémenter la CMP maison dans `parts/consent-modal.php` + JS
24. `tracking-ga4` — Valider Consent Mode v2 via GA4 Debug View

### Phase 9 — Validation

25. `qa-validator` — Lint PHP, JS, CSS — tous les fichiers
26. `qa-validator` — Audit accessibilité sur 10 pages échantillons
27. `qa-validator` — Lighthouse sur home + fiche recette + fiche produit
28. `doc-writer` — Mettre à jour README.md et CHANGELOG.md

## Règles d'exécution

- Commits atomiques après chaque sous-tâche validée
- Format Conventional Commits
- Si un agent échoue, log l'erreur dans la conversation et continue avec les autres agents possible
- À la fin, produit un récap des étapes complétées vs en attente
- Ne déploie jamais en prod automatiquement (pas de push sur main sans validation humaine explicite)
- Aucun appel au workflow GitHub Actions tant que l'utilisateur n'a pas configuré les secrets FTP

## Garde-fous

- Pas de modification de la BDD prod
- Pas de suppression de fichiers existants sans confirmation
- Pas d'exécution de scripts de migration (ils vivent hors dépôt et sont lancés manuellement par l'utilisateur)
- Si un doute persiste : poser la question dans la conversation et attendre la réponse
