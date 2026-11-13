# LEOPARDO TOOLBOX — Rapport final

> **Date** : 2026-11-05 (lot 1) · **Mise à jour** : 2026-11-13 (lot 4 — partage d'un résultat par lien)
> **Branches** : `feat/vitrine-toolbox` (lot 1) → `feat/vitrine-toolbox-lot2` (lot 2) → `feat/toolbox-mes-outils-ui` (lot 3) → `feat/toolbox-resultats-partageables` (lot 4)
> **Statut** : ✅ **livré et mergé dans `main`** — vérifié sur l'état distant réel.
> **`main` distant au 2026-11-13 (lot 4)** : `0bce0432707bea28ebc3ec82e2de40e71b5d38cd`

---

## 1. ✅ Publication GitHub — débloquée et terminée

> **Mise à jour du 2026-11-08** : le blocage décrit dans la version initiale de ce
> rapport (token invalide) a été **levé** avec un token valide. La publication a
> été menée à son terme.

| Vérification | Résultat |
|---|---|
| `GET https://api.github.com/user` | ✅ 200 — authentifié comme **`kitokoh`** |
| Permissions sur `kitokoh/leopardo-hr` | ✅ `admin: true`, `push: true` |
| Branche `feat/vitrine-toolbox` poussée | ✅ |
| PR [#9159](https://github.com/kitokoh/leopardo-hr/pull/9159) | ✅ **merged** — commit `29a5e8863037d9d12feef3612f8a7143b4523d23` |
| Issues #9152–#9155 | ✅ créées puis fermées automatiquement par la PR |
| `main` distant | ✅ `29a5e8863…` puis `410e63e5b…` (lot 2) |

**Lot 2 (2026-11-08)** : 5 outils supplémentaires livrés via la PR
[#9182](https://github.com/kitokoh/leopardo-hr/pull/9182), mergée au commit
`410e63e5b48a069d7e5c17971243dc1f120043b4`. Issue #9181 fermée automatiquement.

**Lot 2 bis (2026-11-08)** : les 4 derniers outils du backlog #9158 (devises, durée
entre dates, âge, taux horaire) livrés via la PR #9190, commit `09d5d6c62`.
Issue #9158 fermée.

**Décisions #9156 / #9157 (2026-11-09)** : tranchées et documentées, puis
implémentées dans le commit `98dd2cb53` (PR #9266) :
- `docs/DECISIONS/2026-11-09-toolbox-persistance-mes-outils.md` — option A,
  `localStorage` reste la source de vérité (clé versionnée
  `leopardo_toolbox_state_v1`, façade `lib/storage.ts`).
- `docs/DECISIONS/2026-11-09-toolbox-administration-metadonnees.md` — option A,
  catalogue statique versionné + champ `enabled` (masquage par PR, sans table).

**Lot 3 (2026-11-13)** : câblage de « Mes outils » dans l'interface publique.
Issue [#9440](https://github.com/kitokoh/leopardo-hr/issues/9440) créée puis fermée
par la PR [#9441](https://github.com/kitokoh/leopardo-hr/pull/9441), mergée au commit
`63d18224142fdeb52c3f3ff04a0f10540b361a64`.

> **Constat du lot 3** : `lib/storage.ts` était **testé mais jamais utilisé** —
> aucun composant n'appelait `loadState` / `toggleFavorite` / `recordUsage`. La
> décision #9156 était appliquée dans le stockage, pas dans l'interface. Le lot 3
> comble exactement cet écart, sans rouvrir la décision.

> ⚠️ **Aucun token n'a jamais été commité** dans ce dépôt : les seules occurrences
> de `ghp_` dans l'historique sont des **placeholders tronqués** (`ghp_…`),
> conformes à la convention anti-secret de `SECURITY.md`. Vérifié par
> `git log --all -S "<token complet>"` → aucun résultat.

---

## 2. Livrables produits

| Livrable | Fichier |
|---|---|
| Audit de l'architecture (Phase 0) | `docs/leopardo-toolbox/00_audit_architecture.md` |
| Document de conception Toolbox (Phase 1) | `docs/leopardo-toolbox/01_conception_toolbox.md` |
| Issues GitHub proposées (prêtes à créer) | `docs/leopardo-toolbox/02_issues_proposees.md` |
| Documentation des formules | `docs/leopardo-toolbox/03_formules.md` |
| Décision d'architecture (ADR) | `docs/adr/TOOLBOX-001-categorie-autonome-client-side.md` |
| Règles du module | `front/web/src/modules/toolbox/AGENTS.md` |
| Mode d'emploi du module | `front/web/src/modules/toolbox/README.md` |

## 3. Fichiers créés / modifiés (implémentation)

### Créés — module `front/web/src/modules/toolbox/`

```
types/index.ts                       # ToolDefinition, ToolCategory, ToolWarningId
data/catalog.ts                      # catalogue déclaratif (5 outils)
data/copy.ts                         # copie i18n fr/en/tr/ar
data/tool-options.ts                 # libellés d'options (IMC, unités)
lib/registry.ts                      # résolution slug, recherche, slugs
lib/validation.ts                    # parseNumber, safeDivide, bornes, arrondi
lib/format.ts                        # formatage Intl (nombres, devises, %)
lib/registry.test.ts                 # 12 tests
lib/validation.test.ts               # 15 tests
components/NumberField.tsx           # champ numérique (unité, bornes, erreur)
components/ResultPanel.tsx           # résultats + copie presse-papiers
components/ToolCard.tsx              # carte du catalogue
components/ToolShell.tsx             # gabarit de page d'outil
components/ToolboxCatalog.tsx        # catalogue (recherche + filtre)
components/ToolRenderer.tsx          # sélecteur de formulaire
components/tools/ImcForm.tsx
components/tools/VatForm.tsx
components/tools/NetSalaryForm.tsx
components/tools/UnitConverterForm.tsx
components/tools/PercentForm.tsx
tools/imc/logic.ts + logic.test.ts
tools/tva/logic.ts + logic.test.ts
tools/salaire-net/logic.ts + logic.test.ts
tools/convertisseur-unites/logic.ts + logic.test.ts
tools/pourcentage/logic.ts + logic.test.ts
index.ts                             # exports publics
AGENTS.md, README.md
```

### Créés — routes publiques `front/web/src/app/toolbox/`

```
layout.tsx                           # chrome autonome (en-tête + pied)
page.tsx                             # catalogue /toolbox (SSG, SEO, hreflang)
[slug]/page.tsx                      # page d'outil (generateStaticParams, SEO)
[slug]/not-found.tsx                 # 404 propre
```

### Modifiés (zone vitrine — branche `vitrine-` conforme au verrou)

```
front/web/src/app/sitemap.ts                          # + /toolbox et 5 slugs
front/web/src/modules/vitrine/components/Footer.tsx   # route '1-11' → /toolbox
front/web/src/modules/vitrine/lib/vitrine-locale.ts   # libellé « Outils » ×4 locales
```

## 4. Tests réellement exécutés (preuves)

| Vérification | Commande | Résultat |
|---|---|---|
| Typecheck | `npx tsc --noEmit` | ✅ 0 erreur |
| Lint (module + routes) | `npx eslint src/modules/toolbox src/app/toolbox --max-warnings 0` | ✅ 0 avertissement |
| Lint (tout `src`) | `npx eslint src --max-warnings 0` | ✅ 0 avertissement |
| Tests Toolbox (lot 1) | `npx jest src/modules/toolbox` | ✅ **74 tests, 6 suites, 0 échec** |
| Tests Toolbox (lot 1 + lot 2) | `npx jest src/modules/toolbox` | ✅ **145 tests, 12 suites, 0 échec** |
| Tests Toolbox (lot 1 + lot 2 + lot 3) | `npx jest src/modules/toolbox --maxWorkers=1 --workerIdleMemoryLimit=400MB` | ✅ **235 tests, 25 suites, 0 échec** |
| Tests Toolbox (lot 4) | `npx jest src/modules/toolbox --maxWorkers=2 --workerIdleMemoryLimit=512MB` | ✅ **259 tests, 27 suites, 0 échec** |
| Tests projet (complet, lot 4) | `npx jest --maxWorkers=2 --workerIdleMemoryLimit=512MB` | ✅ **2243 tests, 256 suites, 0 échec** |
| Tests projet (complet, lot 3) | `npx jest --maxWorkers=2 --workerIdleMemoryLimit=400MB` | ✅ **2219 tests, 254 suites, 0 échec** |
| Garde i18n diff (auto-test) | `bash dev-hub/tools/check-i18n-diff-test.sh` | ✅ `PASS : check-i18n-diff-test.sh` |
| Garde i18n diff (diff réel) | `node dev-hub/tools/check-i18n-diff.js <base> <head>` | ✅ 4 fichiers, 0 chaîne en dur |
| Garde i18n diff (lot 4) | `node dev-hub/tools/check-i18n-diff.js origin/main HEAD` | ✅ **19 fichiers, 0 chaîne en dur** |
| Partage réel (navigateur) | Playwright : saisie → clic « Partager » → lien copié → ouverture dans un **contexte neuf** | ✅ valeurs rejouées `82` / `181`, IMC affiché |
| Lien corrompu (navigateur) | `?s=%%%invalide%%%` | ✅ retombe sur les valeurs par défaut (`70`) |
| Rendu des 15 routes (lot 4) | `curl` sur `/toolbox` + 14 outils + slug inconnu | ✅ 200 partout |
| Tests projet (complet) | `npx jest --maxWorkers=2` | ✅ **1758 tests passés, 0 échec** (2 suites tuées par OOM puis relancées seules : ✅ passent) |
| Garde mojibake | `node scripts/check-mojibake.mjs` | ✅ passe |
| Garde copie FR | `node scripts/check-french-copy.mjs` | ✅ passe |
| Garde pages orphelines | `node scripts/check-orphan-pages.mjs` | ✅ passe |
| Build Next.js | `npx next build` | ⚠️ compile + typecheck OK ; **collecte des pages tuée par OOM** (limite cgroup 2 Go, 63 workers) — non lié au code Toolbox |
| Rendu réel (dev server) | `curl` sur les 7 routes | ✅ `/toolbox` 200, 5 outils 200, slug inconnu 404 |
| Sitemap | `curl /sitemap.xml` | ✅ `/toolbox` + 5 slugs présents |
| Lien footer | `curl /` | ✅ `href="/toolbox"` + libellé « Outils » |
| Vérification visuelle | capture Chromium + analyse vision | ✅ aucune casse (5 cartes, catégories, formulaire IMC avec résultat 22,9) |

**Note honnête sur le build** : `next build` n'a pas pu terminer la collecte des
pages dans cet environnement (mémoire limitée à 2 Go). La compilation et le
typecheck passent ; le rendu réel de toutes les routes a été vérifié via le
serveur de développement. Le build complet doit être confirmé en CI (qui dispose
de plus de mémoire).

## 5. Fonctionnalités livrées

- **Catalogue public** `/toolbox` : recherche plein-texte (insensible casse/accents),
  filtre par catégorie, 5 outils, 100 % client.
- **5 outils** utilisables immédiatement, **sans compte** :
  IMC (classification OMS), TVA (HT ↔ TTC, taux FR), salaire brut → net (estimation
  avec taux paramétrable), convertisseur d'unités (6 catégories, facteurs SI exacts),
  pourcentages (3 modes).
- **i18n** fr/en/tr/ar (dont RTL arabe) pour toute la copie du module.
- **SEO** : métadonnées localisées, alternates hreflang, sitemap, indexable.
- **Avertissements** : estimation (salaire), non-conseil (IMC), références externes.
- **Découvrabilité** : lien « Outils » dans le footer de la vitrine (4 langues).

### Lot 3 — « Mes outils » câblé dans l'interface (2026-11-13)

- **`components/MyTools.tsx`** — section « Mes outils » sur `/toolbox` : favoris et
  historique, hydratation dans `useEffect` (aucune lecture de `localStorage` au
  rendu → pas d'erreur d'hydratation SSR), slugs inconnus ignorés, état vide
  explicite, bouton d'effacement.
- **`components/ToolActions.tsx`** — bouton favori (`aria-pressed`) et
  enregistrement d'usage au montage sur `/toolbox/[slug]`.
- **i18n** — bloc `myTools` dans les 4 locales (fr/en/tr/ar, dont RTL arabe).
- **Tests** — 13 nouveaux cas : état vide, ordre des favoris, slug obsolète,
  effacement, stockage corrompu, stockage inaccessible, bascule favori,
  historique sans doublon.
- **Aucun backend, aucune API, aucune migration** : la décision #9156 (option A)
  reste inchangée.

### Lot 4 — Partage d'un résultat par lien (2026-11-13)

**Manque constaté par inspection du code** (pas supposé) : `ResultPanel` n'offrait
que « Copier » (presse-papiers, perdu à la fermeture), aucun formulaire ne lisait
ni n'écrivait l'URL, et `lib/storage.ts` restait local au navigateur. Un visiteur
ne pouvait donc **pas transmettre** un calcul.

**Solution** : encoder les **entrées** dans l'URL (`?s=…`), jamais le résultat.
Un lien rejoue le calcul avec la formule courante et ne peut donc pas afficher un
résultat calculé par une version obsolète.

| Fichier | Rôle |
|---|---|
| `lib/share.ts` | Codec versionné (base64url d'un JSON `{v,s}`), validation stricte de l'entrée URL, encodeur/décodeur UTF-8 maison (`TextEncoder` absent de jsdom) |
| `lib/useToolState.ts` | État de formulaire synchronisé avec l'URL : lecture au montage (pas de désaccord d'hydratation), écriture `history.replaceState` (pas d'entrée d'historique par frappe), seulement après une saisie |
| `components/ShareButton.tsx` | Copie le lien ; repli en champ sélectionnable si le presse-papiers est refusé |
| `lib/share-copy.ts` | Point unique de résolution des libellés (exigé par la garde de cohérence i18n) |
| `components/ResultPanel.tsx` | Prop `share` optionnelle |
| `components/tools/*.tsx` | **Les 14 formulaires** fournissent la prop `share` |
| `data/copy.ts` | i18n fr/en/tr/ar : `share` / `shared` / `shareFailed` |

**Propriétés de sécurité** : tout ce qui vient de l'URL est non fiable — type,
longueur (2 048 car. max), nombre d'entrées (24 max), clés inconnues écartées,
version inconnue ignorée, JSON/base64 invalide → valeurs par défaut. Un lien forgé
ne peut pas injecter un champ que le formulaire n'attend pas.

**Aucun backend, aucune API, aucune migration** : les entrées restent dans l'URL,
rien n'est envoyé à un serveur.

## 6. Matrice d'accès (livrée)

| Fonctionnalité | Visiteur anonyme | Utilisateur public | Professionnel | Collaborateur Leopardo | Administrateur |
|---|---|---|---|---|---|
| Catalogue `/toolbox` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Utiliser un outil | ✅ **sans compte** | ✅ | ✅ | ✅ | ✅ |
| Copier/partager un résultat | ✅ **lien, sans compte** | ✅ | ✅ | ✅ | ✅ |
| Favoris / historique | ✅ **local, sans compte** | ✅ | ✅ | ✅ | ✅ |
| Synchronisation multi-appareils | — (décision #9156, option A) | — | — | — | — |
| Administration des outils | — (décision #9157, option A) | — | — | — | — |

> Les favoris et l'historique sont **locaux au navigateur** (`localStorage`) : ils
> fonctionnent pour un visiteur anonyme, ne créent aucun compte et n'envoient
> aucune donnée. La synchronisation multi-appareils reste hors périmètre par
> décision explicite (#9156).

## 7. Limites connues

1. **Build complet non terminé localement** (OOM environnement, limite cgroup 2 Go)
   — non lié au code Toolbox ; les 4 checks requis sont verts en CI sur `main`.
2. **Pas de synchronisation serveur** des favoris/historique : décision #9156
   (option A) assumée, réexamen conditionné à un besoin mesuré.
3. **Pas d'administration** des métadonnées : décision #9157 (option A) assumée.
4. **Aucun outil en backlog** : les 14 outils du catalogue sont livrés (#9158 fermée).
5. **Salaire net = estimation** : aucun barème fiscal codé en dur, par choix
   (éviter de présenter un calcul légal non vérifié).
6. **Taux de change statiques** : le convertisseur de devises n'appelle aucun
   service externe (contrainte « pas de dépendance payante ») ; les taux sont
   documentés et datés, l'outil porte l'avertissement `estimate`.
7. **Checks CI non requis en échec** : `Route → OpenAPI coverage guard`,
   `validate-and-sync`, `Hygiene Guards`, `Web Marketing Funnel E2E` échouent sur
   cette PR **comme sur les PR précédentes** (vérifié : la PR #9441 mergée portait
   les mêmes 3 échecs ; `Hygiene Guards` échoue aussi sur `main`). Preuve que
   `Route → OpenAPI coverage guard` n'est pas causé par ce lot : exécuté
   localement, il ne signale que 5 routes de `modules/accounting.php`
   (`/v1/accounting/tax-rates*`, PR #9397) — ce lot ne touche **aucun fichier PHP**.
   Les **4 checks requis** sont verts.
8. **Le lien de partage porte les entrées en clair** (base64url, non chiffré) :
   ne pas y mettre de donnée sensible. Documenté dans `lib/share.ts`.

## 8. Prochaines étapes

1. ✅ ~~Fournir un token GitHub valide~~ — fait ; branches poussées, issues créées,
   PR mergées dans `main` (#9159, #9182, #9190, #9266, #9441).
2. ✅ ~~Trancher #9156 et #9157~~ — fait le 2026-11-09, deux ADR dans
   `docs/DECISIONS/`, option A retenue pour les deux.
3. ✅ ~~Implémenter le lot 3 d'outils (#9158)~~ — fait (PR #9190).
4. ✅ ~~Câbler « Mes outils » dans l'UI~~ — fait (PR #9441).
5. ✅ ~~Partage d'un résultat par lien~~ — fait (issue #9454, PR #9455, merge
   `0bce0432707bea28ebc3ec82e2de40e71b5d38cd`).
6. Confirmer le build complet en CI (le sandbox est limité à 2 Go).
7. Réexaminer #9156 (synchronisation serveur) **uniquement** si un seuil documenté
   est atteint (demande explicite d'un compte authentifié, rattachement à un
   espace utilisateur, ou mesure d'usage montrant une recréation sur 2e appareil).
8. Réexaminer #9157 (administration) **uniquement** si le rythme d'ajout dépasse
   ce qu'une PR absorbe **et** qu'un besoin d'activation par tenant apparaît.
9. Candidats pour un lot 5 (non engagés) : export PDF/CSV d'un résultat, partage
   d'un **ensemble** de calculs (panier), accessibilité avancée (annonce des
   résultats aux lecteurs d'écran).
