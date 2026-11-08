# LEOPARDO TOOLBOX — Rapport final

> **Date** : 2026-11-05 (lot 1) · **Mise à jour** : 2026-11-08 (lot 2)
> **Branches** : `feat/vitrine-toolbox` (lot 1) → `feat/vitrine-toolbox-lot2` (lot 2)
> **Statut** : ✅ **livré et mergé dans `main`** — vérifié sur l'état distant réel.

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

## 6. Matrice d'accès (livrée)

| Fonctionnalité | Visiteur anonyme | Utilisateur public | Professionnel | Collaborateur Leopardo | Administrateur |
|---|---|---|---|---|---|
| Catalogue `/toolbox` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Utiliser un outil | ✅ **sans compte** | ✅ | ✅ | ✅ | ✅ |
| Copier/partager un résultat | ✅ | ✅ | ✅ | ✅ | ✅ |
| Favoris / historique | — (non implémenté) | — | — | — | — |
| Synchronisation multi-appareils | — (décision issue 5) | — | — | — | — |
| Administration des outils | — (décision issue 6) | — | — | — | — |

## 7. Limites connues

1. **Build complet non terminé localement** (OOM environnement, limite cgroup 2 Go)
   — non lié au code Toolbox ; à confirmer en CI.
2. **Pas de persistance** des favoris/historique (décision #9156 non tranchée).
3. **Pas d'administration** des métadonnées (décision #9157 non tranchée).
4. **4 outils restent en backlog** (#9158) : devises, dates, âge, taux horaire.
5. **Salaire net = estimation** : aucun barème fiscal codé en dur, par choix
   (éviter de présenter un calcul légal non vérifié).

## 8. Prochaines étapes

1. ✅ ~~Fournir un token GitHub valide~~ — fait ; branche poussée, issues créées,
   PR mergées dans `main` (#9159, #9182).
2. Confirmer le build complet en CI (le sandbox est limité à 2 Go).
3. Trancher les décisions #9156 (persistance) et #9157 (administration) — validation
   explicite requise avant toute modification serveur.
4. Implémenter le lot 3 d'outils (#9158 : devises, dates, âge, taux horaire).
