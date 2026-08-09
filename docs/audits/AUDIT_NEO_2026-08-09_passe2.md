# 🔍 Audit Neo — Leopardo RH — 2026-08-09 (2ᵉ passe, session de mise au vert)

> Périmètre : suite de l'audit NEO 2026-08-09 (#1585). Vérification de la PR consolidée #1598
> (9 checks rouges → verts), contrôle des points des audits précédents encore en suspens,
> et nouveau rapport avec recommandations + specs.
> Réalisé dans la session du 2026-08-09 (branche `fix/session-2026-08-09-green`).

---

## 1. ✅ Correctifs livrés dans cette passe (PR #1598 → verte)

### 1.1 Fuite multi-tenant paie (critique, F-19 #1549)
- **Constat** : `TaxSlabController::index()` et `SocialContributionController::index()`
  listent les barèmes IRG / cotisations sociales de **tous les tenants** (aucun scope
  `company_id`), et les modèles `TaxSlab` / `SocialContribution` n'utilisent pas le trait
  `BelongsToCompany` (le route-model binding de `update`/`destroy` n'était donc pas scopé
  non plus).
- **Fix** : trait `BelongsToCompany` ajouté aux 2 modèles + `where('company_id', $actor->company_id)`
  dans les 2 index + gardes `company_id` explicites dans update/destroy de TaxSlab.
- **Détecté par** : les tests adversarial tenant (PR #1584, #1549) — la revue a mis en évidence
  que SocialContribution avait la même faille (pas encore couverte par un test → ajouté
  `PayrollReferenceControllersTest::test_reference_data_is_tenant_scoped`).

### 1.2 ProcessPayrollBatchJob — statuts invalides (bug réel du job)
- `ProcessPayrollBatchJob::handle()` écrit `status='processing'` puis `status='error'`,
  valeurs **absentes** de l'enum PG et du CHECK `payroll_runs_status_check`
  (`draft, calculating, calculated, validated, paid, cancelled`) → SQLSTATE 23514
  sur le vrai schéma.
- Fix : migration F-11 étendue (ADD VALUE idempotent pour `processing`/`error` + CHECK
  recréé version-indépendante avec les 9 statuts).

### 1.3 TruffleHog Secret Scan — flag supprimé + image non pinnée
- `--allow=test_.*` : flag **supprimé** dans trufflehog ≥ 3.78 (devenu no-op booléen) →
  `trufflehog: error: unexpected test_.*`. L'action téléchargeait l'image `latest`
  (non reproductible — point AUDIT_CICD 07-19 §supply-chain).
- Fix : `version: 3.96.0` épinglée + `--exclude-detectors=Lob` (39 faux positifs = noms
  de méthodes PHPUnit `test_<nom long>`).

### 1.4 Flutter analyze — 4 apps
- `glass_tile.dart` : `dead_null_aware_expression` + `invalid_null_aware_operator`
  (AppTypography est `static const`, opérateurs `?.`/`??` morts).
- `mobile_list_glass_card.dart` : `unintended_html_in_doc_comment` (`List<Widget>` sans backticks).
- employee/hr/manager : `use_build_context_synchronously` (10-14 chacun) dans
  absence_list_screen + salary_advance_list_screen → gardes `context.mounted`.

### 1.5 Tests Payroll — 5 fichiers corrigés
- `PayrollAnomalyServiceTest::test_duplicate_slip_is_detected` : restauration de la
  contrainte UNIQUE sur des données dupliquées → DDL transactionnel PG, rollback suffit.
- `PayrollTenantIsolationTest` : `status='completed'` invalide (bank_exports) → `'generated'` ;
  route `/me/pay-slips` → `/api/v1/me/pay-slips` (404).
- `PayrollCycleIntegrationTest` : `status='complete'` invalide (attendance_logs) → `'ontime'`.
- `GoldenDzSalaryStructurePerEmployeeTest` : **flaky** — `contract_start` aléatoire de la
  factory (jusqu'à -1 mois) pouvait tomber après le début de période → prorata imprévu.
  Fix : `contract_start='2026-01-01'` explicite.

### 1.6 Onboarding Smoke — DB_CHARSET
- `.env.example` : `DB_CHARSET=utf8mb4` (charset MySQL) envoyé à PostgreSQL en
  `client_encoding` → FATAL. Fix : `DB_CHARSET=utf8` + commentaire (#1591).

### 1.7 Nouveau : PayrollReferenceControllersTest (F-14, coverage)
- 12 tests Feature : CRUD structures/composants/tax-slabs/cotisations + isolation tenant +
  RBAC manager + validation — contribue au seuil F-14 (coverage module ≥ 80 %, gate advisory #1569).

---

## 2. 🔎 Vérification des audits précédents (points restants)

| Audit | Point | Statut 2026-08-09 (2ᵉ passe) |
|---|---|---|
| GLOBAL 07-26 | Code Scanning 10 alertes | ✅ **0 open** (27 total, toutes closed) |
| GLOBAL 07-26 | Dependabot | 🟡 4 open **obsolètes** (nanoid 3.3.17/3.3.18 installés > fixe, postcss 8.5.26 > 8.5.23) — se fermeront au prochain scan |
| GLOBAL 07-26 | Secret scanning | 🟡 2 open google_api_key (= #1467, purge historique = action humaine) |
| CICD 07-19 | tests.yml fragment orphelin | ✅ corrigé (plus de `mobile_smoke_build` legacy) |
| CICD 07-19 | release.yml front/mobile | ✅ corrigé (commentaire seulement) |
| CICD 07-19 | dependabot écosystèmes | ✅ github-actions + web/admin/web-offline + pub ajoutés |
| CICD 07-19 | trufflehog@main | ✅ SHA épinglé + version 3.96.0 (cette passe) |
| CICD 07-19 | pinning SHA actions tierces | 🟡 partiel (tags mutables pour setup-php/flutter-action, Dependabot github-actions actif) |
| AUDIT 07-01 | Token SSE en query param | ✅ corrigé (POST /notifications/sse-token) |
| AUDIT 07-01 | Firebase vars .env.example | ✅ présentes |
| AUDIT 07-01 | Workers Redis Render | ✅ leopardo-queue-worker + docker-compose queue:work |
| AUDIT 07-01 | Secret Redis historique | 🔴 #1472 — action humaine (rotation + purge) |
| #1573 (08-08) | clover paths phpunit.xml vs payroll-ci | 🟡 cosmétique, non bloquant |
| #1573 (08-08) | CSP vitrine Report-Only | 🟡 inchangé (intentionnel) |
| #1573 (08-08) | HR/Attendance sur CreatesMvpSchema | 🟡 189 fichiers — suivi #1593 |

---

## 3. 🟠 Nouveaux points relevés (cette passe)

1. **Secret Neon DB réel dans l'historique git** (`<REDACTED — voir issue #1601, jamais re-copier un secret réel>`,
   commit 70ca415c, docs/GESTION_PROJET/RAPPORT_DEPLOIEMENT_RENDER.md, avril 2026) — plus dans
   HEAD mais l'historique est public → **joindre à #1472** (purge). Détecté par TruffleHog local.
   > Convention (#1614) : ne jamais citer un secret réel (même tronqué) dans un rapport d'audit.
2. **6 workflows `temp-*` de debug poussés sur main** (temp-dart-format, temp-regen-phpstan-strict,
   etc.) + commits `ci(temp)` — à supprimer/nettoyer (cette session, phase cleanup).
3. **Workflows temp à supprimer** : `temp-check-dart-format.yml`, `temp-dart-final.yml`,
   `temp-dart-format.yml`, `temp-list-dart-format.yml`, `temp-regen-phpstan-strict.yml`,
   `temp-verify-dart-format.yml`.
4. **Payroll coverage 39,6 % < 80 %** (F-14) : gate advisory `continue-on-error` (TODO #1569) —
   mon test CRUD contribue ; le seuil 80 % reste un chantier (services PayrollCalculator,
   PayrollCycleService, générateurs d'exports non couverts par des tests directs).
5. **SocialContributionResource expose `employee_rate`/`employer_rate`/`is_active`** qui
   n'existent pas sur le modèle (`rate`+`type` à la place) — toujours null dans les réponses
   (bug documenté dans le CHANGELOG #1409, non corrigé). À corriger.

---

## 4. 🎯 Recommandations pour la suite (spécifications)

1. **Sécurité** : rotation + purge historique git (#1472/#1467) — action humaine impérative,
   le repo est public.
2. **F-17 chiffrement au repos** : implémenter les casts Eloquent chiffrés sur les colonnes
   paie sensibles (RIB, montants nets ?) — spec dans docs/payroll/DATA_AT_REST.md.
3. **F-12 perf** : protocole de benchmark clôture 10 000 employés (#1594) puis mesure staging.
4. **F-13b** : étendre la migration des tests HR/Attendance vers les vraies migrations (#1593).
5. **F-14 coverage Payroll** : lever le TODO #1569 (gate bloquant) en ajoutant des tests
   pour PayrollCycleService / exports / SalaryAdvance.
6. **Cleanup CI** : supprimer les 6 workflows temp + re-pinner les actions tierces par SHA.
7. **Corriger SocialContributionResource** (champs fantômes).

---

*Audit réalisé par Neo dans la 2ᵉ passe de la session du 2026-08-09. Écritures via la PR #1598.*
