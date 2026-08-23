# Feature Specification: E2E parcours critiques en CI (issue #5285)

**Feature Branch**: `mod/platform/5285-e2e-critical-funnel`

**Created**: 2026-08-22

**Status**: Draft → Implemented

**Module**: `platform` (cross-cutting) — périmètre touché : `tests/Feature/**`,
`.specify/features/5285-e2e-critical-funnel/`, `CHANGELOG.md`. Aucune
collision : les modules métier (Payroll DZ #5240/#5241, Attendance #5268,
Accounting #5221, HR #5260/#5262…) ne sont pas touchés (branches/PR vérifiées
avant claim).

## Contexte

Les parcours transverses critiques doivent être testés de bout en bout en CI
(post-#5201, gate W0 passée). La spec funnel prospect #5146 est livrée
(`.specify/features/e2e-funnel-prospect/`, E2E Playwright vitrine + Feature
tests), mais **aucun test CI ne traverse le parcours complet
signup → provision → employés → paie → bulletin**.

État vérifié le 2026-08-22 :

| Brique | Existant | Gap |
|---|---|---|
| Signup trial | `POST /api/v1/trial/signup` (guided/self_service) testé isolément (`TrialSignupLocalizationTest`, dédup #3951, course #5290) | Pas de test qui enchaîne sur la provision + la paie |
| Provision | `ProvisionGuidedTrial::execute()` (company + manager principal + seed) ; job `ProvisionDemoTenantJob` | Pas d'utilisation dans un parcours E2E complet |
| Employés | `POST /api/v1/employees` testé (#4947 password_hash, invitation) | Pas de test qui part du tenant *provisionné par le funnel* |
| Paie | `PayrollRunClosingE2ETest` (moteur réel, company factory) | Part d'une company factory, pas du funnel prospect |
| Bulletin PDF | `PaySlipDzMentionsTest` (dompdf en CI OK), `downloadPdf` | Pas de test qui génère/télécharge le bulletin dans le parcours |

## Décisions

1. **Parcours 1 livrable maintenant** : `signup trial → provision → employés →
   run de paie → bulletin`, en Feature test HTTP avec le **moteur réel** et les
   **endpoints réels**, dans `api/tests/Feature/E2E/CriticalFunnelPayrollE2ETest.php`
   (pattern : `PayrollRunClosingE2ETest` pour la paie + `TrialSignupLocalizationTest`
   pour le signup + `EmployeePasswordProvisioningTest` pour l'employé).
   Le test tourne dans la suite Feature standard → **vert en CI sur chaque
   merge** (Backend Coverage Gate, PHP 8.4 + PostgreSQL 16) → DoD parcours 1.
2. **Parcours 2 (lead → contact → facture → paiement) NON implémentable
   maintenant** : la Comptabilité est greenfield (`api/app/Modules/Accounting`
   n'existe pas, Phase A en cours — PR #5301 non mergée). Constat identique au
   tracker §6.3 (« parcours facture bloqué par le greenfield Comptabilité »).
   → documenté dans l'issue (#5285), pas de faux vert : le DoD complet sera
   atteint quand la Phase A/B Comptabilité permettra d'écrire le parcours.
3. **Pas de job CI dédié** (Plan B de l'issue) : l'isolation workers #5201 est
   résolue (W0 passée) et le test Feature s'exécute dans la suite standard —
   un job supplémentaire serait de la dette CI. Le Plan B n'est pertinent que
   si le test s'avère flaky dans la suite parallèle.

## User Scenarios & Testing

### US1 — Parcours prospect complet : signup → bulletin (Priority: P0)

**Independent Test**: `php artisan test --filter=CriticalFunnelPayrollE2ETest`
→ 1/1 vert (CI : Backend Coverage Gate).

**Acceptance Scenarios**:

1. **Given** un prospect inconnu, **When** il POST `/api/v1/trial/signup`
   (`guided_trial`, pays DZ), **Then** 200 `provisioning_sandbox` +
   `provisioning_token` + ligne `trial_provisionings` pending.
2. **Given** la ligne pending, **When** le job de provision s'exécute
   (`ProvisionGuidedTrial` — le code réel), **Then** company `trial` créée +
   manager `principal` actif, sans 500.
3. **Given** la session du manager provisionné, **When** `POST /api/v1/employees`
   avec `password`, **Then** 201 (régression #4947) et l'employé est listable.
4. **Given** une grille salariale DZ active, **When** `POST /api/v1/payroll-runs`
   → `calculate` → `validate`, **Then** run `validated` avec ≥ 1 bulletin
   calculé (moteur réel).
5. **Given** un bulletin du run, **When** `GET /me/pay-slips/{id}/pdf`,
   **Then** 200 `application/pdf` et le contenu commence par `%PDF` (bulletin
   réel généré, pas un stub).

### US2 — Contrat anti-régression du funnel (Priority: P1)

1. **Given** le parcours entier, **When** une future régression casse une étape
   (signup, provision, employé, calcul paie, PDF), **Then** la CI rouge sur la
   PR avec l'étape fautive dans les logs (pas de faux vert).

## Edge Cases

- **Email unique** : timestamp dans l'email de test → zéro collision entre
  runs CI parallèles (leçon #5146 FR-5).
- **`search_path`** : requêtes directes après les appels HTTP avec
  `SET search_path TO shared_tenants,public` (pattern
  `EmployeePasswordProvisioningTest`) — le middleware tenant ne pose le
  search_path que par requête HTTP.
- **`Storage::fake`** : PDF généré sur le disque fake (`local`/`private`) —
  aucun fichier réel écrit en CI.
- **dompdf en CI** : prouvé par `PaySlipDzMentionsTest` (décompression
  FlateDecode) — le test n'extrait pas le texte, il vérifie la signature
  `%PDF` + le Content-Type.

## Deliverables

- [x] Spec `.specify/features/5285-e2e-critical-funnel/spec.md`
- [x] `api/tests/Feature/E2E/CriticalFunnelPayrollE2ETest.php` — parcours 1
      complet via HTTP, moteur réel
- [x] Parcours 2 documenté comme bloqué par le greenfield Comptabilité
      (issue #5285, cohérent avec le tracker §6.3)
- [x] Entrée CHANGELOG `[Unreleased]` + PR avec `Closes #5285`
