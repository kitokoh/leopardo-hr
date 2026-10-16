# Rapport de clôture — Mission Leopardo (vitrine, constellation, présentation des solutions)

> Branche : `mission/leopardo-vitrine-constellation`
> Commits : `ddd7b19` (implémentation, 20 fichiers, +1918 / −101) · `44d21d9` (rapport) · mise à jour finale du présent rapport
> Dépôt : `kitokoh/leopardo-hr`
> Date : 2026-10-16

---

## 1. Changements réalisés

### 1.1 Trade repositionné (sans casser les routes)

| Fichier | Changement |
|---|---|
| `front/web/src/modules/vitrine/components/Navbar.tsx` | L'entrée `Trade` retirée de la navigation principale |
| `front/web/src/app/(landing)/packs/page.tsx` | Carte « International Trade OS » ajoutée aux offres (pleine largeur, fin de grille) |

`src/app/(landing)/trade/{page,layout}.tsx`, `src/app/sitemap.ts` et
`app/(landing)/trade/__tests__/trade-page.test.tsx` **ne sont pas modifiés**.
La garde `check:orphans` confirme que `/trade` reste liée en interne.

### 1.2 Constellation = solutions métier réelles

- **Nouveau** `data/solution-catalog.ts` : source unique. Les solutions qui ont une page pack lisent `PACK_PAGES` (aucune donnée métier dupliquée) ; les autres portent une copie courte sous `data/`.
- 10 étoiles : **Restaurant OS** (anneau intérieur, avec Travel OS, Fuel Station OS, Education OS, Mobility OS) puis **International Trade OS, Community OS, HR & Payroll OS, Accounting OS, Documents OS** (anneau extérieur).
- Entrées redondantes avec le menu supprimées : **Contact**, **Pricing**, ainsi que `careers`, `alternatives`, `demo`.
- `HeroBusinessOS3D` / `HeroScene3D` / `HeroScene3DPanel` : la navigation `href` remplacée par un **callback d'index**.

### 1.3 Fiche interactive au clic

**Nouveau** `components/hero/SolutionCard.tsx` : description, profils concernés,
fonctionnalités incluses, CTA principal (page de la solution) + CTA secondaire
(onboarding `/signup`).

- `role="dialog"` + `aria-modal` + titre lié (`aria-labelledby`)
- Échap, bouton de fermeture, clic sur le fond
- Focus piégé (Tab/Shift+Tab cycliques) puis **restitué à l'étoile d'origine**
- `body.overflow` verrouillé puis restauré
- Mobile : feuille plein écran scrollable (`max-h-[92dvh]`)

### 1.4 CTA sous la constellation

**Nouveau** `components/hero/HeroSolutionsCTA.tsx`, monté dans `HeroSection` (layout `stage`, `z-20`, sous le canvas) :
« Trouver la solution adaptée à mon activité » → **Découvrir nos solutions** (`/packs`) · **Commencer maintenant** (`/signup`) · ligne de réassurance.

### 1.5 Cartes géographiques animées en arrière-plan

**Nouveau** `components/hero/GeoBackdrop.tsx` + `data/geo/map-paths.ts` (tracés générés depuis Natural Earth 1:110m, domaine public ; frontières partagées supprimées par union — l'Afrique est un seul tracé).

- Cycle France → Algérie → Turquie → Tchad → continent africain, **Option A** (transition successive, fondu croisé 1,4 s, une carte toutes les 9 s).
- La France n'est plus un décor figé.
- Opacité plafonnée à `0.22`, plan `z-0`, `aria-hidden`, jamais cliquable.
- `prefers-reduced-motion` : pas de rotation, pas de fondu, première carte affichée. Repli si `matchMedia` absent.

### 1.6 Documentation

Audit préalable + issues proposées, TAD, TRD, AppFlow, UIX/UI Design, Implementation Plan (`docs/mission-leopardo/`) + **paquet de livraison GitHub** (`docs/mission-leopardo/github-handoff.md`) : les 5 issues, le corps complet de la Pull Request et les commandes exactes. **Aucun Backend Schema** : aucune évolution du modèle de données n'est justifiée.

---

## 2. Captures d'écran avant / après

**Non produites.** Aucune capture avant/après honnête n'est disponible : les vérifications ont porté sur le code, le typecheck, le lint, les tests et les gardes. Un serveur de prévisualisation a bien été démarré pour la campagne Playwright (voir §3.3), mais celle-ci s'est arrêtée au lancement du navigateur : aucune page n'a été rendue, donc aucune capture.

---

## 3. Tests réellement exécutés et résultats

### 3.1 Suite locale (sur la branche) — ✅ verte

| Vérification | Commande | Résultat |
|---|---|---|
| Typecheck | `tsc --noEmit` | ✅ **exit 0** |
| Lint (fichiers touchés) | `eslint … --max-warnings 0` | ✅ **exit 0**, 0 warning |
| Tests unitaires (nouveaux) | `jest …/hero/__tests__/mission-leopardo.test.tsx …/sections/__tests__/HeroSection.test.tsx` | ✅ **2 suites, 25 tests, 0 échec** |
| Tests d'intégration vitrine | `jest src/modules/vitrine/__tests__ src/modules/vitrine/components/__tests__ src/modules/vitrine/lib/__tests__ "src/app/(landing)"` | ✅ **22 suites, 377 tests, 0 échec** |
| Route Trade | `jest landing.*(trade\|packs)` | ✅ **1 suite, 6 tests, 0 échec** |
| Garde mojibake | `npm run check:mojibake` | ✅ OK |
| Garde copie FR | `npm run check:french-copy` | ✅ OK |
| Garde pages orphelines | `npm run check:orphans` | ✅ 27 pages liées, `/trade` incluse |
| Garde assets de marque | `npm run check:assets` | ✅ 5 assets OK |

### 3.2 Échecs rencontrés et corrigés

- `mission-leopardo.test.tsx` : 2 échecs au premier passage (Échap dispatché sur `window` au lieu de `document` ; libellé « Travel » absent car le catalogue renvoyait le nom du pack). **Corrigés** → suite verte.
- Un premier run parallèle a vu 7 suites tuées par le noyau (`SIGKILL`, worker) : limite mémoire de l'environnement, **pas** un échec de test — les mêmes suites repassent en vert avec `--maxWorkers=2`.

### 3.3 Build de production — ⛔ non concluant (limite d'environnement)

`next build` a été tenté **4 fois** (défaut, `NODE_OPTIONS=--max-old-space-size=1280`, `=1536`, puis `--experimental-build-mode compile`). Le conteneur est **strictement plafonné à 2 Gio** de mémoire :

- `/sys/fs/cgroup/memory.max` = `2147483648` — **non modifiable** (`Permission denied`, pas de `sudo` effectif sur le cgroup) ;
- **aucun swap possible** (`fallocate` → *No space left on device*) ;
- pic mémoire mesuré à chaque tentative : **2,00 Gio** exactement, puis `Killed` (**exit 137**, OOM).

**Ce qui passe** : `✓ Compiled successfully in 36,5 s` — la phase de compilation **Turbopack réussit**. Le processus est tué à l'étape suivante (« Running TypeScript… »), Turbopack (binaire Rust) consommant de la mémoire **hors du tas JS** que `--max-old-space-size` ne plafonne pas.

**Conclusion honnête** : l'échec est **environnemental, pas applicatif**. La même surface est couverte par `tsc --noEmit` (**0 erreur**) et `eslint --max-warnings 0` (**0 warning**) ; le build doit être rejoué en CI (ou dans un conteneur disposant de plus de mémoire).

### 3.4 Tests Playwright e2e — ⛔ non concluant (limite d'outillage)

Campagne lancée sur les 2 specs vitrine (`vitrine-home-visibility.spec.ts`, `vitrine-assets-and-seo.spec.ts`, `--project=chromium`), d'abord à 8 workers (tué sur OOM au démarrage), puis **`--workers=1`** :

- le **serveur de développement démarre** (Playwright atteint `localhost:3000`, le proxy CSP journalise sa configuration) ;
- les **8 tests** échouent tous au **même point** : `browserType.launch: Executable doesn't exist at /root/.cache/ms-playwright/chromium_headless_shell-1243/…` ;
- le cache ne contient que `chromium_headless_shell-1181` : le binaire requis par la version **épinglée** de Playwright (`1.63.0`) n'est pas installé, et `npx playwright install` ne peut être tenté de façon fiable dans le disque/mémoire contraints.

**Conclusion honnête** : échec d'**outillage**, pas de comportement applicatif — aucun test n'a atteint la moindre assertion. **8 échecs, 0 succès** ; aucune conclusion de non-régression ne peut être tirée de cette campagne.

### 3.5 Non exécuté

- CI distante — **impossible** (pas de push, cf. §5).
- Fusion / suppression de branche — bloqués.

---

## 4. Issues traitées et restantes

**Traitées :** aucune issue GitHub existante ne couvrait ces chantiers (99 issues ouvertes, aucun doublon sur la vitrine/constellation).

**Proposées (à créer) :** `LEO-VIT-01` (Trade), `LEO-VIT-02` (constellation), `LEO-VIT-03` (fiche), `LEO-VIT-04` (CTA), `LEO-VIT-05` (cartes géo) — titre, description, critères d'acceptation et labels **rédigés dans `docs/mission-leopardo/github-handoff.md` §1** (et repris dans `docs/MISSION_LEOPARDO_AUDIT.md` §8).

**Restantes :** les 5 issues ci-dessus, **non créées** (accès GitHub absent).

---

## 5. ⚠️ Problème bloquant : accès GitHub en écriture

### 5.1 Constat (vérifié ce run)

| Vérification | Résultat |
|---|---|
| Profils de credentials GitHub (`get_credential_profiles`) | **0 profil** — aucun toolkit configuré |
| Jeton dans l'environnement (`env`, `.netrc`, `~/.git-credentials`) | **absent** |
| `git config` global / helper d'identification | **absent** (`/root/.gitconfig` inexistant) |
| CLI `gh` | **non installé** (seul `git` est présent) |
| `git remote` | `https://github.com/kitokoh/leopardo-hr.git` (sans credential) |

**Le jeton évoqué dans la consigne n'a jamais été transmis à l'environnement.**

### 5.2 Accès en lecture : disponible (et utilisé)

Le dépôt étant **public**, la lecture fonctionne sans jeton :
`git ls-remote https://github.com/kitokoh/leopardo-hr.git` **répond**, et `api.github.com/repos/kitokoh/leopardo-hr` renvoie **200** (`private: false`, `default_branch: main`). Cela a permis la vérification de collaboration (§6.3) — mais **aucun droit d'écriture**.

### 5.3 Étapes non exécutées (et non simulées)

- ❌ création des issues GitHub ;
- ❌ `git push` de la branche de mission ;
- ❌ Pull Request ;
- ❌ fusion et suppression de branche ;
- ❌ vérification de la CI distante.

**Prérequis manquant (exact)** : un **PAT GitHub limité au dépôt `kitokoh/leopardo-hr`, portée `repo`** (ou `contents:write` + `issues:write`). Le jeton ne doit jamais être écrit dans le code, les commits, les issues, les logs ni cette documentation versionnée.

**Tout le texte prêt à l'emploi** (5 issues + corps de PR + commandes) est dans **`docs/mission-leopardo/github-handoff.md`**.

**État de `main`** : **non modifiée** — rien n'a été poussé, donc `main` est intacte par construction (aucun contournement de protection, aucun workflow CI ajouté).

---

## 6. Fonctionnalités réellement implémentées vs réutilisées

**Implémentées :** catalogue unique des solutions ; fiche interactive au clic ; CTA sous la constellation ; fond cartographique rotatif ; sélection par index dans la scène 3D ; repositionnement de Trade dans les offres ; 12 tests unitaires nouveaux.

**Réutilisées (aucune réécriture) :** moteur WebGL `HeroBusinessOS3D` (noyau, anneaux, liens, poussière, étoiles filantes) ; `HeroScene3D` (repli 2D, `hasWebGL`, `saveData`, `requestIdleCallback`) ; `PACK_PAGES` / `packDisplayName` comme source des données métier ; `withLocaleHref` pour la propagation de `?lang=` ; `FranceConstellation` (conservée pour les autres pages) ; palette et tokens de marque existants.

---

## 7. Coûts et dépendances externes

- **Aucune dépendance npm ajoutée.** Le fond géographique est du SVG déclaratif + une transition d'opacité CSS.
- **Coût CPU/mémoire** : la rotation ne modifie qu'une propriété d'opacité sur 5 nœuds SVG ; aucune frame three.js supplémentaire (`will-change: opacity` seulement sur ces couches).
- Coût externe : **0 €** (Natural Earth est dans le domaine public ; le générateur de tracés est hors ligne).
- Le seul coût non nul est **humain** : la création des issues/PR reste à faire une fois le jeton fourni.

---

## 8. Limites identifiées

1. Pas de tickets GitHub ni de PR → la traçabilité prévue par la mission reste incomplète (textes prêts, création bloquée).
2. **`next build` non concluant** (OOM du conteneur à 2 Gio) → à rejouer en CI.
3. **e2e Playwright non concluant** (binaire Chromium de la version épinglée absent) → à rejouer en CI.
4. Aucune vérification visuelle ni mesure de performance terrain (ni build servi, ni captures).
5. La fiche n'est atteignable qu'au clic sur une étoile (champ canvas) : les mêmes destinations restent toutes accessibles par la navbar, les cartes de verticales, le hub `/packs` et le footer — aucune impasse — mais l'ouverture de la fiche à la souris n'a pas été vérifiée de bout en bout.
6. Les libellés d'étoiles sont des libellés de présentation (« Travel OS ») ; le nom enregistré du pack (« Agences de voyage ») reste inchangé dans le catalogue et le hub.

---

## 9. Critères d'acceptation — état final

| Critère | État |
|---|---|
| Lien Trade repositionné sans casser les routes | ✅ |
| Constellation = solutions métier réelles | ✅ |
| Entrées redondantes retirées | ✅ |
| Clic → fiche interactive pertinente | ✅ |
| CTA visible et fonctionnel | ✅ |
| Cartes géographiques fluides en fond | ✅ |
| Constellation dominante | ✅ |
| Performance & mobile vérifiées | ⚠️ typecheck/lint/tests verts ; **build & e2e bloqués par l'environnement** (à rejouer en CI) |
| Tests et CI passent | ⚠️ tests locaux verts · ⛔ CI distante non vérifiable |
| PR et commits documentés | ⚠️ commits locaux + **PR et issues rédigées** (`github-handoff.md`) · ⛔ création impossible |
| `main` propre et verte | ✅ intacte (aucun push) |

> **Aucune fonction critique n'est simulée.** La mission est livrée à l'état **« préparée mais bloquée »** que la consigne prévoit en l'absence d'accès GitHub : le build et les e2e n'ont pas pu conclure pour des raisons **d'environnement** dûment mesurées, jamais masquées.
