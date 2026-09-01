# Feature Specification: Audit 360° expert 12 — 2026-08-15

**Feature Branch**: `docs/qa-audit-expert12-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission 360° (vitrine, web app, admin, mobile, API, edge/kiosk, CI/CD) — session 2026-08-15.
Vérifications réalisées : lint/build locaux (`npm run lint`, `vite build`, `tsc --noEmit`, `eslint --max-warnings 0`),
gardes dev-hub (OpenAPI coverage, env parity, app-version sync, migrations, strict-types), probes live
(API Render v4.23.5, admin Cloudflare Pages 200, vitrine Vercel 404 / NXDOMAIN), revue des 20 PRs mergées
et des 46 branches nettoyées.

## User Stories & Testing

### User Story 1 — Le lint admin-dashboard est à 0 warning (Priority: P3)

`npm run lint` dans `front/admin-dashboard` ne remonte aucun warning `no-unused-vars` (9 aujourd'hui,
dont 2 introduits par les PRs #3699 et #3701 mergées le 2026-08-15).

**Why this priority**: Hygiène maintenabilité. Les 9 warnings actuels (dont 5 imports inutilisés
CommandPalette) polluent la sortie CI et masquent les vraies erreurs ; deux d'entre eux ont été
introduits par des merges récents — la dérive repart.

**Independent Test**: `cd front/admin-dashboard && npm run lint` → 0 warning, exit 0.

**Acceptance Scenarios**:
1. **Given** `CommandPalette.vue` importe 5 icônes inutilisées (ArrowDownTrayIcon, CalendarDaysIcon, BriefcaseIcon, AcademicCapIcon, ChatBubbleLeftRightIcon), **When** on lance lint, **Then** les imports sont retirés et lint passe.
2. **Given** `SystemView.vue:84` importe InformationCircleIcon sans usage (introduit par #3699), **When** on lance lint, **Then** l'import est retiré.
3. **Given** `WebhooksView.vue:130` importe StatusBadge sans usage (introduit par #3701), **When** on lance lint, **Then** l'import est retiré.
4. **Given** `EdgeNodesView.vue:220` et `TaxRatesView.vue:352` définissent des helpers inutilisés, **When** on lance lint, **Then** les helpers morts sont supprimés.
5. **Given** le build, **When** on lance `npm run build`, **Then** il reste vert (0 erreur).

### User Story 2 — Les gardes CI vitrine restent vertes après la vague de merges (Priority: P2)

`npx tsc --noEmit` et `npx eslint src --ext .ts,.tsx --max-warnings 0` dans `front/web` passent
sur main — vérifiés localement ce jour après le merge de 20 PRs (dont PWA manifest localisé #3682,
robots #3703, pricing #3687).

**Why this priority**: Le check requis « Frontend — ESLint + TypeScript » est bloquant pour main ;
la famine CI (#3545) empêche la détection rapide d'une régression. La validation locale est la
seule garantie disponible.

**Independent Test**: `cd front/web && npx tsc --noEmit && npx eslint src --ext .ts,.tsx --max-warnings 0` → exit 0.

**Acceptance Scenarios**:
1. **Given** le checkout main courant, **When** on exécute tsc, **Then** 0 erreur.
2. **Given** le checkout main courant, **When** on exécute eslint, **Then** 0 warning (max-warnings 0).

### User Story 3 — L'état live est documenté et réconcilié avec les issues ops (Priority: P2)

Les constats live de la session (API Render v4.23.5 + `queue: sync`, admin Pages 200, vitrine
Vercel 404 + NXDOMAIN leopardo-rh.com) sont consignés dans le registre de session et rattachés
aux issues existantes (#2812, #2627, #3562, #3452) — aucune nouvelle issue ops dupliquée.

**Why this priority**: La réconciliation évite les doublons d'issues ops et garde une trace
datée de l'état observé pour les agents suivants.

**Independent Test**: `docs/qa/QA_SESSION_2026-08-15-expert12.md` contient le tableau de bord
live avec les références d'issues.

**Acceptance Scenarios**:
1. **Given** les probes live, **When** on écrit le registre, **Then** chaque constat cite l'issue existante.
2. **Given** le registre, **When** on vérifie, **Then** aucune nouvelle issue ops créée pour des constats déjà suivis.

### User Story 4 — Les branches et PRs orphelines sont consolidées (Priority: P2)

Fin de session : 0 PR ouverte non traitée, aucune branche dont le contenu n'est ni dans main ni
dans une PR ouverte, issues fermées par les PRs mergées vérifiées.

**Why this priority**: Mission Phase 2 — consolidation de la dette : 20 PRs mergées, 46 branches
supprimées (supersédées ou mergées), issues #3270/#3273/#3274/#3277/#3285/#3286/#3262/#3268/
#3238/#3601/#3272/#3271/#3562/#3568/#3377/#3586/#3587/#3588/#3592 fermées par merge.

**Independent Test**: API GitHub → 0 PR ouverte ; `git branch -r` → seules `main` + branches
agents actives.

**Acceptance Scenarios**:
1. **Given** la liste des PRs, **When** on vérifie, **Then** toutes les PRs de la vague sont mergées ou refermées proprement.
2. **Given** la liste des branches, **When** on vérifie, **Then** aucune branche supersédée ne subsiste.

## Requirements

### Functional Requirements

- **FR-001**: `front/admin-dashboard` lint → 0 warning (suppression imports/helpers inutilisés).
- **FR-002**: `front/web` tsc + eslint --max-warnings 0 → verts sur main.
- **FR-003**: Le registre de session expert 12 consigne les constats live avec références d'issues.
- **FR-004**: Aucune régression de comportement : le build admin reste vert, les pages touchées sont inchangées fonctionnellement.
- **FR-005**: Les issues de la vague QA mergée sont fermées (vérifié via API).

### Key Entities

- `front/admin-dashboard/src/components/common/CommandPalette.vue` — 5 imports inutilisés.
- `front/admin-dashboard/src/views/system/SystemView.vue` — import InformationCircleIcon inutilisé (#3699).
- `front/admin-dashboard/src/views/webhooks/WebhooksView.vue` — import StatusBadge inutilisé (#3701).
- `front/admin-dashboard/src/views/edge/EdgeNodesView.vue` — helper formatDuration mort.
- `front/admin-dashboard/src/views/settings/TaxRatesView.vue` — helper formatDate mort.
- `docs/qa/QA_SESSION_2026-08-15-expert12.md` — registre de session.

## Success Criteria

- **SC-001**: `npm run lint` (admin) → 0 warning.
- **SC-002**: `npm run build` (admin) → vert.
- **SC-003**: `tsc --noEmit` + `eslint --max-warnings 0` (web) → verts.
- **SC-004**: 0 PR ouverte orpheline ; 0 branche supersédée.
- **SC-005**: Issues référencées par les PRs mergées fermées.

## Assumptions

- Les 9 warnings admin sont tous du `no-unused-vars` (vérifié) — suppression sans risque.
- Les constats ops live correspondent aux issues déjà ouvertes (#2812/#2627/#3562/#3452) — pas de doublon.
- La famine CI (#3545) persiste ; la validation locale est la référence pour cette session.
