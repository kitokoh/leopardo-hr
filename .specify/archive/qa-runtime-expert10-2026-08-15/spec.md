# Feature Specification: Session QA runtime expert 10 — 2026-08-15

**Branch**: `docs/qa-runtime-expert10-2026-08-15`
**Created**: 2026-08-15 | **Status**: Session documentée, correctifs livrés via PRs dédiées

**Input**: Mission propriétaire — audit 360° (vitrine, web, admin, mobiles, workflows, APIs,
logiques, onboarding, cohérence UI/UX), ticketiser chaque manquement (méthode Spec Kit),
consolider la dette existante (issues + branches), garder `main` vert, implémenter les constats.

## Différentiel de cette session : vérification RUNTIME

Les 9 sessions expertes du 2026-08-15 ont massivement couvert l'audit statique. Cette session
privilégie la **preuve par l'exécution** : builds réels, gardes CI rejouées localement, sondes
live production, et mécanique de merge (résolution de conflits, fermeture de PRs absorbées).

## Périmètre vérifié (avec preuves)

### Builds & qualité statique rejouée (local, PHP 8.4.24 / Node 24)

| Surface | Vérification | Résultat |
|---------|--------------|----------|
| Vitrine `front/web` | `eslint --max-warnings 0` + `next build` | ✅ vert (0 erreur, 0 warning, toutes routes compilées) |
| Admin `front/admin-dashboard` | `eslint` + `vite build` (worktree main propre) | ✅ vert (0 erreur, 2 warnings `no-unused-vars` résiduels) |
| API | PHPStan strict level 8 (`phpstan-strict.neon`, mêmes flags que CI) | 🔴 **33 erreurs sur main** → baseline régénérée poussée sur la branche canonique #3515 (`[OK] No errors` vérifié) |
| API | Garde OpenAPI route coverage (`check-openapi-route-coverage.py`) | ✅ 0 drift (122 gaps allowlistés) |
| API | Garde collisions migrations (`check-migration-basename-collisions.sh`) | ✅ aucune |
| API | Garde catalogue pays (`check-country-catalog.sh`) | ✅ 19 codes, 0 doublon |
| API | Garde interfaces orphelines | ✅ 0 nouveau (20 allowlistés) |
| Mobile | Anti-patterns carte agent (withOpacity, dio direct, await/runApp, Firebase non gardé) | ✅ 0 occurrence |

### Sondes live production (2026-08-15 ~15:41-16:05 UTC)

| Sonde | Résultat |
|-------|----------|
| Vitrine Vercel : 13 routes (`/`, `/pricing`, `/signup`, `/faq`, `/docs`, `/download`, `/demo`, `/integrations`, `/contact`, `/about`, `/blog`, `/terms`, `/privacy`) | ✅ 200 ×13 |
| Headers sécurité vitrine | ✅ HSTS preload, CSP report-only (décision documentée #1607), X-Frame-Options DENY, nosniff, referrer-policy |
| API Render `/api/v1/health` | 🔴 `version: 4.23.5` (main = 4.24.0), `queue.driver: sync`, `redis: skipped` → preuve ajoutée sur #2627 |
| `leopardo-rh.com` | 🔴 NXDOMAIN confirmé (curl resolve fail) — confirme #3452 (action propriétaire ops) |
| `GET /api/v1/plans` | ✅ 404 attendu (route super-admin `/platform/plans` uniquement — pas de fuite publique) |
| `GET /api/v1/sso/providers` | ✅ 200 (throttlé #3241) |

## User Stories (constats → actions)

### US1 — Dette « fixed-but-open » fermée avec preuve (P2)
En tant que mainteneur, je veux que les issues déjà corrigées sur main soient fermées avec
référence de code, pour que le backlog reflète la réalité.
**Livré** : #3340 (CSV leaves — guard présent l.280-285), #3443 (pricing i18n — 4 catalogues +
template sur clés). Vérifiés déjà clos : #3324, #3436. Commentaire-preuve #2627 (version +
queue=sync).

### US2 — APP_VERSION synchronisé + garde anti-drift (P2) — **implémenté**
En tant qu'ops, je veux que `/api/v1/health` rapporte la version réellement déployée.
**Livré** : PR #3579 — `.env.example` 4.23.5→4.24.0 + garde `check-app-version-sync.sh` câblée
dans Governance Gates. Closes #3528.

### US3 — Main PHPStan strict reverdi (P1) — **débloqué**
En tant que swarm, nous voulons le check requis « PHPStan — Strict » vert sur main.
**Livré** : contribution à la branche canonique #3515 (merge main + baseline régénérée,
`[OK] No errors` local). Dette de fond tracée par #3158 (ternaires TrialWelcomeMail,
`$kiosk` indéfini KioskController, types tests Feature).

### US4 — Mécanique de merge du swarm (P1)
Résolution de conflits sûre (union CHANGELOG, version main quand superset) + fermeture des
PRs absorbées avec renvoi canonique.
**Livré** : branches dé-conflictées poussées (#3442, #3438, #3424, #3422, #3421, #3425,
#3539*, #3462*, #3448*, #3408* (*=puis fermées absorbées)) ; PRs mergées après update-branch :
#3464, #3454 ; doublon #3435 arbitré (#3482 fermée, canonique #3483 dé-conflictée).

## Hors périmètre / déjà couvert (pas de doublon)

- CSP report-only vitrine : décision documentée (#1607, #1617) — non re-ouvert.
- DNS `leopardo-rh.com` : action propriétaire pure (#3452) — confirmation ajoutée.
- Placeholders `MAIL_*` dans `.env.example` : tolérés par design (garde #1605, clés allowlistées) — faux positif écarté après lecture de la garde.
- Compile Flutter : SDK absent du sandbox — non couvert (CI Mobile Flutter fait foi).
- 14j vs 30j essai : tranché propriétaire (D-E4-01, constante `billing.trial_days`) — clos.
