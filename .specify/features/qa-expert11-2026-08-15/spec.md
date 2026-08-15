# Feature Specification: Domaines canoniques, régression .env.example, gardes de hygiène (qa-expert11)

**Created**: 2026-08-15

**Status**: Draft

**Wave**: qa-expert11-2026-08-15 (audit 360° — surfaces live, configs, CI, onboarding)

**Input**: Audit 2026-08-15 — vérification DNS live de tous les domaines documentés, revue des defaults de build/déploiement, revue des gardes dev-hub vs CI, vérification des régressions sur main.

## Résumé des constats (vérifiés en live)

### US1 — Domaine API canonique introuvable : seuls les defaults « legacy » sont vivants (P1)
Vérification DNS/HTTP en live (2026-08-15) :

| Domaine documenté | DNS | HTTP |
|---|---|---|
| `gestionemployerbackend.onrender.com` (defaults code/builds) | ✅ 216.24.57.7 | ✅ 200 — API v4.23.5, `queue: sync` |
| `api.leopardo-rh.com` (api/.env.example `APP_URL`) | ❌ NXDOMAIN | ❌ 000 |
| `app.leopardo-rh.com` (api/.env.example `FRONTEND_URL`) | ❌ NXDOMAIN | ❌ 000 |
| `leopardo-rh.com` / `www.leopardo-rh.com` (vitrine) | ❌ NXDOMAIN | ❌ 000 (cf. #3452) |
| `api.leopardo.app` (api/.env.example `CLOUD_API_URL`, docs edge) | ❌ NXDOMAIN | ❌ 000 |

Conséquence : le seul backend joignable porte un nom de domaine legacy ; toute doc,
template d'env, collection Postman ou README qui référence `*.leopardo-rh.com` /
`*.leopardo.app` pointe vers du néant. Chaque nouvel installateur qui suit les docs
obtient un backend mort, et la confusion sur « quel est le vrai domaine prod » persiste.
Le nom canonique devra être tranché par le propriétaire (issue #3452 = infra DNS) ;
ce spec couvre la **canonicalisation côté code/docs** : une seule source de vérité,
tous les fichiers alignés, garde CI anti-réapparition.

Fichiers impactés (constatés) :
- `api/.env.example` (APP_URL, FRONTEND_URL, CLOUD_API_URL)
- `.github/workflows/deploy-main.yml` + `.github/workflows/mobile-distribute-main.yml` (`DEFAULT_API_BASE_URL` / `DEFAULT_API_HEALTHCHECK_URL`)
- `front/web/src/lib/backend-url.ts` (DEFAULT_BACKEND_API_URL)
- `front/web/next.config.ts` (fallback NEXT_PUBLIC_API_URL)
- `front/web/.env.local.example`, `front/web/README.md`
- `front/web/vercel.json` (CSP connect-src)
- `front/mobile_apps/*/README.md` (colonne Staging), `front/mobile_apps/leopardo_platform_admin/README.md` (commandes run/build)
- `front/zkteco-kiosk/config.example.json` (apiBaseUrl)
- `postman/leopardo_hr.postman_collection.json`
- `scripts/agent-smoke-api.sh` (STAGING_API par défaut)
- `docs/edge-sync/ARCHITECTURE.md`

### US2 — Régression sur main : commit b0630dd5 (fix/3058-mail-bounce-secret) (P1)
Le commit `b0630dd5` (mergé sur main) a régressé trois choses dans `api/.env.example` :
1. `APP_VERSION` repassé de `4.24.0` → `4.23.5` — annule le fix #3528 ; la garde
   `dev-hub/tools/check-app-version-sync.sh` (issue #3528) échoue sur main.
2. Suppression de la ligne `MAIL_BOUNCE_WEBHOOK_SECRET=` alors que
   `api/config/services.php` lit toujours `env('MAIL_BOUNCE_WEBHOOK_SECRET')` et que
   `EmailBounceWebhookController` est fail-closed (503) sans le secret (#3058) —
   rupture de parité .env.example, nouveau déploiement = webhook bounce mort en silence.
3. Suppression du commentaire `EDGE_LICENSE_PUBLIC_KEY` (fail-closed #3317) — la
   doc de génération de clé disparaît du template.

Vérifié : `bash dev-hub/tools/check-env-example-parity.sh` échoue aussi sur main
(6 clés config/ absentes de .env.example — RATE_LIMIT_PLAN_*, STRIPE_PRICE_*, TRIAL_DAYS,
couvertes par PR #3690 ; le retour de MAIL_BOUNCE_WEBHOOK_SECRET doit s'y ajouter).

### US3 — Gardes dev-hub non câblées en CI (P2)
`check-app-version-sync.sh` et `check-env-example-parity.sh` ne sont appelés par
aucun workflow : la dérive US2 est passée inaperçue. Câbler un job de garde
« hygiene » dans `architecture-check.yml` (ou workflow dédié) pour que toute
régression de ce type fasse échouer la PR.

**Acceptance Scenarios**:
1. **Given** main, **When** `bash dev-hub/tools/check-app-version-sync.sh` et
   `bash dev-hub/tools/check-env-example-parity.sh` sont exécutés, **Then** les deux passent (0 erreur).
2. **Given** un fichier de config/docs qui référence un domaine hors registre,
   **When** la garde CI « domaines canoniques » tourne, **Then** la PR échoue avec le chemin fautif listé.
3. **Given** la CI PR, **When** un commit modifie `api/.env.example`,
   **Then** les gardes de parité/version s'exécutent (pas de drift silencieux).
