# Retours vague 5 — fiche accessible, CTA unique, cartes derrière la constellation

Branche `fix/vitrine-hero-constellation-ux` → `main`.

## Contexte

Cette PR corrige dix retours utilisateur sur la vitrine publique (héros +
constellation « Business OS ») et les transforme en comportements vérifiables.
L'audit préalable est dans `docs/mission-leopardo/MISSION_LEOPARDO_AUDIT.md` ;
les conventions i18n du dépôt sont respectées (sources uniquement, puis sync).

## Changements

### 1. Un seul CTA, plus bas — `HeroSolutionsCTA`
- Un **unique** lien « Découvrir nos solutions » → `/packs` (`?lang=` préservé).
- Suppression du second bouton (onboarding) et du bloc de réassurance commerciale.
- Rendu par `HeroScene3DPanel`, donc **sous la scène** (jamais dupliqué par
  `HeroSection`), avec une marge haute qui le décolle du globe.

### 2. Fiche solution réparée — `SolutionCard`
Cause racine identifiée : la fiche était rendue **dans** le contexte d'empilement
du héros (`z-10` `isolate`), dont l'overlay était recouvert par un plan `z-20`
plein écran, et le parent `overflow-hidden` la coupait.

- `createPortal(…, document.body)` + overlay `fixed inset-0 z-[100]`.
- Fermeture par **bouton X**, **clic sur le fond**, **Échap**.
- Focus piégé (Tab / Shift+Tab cycliques), `role="dialog"`, `aria-modal`,
  `aria-labelledby`, et **focus restitué à l'étoile d'origine**.
- Mobile : feuille ancrée en bas, décollée du bord (`p-3`, `max-h-[88dvh]`).

### 3. Cartes géographiques DERRIÈRE la constellation — `GeoBackdrop`
- Demi-cercle unique : `z-[1]`, soit **sous** le titre (`z-[5]`) et la
  constellation (`z-10`), chaque couche en `isolate`.
- 11 cartes en arc : **continents d'abord, puis pays**.
- Échelle gauche → centre → droite : 0,68 → 1 → 0,68 ; opacité et flou discrets.
- Intérieur **vide** : un tracé Natural Earth + contour, aucun texte.
- `prefers-reduced-motion` : rotation et fondu neutralisés (vérifié en
  navigateur, `animationName: none`).

### 4. Le titre passe DERRIÈRE la constellation
Pile explicite et documentée dans `HeroSection` : cartes `z-[1]` < titre `z-[5]`
< constellation `z-10` < CTA porté par la scène.

### 5. Constellation agrandie
Échelle 3D : plafond `1,12 → 1,3`, plancher `0,45 → 0,55`.

### 6. Libellés bornés au cadre
Sur mobile, les étiquettes sortaient tronquées au bord gauche (« OS » seul).
Le centre projeté est désormais borné à 15 % du cadre.

### 7. Copie allégée (4 locales)
| Retiré | Où |
|---|---|
| « Modules to explore » (et Équivalents tr/en/ar) | `hero3d.hint` — supprimé des sources |
| « Find the right solution… » + sous-titre | `ctaTitle` / `ctaSubtitle` |
| « Free pack · No credit card · No commitment » | `ctaReassurance` |
| « Free packs » dans le menu | `nav.packs` → **« Pack »** |
| « Le pack de démarrage est offert » (fiche Restaurant) | `solution-catalog` fr/en/ar |

## i18n

Sources `shared/i18n/locales/{fr,en,tr,ar}.json` éditées, puis
`node shared/i18n/sync/sync-web.js` → `I18N_SYNC_WEB_OK`.
**Aucun catalogue généré n'a été modifié à la main.** Aucune chaîne nouvelle
codée en dur dans un composant (copie dans `data/solution-catalog.ts`).

## Tests réellement exécutés

| Commande | Résultat |
|---|---|
| `npx tsc --noEmit` | ✅ 0 erreur |
| `npx eslint --max-warnings 0` (fichiers touchés) | ✅ 0 warning |
| `npx jest src/modules/vitrine` | ✅ **55 suites / 649 tests** — 0 échec |
| `check:french-copy` / `check:mojibake` / `check:orphans` / `check:assets` | ✅ OK |
| `next build` | ⛔ **exit 137 — OOM** (cgroup 2 Gio du sandbox) |

`next build` n'est **pas** déclaré vert : le conteneur de développement est
plafonné à 2 Gio (`memory.peak == memory.max`) et le processus est tué par le
noyau hors tas JS. À rejouer en CI.

## Vérification navigateur (Chromium)

| Taille | CTA | Fiche |
|---|---|---|
| 1440×900 | 1 lien, visible, `arcCards: 11`, `arcZ: 1`, débordement 0 px | centrée, dans le viewport, focus dedans, Échap ✅ X ✅ |
| 834×1112 | idem | idem |
| 390×844 | idem | feuille bas décollée, Échap ✅ X ✅ |

`prefers-reduced-motion: reduce` : aucun effet animé sur les cartes, contenu
principal toujours présent.

## Checklist des critères d'acceptation

- [x] Un seul bouton sous la constellation, plus bas
- [x] Fiche centrée, au-dessus de tout, fermable par X / fond / Échap
- [x] Focus piégé puis restitué à l'étoile d'origine
- [x] Cartes pays derrière la constellation, sur un demi-cercle, gauche→centre→droite
- [x] Continents puis pays
- [x] « Modules to explore » retiré (4 langues)
- [x] Carte du pays vide à l'intérieur
- [x] Titre/sous-titre du CTA retirés
- [x] « Free pack » / « No credit card » retirés ; constellation agrandie
- [x] Titre principal derrière la constellation
- [x] Menu : « Pack »
- [x] Accessibilité : clavier, ARIA, focus visible, `prefers-reduced-motion`
- [x] Typecheck, lint, tests, gardes dépôt verts
- [ ] `next build` — non concluant en local (OOM), à valider en CI
