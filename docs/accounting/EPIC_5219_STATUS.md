# Épic Comptabilité (#5219) — État & audit de complétude

> **Issue** : #5219 — [EPIC][spec-kit][P1] Module Comptabilité — facturation, trésorerie,
> intégration marketing (conception v1).
> **Plan** : `docs/architecture/COMPTABILITE_CONCEPTION.md` · **Statut** : 2026-08-25.
> **Méthode** : mesures API GitHub (issues + PRs ouvertes) — coordination protocole #2400.

## 1. Vision vs conception v1 (audit de complétude)

| Élément de la vision (#5219) | Couvert par `COMPTABILITE_CONCEPTION.md` | Statut |
|---|---|---|
| Factures / proformas / devis / avoirs / bordereaux (irsaliye) / reçus | §2.1-2.3 (types, statuts, numérotation) | ✅ livré (#5223, #5352) |
| Contacts client / fournisseur | §2.4 + CRUD RBAC | ✅ livré (#5222) |
| Paiements + rapprochement + relances | §2.6 | ✅ livré (#5229) |
| TVA paramétrable + multi-pays | §2.7 | ✅ livré (#5232, #5271) |
| PDF multi-langues ×4 (RTL arabe) | §2.5 | ✅ livré (#5224, #5227) |
| Envoi email + portail client web | §2.8 | 🟡 PR #5357/#5403 |
| Intégration Marketing (lead → contact) | §2.9 | ✅ livré (#5231) |
| Tableaux de bord (dépenses, encaissements, impayés) | §2.10 | 🟡 PR #5395 |
| Journal + écritures + exports expert-comptable | §3 (Phase C) | 🟡 PR #5363 |
| Flux Paie → Comptabilité + ordre de virement | §3 (Phase C) | 🟡 PRs #5392/#5394 |
| Expense → écritures | §3 (Phase C) | 🟡 PR #5397 |
| App mobile `leopardo_accounting` | §6.3 (Phase B/C) | 🟡 PR #5421 |
| Multi-devises + taux de change | §6.4 | 🟡 PR #5416 |
| Audit log + rétention RGPD + purge | §4 | 🟡 PR #5377 |
| Activation guidée (wizard) | §5 | 🟡 PR #5388 |
| Données démo/seed + E2E | §7 | 🟡 PR #5387 |
| i18n ×4 du module | transverse | ✅ livré (#5227, #5400) |
| Perf/scale + benchmark | §8 | ✅ livré (#5275) |
| Docs + runbook + recette pilotes | transverse | ✅ livré (#5276, #5237) |

**Verdict** : la conception v1 couvre **100 % de la vision** de l'épic (aucun gap documentaire).
Le chantier est en phase de **convergence des PRs ouvertes** (10 PRs en vol).

## 2. Sous-issues — état mesuré (API, 2026-08-25)

| Issue | Titre | État | PR(s) |
|---|---|---|---|
| #5220 | Spec modèle de données | ✅ CLOSED | — |
| #5221 | Migrations + modèles DDD | ✅ CLOSED | — |
| #5222 | CRUD contacts + RBAC | ✅ CLOSED | — |
| #5223 | Workflow documents + numérotation | ✅ CLOSED | — |
| #5224 | PDF multi-langues ×4 | ✅ CLOSED | — |
| #5225 | Envoi email docs + portail | 🟡 OPEN | #5357 |
| #5226 | API REST /accounting/* + RBAC + OpenAPI | ✅ CLOSED | — |
| #5227 | i18n ×4 du module | ✅ CLOSED | — |
| #5228 | Tests Feature + gate coverage | ✅ CLOSED | — |
| #5229 | Paiements + rapprochement + relances | ✅ CLOSED | — |
| #5230 | Tableaux de bord comptables | 🟡 OPEN | #5395 |
| #5231 | Intégration Marketing (lead → contact) | ✅ CLOSED | — |
| #5232 | Paramétrage entreprise | ✅ CLOSED | — |
| #5233 | Portail client web | 🟡 OPEN | #5403 |
| #5234 | Journal + écritures + exports | 🟡 OPEN | #5363 |
| #5235 | Expense + Payroll → écritures | 🟡 OPEN | #5397 |
| #5236 | App mobile leopardo_accounting | 🟡 OPEN (prise) | #5421 |
| #5237 | Docs + runbook + formation pilotes | ✅ CLOSED | — |
| #5238 | Conception v1 | ✅ MERGED | — |
| #5239 | Flux Paie → Comptabilité + virement | 🟡 OPEN | #5392/#5394 |
| #5240 | Paie DZ 100 % — audit légal | ✅ CLOSED | — |

**Bilan** : **14/21** sous-issues closes/mergées · **7 ouvertes**, toutes couvertes par une PR
ouverte (aucune orpheline) — dont 2 PRs mergeable sans conflit (#5403, #5421).

## 3. Reste à faire (dépend de la CI + des agents en vol)

1. Merger les 10 PRs ouvertes (canoniques par issue — les doublons éventuels sont fermés
   par le protocole #2400, 1 PR = 1 issue).
2. À la convergence : re-auditer la couverture OpenAPI/SDK (garde « Route → OpenAPI »),
   mettre à jour ce tracker, puis **clore #5219** (anti ghost-close : uniquement quand
   toutes les sous-issues sont mergées).

## 4. Décisions en attente (hors code)

- #5272 — passerelle de paiement en ligne : ADR-0017 mergée, **3 décisions fondateur requises**
  (Option A dual-PSP : DZ → Chargily, FR/UK/US/CI → Stripe, MA/TN/TR phase 2).
