# Session QA — Expert 21 (2026-08-16) : audit 360° runtime, consolidation, P1 fillable

**Agent**: Expert Software Engineer / Senior QA — `kitokoh`
**Périmètre**: Phase 0 (main vert, merges), Phase 1 (audit 360° vérifié sur le
code et le runtime), Phase 2 (consolidation dette), Phase 3 (implémentations).
**Contexte**: session multi-agents fortement concurrente (experts 14-20 actifs,
~30 PRs ouvertes en parallèle).

## Phase 0 — Consolidation & main vert

### Incident prod P0 — TOUS les POST API → 500 corps vide

Vérifié runtime (13:20-13:50 UTC) : `POST /auth/login`, `/auth/register`,
`/platform/auth/login`, `/trial/signup`, `/onboarding/invitation/*/activate`,
`/marketing/leads` → **500 corps vide** (`content-type: text/html`,
`x-render-origin-server: Render`), quelle que soit la route/middleware/payload
(JSON, form, vide, gzip, sans Content-Type). Les GETs identiques répondent 200/401/405.

**Cause racine** : parse error `lang/errors.php` (`];` manquant) introduit par
la vague i18n #4275/#4277/#4280 du matin, déployé en prod → les routes POST qui
chargent les catalogues fatals. **Hotfix déjà mergé sur main** (`1ccaaf5`,
12:49) + garde CI `php -l` sur `api/lang/**` (PR #4376). Prod non encore
déployé (famine #3545 persistante, file deploy saturée #4902-#4911 queued/cancelled).
Preuve + actions documentées sur **#4370**. Vérifications post-déploiement :
`/i18n/catalog/fr` 200 ✓, `/supported-countries` 200 ✓ (déploiements progressifs).

### Consolidation de doublons (protocole #2400)

- **#4428 (mon issue P1) + PR #4436 (mon fix EmployeeService)** → doublons de
  **#4307 (P0, existant)** : même bug, branche canonique `fix/4307-employee-role-nonfillable`
  (PR #4308) créée avant. J'ai **contribué mes apports sur la branche canonique**
  (salary_base dans create/update — manquant — + `EmployeeServiceCreateFillableTest`
  3 tests + CHANGELOG), puis fermé mes doublons avec renvoi.
- **#4391 (mon issue P1 Plan 29) + PR #4392** → doublons de **#4388** (PR #4389,
  fix identique + bump Gradle 8.14). Fermés avec renvoi.
- **#3842 (mobile routes)** et **#3846 (OpenGraph)** → fermés avec preuve code
  (fallback #2512) : contrats alignés sur main, garde verte, pipeline OG n'existe plus.

### Vérification des audits précédents

Tous les findings des sessions 2026-08-15/16 vérifiés implémentés sur main :
#4169-#4182 (14 issues expert19), #4186/#4188 (QA-360), #4191/#4197/#3237/#4206
(i18n), #4151 (fillable), #3867, #4185, #4190.

## Phase 1 — Nouveaux constats (vérifiés runtime/code)

| # | Sévérité | Surface | Sujet |
|---|----------|---------|-------|
| 4391 → 4388 | P1 CI | mobile | Garde Plan 29 périmée post-dédup #3756 (consolidé) |
| 4428 → 4307 | P0 api | HR | `EmployeeService::create/update` abandonne role/manager_role/status/company_id/salary_base (#3677) — consolidé |
| 4397 | P3 tooling | i18n | Scanner i18n-debt aveugle sur `leopardo_hr` (676 signaux invisibles) |
| 4446 | P3 api | i18n | `/supported-countries` — `compliance.warning` mélange FR/EN + labels francisés (US/GB) |
| 4447 | P3 web | i18n | `/auth/login` — placeholder `manager@entreprise.com` FR dans les 4 locales |

Autres vérifications runtime : checkout 100 % FR en EN/AR (#4185 — fix mergé
#4287), /docs localisée ×4 ✓ (#4240), login localisé ✓ (sauf placeholder),
pricing 3 cartes (Free manquant = lag déploiement, code main OK ×4 plans).

## Phase 2/3 — Implémentations

| PR | Issue | Sujet | Statut |
|----|-------|-------|--------|
| #4418 | 4397 | scanner i18n-debt + surface leopardo_hr + spec spec-kit | ouverte (CI) |
| #4449 | 4447 | placeholder login locale-neutre | ouverte (CI) |
| #4308 (contribué) | 4307 | EmployeeService fillable + salary_base + tests | ouverte (CI) |
| #4389 (concurrent) | 4388 | garde Plan 29 + Gradle 8.14 | ouverte (CI) |

## Leçons

1. **Vérifier la présence d'une branche/issue concurrente AVANT de créer un
   fix** : les agents parallèles créent issues+PRs en quelques minutes. J'ai dû
   consolider 2 doublons (#4307/#4428, #4388/#4391) — le protocole #2400
   (contribuer sur la branche canonique) fonctionne bien, mais coûte du temps.
2. **Les traits Eloquent (BelongsToCompany) masquent les vrais impacts fillable** :
   `company_id` est auto-rempli par le hook `creating` sur la surface tenant —
   `role`/`status`/`salary_base` NON → ce sont eux les pertes silencieuses réelles
   (salary_base=0, manager→employee).
3. **`EmployeeFactory::newModel()` forceFill** explique pourquoi des centaines
   de tests passent malgré le durcissement #3677 : la factory n'est pas un
   miroir du comportement applicatif — toujours tracer le chemin de production.
4. **Incident prod = souvent un parse error PHP déployé + file de déploiement
   saturée** : vérifier `php -l` sur les fichiers lang au merge, et vérifier le
   SHA réellement déployé avant de conclure à un bug applicatif.
