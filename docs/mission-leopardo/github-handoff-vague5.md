# Handoff — retours vague 5 (vitrine Leopardo)

Branche : `fix/vitrine-hero-constellation-ux`, créée depuis `origin/main` (`e9c5f986c`).
État : **arbre propre**, `main` intacte (rien n'a été poussé — aucun identifiant
GitHub en écriture dans l'environnement de développement).

> ⚠️ Prérequis manquant : un PAT GitHub limité au dépôt `kitokoh/leopardo-hr`
> (portée `repo`, ou `contents:write` + `issues:write`). Aucun secret n'est
> écrit dans ce fichier, dans le code, les commits ou les logs.

## 1. Les 10 points demandés — état

| # | Demande | Implémentation |
|---|---|---|
| 1 | Un seul bouton sous la constellation, un peu plus bas | `HeroSolutionsCTA` rend **un unique** `<Link>` vers `/packs` ; second bouton et bloc de réassurance supprimés ; `mt-6 sm:mt-10` le décolle du globe. Rendu par `HeroScene3DPanel`, donc collé sous la scène, jamais dupliqué par `HeroSection`. |
| 2 | Fiche qui s'ouvre trop bas et ne se ferme pas | `SolutionCard` rendue via `createPortal(…, document.body)`, overlay `fixed inset-0 z-[100]` — hors du contexte d'empilement du héros. Fermeture par X, clic sur le fond et Échap ; focus piégé (Tab/Shift+Tab cycliques) puis **restitué à l'étoile d'origine**. |
| 3 | Cartes pays DERRIÈRE la constellation, sur un demi-cercle | `GeoBackdrop` sur `z-[1]` (constellation `z-10`), 11 cartes en arc, échelle 0,68 → 1 → 0,68 (gauche → centre → droite), **continents d'abord puis pays**. |
| 4 | Retirer « Modules to explore » | Clé `hero3d.hint` supprimée des 4 locales sources ; le panneau ne rend plus le hint. |
| 5 | Carte du pays vide à l'intérieur | Chaque `.geo-arc-card` = un `<svg>` de tracé + contour, **aucun texte, aucun contenu**. |
| 6 | Retirer « Find the right solution… » et son sous-titre | `ctaTitle` / `ctaSubtitle` / `ctaReassurance` retirés du catalogue de copie (4 locales) et du composant. |
| 7 | Retirer « Free pack » / « No credit card », agrandir la constellation | Ligne de réassurance supprimée ; fiche Restaurant nettoyée de « pack de démarrage est offert » (fr/en/ar) ; échelle 3D 1,12 → **1,3** (plancher 0,45 → 0,55). |
| 8 | Titre DERRIÈRE la constellation, étoiles au premier plan | Pile explicite : cartes `z-[1]` < **titre `z-[5]`** < constellation `z-10`, chaque couche en `isolate`. |
| 9 | Menu : « Pack » au lieu de « Free pack » | `nav.packs` → « Pack » / « Pack » / « Paket » / « حزمة » dans les 4 locales sources. |
| 10 | UX desktop / tablette / mobile | Vérifié en Chromium : 1440×900, 834×1112, 390×844 — voir §3. |

## 2. i18n et conventions

- Seules les **sources** `shared/i18n/locales/{fr,en,tr,ar}.json` ont été éditées,
  puis `node shared/i18n/sync/sync-web.js` → `I18N_SYNC_WEB_OK`.
  Aucun catalogue généré touché à la main.
- Aucune chaîne nouvelle codée en dur dans un composant : la copie vit dans
  `data/solution-catalog.ts` (chemin exempté PA2-I18N-014).

## 3. Vérifications réellement exécutées

| Vérification | Résultat |
|---|---|
| `npx tsc --noEmit` | ✅ 0 erreur |
| `npx eslint` (fichiers touchés, `--max-warnings 0`) | ✅ 0 warning |
| `npx jest src/modules/vitrine` | ✅ **55 suites / 649 tests**, 0 échec |
| `check:french-copy`, `check:mojibake`, `check:orphans`, `check:assets` | ✅ OK |
| `sync-web.js` | ✅ `I18N_SYNC_WEB_OK` |
| Navigateur (Chromium 1181), 3 tailles | ✅ 1 seul lien CTA, `arcCards: 11`, `arcZ: 1`, débordement horizontal **0 px** |
| `prefers-reduced-motion: reduce` | ✅ aucune animation de carte (`animationName: none`), titre et canvas présents |
| Fiche au clic (desktop 1440×900) | ✅ ouverte, **centrée**, dans le viewport, focus dans la fiche, fermeture Échap + X |
| Fiche au clic (mobile 390×844) | ✅ feuille ancrée en bas, décollée du bord, fermeture Échap + X |
| `next build` | ⛔ **exit 137 — OOM** (cgroup 2 Gio, voir §4) |

## 4. Échec réel restant : `next build`

Le conteneur est plafonné à **2 Gio** (`memory.peak == memory.max == 2147483648`) ;
le processus est tué par le noyau pendant la compilation Turbopack. `NODE_OPTIONS=--max-old-space-size=1200`
ne change rien : la consommation est hors tas JS. **À rejouer en CI**, qui dispose
de la marge nécessaire. La surface est déjà couverte par `tsc` (0 erreur), `eslint`
(0 warning) et 649 tests verts.

## 5. Commandes à exécuter (une fois le PAT disponible)

```bash
cd leopardo-hr
# le jeton doit venir d'un credential helper éphémère, jamais du dépôt
git push -u origin fix/vitrine-hero-constellation-ux
```

Puis, via l'API (5 issues… ici 1 seule mission, donc 1 PR) :

```bash
gh pr create --repo kitokoh/leopardo-hr \
  --base main --head fix/vitrine-hero-constellation-ux \
  --title "fix(vitrine): retours vague 5 — fiche accessible, CTA unique, cartes derrière la constellation" \
  --body-file docs/mission-leopardo/pull-request-vague5.md
```

Fusionner **uniquement** si les checks requis passent
(`PHPStan — Strict (level 8)`, `Module Structure Validator`,
`Frontend — ESLint + TypeScript`, `actionlint (+ shellcheck)`), puis :

```bash
git push origin --delete fix/vitrine-hero-constellation-ux
```

## 6. État distant (mis à jour)

- **Issue** : #8638 créée (retours vague 5, critères d'acceptation) — liée par `Closes #8638`.
- **Branche** : `fix/vitrine-hero-constellation-ux` poussée sur `origin`.
- **Pull Request** : **#8637** — https://github.com/kitokoh/leopardo-hr/pull/8637
- **Fusion `origin/main`** : `e9c5f986c` → `69b7cc599` fusionné dans la branche ; seul conflit
  sur le fichier **généré** `shared/i18n/versions/versions.json`, résolu par régénération
  (`sync-web` / `sync-mobile` / `sync-backend`), jamais à la main.
- **CI** : en cours de vérification. Échecs triés :
  - `Desktop verify *` (Flutter) — **préexistant**, conflit `sentry_flutter ^9` déjà tracé par
    l'issue #8599 ; non requis, hors périmètre.
  - `Check PR/issue governance` — corrigé (issue #8638 + `Closes #8638`).
  - `Governance Gates (changelog + canonical files)` — corrigé (entrée de registre ci-dessus).
- **Statut** : fusion **en attente** de la CI verte sur les checks requis.

## 7. Ce qui n'a PAS été fait

- Aucun test e2e Playwright du dépôt lancé (binaire Chromium du projet absent) ;
  les vérifications navigateur ont été faites avec le Chromium 1181 du sandbox.
- `next build` non concluant en local (OOM, cgroup 2 Gio) — à valider en CI.
