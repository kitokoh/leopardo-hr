# Findings Registry — Audit 360° expert 12 (2026-08-15)

> Registre des constats vérifiés de la session. Chaque ligne : constat → preuve → statut → issue(s).

## Verdict global

| Domaine | État | Preuve |
|---|---|---|
| API (contrats OpenAPI) | ✅ 0 drift (2 sens) | `check-openapi-route-coverage.py` → 0 nouveau, 121 allowlistés |
| Vitrine web (lint/tsc) | ✅ vert | `tsc --noEmit` + `eslint --max-warnings 0` → exit 0 |
| Admin dashboard (build) | ✅ vert | `vite build` → exit 0 |
| Admin dashboard (lint) | ⚠️ 9 warnings | `npm run lint` → 9 `no-unused-vars` (dont 2 introduits par #3699/#3701) |
| Kiosk | ✅ tests présents, i18n 4 locales | `tests/` intacts, `i18n.js` fr/en/tr/ar |
| Migrations | ✅ | `check-migration-basename-collisions.sh` → 0 |
| Env parity | ✅ | `check-env-example-parity.sh` → exit 0 (fix #3707 vérifié) |
| APP_VERSION | ✅ 4.24.0 | `check-app-version-sync.sh` → ✓ |
| Prod API live | ⚠️ stale v4.23.5 + `queue: sync` | probe `/api/v1/health` → #2812/#2627/#3562 (déploiement, hors code) |
| Admin live | ✅ 200 | `leo-admin.pages.dev` |
| Vitrine live | ⚠️ 404/NXDOMAIN | `leopardo-hr.vercel.app` 404, `leopardo-rh.com` NXDOMAIN → #3452 (DNS infra) |
| FCM mobile | ⚠️ placeholders | `google-services.json` ×3 + `GoogleService-Info.plist` ×4 → #3152 |
| Mobile Dart | ✅ switch OK | vérifié SDK Dart 3.13 : implicite break (pas de fallthrough) |

## Constats détaillés

### C1 — Admin lint : 9 warnings no-unused-vars (NOUVEAU, P3)
- `CommandPalette.vue:83-88` : 5 imports d'icônes inutilisés.
- `SystemView.vue:84` : `InformationCircleIcon` inutilisé — introduit par le merge de #3699 (2026-08-15).
- `WebhooksView.vue:130` : `StatusBadge` inutilisé — introduit par le merge de #3701 (2026-08-15).
- `EdgeNodesView.vue:220` : helper `formatDuration` mort.
- `TaxRatesView.vue:352` : helper `formatDate` mort.
- **Preuve** : `npm run lint` (admin) → « ✖ 9 problems (0 errors, 9 warnings) ».
- **Traitement** : tâches T002-T006 de la spec `qa-audit-expert12-2026-08-15`.

### C2 — Gardes vitrine vertes sur main (NOUVEAU, positif)
- **Preuve** : `npx tsc --noEmit` → exit 0 ; `npx eslint src --max-warnings 0` → exit 0 (check requis CI).
- **Traitement** : suivi US2 (aucune issue).

### C3 — Prod API stale + queue sync (connu, ré-vérifié)
- **Preuve** : `GET https://gestionemployerbackend.onrender.com/api/v1/health` → `"version":"4.23.5"`, `"queue":{"driver":"sync"}`.
- **Issues** : #2812, #2627 (déploiement), #3562 (queue sync prod), #3545 (famine CI qui bloque le déploiement).

### C4 — Vitrine inaccessible (connu, ré-vérifié)
- **Preuve** : `leopardo-rh.com` NXDOMAIN (#3452) ; `leopardo-hr.vercel.app` → 404 (domaine custom absent).
- **Issue** : #3452 (DNS infra), #2813 (déploiement Vercel).

### C5 — FCM placeholders (connu, ré-vérifié)
- **Preuve** : `google-services.json` (employee/hr/manager/platform_admin) contiennent `000000000000` ; plists idem.
- **Issue** : #3152.

### C6 — Faux positif écarté : switch Dart sans break
- **Preuve** : test SDK Dart 3.13.0 → `dart analyze` OK, runtime `p=1 s=0 f=0` (break implicite). Aucun bug.
- **Traitement** : aucun (documenté pour éviter les fausses issues).

### C7 — Nettoyage branches/PRs (réalisé)
- 20 PRs mergées (dont 12 par cet agent), 46 branches supprimées (supersédées/mergées), 2 branches agents actives conservées (fix/3708-hygiene-guards*).
- Issues fermées par merge : #3270, #3273, #3274, #3277, #3285, #3286, #3262, #3268, #3238, #3601, #3272, #3271, #3562, #3568, #3377, #3586, #3587, #3588, #3592 (+ autres par les agents parallèles).
