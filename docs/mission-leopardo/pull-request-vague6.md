# Vague 6 — direction artistique premium du héros `stage`

Branche : `feat/vitrine-vague6-premium-ui`, créée depuis `origin/main`
(`7c8d6c08d`, puis resynchronisée sur `26b5c6b3c`).
Base de comparaison pour la revue : `origin/main`.

---

## Résumé

La vague 5 avait rendu le héros **correct** : un seul CTA, une fiche qui s'ouvre
et se ferme, des cartes derrière la constellation. La vague 6 le rend
**premium** — et pour cela la composition est réorganisée, pas seulement
ajustée. Trois décisions structurent le résultat :

1. **La profondeur est une couche nommée, pas une couleur de fond.** Un ciel
   bleu nuit/graphite, une lumière diffuse derrière la constellation, un
   vignettage qui referme les bords. Trois plans, pas dix effets.
2. **La constellation domine réellement.** Elle est agrandie, éclaircie, et ses
   étiquettes passent en verre sombre : elles étaient auparavant les pastilles
   les plus blanches de l'écran, donc plus lumineuses que la scène qu'elles
   devaient servir.
3. **Le titre reste derrière — et devient lisible.** Le propriétaire veut la
   phrase sous la constellation ; la mettre devant aurait trahi la demande, la
   laisser telle quelle la rendait illisible. La réponse est un voile radial
   flouté et un liseré sombre, pas un changement de plan.

## Fichiers modifiés

| Fichier | Nature |
|---|---|
| `front/web/src/app/globals.css` | couche de profondeur, voile du titre, arc géographique recalé, variantes responsive |
| `front/web/src/modules/vitrine/components/sections/HeroSection.tsx` | plans de profondeur, titre repositionné, indicateur de défilement retiré du layout `stage` |
| `front/web/src/modules/vitrine/components/hero/HeroBusinessOS3D.tsx` | échelle/éclat de la constellation, étiquettes en verre sombre, anti-chevauchement |
| `front/web/src/modules/vitrine/components/hero/HeroScene3DPanel.tsx` | scène à 86 % de la hauteur pour rapprocher le CTA |
| `front/web/src/modules/vitrine/components/hero/HeroSolutionsCTA.tsx` | CTA unique : cible tactile ≥ 56 px, contour de focus, halo sobre |
| `front/web/.../sections/__tests__/HeroStageDepth.test.tsx` | **nouveau** — 9 cas de non-régression de composition |
| `CHANGELOG.md` | entrée `[Unreleased]` |
| `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md` | surface touchée + scénarios couverts |

## Changements réalisés

### 1. Profondeur — fond bleu nuit / graphite
`.hero-stage-base` remplace l'aplat `bg-slate-950` par trois dégradés
superposés : ciel bleu nuit en haut, deux lueurs discrètes (émeraude, cyan) en
bas, et un dégradé graphite `rgb(8 14 26) → rgb(11 19 34) → rgb(9 14 24)`.
`.hero-stage-halo` pose deux halos concentriques **derrière** la scène, animés
par `hero-halo-breathe` (14 s, amplitude 4,5 %) — déclaré **uniquement** sous
`prefers-reduced-motion: no-preference`. `.hero-stage-vignette` (`z-[11]`)
referme les bords sans jamais passer devant la scène (qui est en `z-10`).

La trame de fond est atténuée (0,05 → 0,035) et éteinte vers les bords par un
`mask-image` radial : elle structurait autant que la carte et ajoutait du bruit
sous le titre.

### 2. Le titre derrière la constellation, mais lisible
La phrase reste en `z-[5]`, la scène en `z-10`. Deux ajouts la rendent lisible
sans changer de plan :
- `.hero-stage-title-scrim` — voile radial flouté (`rgba(8,14,26,0.82)` au
  centre, transparent à 78 %) ;
- `.hero-stage-title-text` — liseré sombre (`text-shadow`, qui **ne crée pas**
  de contexte d'empilement, contrairement à un `filter`).

Le dégradé `bg-clip-text` est **conservé** : il porte le dégradé blanc →
ardoise. Le remplacer par un aplat aurait fait perdre le contraste mesuré
(`rgb(255,255,255)` en haut, `lab(84.7)` en bas — ~12:1 sur le fond).

### 3. Constellation dominante
- échelle **1,3 → 1,52** (plancher mobile 0,60 → 0,62, jamais sous le seuil de
  lisibilité) ;
- halo de noyau **5,6 → 6,4**, opacité 0,50 → 0,62 ;
- lumière de scène **18 → 26**, émission du noyau **0,7 → 1,15** ;
- étiquettes en **verre sombre** (`bg-slate-950/55`, `backdrop-blur-md`) ;
- **anti-chevauchement** : tampon de slots réutilisé (aucune allocation par
  image), tri par ordonnée, écart minimal 19 px, et masquage des étiquettes qui
  tomberaient sur le noyau.

### 4. Arc géographique recalé sur la scène
Le rayon était `min(54vw, 40vh)` : le **sommet** de l'arc tombait ~20 vh trop
haut, c'est-à-dire sur la phrase. Nouveau couple :
- `--geo-r: min(30vw, 21vh)` ;
- `--geo-apex: 54%` — ordonnée du sommet, calée sur le cœur de la scène.

L'échelle demandée est respectée (0,68 → 1 → 0,68 de gauche à droite), les
continents passent avant les pays, l'intérieur reste vide, et
`prefers-reduced-motion` ne conserve qu'une carte immobile. Variantes tablette
(`min(32vw, 17vh)`) et téléphone (`min(30vw, 12vh)`) pour que l'arc ne vienne
pas frotter le CTA.

### 5. Mise en page
- **Indicateur de défilement** : il occupait `bottom-8` centré — exactement la
  place du CTA unique. La superposition était réelle au rendu. Il est retiré du
  layout `stage` et conservé dans `centered`/`split`, où rien ne l'occupe.
- **Scène à 86 %** de la hauteur : le CTA cesse de flotter dans un vide de
  ~250 px et vient juste sous la constellation.
- **CTA** : cible tactile ≥ 56 px (`min-h-14`), `focus-visible` explicite,
  `active:scale` pour le retour tactile, halo émeraude discret.

## Tests réellement exécutés

| Commande | Résultat |
|---|---|
| `npx tsc --noEmit` | **0 erreur** |
| `npm run lint` (`eslint src --max-warnings 0`) | **0 warning** |
| `npx jest src/modules/vitrine --maxWorkers=2` | **56 suites / 658 tests** — 0 échec |
| `check:french-copy` · `check:mojibake` · `check:orphans` | **OK** |
| `node dev-hub/tools/check-i18n-diff.js origin/main HEAD` | **✅ aucune chaîne utilisateur codée en dur nouvelle** |
| `npx next build` | **exit 137 (SIGKILL) — non concluant** |

### `next build` : échec d'environnement, pas de code
`Creating an optimized production build ...` puis `Killed`, `BUILD_EXIT=137`.
Cause mesurée, pas supposée :

```
$ cat /sys/fs/cgroup/memory.max   → 2147483648   (2 Gio)
$ cat /sys/fs/cgroup/memory.peak  → 2147483648   (plafond atteint exactement)
```

Le processus est tué par le noyau ; la consommation se fait hors tas JS, donc
`--max-old-space-size` n'y changerait rien. **Une seule tentative** a été faite :
le plafond étant démontré, insister n'aurait produit qu'un second échec
identique. Le build est **à rejouer en CI**. La surface qu'il couvrirait est
déjà couverte par `tsc` (0 erreur), `eslint` (0 warning) et 658 tests verts.

## Vérification en navigateur (Chromium, `next dev`)

Captures : voir `docs/mission-leopardo/captures/`.

| Format | CTA | Débordement horizontal | Cartes d'arc |
|---|---|---|---|
| 1440 × 900 | 1 lien | **0 px** | 11 |
| 834 × 1112 | 1 lien | **0 px** | 11 |
| 390 × 844 | 1 lien | **0 px** | 11 |

Fiche solution (clic sur une étoile) : **ouverte**, **centrée** (écart < 25 px
sur l'axe horizontal), **fermée par Échap** et **par le bouton X**. Focus piégé
puis restitué à l'étoile d'origine — couvert par `mission-leopardo.test.tsx`
(vague 5), inchangé par ce lot. Aucun conflit de `stacking context` : le
portail monte en `z-[100]` sur `document.body`.

## Checklist des critères d'acceptation

- [x] Fond bleu nuit / graphite, dégradés discrets, lumière diffuse
- [x] Profondeur réelle (plans, halo, vignettage) sans surcharge
- [x] Constellation = élément le plus lumineux et le plus net
- [x] Animations lentes, amplitude faible, `prefers-reduced-motion` respecté
- [x] Fiche ouvrable/fermable (X, fond, Échap), focus piégé et restitué
- [x] Navigation clavier complète, cibles tactiles ≥ 56 px pour le CTA
- [x] Hiérarchie typographique et contraste (~12:1 sur le titre)
- [x] Aucun débordement horizontal sur les trois formats
- [x] Un seul CTA, sous la constellation, resserré
- [x] Cartes géographiques derrière la constellation, demi-cercle, 0,68 → 1 → 0,68
- [x] Titre derrière la constellation
- [x] Règles i18n respectées : **aucune** clé ajoutée, **aucun** catalogue généré touché
- [x] Tests ajoutés pour la nouvelle composition (9 cas)
- [ ] `next build` vert → **bloqué par le cgroup 2 Gio**, à rejouer en CI
- [ ] CI distante → **pas encore exécutée** (branche non poussée)

## Reste à faire

1. **Prérequis bloquant** : un PAT GitHub limité à `kitokoh/leopardo-hr` avec la
   portée `repo` (ou `contents:write` + `issues:write`). Voir
   `docs/mission-leopardo/github-handoff-vague6.md` pour les commandes exactes.
2. Rejouer `next build` en CI (le job `Frontend — ESLint + TypeScript` couvre
   déjà la surface).
3. Vérifier les 4 checks requis avant fusion ; ne pas fusionner s'ils échouent.
