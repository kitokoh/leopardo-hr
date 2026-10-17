# Mission Leopardo Business OS — dossier documentaire

**Branche :** `mission/leopardo-business-os` · **Base :** `main` @ `e9c5f986c`
**Mission :** auditer le Business OS existant et le faire évoluer vers une plateforme
réellement opérationnelle (onboarding universel, verticales, applications, hors-ligne,
branding, marketing).

---

## Lire dans cet ordre

| # | Document | Rôle |
|---|---|---|
| **00** | [`00_audit_report.md`](00_audit_report.md) | Rapport d'audit initial : ce qui existe, ce qui bloque, ce qui est hypothétique |
| **01** | [`01_tad.md`](01_tad.md) | **TAD** — architecture technique, topologie, multi-tenant, onboarding, applications, hors-ligne |
| **02** | [`02_trd.md`](02_trd.md) | **TRD** — exigences fonctionnelles et non fonctionnelles, identifiées et traçables |
| **03** | [`03_appflow.md`](03_appflow.md) | **AppFlow** — parcours réels vs parcours cible, état par étape |
| **04** | [`04_uix.md`](04_uix.md) | **UIX** — expérience, structure d'écran, accessibilité, microcopie |
| **05** | [`05_ui_design_system.md`](05_ui_design_system.md) | **UI Design System** — tokens, composants, mouvement, branding borné |
| **06** | [`06_backend_schema.md`](06_backend_schema.md) | **Backend Schema** — tables vérifiées, colonnes à ajouter, index, rétention |
| **07** | [`07_implementation_plan.md`](07_implementation_plan.md) | **Implementation Plan** — 8 lots, dépendances, politique de commit, « terminé » |

## Annexes (valeur réelle, pas de remplissage)

| # | Document | Pourquoi il existe |
|---|---|---|
| **08** | [`08_gap_matrix.md`](08_gap_matrix.md) | 48 écarts classés R/C/X/N/V + matrice verticale × capacité |
| **09** | [`09_adr.md`](09_adr.md) | 9 décisions avec alternatives **écartées** (ce qui empêche de refaire le débat) |
| **10** | [`10_field_validation_plan.md`](10_field_validation_plan.md) | Protocole d'observation terrain, seuils go/no-go |
| **11** | [`11_integrations_catalogue.md`](11_integrations_catalogue.md) | Statut réel de chaque intégration, prérequis d'exploitation |
| **12** | [`12_marketing_analysis.md`](12_marketing_analysis.md) | Positionnement, profils, concurrence sourcée, plan de contenu |
| **14** | [`14_audit_baseline.md`](14_audit_baseline.md) | Baseline CI de `main` : 17 échecs préexistants, protocole de non-régression |
| **15** | [`15_test_plan.md`](15_test_plan.md) | Scénarios attendus (onboarding, isolation, apps, hors-ligne, verticales, branding) |

`13` est réservé au suivi des issues GitHub (voir la section « Issues » du rapport final).

---

## Conventions de lecture

- **`[existant]`** — capacité présente sur `main`, avec référence de fichier.
- **`[à faire]` / `à créer`** — écart ouvert, tracé dans `08_gap_matrix.md`.
- **`✅` / `⚠️` / `⛔`** — parcours câblé / partiel / inexistant (`03_appflow.md`).
- **`R` `C` `X` `N` `V`** — Réutilisable / À compléter / À corriger / À créer / À vérifier.

**Aucune capacité n'est décrite comme implémentée sans référence de code.** Aucun test n'est
décrit comme réussi sans exécution constatée. Aucune intégration n'est dite opérationnelle
sans clé réelle.

---

## Ce que ce dossier ne fait pas

- Il ne remplace pas le code : chaque lot de `07_implementation_plan.md` doit être
  implémenté, testé et prouvé.
- Il ne qualifie pas le produit de « MVP ».
- Il ne présente aucun schéma théorique comme déjà en base : `06_backend_schema.md`
  distingue `[existant]` de `[à créer]`.
- Il n'annonce aucune publication, campagne ou dépense.

---

## Dépendances externes à la branche

| Dépendance | État | Effet sur la mission |
|---|---|---|
| PR #8487 `bc/bc-36-trade-os` | ouverte | verticale Trade non disponible sur `main` |
| Branche `bc/bc-37-farm-os` | non fusionnée | verticale Farm absente |
| 4 jobs Firebase en échec | préexistant | canal « build testeurs » non opérationnel |
| Issues #8496, #8498, #8583, #8585, #8610 | ouvertes | isolation edge, rejeu réel, logo station, app pompiste, vue groupe resto |
