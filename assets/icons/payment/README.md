# Icônes de moyens de paiement

Logos de marque (SVG monochromes, un seul `<path>` + `<title>`) consommés par
`template-parts/subscribe/payment-badges.php` :

| Fichier | Marque |
|---|---|
| `visa.svg` | Visa |
| `mastercard.svg` | Mastercard |
| `apple-pay.svg` | Apple Pay |
| `google-pay.svg` | Google Pay |
| `paypal.svg` | PayPal |

## Rendu

Les SVG sont **inlinés** dans le template et neutralisés en **gris monochrome
adaptatif** : le CSS force `fill: currentColor`, et `currentColor` vaut
`--color-fg-muted` (couleur de texte secondaire qui bascule clair/sombre selon
le thème). Les fichiers doivent donc rester **monochromes** (forme pleine, sans
couleurs codées en dur) pour que la neutralisation fonctionne.

Le `<title>` de chaque SVG fournit le nom accessible (role=img). La pastille
(bordure) et le cadenas « Paiement sécurisé » héritent du même gris adaptatif.

## Remplacement

Pour mettre à jour un logo, déposez la version officielle **monochrome** sous le
**même nom de fichier**, en respectant les guidelines de chaque marque (ne pas
déformer, conserver les ratios) :

- Apple Pay : https://developer.apple.com/apple-pay/marketing/
- Google Pay : https://developers.google.com/pay/api/web/guides/brand-guidelines
- PayPal : https://www.paypal.com/us/webapps/mpp/logo-center
- Visa / Mastercard : kits de marque respectifs
