# Feature Specification: Session QA live expert 3 — 2026-08-15

**Branch**: `fix/expert3-live-qa-2026-08-15` (PR #3396)
**Created**: 2026-08-15 | **Status**: Correctifs livrés, PR ouverte

**Input**: Mission propriétaire — merger le max de branches (main vert), tester la plateforme dans
tous les sens (vitrine, web, admin, mobiles, workflows, APIs, logiques, onboarding, cohérence),
ticketiser chaque manquement (méthode Spec Kit), implémenter les manquements en fin de test et le
max d'issues ouvertes.

## Périmètre testé (runtime local : Laravel 12.61 + PostgreSQL 16 + Redis, vitrine Next.js build/start, admin SPA build+login)

- **Auth** : login manager/rh/employee/super-admin, mauvais identifiants 401, /auth/me, /platform/auth/login, 2FA non requis (demo).
- **RBAC / isolation tenant** : employee→/employees 403, cross-tenant 403, 404 cross-company.
- **Workflows** : absence create (employee) → approve (manager) ✅ ; avance salaire create → manager-approve ✅ ; pointage check-in/out ✅ ; notifications read ✅ ; expense-claims (validation champs) ; onboarding-setup/checklist, launch-readiness, QR onboarding, branding ✅.
- **Cockpit platform admin** : /admin/dashboard/stats|activities|alerts, /admin/users, /platform/companies, /platform/country-defaults, /platform/plans ✅ — **/platform/users ❌ 500** (fixé).
- **Vitrine** : home/pricing/signup/faq/docs/download/demo/integrations 200 ; blog 404 = gating voulu par flag ; **/pricing mélange 14 j et 30 j** (constat).

## User Stories

### US1 — Les previews Cloudflare Pages du dashboard admin ne doivent plus renvoyer 500 (P1)
`allowed_origins_patterns` = regex complète, pas un glob. **Closes #3384.** Acceptance : preflight `*.pages.dev` → 204 + ACAO.

### US2 — La liste des administrateurs plateforme ne doit plus crasher (P1)
`SuperAdmin` casté datetime → `/platform/users` 200. **Closes #3385.**

### US3 — La réponse de vérification d'essai est cohérente avec le provisionnement (P2)
`days` = 14 (et non 30) aligné sur `subscription_end = now()+14j` et la décision « essai 14 jours ».

## Hors périmètre / déjà couvert (pas de doublon)
- Pricing 14 j vs 30 j vitrine → #3012 + PR #3135/#3218.
- Constraint `company_requests.status` (processing ∉ pending/approved/rejected) casse le parcours trial → pré-existant sur main, PR #3227.
- Codes de plans backend vs frontend → #2977.
