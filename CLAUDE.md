# CLAUDE.md — 180c-theme

> Contexte pour Claude Code. À lire en premier avant toute intervention.

## Projet

Refonte complète du thème WordPress de [180c.fr](https://180c.fr) : magazine culinaire avec site éditorial + e-commerce + abonnement aux recettes.

L'écosystème comprend aussi des apps mobiles iOS et Android qui consomment l'API REST.

## Stack

| Couche | Technologie |
|---|---|
| CMS | WordPress 6.9+ |
| PHP | 8.3 |
| Front | Vite 6 + Tailwind CSS v4 + vanilla JS ES modules |
| Modèle de contenu | ACF Pro (Local JSON sync dans `/acf-json`) |
| E-commerce | WooCommerce 10+ avec Subscriptions et Memberships |
| Auth API | Simple JWT Login |
| Newsletter | Mailchimp API v3 (intégration custom, pas de plugin) |
| Hébergement | Shared hosting |
| Local dev | Local by Flywheel |

## Documentation projet

Ce dépôt est **public**. La documentation produit (brief, architecture, modèle
de contenu, design system), les runbooks d'exploitation, les audits et les
scripts de migration vivent **hors dépôt**. Les liens et procédures privés sont
dans `CLAUDE.local.md` (non versionné, à la racine du clone) : le lire en
premier quand il existe. Ne jamais recopier dans un fichier versionné un
secret, une donnée client ou une information d'infrastructure.

### Valeurs non versionnées (constantes `wp-config.php`)

Toute valeur non publique lue au runtime vit dans une constante `_180C_*` de
`wp-config.php`, jamais dans le code. Constantes introduites à la publication :

| Constante | Rôle | Absente |
|---|---|---|
| `_180C_2FA_LOGIN` | `user_login` du seul compte soumis au 2FA | échec fermé : connexion par mot de passe des administrateurs refusée |
| `_180C_MC_AUDIENCE_ID` | ID d'audience Mailchimp | tout appel `/lists/…` refusé (503), jamais lu comme un succès |
| `_180C_CONTACT_RECIPIENTS` | tableau `objet => adresse` du formulaire de contact | routage vers `admin_email` |
| `_180C_EXTRA_REDIRECTS` | redirections `source => cible` non versionnables | ignorées |
| `_180C_MAIL_FROM_ADDRESS` | expéditeur des e-mails WP (facultatif) | option WooCommerce `woocommerce_email_from_address` |
| `_180C_ONESIGNAL_SEGMENT_IDS` | tableau `clé => UUID` des segments OneSignal | envoi par nom de segment, sans contrôle |
| `_180C_TRUSTED_PROXIES` | IP ou plages CIDR (virgules) des proxys dont `X-Forwarded-For` / `CF-Connecting-IP` sont crus | seul `REMOTE_ADDR` fait foi (production vérifiée : il porte l'IP réelle) |

## Conventions

### PHP

- Préfixe global : `_180c_` (fonctions), `_180C_` (constantes/classes)
- Text domain : `180c`
- Tous les fichiers PHP commencent par `defined('ABSPATH') || exit;`
- PHPDoc complète sur les fonctions publiques
- Conforme à WordPress Coding Standards (WPCS)
- Lint : `composer lint`

### JS

- Vanilla ES modules
- Pas de framework lourd (sauf Gutenberg/React pour les blocs custom côté éditeur)
- Modules : kebab-case (`dark-mode.js`, `smart-banner.js`)
- Lint : `npm run lint:js`

### CSS

- Tailwind v4 (approche CSS-first, pas de tailwind.config.js)
- Variables CSS pour tous les tokens (couleurs, typo, spacing)
- Pas de couleurs hardcodées
- Mobile-first
- Dark mode obligatoire (`prefers-color-scheme` + toggle `data-theme`)
- Lint : `npm run lint:css`

### Hooks WordPress custom

- Namespace : `180c/` (exemple : `do_action('180c/favorite_added', $user_id, $recipe_id)`)

### Git

- Branches : `main` (prod), `develop` (travail), `feature/<slug>`, `fix/<slug>`, `migration/<slug>`
- Commits : Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:`, `refactor:`)
- Pas de force-push sur `main`

## Blocs Gutenberg — deux pièges vérifiés (2026-08-31)

**1. Le namespace ne peut PAS commencer par un chiffre.** Le parseur du cœur
impose `(?P<namespace>[a-z][a-z0-9_-]*\/)?` (`wp-includes/class-wp-block-parser.php`).
`<!-- wp:180c/xxx -->` n'est donc **jamais** reconnu : `parse_blocks()` renvoie
`blockName: null` et le commentaire traverse la page sans rien rendre.
`register_block_type()` accepte pourtant ces noms sans broncher — panne
silencieuse de bout en bout. Le namespace en vigueur pour tout nouveau bloc est
**`one80c/`** (cf. `inc/blocks/consent-toggle/`).

> **Corollaire, même famille : un identifiant HTML commençant par un chiffre
> n'est pas adressable en CSS.** `#180c-notif-automation` lève une
> `SyntaxError` dans `querySelector()` et ne matche jamais dans une feuille de
> style — un identifiant CSS ne peut pas commencer par un chiffre sans
> échappement (`#\31 80c-…`). `getElementById()`, lui, s'en moque. Constaté
> sur le panneau d'automation des notifications (2026-09-05), qui cible donc
> des **classes** en CSS et passe par `getElementById()` en JS. Préférer un
> préfixe alphabétique (`one80c-…`) sur tout nouvel identifiant.

**2. Un bloc PHP seul n'existe pas dans l'éditeur.** Sans `editorScript`, le
registre JS ne le connaît pas : il s'affiche en `core/missing` (« contenu
inattendu »), non insérable et non déplaçable. Il faut un `index.js` qui appelle
`registerBlockType`, accompagné d'un `index.asset.php` déclarant ses dépendances
(le thème n'a pas de chaîne de build pour les blocs — écrire ce fichier à la
main est la voie normale ici).

> Les **18 blocs `180c/*`** existants cumulent les deux défauts : vérifié dans
> l'éditeur réel, aucun n'est exposé au registre JS, et aucun n'est utilisé dans
> le moindre `post_content`. Ils sont inertes. Leur remise en état est un
> chantier distinct, non entrepris.

## Design System

| Élément | Valeur |
|---|---|
| Couleur d'accent | `#FFAE3A` |
| Typo display | Oswald (variable wght) |
| Typo body | Playfair Display (variable wght + italic) |
| Mode | Dark obligatoire (auto + toggle footer) |
| Mobile-first | Oui |
| Accessibilité | WCAG 2.1 AA |

### Discipline tokens typographiques (règle dure)

Distinction stricte entre tokens **fluid** (clamp) pour le contenu éditorial et tokens **fixes** pour la chrome / UI. Ne **jamais** mélanger.

| Échelle | Tokens | Usage autorisé |
|---|---|---|
| **Fluid (clamp)** | `--text-2xl`, `--text-3xl`, `--text-4xl`, `--text-5xl` | **Contenu éditorial principal** : h1 d'article, h2 de section éditoriale, hero, titres de page, citations marquantes, blocs Gutenberg « display ». |
| **Fixes** | `--text-xs`, `--text-sm`, `--text-base`, `--text-lg`, `--text-xl` | **Chrome / UI** : header nav, side menu, footer, sidebar, labels, captions, métadonnées, boutons, badges, tooltips. |

Référence courte :
- Titre de colonne footer → `--text-sm` uppercase + letter-spacing 0.06em.
- Sub-title de bloc UI → `--text-xs` uppercase + letter-spacing 0.08em.
- Si un titre UI semble « trop petit », corriger par letter-spacing / casse / weight — **pas** par une taille plus grande. Sinon la hiérarchie globale dérive.

Voir le bloc de commentaire en tête de `src/css/tokens.css` pour la version canonique de la règle.

## Décisions clés actées

| Sujet | Décision |
|---|---|
| Articles | `post` natif WordPress |
| Recettes | CPT custom `recipe` (migration des 2 136 posts catégorie "Recettes" via script one-shot) |
| Auth | Email/password + JWT pour les apps. Pas de social login |
| Memberships | 1 seul plan `abonne-recettes` (consolidation des 4 plans payants existants) |
| Abonnement Woo | 1 seul produit d'abonnement (mensuel + annuel via variations) |
| Paiement | Stripe (CB + Apple Pay + Google Pay via Express Checkout) + PayPal |
| Tracking | GA4 manuel + Umami Cloud + Consent Mode v2 + CMP maison (aucune bibliotheque) |
| SEO | Natif (theme + ACF), pas de plugin (suppression Yoast + Rank Math) |
| Search | Natif WordPress |
| Commentaires | Désactivés |
| Multilingue | Site monolingue FR |
| Cache | WP Super Cache |
| Hébergement | Shared hosting, sans staging |

## Sous-agents disponibles

Dans `.claude/agents/` :

| Agent | Modèle | Rôle |
|---|---|---|
| `wp-architect` | sonnet | Structure thème, CPT, ACF, blocs |
| `php-backend` | sonnet | Hooks, REST endpoints, services |
| `woo-specialist` | sonnet | Templates Woo, paiements, Mon Compte |
| `frontend-blocks` | sonnet | Blocs Gutenberg, CSS/JS, design system |
| `tracking-ga4` | haiku | GA4, Umami, Consent Mode, CMP maison |
| `seo-guardian` | sonnet | Meta tags, schema.org, sitemap |
| `qa-validator` | haiku | Lint, audit, validation |
| `doc-writer` | haiku | README, PHPDoc, CHANGELOG |

## Commande orchestratrice

Dans `.claude/commands/` :

- `/build-theme-v1` — pipeline complet de construction v1

## À ne JAMAIS faire

- ❌ Modifier la BDD prod depuis le thème (toujours via des scripts de migration, hors dépôt)
- ❌ Reproduire le comportement "Direct Checkout" custom (décision actée : cart-first standard)
- ❌ Ajouter Apple Pay / Google Pay autrement que via Stripe Express Checkout
- ❌ Créer plusieurs plans WC Memberships (le plan unique est `abonne-recettes`)
- ❌ Ré-introduire Yoast, Rank Math, Pixel Caffeine, Relevanssi, ou d'autres plugins de la liste "supprimés"
- ❌ Hardcoder des clés API ou secrets dans le code
- ❌ Push direct sur `main` sans review

> **Caduque depuis le web push (2026-09-04)** : « Activer un Service Worker en v1 »
> ne tient plus. Un service worker EST servi, à l'URL racine `/OneSignalSDKWorker.js`,
> par le handler PHP `inc/push/worker.php` — voir la section « Web push » ci-dessous.
> Il ne fait rien d'autre qu'importer le SDK OneSignal : **aucun cache offline,
> aucune interception de requête**. L'interdiction reste donc valable dans son
> esprit d'origine (pas de stratégie de cache côté client en v1).

## À configurer en WP Admin (refonte 2026-05)

**Apparence > Menus** : 6 emplacements de menus à créer et affecter. Tant qu'un menu n'est pas affecté, le template rend automatiquement une liste hardcodée de fallback (voir `inc/menus.php`).

| Theme location | Zone du site | Items recommandés / convention |
|---|---|---|
| `primary` | Header desktop (≥1024px) | La Gazette, Cahiers de Delphine, Boutique en ligne, **Premium (avec classe CSS `site-header__nav-cta`)** |
| `side` | Panneau latéral push (toutes résolutions) | Articles (classe `expand-category`), Recettes (`expand-recipe_category`), Boutique (`expand-product_cat`), Newsletter, Contact, À propos |
| `footer-discover` | Footer — colonne « Découvrir 180°C » | La Gazette, Cahiers de Delphine, Recettes en ligne, Boutique, S'abonner |
| `footer-editions` | Footer — colonne « Maison d'édition » | Revues 180°C, Revues 12°5, Petits/Grands Cahiers, Livres, ePub |
| `footer-help` | Footer — colonne « Aide & contact » | Centre d'aide, Rédaction, Presse, Espace pro, Partenariats, Qui sommes-nous |
| `legals` | Footer — liens légaux bas | (réservé — actuellement les liens légaux sont hardcodés dans le footer) |

**Conventions de classes CSS sur les menu items** (activer le champ « Classes CSS » via Apparence > Menus > Options de l'écran en haut à droite) :

- `site-header__nav-cta` sur un item du menu `primary` → l'affiche en accent jaune (CTA Premium).
- `expand-{taxonomy}` sur un item du menu `side` → transforme l'item en bouton « expand » avec une sous-liste alimentée par les termes de la taxonomy spécifiée. Valeurs supportées : `expand-category` (Articles), `expand-recipe_category` (Recettes), `expand-product_cat` (Boutique). Pour ajouter d'autres taxonomies à l'avenir, il suffit de respecter ce nommage : `expand-{slug}` où `{slug}` est un `taxonomy` enregistré.

**État actif** (couleur accent + underline) : géré automatiquement via les classes natives WP `current-menu-item`, `current-menu-parent`, `current-menu-ancestor`, `current_page_item`, `current_page_parent`, `current-cat`, posées par `wp_nav_menu`. Le fallback PHP applique manuellement `current-menu-item` quand l'URL de l'item matche `REQUEST_URI`.

**À remplacer dans `src/images/icons/` et `src/images/badges/`** : les SVG actuels sont des placeholders. À substituer par les badges officiels une fois les URLs des apps disponibles.

- `src/images/icons/app-store-badge.svg` → footer principal (Apple)
- `src/images/icons/google-play-badge.svg` → footer principal (Google)
- `src/images/badges/app-store-fr-black.svg` → side menu (Apple, version française fond noir)

Pour Apple : https://developer.apple.com/app-store/marketing/guidelines/. Pour Google : https://play.google.com/intl/en_us/badges/. Voir aussi `src/images/badges/README.md`.

**Pages et réglages de production** (page « Politique de cookies », réglage
« Cache Status Messages » de WP Super Cache) : procédures dans `CLAUDE.local.md`.
Retenir côté code : **le slug de la page cookies doit rester `cookies`**
(`_180c_consent_policy_url()`, `inc/consent.php`, pointe sur `home_url( '/cookies/' )`).

## Workflow type

1. Lire la documentation produit pertinente (liens dans `CLAUDE.local.md`)
2. Travailler sur une branche `feature/*` ou `fix/*`
3. Code + lint propre
4. Commit conventionnel
5. PR (self-review en solo)
6. Merge dans `develop`
7. Quand `develop` est stable : merge dans `main` → déploiement auto

## Web push (OneSignal)

Les abonnés peuvent recevoir sur le web les notifications déjà envoyées à l'app
mobile. Le miroir app → web est **acquis par construction** : le module d'envoi
BO (`inc/notifications/`) cible `included_segments`, et le web s'inscrit dans la
même app OneSignal, avec le même `external_id` et le même tag de statut que
l'app iOS. **Ne pas modifier le module d'envoi pour ajouter le web.**

| Élément | Valeur |
|---|---|
| App OneSignal | La même que le mobile — constantes `_180C_ONESIGNAL_APP_ID` / `_180C_ONESIGNAL_REST_KEY` (wp-config), jamais redéfinies par le module push |
| Éligibilité | Logué **et** abonné, appliqué en PHP (`_180c_push_user_is_eligible()`) : sans App ID imprimé, le front ne peut rien initialiser |
| `external_id` | ID utilisateur WP, **en chaîne** — identique à `OneSignal.login(String(id))` de l'app iOS. Un entier créerait un second utilisateur OneSignal pour le même compte |
| Tag de statut | **`subscription_status`** = `active` / `none`. Nomenclature de l'app reprise à l'identique. **Ne jamais créer de tag `is_subscriber`** : les deux canaux alimentent les mêmes segments |
| Service worker | Fichier source `assets/onesignal/OneSignalSDKWorker.js`, servi à `/OneSignalSDKWorker.js` par `inc/push/worker.php` |
| Route REST | `GET, POST /180c/v1/push/account-optin` |
| Meta miroir | `_180c_push_web_optin` — **affichage seulement**, jamais une autorisation |

### Pièges vérifiés

- **Le handler du worker est en `template_redirect` priorité 0.** `redirect_canonical()`
  occupe la priorité 10 et renvoyait l'URL en 301 vers `/OneSignalSDKWorker.js/`.
  Un worker servi derrière une redirection est refusé par le navigateur.
- **Le worker est servi en `no-store`**, avec `DONOTCACHEPAGE`. Le CDN de l'hébergeur sert
  les assets du thème en `max-age=900` : un worker figé quinze minutes rendrait
  tout correctif impraticable.
- **Toute modification de `_180c_push_register_rewrite()` impose d'incrémenter
  `_180C_PUSH_REWRITE_VERSION`.** C'est le seul déclencheur du flush ; sans
  incrément, l'URL retombe en 404 après déploiement.
- **`_180c_user_is_subscriber()` n'est pas utilisable hors requête front** : son
  mécanisme Memberships est gardé par `get_current_user_id() === $user_id`. La
  synchro passe donc par `_180c_push_user_is_subscriber()`
  (`inc/push/subscriber-status.php`), qui n'a pas cette garde. Ne pas confondre
  les deux : la canonique reste celle du paywall et du REST.
- **La synchro du tag est différée** (`wp_schedule_single_event`, 30 s). Au
  moment où `wc_memberships_user_membership_status_changed` se déclenche, les
  caches objets du plugin portent encore l'ancien statut.
- **`PATCH` et jamais `PUT`** sur l'utilisateur OneSignal : un remplacement
  effacerait les tags `env` et `app_version` posés par l'app iOS.
- **Rien de OneSignal n'est chargé avant une action explicite de l'utilisateur.**
  C'est ce qui dispense d'une catégorie de consentement supplémentaire. Ne
  jamais appeler `loadSdk()` depuis un `init()`.
- **Le nonce n'est plus inliné dans le HTML** (cache WPSC) : les modules push
  passent par `restFetch()` de `session.js`. Un `const NONCE = window._180c?.nonce`
  lu à l'import capturerait une chaîne vide.

### ❌ Ne JAMAIS coller le snippet d'intégration de la console OneSignal

La console propose un snippet « standard » à poser dans le `<head>` :

```html
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init({ appId: "..." });
  });
</script>
```

**Il ne doit jamais être ajouté au site** — ni par un plugin, ni par un champ
d'en-tête, ni à la main. Trois conséquences, dans l'ordre de gravité :

1. **La décision CMP tombe.** Le SDK se chargerait sur chaque page et écrirait
   dans le navigateur avant toute action de l'utilisateur. C'est exactement ce
   que ce lot évite, et c'est ce qui dispense aujourd'hui d'une catégorie de
   consentement supplémentaire. Le poser rendrait une catégorie CMP obligatoire.
2. **Double `init()`.** `push-sdk.js` appelle déjà `OneSignal.init()` avec le
   chemin du service worker et le scope racine. Un second `init()` sans ces
   options entrerait en conflit sur la configuration du worker.
3. **Fichier source du worker.** `assets/onesignal/OneSignalSDKWorker.js` est la
   copie exacte du fichier de la console, une seule ligne. Ne rien y ajouter :
   il est servi à l'URL racine par `inc/push/worker.php`.

Le seul point d'entrée légitime du SDK est `loadSdk()` dans
`src/js/modules/push-sdk.js`, appelé uniquement depuis `enablePush()`.

### Rendu des notifications selon la plateforme

Le rendu d'une notification n'est PAS le même partout, et l'écart n'est pas
corrigeable — c'est une limite de plateforme, à ne pas chercher à contourner.

| | Safari (macOS) | Chrome / Android |
|---|---|---|
| Grande image (photo du plat) | **jamais affichée** | affichée |
| Icône | **l'icône du SITE**, jamais celle de la notification | icône par défaut de la console OneSignal |

Autrement dit : une notification de recette montre **le logo 180°C sur Safari**
et **la photo du plat sur Chrome et Android**. Ce n'est pas un défaut.

Le module d'envoi (`inc/notifications/onesignal-client.php`) transmet
`ios_attachments` et `big_picture` — la grande image — mais **aucun champ
d'icône** (`chrome_web_icon`, `firefox_icon`…). L'icône affichée hors Safari
vient donc entièrement de la **configuration web de l'app dans la console
OneSignal**, jamais du code.

#### Icône de site : déjà en place, ne rien redéclarer

Safari résout l'icône depuis l'`apple-touch-icon`, qui **prime** sur les icônes
du manifeste. Les deux existent déjà, servis par `inc/favicon.php` depuis
`assets/favicon/` (lot favicon du 2026-08-03) :

- `apple-touch-icon.png` — 180×180, **RGB sans canal alpha** ;
- `site.webmanifest` — icônes 192, 512 et maskable 512, servi en
  `application/manifest+json` grâce au `AddType` de `assets/favicon/.htaccess` ;
- `display: "browser"` dans le manifeste, **volontairement** : `standalone`
  ferait de 180c.fr une application web installable, en concurrence avec les
  apps iOS et Android natives.

`inc/favicon.php` **désaccroche `wp_site_icon()`** de `wp_head`, `login_head` et
`admin_head` : le champ « Icône du site » de l'administration est donc inerte
dans le `<head>`. Il y a exactement UNE déclaration `apple-touch-icon`, celle du
thème. **Ne jamais en ajouter une seconde** — deux déclarations concurrentes
créent une ambiguïté de résolution.

⚠️ **Les icônes doivent rester opaques.** Safari remplit la transparence de
noir : une icône à fond transparent donnerait un logo sur pavé noir dans le
centre de notifications. Toutes les icônes actuelles sont en RGB sans alpha —
le vérifier (`file assets/favicon/*.png`) après toute régénération.

### Limites connues

- iOS et Safari mobile ne délivrent le web push que si le site a été **ajouté à
  l'écran d'accueil**.
- Un abonné présent sur l'app **et** sur le web reçoit la notification **deux
  fois**. Comportement accepté.

## Design System

Référentiel visuel interne **réservé aux admins** (`manage_options`, `noindex,nofollow`), modèle base.uber.com. 3 pages partageant `template-design-system.php`, routées par slug : `/design-system/` (welcome), `/design-system/foundations/`, `/design-system/components/`.

- **Foundations** : rendu vivant des tokens, parsés à l'exécution depuis `src/css/tokens.css` **+** le bloc `@theme` de `src/css/main.css` (couleurs light/dark) via `_180c_ds_parse_tokens()` (`inc/design-system.php`). Aucune valeur en dur.
- **Components** : ~91 composants groupés par lot, chaque fiche = aperçu + classe BEM racine + variantes + note (badge *live* = vrai partial, *snapshot* = HTML statique pour les contextes ACF/Woo/JS).
- **Partials** : `template-parts/ds/` (`nav.php`, `welcome.php`, `foundations.php`, `components.php`, utilitaires `swatch.php`/`specimen.php`/`component-card.php`) ; un lot par fichier dans `template-parts/ds/components/`.
- **Assets** : entrée Vite dédiée `src/js/design-system.js` → `src/css/design-system.css` (classes `.ds-*`), enqueue conditionnel au template (`inc/enqueue.php`). Scroll-spy de la nav latérale.

## Hors scope worker (chantiers déjà réalisés manuellement)

Ces chantiers ont été menés manuellement en amont du worker d'orchestration et **ne doivent pas être refaits** :

- Refonte **Header** (`header.php` + `parts/`)
- Refonte **Footer** (`footer.php`, toggle dark mode inclus)
- Page **Mon Compte** (WooCommerce, `inc/woo/account-*`)
- **Centre d'aide** (`page-aide.php`)
- **CMP maison** (modale binaire + Consent Mode v2) — `parts/consent-modal.php`, `src/js/modules/consent.js`, `src/css/components/consent.css`. Aucune bibliotheque tierce : le nom « Klaro » etait un vestige.
- **Newsletter** (`page-newsletter.php`) — remplace l'ancienne optin « Cahiers de Delphine », dont le template a été supprimé et l'URL redirigée en 301 vers `/newsletter/` (voir `inc/redirects.php`)

> **Convention** : les briefs importés du worker (hors dépôt) emploient parfois le préfixe `oneeightyc_`. Le préfixe **réellement en vigueur dans ce thème est `_180c_`** (voir « Conventions › PHP »). Aucun nouveau code ne doit utiliser `oneeightyc_` ni `c180_`.
