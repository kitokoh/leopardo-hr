# Feature Specification: E2E funnel prospect automatisé (issue #5146)

**Created** : 2026-08-19

**Status** : Ready for implementation

**Input** : Le funnel d'acquisition (vitrine → signup → trial → dashboard) est le contrat de vente de la startup ; les fixes #4947/#4948/#4949/#5161/#5162 doivent être protégés contre la régression. Les E2E existants couvrent l'admin (#4101) mais pas ce parcours complet. Les parcours trial sont actuellement cassés en prod (#5161, #5162) — cette spec verrouille le contrat que les fixes devront satisfaire.

## Contexte technique

- Surfaces : vitrine `front/web` (Next.js, Vercel) + API `api` (Laravel, Render `gestionemployerbackend.onrender.com`, v4.24.0)
- Endpoints : `POST /api/v1/trial/signup` (payload : email, company, country, `requestedWorkflow` ∈ `guided_trial|self_service`) → `provisioning_token` ; `GET /api/v1/trial/status?token=…` (pollé ~1 req/5 s, throttle `trial-status`) → `provisioning_sandbox` → `ready` (+ `login_url`) | `failed`
- Parcours OTP : signup `self_service` → email OTP → `POST /api/v1/trial/verify`
- Création employé : `POST /api/v1/employees` (régression #4947 : `password_hash` NOT NULL)
- E2E existants : `front/web/e2e/` (Playwright, vitrine) + `front/admin-dashboard/playwright` ; workflow `e2e-staging.yml` (warm-up anti cold-start Render, `expect.timeout` 15 s)
- Contraintes : staging = prod free tier Render (cold start 30-60 s) ; `MAILGUN_*` en cours de stabilisation (les tests doivent tolérer un backend mail de test, pas l'email réel)

## User Scenarios & Testing

### US1 — Parcours guided complet (P0)
Un prospect inconnu entre dans le produit sans assistance.

**Acceptance Scenarios**:
1. **Given** la vitrine `/signup`, **When** le prospect soumet le formulaire (company, email, pays DZ), **Then** le statut passe `provisioning_sandbox` puis **`ready` en < 2 min** (tolérance CI 3 min cold start) et un `login_url` est retourné.
2. **Given** un `login_url`, **When** le prospect l'ouvre, **Then** il atteint le dashboard sans erreur 5xx.
3. **Given** un statut `failed`, **When** le test échoue, **Then** le workflow est rouge avec le message d'erreur dans les logs (pas de faux vert).

### US2 — Parcours self-service OTP (P1)
Le prospect reçoit un code par email et le valide.

**Acceptance Scenarios**:
1. **Given** un signup `self_service` avec boîte mail de test, **When** l'OTP est émis, **Then** le code est récupérable (mail catcher) et `verify` retourne un accès.
2. **Given** un code invalide, **When** le prospect soumet, **Then** erreur 4xx localisée, pas de 500.

### US3 — Création d'employé sur le tenant provisionné (P1)
Le manager peut créer un employé dès la première session (régression #4947).

**Acceptance Scenarios**:
1. **Given** une session trial `ready`, **When** `POST /api/v1/employees` (avec password), **Then** 201 et l'employé est listable.
2. **Given** un payload sans password avec `send_invitation=true`, **Then** 201 (invitation planifiée), jamais 500.
3. **Given** un import CSV valide, **Then** import OK, rapport ligne par ligne.

## Requirements

- FR-1: `front/web/e2e/funnel.spec.ts` — scénarios US1 (guided complet, avec poll `trial/status` jusqu'à `ready` ou `failed`, timeout 3 min)
- FR-2: scénario US2 (OTP) **uniquement si un mail catcher est disponible en CI** ; sinon test API seul (signup → pas de 503) en attendant la stabilisation Mailgun
- FR-3: scénario US3 via API avec session du tenant provisionné (token/magic-link)
- FR-4: intégration `e2e-staging.yml` — warm-up anti cold start conservé, `expect.timeout` ≥ 15 s, la PR est **bloquante** au merge (fail-closed)
- FR-5: les tests sont **déterministes** : emails de test uniques (timestamp), tenant supprimé/nettoyé après run (ou toléré en sandbox)
- FR-6: entrée `CHANGELOG.md` sous `## [Unreleased]` → `### Added` (référence #5146, #4101)

## Non-objectifs

- Pas les 10 parcours admin de #4101 (suivre séparément)
- Pas d'E2E mobile Flutter (hors sandbox)
- Pas de nouveau service mail catcher en prod (uniquement CI/staging)

## DoD

- [ ] Spec approuvée avant code (constitution `.specify`)
- [ ] US1 vert sur staging 2 fois de suite sans intervention
- [ ] US3 vert (API, tenant réel) ; US2 vert si mail catcher, sinon test API du signup (pas de 503)
- [ ] Workflow `e2e-staging.yml` étendu, bloque le merge en cas d'échec
- [ ] CHANGELOG + commentaire de clôture avec les captures des runs

## Dépendances

- #5161 (trial guided → `failed`) et #5162 (OTP 503) : les scénarios US1/US2 sont **rouges tant que ces fixes ne sont pas déployés** — c'est voulu (le test verrouille le contrat). Le PR de cette spec peut merger avec les tests marqués `test.skip` documentés, à activer dès que #5161/#5162 sont fermés.
