# QA Session — 2026-08-16 (Expert QA Agent, audit 360°)

**Branch**: `docs/qa-session-2026-08-16-expert-360`
**Scope**: Audit API runtime (locale), consolidation #4151, coordination multi-agents, CI.

## 1. Bloqueur P0 traité — suite Feature rouge (#4151)

Le durcissement `$fillable` #3677 a rendu la suite Feature rouge sur main (~280 sites `create()`/`update()` passant des clés non-fillable) — masqué par la famine CI #3545.

- **PR canonique** : #4203 (`fix/4151-fillable-regression-suite`) — 274 create + 5 update + 1 firstOrCreate scindés (pattern #4079), validée localement (php -l + tests ciblés) et en CI.
- **Doublons fermés** : #4165 (bug bloquant : variable `$employee` indéfinie sur les create standalone) et #4168 (approche forceCreate, incomplète — PartnerDashboard manquant), avec commentaires de renvoi.
- **Bugs prod réparés au passage** : `VerifyTrialSignup` (manager de trial sans rôle), `PlatformUserController` update/destroy/activate/deactivate/suspend (status SuperAdmin jamais persisté, y compris via `$validated['status']`), `DemoDzSeeder` (comptes démo sans rôle/statut — garde `wasRecentlyCreated`), `PartnerDashboardController` (status user partenaire perdu).

## 2. Nouveau constat — registre multi-pays verrouillé (#4217)

`GET /api/v1/supported-countries` (registre canonique #1867, aucune PII) était dans le groupe `auth:sanctum`+`tenant` → 401 pré-auth. La vitrine duplique `CURRENCY_OPTIONS` hardcodée (risque de drift type #3919) ; les apps mobiles et le formulaire public `trial/signup` ne peuvent pas charger la liste canonique.

- **Issue** : #4217 · **PR** : #4228 (`fix/4217-supported-countries-public`) — route publique, throttle `public-registry` (60/min/IP), GET-only, tests ×4.
- Vérifié en local : 200 sans token avec le payload complet (`confidence`, `compliance.level`…), `/auth/me` toujours 401.

## 3. Constats CI

- **Saturation CI** : 30+ PRs ouvertes simultanément × ~20 checks chacune → file d'attente profonde ; les merges sont bloqués sur les checks requis (état `blocked`). La famine #3545 (PR #4113) est le correctif structurant.
- **validate-and-sync rouge sur PRs web** : `front/web/public/og/*.png` régénérés non déterministes (issue #3846) — le correctif LFS #4129 (PR #4129) doit être mergé avant de réévaluer.
- **Mobile apps split guard rouge** : drift du contrat mobile (`platform_admin` expose `/attendance`) — traité par #4166/#4167/#4141.

## 4. Constats runtime (API locale, v4.24.0)

| Endpoint | Résultat |
|---|---|
| `GET /api/v1/health` | 200 ok |
| `GET /api/v1/i18n/catalog` + `/catalog/fr` | 200 (fixé sur main, pas encore déployé — #2812) |
| `POST /api/v1/trial/signup` | 200 anti-énumération (« Code de vérification envoyé ») |
| `POST /api/v1/auth/login` (mauvais MDP) | 401 INVALID_CREDENTIALS localisé |
| `GET /api/v1/supported-countries` | 401 → **200 après #4228** |
| `GET /api/v1/features/manifest` | 401 (contrat mobile — à confirmer si pré-login requis) |

## 5. Coordination multi-agents

- Branches `fix/4151-fillable-*` ×4 créées par des sessions concurrentes → une seule PR canonique conservée (#4203), les autres fermées avec commentaires.
- Recommandation : passer `required_pull_request_reviews` à 1 pour main (qualité), ou documenter le protocole de claim de branche (le nom de branche EST le lock, constitution §I) pour éviter les doublons.
