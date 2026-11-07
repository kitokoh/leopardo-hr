# LEOPARDO TOOLBOX — Rapport final

> **Date** : 2026-11-05 · **Branche** : `feat/vitrine-toolbox` · **Commit de base** : `dd7522d7` (main)
> **Statut** : implémentation locale **terminée et vérifiée** ; **publication GitHub bloquée** (token invalide).

---

## 1. ⚠️ Blocage bloquant : token GitHub invalide

Le token fourni pour la session (`ghp_YKOw…`) est **rejeté** :

| Vérification | Résultat |
|---|---|
| `GET https://api.github.com/repos/kitokoh/leopardo-hr` avec `Authorization: Bearer <token>` | `401 Bad credentials` |
| `GET https://api.github.com/user` avec `Authorization: token <token>` | `401 Bad credentials` |
| `git push https://x-access-token:<token>@github.com/...` | `Invalid username or token` |
| `git clone` **sans** token (dépôt public) | ✅ succès |

**Conséquences** : les issues GitHub **n'ont pas été créées**, les commits **n'ont pas
été poussés**, la branche **n'a pas été mergée dans `main`**. Tout le travail est
commité **localement** sur `feat/vitrine-toolbox` (3 commits).

**Pour débloquer** : fournir un token avec le scope `repo` (et `workflow` si les
workflows CI doivent être modifiés). Les commandes à exécuter sont prêtes :
`git push -u origin feat/vitrine-toolbox`, puis création des issues depuis
`docs/leopardo-toolbox/02_issues_proposees.md`.

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
| Tests Toolbox | `npx jest src/modules/toolbox` | ✅ **74 tests, 6 suites, 0 échec** |
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

1. **Publication GitHub bloquée** (token invalide) — voir §1.
2. **Build complet non terminé localement** (OOM environnement) — à confirmer en CI.
3. **Pas de persistance** des favoris/historique (décision issue 5 non tranchée).
4. **Pas d'administration** des métadonnées (décision issue 6 non tranchée).
5. **Devises non incluses** dans le lot 1 (taux statiques à documenter — lot 2).
6. **Salaire net = estimation** : aucun barème fiscal codé en dur, par choix
   (éviter de présenter un calcul légal non vérifié).

## 8. Prochaines étapes

1. **Fournir un token GitHub valide** → pousser la branche, créer les 7 issues,
   ouvrir la PR, merger dans `main`.
2. Confirmer le build complet en CI.
3. Trancher les décisions 5 (persistance) et 6 (administration) — validation
   explicite requise avant toute modification serveur.
4. Implémenter le lot 2 d'outils (issue 7).
