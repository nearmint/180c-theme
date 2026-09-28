# mu-plugins/ — sources versionnées des must-use plugins 180°C

Ce dossier contient les **sources versionnées** des must-use plugins du projet
180°C. Ils ne vivent pas dans le thème (chargé trop tard, sur `after_setup_theme`)
mais doivent s'exécuter **avant les extensions classiques**. Ils ne font pas partie
de l'artefact de déploiement du thème : chacun s'installe à la main dans
`wp-content/mu-plugins/`, où WordPress le charge automatiquement.

---

## `180c-html-cache-headers.php`

Normalise l'en-tête `Cache-Control` des réponses HTML **front-end**, pour que les
pages publiques puissent être servies par un cache partagé au lieu d'un
`no-store` systématique.

### Stratégie de cache appliquée

| Contexte | Cache-Control émis |
|---|---|
| Visiteur **anonyme** | `public, max-age=300, s-maxage=600` |
| Visiteur **connecté** ou porteur d'un cookie de session | `private, no-cache, max-age=0, must-revalidate` (**jamais** no-store) |
| Page **404** | `public, max-age=60` |
| Panier, checkout, `/mon-compte/`, preview/customizer | `no-store, no-cache, must-revalidate, max-age=0` (cache désactivé) |
| `wp-admin`, AJAX, REST, XML-RPC, cron, WP-CLI, `wp-login.php`, POST | **non touché** (no-store conservé) |

### Comment ça marche

Deux mécanismes complémentaires :

1. **Autoritaire** — `send_headers` puis `template_redirect` en priorité
   `PHP_INT_MAX` posent l'en-tête avec `replace = true`, remplaçant tout
   `Cache-Control` posé en amont (cœur WP, extension de cache, header hérité).
   Pour les réponses cacheables, `Pragma` et un `Expires` dans le passé sont
   retirés.
2. **Filet de sécurité** — le filtre `nocache_headers` retire le seul token
   `no-store` des appels `nocache_headers()` front-end non dynamiques, empêchant
   sa réintroduction tardive. L'admin et les pages Woo dynamiques gardent leur
   `no-store` complet.

**Garde-fou anti-fuite** : un visiteur « anonyme » porteur d'un cookie de session
(`wordpress_logged_in_*`, `wp-postpass_*`, `comment_author_*`,
`woocommerce_items_in_cart`, `woocommerce_cart_hash`, `wp_woocommerce_session_*`)
bascule en `private` — jamais de page personnalisée servie au cache partagé. Les
cookies inoffensifs (analytics, consentement CMP) sont ignorés pour ne pas
fragmenter le cache.

### Limite

Un `Header set Cache-Control` posé **au niveau serveur** (Apache `.htaccess` avec
`always`, ou réglage du CDN) s'applique **après** PHP et ne peut pas être
surchargé par ce mu-plugin.

### Constante `wp-config.php`

```php
// Désactive le mu-plugin sans retirer le fichier.
define( '_180C_HTML_CACHE_HEADERS_DISABLED', true );
```

### Ajuster les durées de cache

Via le filtre (par ex. dans un autre mu-plugin) :

```php
add_filter( '180c/html_cache_control', function ( $directive, $context ) {
    if ( 'public' === $context ) {
        return 'public, max-age=600, s-maxage=1200'; // cache plus long
    }
    return $directive;
}, 10, 2 );
```

---

## `180c-maintenance-mode.php`

Page de **maintenance 503** autonome. Sert un HTML signé 180°C (CSS inline,
**aucune dépendance** au thème ni au build Vite : opérante même pendant un dépôt
de fichiers ou un import BDD), avec `Retry-After` et `Cache-Control: no-store`
(jamais mise en cache CDN/navigateur).

### Commandes WP-CLI

| Commande | Effet |
|---|---|
| `wp 180c maintenance status` | État courant (option BDD + verrou dur + échappatoire). |
| `wp 180c maintenance on` | Arme la maintenance (option autoloadée `180c_maintenance_active`). |
| `wp 180c maintenance off` | Lève la maintenance. |

L'état vit dans la **BDD** : un import de dump pris « site gelé » **réarme** donc
la maintenance d'elle-même.

### Qui passe au travers

- utilisateur connecté `manage_options` (aperçu admin) ;
- requête portant le cookie d'aperçu, armé via `/?180c-preview=<token>` et retiré
  via `?180c-preview=off` (token = constante `_180C_MAINTENANCE_BYPASS_TOKEN`) ;
- IP listée dans `_180C_MAINTENANCE_ALLOW_IPS` ;
- admin, `wp-login.php`, AJAX, REST, cron, WP-CLI (le court-circuit n'opère que
  sur le rendu front via `template_redirect`).

### Constantes `wp-config.php` (optionnelles)

```php
// Verrou « dur » : prime sur l'option, survit à un import BDD.
define( '_180C_MAINTENANCE', true );

// Aperçu sans connexion : jeton long et aléatoire.
define( '_180C_MAINTENANCE_BYPASS_TOKEN', 'un-secret-long-et-aleatoire' );

// IP laissées passer (séparées par des virgules).
define( '_180C_MAINTENANCE_ALLOW_IPS', '<IP_1>,<IP_2>' );

// Échappatoire : rend le front public quel que soit l'état de l'option ou du verrou.
define( '_180C_MAINTENANCE_DISABLED', true );
```
