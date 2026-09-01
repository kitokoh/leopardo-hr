# Tasks: Domaines canoniques, régression .env.example, gardes de hygiène (qa-expert11)

**Input**: spec.md + plan.md — 3 US vérifiées en live (2026-08-15).

## US1 — Domaine API canonique (P1)
- [ ] T001 Créer `docs/ops/DOMAINS.md` — registre canonique (domaine → usage → statut DNS vérifié) + procédure de mise à jour
- [ ] T002 Aligner `api/.env.example` (APP_URL, FRONTEND_URL, CLOUD_API_URL) sur le registre
- [ ] T003 Aligner les defaults web : `front/web/src/lib/backend-url.ts`, `next.config.ts`, `.env.local.example`, `README.md`, `vercel.json` (CSP connect-src)
- [ ] T004 Aligner les workflows : `deploy-main.yml` + `mobile-distribute-main.yml` (DEFAULT_API_BASE_URL / DEFAULT_API_HEALTHCHECK_URL)
- [ ] T005 Aligner docs/outils : READMEs mobile, `leopardo_platform_admin/README.md`, `zkteco-kiosk/config.example.json`, collection Postman, `scripts/agent-smoke-api.sh`, `docs/edge-sync/ARCHITECTURE.md`
- [ ] T006 Ajouter la garde `dev-hub/tools/check-canonical-domains.sh` + allowlist

## US2 — Régression b0630dd5 sur main (P1)
- [ ] T007 Restaurer `APP_VERSION=4.24.0`, `MAIL_BOUNCE_WEBHOOK_SECRET=` (commentaire #3058) et le commentaire `EDGE_LICENSE_PUBLIC_KEY` (#3317) dans `api/.env.example` ; `check-app-version-sync.sh` → OK

## US3 — Gardes de hygiène en CI (P2)
- [ ] T008 Job `hygiene-guards` dans `architecture-check.yml` : `check-app-version-sync.sh`, `check-env-example-parity.sh`, `check-canonical-domains.sh`

## Clôture
- [ ] T009 CHANGELOG + `docs/qa-expert11-session-2026-08-15.md` + PR(s) `Closes #...` ; issues fermées avec preuve code
