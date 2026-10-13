# Branding — proposition d'intégration (#8446)

**Date :** 2026-10-13 · **Statut :** proposition prête à intégrer

## Assets livrés

| Fichier | Rôle | Format |
|---|---|---|
| `logo-loader.svg` | Logo animé de chargement (SMIL, zéro JS) | SVG animé |
| `favicon-premium.svg` | Favicon retravaillé (même monogramme LT, dégradé, lisible 16→64 px) | SVG |
| `leopard-404.png` | Mascotte 404 / empty states (léopard + câble débranché) | PNG 1024² |
| `logo-240.png`, `og-banner.png` | existants, inchangés | — |

Le monogramme **LT** de l'existant est conservé : c'est une montée en
qualité, pas un changement de marque. La mascotte léopard devient le
personnage des états hors parcours (404, offline, empty states).

## Intégration proposée

### 1. Loader de page (`logo-loader.svg`)

- Affiché pendant les transitions lourdes du dashboard et au boot des PWA
  (`front/web-offline`).
- Durée plafonnée (~1,6 s de boucle) ; jamais de spinner générique en plus.
- **`prefers-reduced-motion`** : SMIL ne lit pas la media query — servir
  alors `favicon-premium.svg` (statique). Implémentation : wrapper React qui
  choisit l'asset selon `window.matchMedia('(prefers-reduced-motion: reduce)')`.
- Texte éventuel sous le logo : depuis le catalogue i18n, jamais en dur
  (PA2-I18N-014).

### 2. Favicon

- Remplacer `front/web/public/favicon.svg` par `favicon-premium.svg` après
  validation visuelle (mêmes couleurs, géométrie resserrée).
- Décliner en `.ico` 32/16 pour les navigateurs legacy
  (`front/web/src/app/favicon.ico`) et en PNG 192/512 pour les manifests PWA
  (`front/mobile_apps/*/web/favicon.png` à régénérer depuis le même master).

### 3. Page 404 (`leopard-404.png`)

- `front/web/src/app/not-found.tsx` : illustration en haut de page (avec
  `alt` i18n), titre et chemins de retour existants conservés.
- Message suggéré (clés i18n à créer ×4 langues) :
  « Cette page s'est égarée. Pendant ce temps, votre entreprise tourne. »
- Réutiliser la mascotte pour : pages d'erreur 500, empty states du
  dashboard, écran offline de la PWA.

### 4. Cohérence

- Palette inchangée : ardoise `#0f172a`, émeraude `#34d399`, clair `#ecfeff`.
- Tout futur visuel (marketing compris) sort des prompts de
  `docs/GOTO_MARKET/PACK_LANCEMENT/01_PROMPTS_SUPPORTS_COM.md`.

## Hors périmètre de cette livraison

- Remplacement effectif dans `front/web` (attend validation sur cette PR).
- Refonte du logotype wordmark « Leopardo » (inutile : l'existant est sain).
