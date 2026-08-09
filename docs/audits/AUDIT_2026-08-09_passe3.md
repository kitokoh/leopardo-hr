# Audit 2026-08-09 — Passe 3 (rapport complet)

> Périmètre : vérification des audits précédents, état de la CI sur `main`, correctifs livrés en session, spécifications créées.
> Références : passe 1 (`AUDIT_NEO_2026-08-09.md`), passe 2 (`AUDIT_NEO_2026-08-09_passe2.md`), passe 4 (PR #1610).
> Ce rapport est le livrable de l'issue **#1608**.

## 1. État de la CI sur `main`

- **CI globale** : verte sur les runs récents de `main` (workflow_run dispatchers OK : E2E Playwright staging, OWASP ZAP, Deploy).
- **Onboarding Smoke (Fresh docker compose)** : corrigé — root cause identifiée (issue #1591) : `php artisan serve` ne recharge pas `.env` après `key:generate` (Dotenv immutable) ; le parcours documenté échouait aussi sur des valeurs invalides de `.env.example`. Correctifs : alignement `.env.example` sur docker-compose, redémarrage explicite du conteneur `api` après `key:generate`, sonde health plus tolérante, garde anti-régression `check-env-example-safety.sh` (#1605).
- **Suite backend** : passée au vert sur le vrai schéma (migrations réelles, `RefreshTenantDatabase`) — 58 échecs Feature de `main` levés (PR #1611) : tests Absences conformes au schéma réel (`absence_types.code` unique, colonnes NOT NULL), gardes search_path retirées de `PayrollCycleService`.
- **Gates qualité** : PHPStan strict `[OK] No errors`, PHPStan modules, Pint, ESLint+TypeScript, Module Structure Validator, actionlint — verts.
- **Gates sécurité** : CodeQL, Semgrep, TruffleHog (historique), Composer Audit, Dependency Review — verts.
- **Gates paie** : Golden tests DZ, Tests module Payroll, Coverage Payroll ≥ 80 % — verts (coverage **toujours advisory**, `continue-on-error: true`, voir §3.4).

## 2. Bugs réels corrigés (session 2026-08-09)

| # | Bug | Correctif |
|---|---|---|
| B1 | 401 massifs (`AUTH_MODEL=Employee::class` cassait Sanctum) | Garde `check-env-example-safety.sh` : interdit `::class`/placeholders cassants (#1605) |
| B2 | Onboarding smoke rouge (health 500 après `key:generate`) | Redémarrage du conteneur api + sonde 200+body (#1591) |
| B3 | Filtres search_path-dépendants (PayrollCycleService) | Helpers bi-schéma, gardes `Schema::hasTable/table` au nom nu retirées (#1597/#1606) |
| B4 | Tests Absences 58 échecs (schéma manuel en dérive) | Migration vers vraies migrations + conformité NOT NULL/unique (#1569/#1593) |
| B5 | `ExpenseClaimResource` champ fantôme (`total_amount`) | Champ exposé |
| B6 | Signature consentement paie non déterministe | Hash sur `confirmed_at` persisté + canonicalisation UTC (PA2-PAY-016) |
| B7 | `payment_documents.metadata` json→text (cast `encrypted:array` F-17 → 22P02) | Migration de format + backfill idempotent |
| B8 | Workflows temp-* de debug sur main | Suppression (PR #1610, issue #1600) |
| B9 | CHANGELOG mojibake `GÇö` (encodage latin-1/UTF-8) | Ré-encodage UTF-8 propre (#1589) + garde renforcée (#1612) |
| B10 | Épinglage actions tierces par tag mutable | SHA épinglés (setup-php, flutter-action, lighthouse, Firebase Distribution — #1603) |
| B11 | Clover paths incohérents phpunit.xml vs payroll-ci | Alignement sur `storage/coverage/` (#1597) |
| B12 | Secret Neon réel cité dans un rapport d'audit (HEAD) | Rédaction + convention + garde CI (#1614) |

## 3. Vérification des audits précédents

- **AUDIT_CICD_2026-07-19** (§supply-chain) : actions tierces épinglées par SHA ✅ ; Dependabot github-actions configuré ✅ ; reste : vérification composite maison (suivi #1603).
- **AUDIT_GLOBAL_2026-07-26** : security headers, CORS, retention RGPD — confirmés résolus ✅.
- **Audit Neo 2026-08-08 (#1573)** : MetricCard, gate coverage Clover parsing, `set -euo pipefail` — résolus ✅ ; dérive CreatesMvpSchema suivie (#1569) ; clover paths harmonisés (#1597) ; CSP vitrine décision documentée (#1607).
- **Audit Neo 2026-08-09 passe 2** : 40 issues passées en revue, 12 bugs corrigés (tableau §2), 4 spécifications créées (#1604/#1605/#1606/#1607).

## 4. Spécifications créées

- **#1604** — `PayrollBenchmarkSeeder` (10 000 employés DZ) + commande `payroll:benchmark` (protocole F-12).
- **#1605** — Garde `.env.example` (phpdotenv + `::class`/placeholders interdits).
- **#1606** — Suite migration HR/Attendance vers les vraies migrations (F-13b, comptage exact).
- **#1607** — CSP vitrine : décision enforce vs Report-Only documentée + test de headers.

## 5. Reste à traiter

### Action humaine (bloqué sur rotation/purge, repo public)
- **#1472 (P0)** : rotation mot de passe Redis Upstash + purge historique git (ouvert depuis 2026-07-01).
- **#1601 (P1)** : secret Neon DB réel dans l'historique (commit 70ca415c) — purge coordonnée avec #1472.
- **#1615** : création du secret GitHub `FIREBASE_APP_ID_HR` (Settings → Secrets) puis revert du fallback.
- **#1467** : rotation des clés Google committées (google-services.json).

### Implémentation (agent, en cours)
- **#1593/#1606** : migration des tests Feature HR + Attendance vers `RefreshTenantDatabase` (drift 40 tables → 0 pour le noyau).
- **#1602** : couverture Payroll ≥ 80 % et passage de la gate en bloquant.
- **#1612** : garde CHANGELOG renforcée (mojibake non-standard `GÇö`/`ó——`).
- **#1613** : migrations tenant — bannir `Schema::hasTable/Schema::table` au nom nu (helper `resolveTableSchema`).
- **#1553** : kit de démo DZ (DemoDzSeeder) ; **#1560** : widget tests leopardo_employee ; **#1549/#1547/#1548/#1545/#1542/#1541/#1540/#1539/#1538/#1537/#1536/#1535** : programme FOCUS paie (suivi dédié).

## 6. Méthode

- Aucune écriture directe sur `main` : chaque correctif passe par une PR revue (protection de branche + checks requis).
- Les rapports d'audit ne citent jamais de secret réel (convention #1614).
- Ce rapport est généré par un agent autonome sur la base de vérifications réelles (CI, greps, logs).
