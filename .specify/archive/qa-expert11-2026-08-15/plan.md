# Plan: Domaines canoniques, régression .env.example, gardes de hygiène

**Input**: spec.md (qa-expert11-2026-08-15)

## Phase A — Réparer la régression b0630dd5 (US2) — PR courte, prioritaire

1. Restaurer `APP_VERSION=4.24.0` dans `api/.env.example` (fix #3528 re-cassé).
2. Restaurer la ligne `MAIL_BOUNCE_WEBHOOK_SECRET=` (avec commentaire #3058).
3. Restaurer le commentaire `EDGE_LICENSE_PUBLIC_KEY` (génération openssl, #3317).
4. Vérifier localement : `bash dev-hub/tools/check-app-version-sync.sh` → OK.
5. PR `fix/3528-3058-3317-env-example-regression` (Closes les issues dédiées), CHANGELOG.

## Phase B — Registre des domaines canoniques (US1)

1. Créer `docs/ops/DOMAINS.md` — source de vérité : tableau domaine → usage → statut DNS
   (vérifié via `getent hosts` / curl /health), + procédure de mise à jour.
2. Aligner `api/.env.example` (APP_URL/FRONTEND_URL/CLOUD_API_URL) sur le registre.
3. Aligner les defaults web : `front/web/src/lib/backend-url.ts`, `front/web/next.config.ts`,
   `front/web/.env.local.example`, `front/web/README.md`, `front/web/vercel.json` (CSP connect-src).
4. Aligner les workflows : `deploy-main.yml`, `mobile-distribute-main.yml`
   (DEFAULT_API_BASE_URL / DEFAULT_API_HEALTHCHECK_URL) sur le registre.
5. Aligner les docs/outils : READMEs mobile, `front/mobile_apps/leopardo_platform_admin/README.md`,
   `front/zkteco-kiosk/config.example.json`, `postman/leopardo_hr.postman_collection.json`,
   `scripts/agent-smoke-api.sh`, `docs/edge-sync/ARCHITECTURE.md`.
6. Ajouter le garde `dev-hub/tools/check-canonical-domains.sh` + allowlist.

## Phase C — Câbler les gardes en CI (US3)

1. Nouveau job `hygiene-guards` dans `architecture-check.yml` : exécute
   `check-app-version-sync.sh`, `check-env-example-parity.sh`, `check-canonical-domains.sh`.
2. Tests locaux avant push (bash), puis PR + CHANGELOG.

## Phase D — Clôture

- Issues fermées avec preuve code (commentaire + état closed).
- Entrée CHANGELOG + session docs (`docs/qa-expert11-session-2026-08-15.md`).
