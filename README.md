# 180c-theme

![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759B?logo=wordpress&logoColor=white) ![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white) ![WooCommerce](https://img.shields.io/badge/WooCommerce-Subscriptions-7F54B3?logo=woocommerce&logoColor=white) ![Tailwind CSS v4](https://img.shields.io/badge/Tailwind%20CSS-v4-06B6D4?logo=tailwindcss&logoColor=white) [![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)

Custom WordPress theme powering [180c.fr](https://www.180c.fr), the website of 180°C, an independent French food magazine: editorial content, a recipe library, a WooCommerce store and paid subscriptions. It also serves the REST API used by the 180°C iOS and Android apps.

## Highlights

- **Editor-driven layouts**: classic PHP templates with an ACF Flexible Content page builder for the home page and landing pages.
- **App-ready API**: `180c/v1` REST namespace (auth, favorites, recipes, push, newsletter) consumed by the native apps via JWT.
- **Native SEO**: meta tags, JSON-LD (`Article`, `Recipe`, `Product`, `FAQPage`, `VideoObject`), sitemap and `llms.txt`, without any SEO plugin.
- **Privacy by design**: in-house consent banner; GA4 and Umami load only after consent.
- **Push notifications**: OneSignal for mobile and web, including automated sends on recipe publication.
- **Hardening in code**: rate limiting, security headers, and TOTP two-factor authentication for the admin account.

## Stack

| Layer | Technology |
|---|---|
| CMS | WordPress 6.4+, PHP 8.3+ |
| Commerce | WooCommerce, WooCommerce Subscriptions, WooCommerce Memberships |
| Content model | ACF Pro, versioned as Local JSON in `acf-json/` |
| Front end | Vite 6, Tailwind CSS v4 (CSS-first), vanilla ES modules, BEM |
| Integrations | Stripe, PayPal, Mailchimp, OneSignal, Simple JWT Login |
| Quality | PHPCS + WordPress Coding Standards, ESLint, Stylelint |

## Getting started

Requirements: PHP 8.3+, Composer, Node 24+, a local WordPress install with the plugins above.

```bash
cd wp-content/themes
git clone https://github.com/nearmint/180c-theme.git
cd 180c-theme
composer install
npm install
npm run build
```

Then activate **180°C** in *Appearance › Themes*. ACF field groups sync from `acf-json/`.

## Development

```bash
npm run dev       # Vite dev server
npm run build     # production build to dist/
npm test          # build + JS unit tests
composer lint     # PHPCS / WPCS
npm run lint      # ESLint + Stylelint
```

Conventions:

- PHP functions and options are prefixed `_180c_`; the text domain is `180c`.
- One feature per file under `inc/`, loaded from `inc/bootstrap.php`.
- CSS follows strict BEM on top of design tokens.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/); `main` is merged with `--no-ff` only.

## Deployment

Every push to `main` triggers `.github/workflows/deploy.yml`: PHP lint, build, artifact checks, then SFTP upload of the theme to production. `lint.yml` runs PHPCS on PHP 8.3 and 8.5 for `main` and `develop`.

## Project layout

```
inc/            PHP features: blocks, REST API, SEO, WooCommerce, auth, push, analytics
src/            CSS and JS sources, compiled by Vite
parts/          Template parts
woocommerce/    WooCommerce template overrides
acf-json/       ACF field groups (Local JSON)
mu-plugins/     Must-use plugin sources, installed manually (not deployed)
tools/          CI checks (REST argument validation)
```

Further reading: [`CLAUDE.md`](CLAUDE.md) for architecture rules, [`mu-plugins/README.md`](mu-plugins/README.md) for must-use plugins.

## License

[MIT](LICENSE)
