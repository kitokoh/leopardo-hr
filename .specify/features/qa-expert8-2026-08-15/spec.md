# Feature Specification: Session QA expert 8 — 2026-08-15 (audit 360° + forensique déploiement)

**Branch**: `docs/qa-expert8-session-2026-08-15`
**Created**: 2026-08-15 | **Status**: Audit terminé — 1 constat P1 nouveau, 5 surfaces revérifiées saines
**Input**: Mission propriétaire — audit 360° (vitrine, web, admin, mobiles, workflows, APIs, logiques,
onboarding, cohérence UI/UX), ticketiser chaque manquement selon Spec Kit, puis consolider la dette
et implémenter.

## Périmètre audité et méthode

| Surface | Méthode | Résultat |
|---------|---------|----------|
| API production Render | Probes HTTP live (`/health/live|ready`, `/`, `/tester-guide`, `/docs`, `/docs/openapi.yaml`, `/api/v1/demo-users`, `/api/v1/sso/providers`, `/api/v1/trial/status`, `/api/v1/marketing/leads`, `/api-explorer`) | 500 `/api-explorer` confirmé = prod stale (déjà #2265 fixé sur main, déploiement jamais appliqué — voir F8-01) |
| Vitrine (Vercel preview) | 20 routes probées + extraction des hrefs de la home + scan mojibake | Routes 200 ; pas de CTA mort `#` ; pas de mojibake ; `/fr|/en|/ar|/tr` 404 = routing `?lang=` voulu |
| Admin SPA (leo-admin.pages.dev) | Probe live + contrat statique vues ↔ routes | Bundle stale (déjà #2632) ; contrat sain sur main |
| Contrat mobile ↔ API | Extraction des endpoints des couches `data/`/`repositories/`/`services/` des 5 apps Flutter (119 endpoints) croisée avec les routes Laravel parsées statiquement (573 chemins, préfixes imbriqués résolus) | 0 endpoint manquant (les 13 écarts initiaux = faux positifs : routes modules `user.php`/`smart_attendance.php` et mocks) |
| Contrat admin SPA ↔ API | 102 endpoints extraits des vues/stores Vue croisés avec les routes | 0 endpoint manquant ; `/fleet` et autres vues tenant protégées par `requiresTenant` (#2272) |
| Patterns interdits (quick card) | `.withOpacity(`, `apiClient.dio.*`, `dd()/dump()`, `href="#"`, `/auth/signup`, mojibake i18n | 0 occurrence sur main |
| OpenAPI ↔ routes | `dev-hub/tools/check-openapi-route-coverage.py` | exit 0 (drift allowlisté uniquement) |
| Kiosk ZKTeco | IDs dupliqués `checkInButton/checkOutButton/statusBox` | 1 occurrence chacun — sain |
| Pipeline de déploiement | Analyse des runs `tests.yml`/`deploy-main.yml` + logs | **F8-01 (P1) : famine systémique du déploiement** |

## Constat nouveau

### F8-01 (P1) — Famine du pipeline de déploiement : les runs Tests sur main n'aboutissent jamais

Sur les 50 derniers runs `Tests - Leopardo RH` sur `main` : **48 annulés, 2 en cours, 0 succès**.
Mécanisme (vérifié dans les logs) :

1. Le group de concurrence `tests-${{ github.workflow }}-${{ github.ref }}` avec
   `cancel-in-progress: ${{ github.event_name == 'pull_request' }}` (fix #589e41c4) protège les runs
   **en cours** mais pas les runs **en attente** : GitHub n'autorise qu'un run pending par groupe ;
   chaque nouveau push sur main annule le run pending précédent.
2. La cadence de merges (agents parallèles, ~1 push/2 min) + file de runners saturée par les checks
   PR font qu'aucun run main ne démarre jamais (run 31892237640 : 0 job, annulé au push suivant).
3. `deploy-main.yml` exige `WR_CONCLUSION == "success"` → `should_deploy=false` en permanence →
   job `Deploy API + Web to Render` **skipped sur 100 % des runs** (run 31890393808 vérifié) tout en
   affichant un statut « success » trompeur.
4. Conséquence : prod figée (v4.23.5 vs main 4.24.0+), symptômes déjà ticketés (#2632, #2627,
   #2812, #2813, #3259) mais sans le mécanisme racine.

Spec dédiée : `.specify/features/ci-deploy-pipeline-starvation/spec.md`.

## Constats revérifiés — déjà couverts (pas de doublon)

- `/api-explorer` 500 prod → fix mergé `4a78011c` (#2265) ; reste le déploiement (F8-01).
- Checkout `?plan=<invalide>` crash → fixé par #3440 (fallback `'free'`) pendant la session.
- Demo login 401 + `/api/v1/demo-users` 404 en prod → comportement voulu (gate `DEMO_MODE_ENABLED`),
  doc `docs/DEMO_ACCOUNTS.md` alignée ; #2646 reste l'arbitrage produit.
- Seeder plans backend (Starter/Business/Enterprise) vs vitrine (Free/Pilot/Operations) → #2977.
- Trial 14j vs 30j → #3012/#2909 arbitrés.

## User Stories

### US1 — Un push sur main produit toujours un run Tests qui aboutit (P1)
**Acceptance** : après 3 pushes rapprochés (< 5 min) sur main, au moins le run du dernier SHA se
termine avec une conclusion `success|failure` (jamais annulé silencieusement) ; `deploy-main`
déploie le SHA vert le plus récent.

### US2 — Le statut « deploy skipped » est visible (P2)
**Acceptance** : quand `should_deploy=false`, le run publie un notice explicite
(`::notice::`/`::warning::` + summary) au lieu d'un vert silencieux.

## Hors périmètre
- Redéploiement manuel effectif (action ops propriétaire via le workflow `Deploy - Leopardo RH`).
- Migration vers un merge queue GitHub (option d'architecture évaluée dans la spec dédiée).
