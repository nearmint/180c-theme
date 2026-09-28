# Badges des stores

Ce dossier contient les SVG des badges « Télécharger sur l'App Store » et
« Disponible sur Google Play » utilisés dans le side menu et le footer.

| Fichier | Usage |
|---|---|
| `app-store-fr-black.svg` | Side menu — badge App Store français, fond noir |
| (Google Play : ./icons/google-play-badge.svg — utilisé dans le footer principal, voir src/images/icons/) | Footer principal |

## Procédure pour remplacer les placeholders

**Apple — App Store**

1. Aller sur https://developer.apple.com/app-store/marketing/guidelines/
2. Section « App Store Badges » → télécharger le SVG officiel
3. Langue : Français (FR). Variante : noir foncé (sur fond clair) OU noir avec
   contour blanc (sur fond sombre). Choisir selon l'usage : ici fond noir
   uniforme, donc badge à fond noir sur ce site.
4. Renommer en `app-store-fr-black.svg`, écraser le fichier actuel.
5. **Respecter les guidelines Apple** :
   - Espace blanc minimum autour du badge = 1/10 de la hauteur du badge.
   - Ne pas modifier les couleurs, proportions, ou ajouter d'éléments.
   - Hauteur minimum 40 px à l'écran (ici on est à 54 px → OK).
6. `npm run build` (pas obligatoire pour un asset servi directement depuis
   src/images/, mais utile pour cache busting si l'on met à jour aussi le CSS).

**Google — Play Store**

Voir `src/images/icons/google-play-badge.svg` (footer principal). Procédure
équivalente sur https://play.google.com/intl/en_us/badges/.

## Note technique

Les SVG sont servis directement depuis `_180C_THEME_URI . '/src/images/...'`
sans passer par le bundle Vite (ce sont des images HTML, pas des assets
référencés dans le CSS). Vite ne les hashe donc pas, mais leur URL absolue
reste stable d'une release à l'autre, ce qui est ce qu'on veut pour des
assets statiques de branding.
