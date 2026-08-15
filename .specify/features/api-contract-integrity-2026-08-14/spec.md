# Feature Specification: Intégrité des contrats API (OpenAPI ↔ routes ↔ clients)

**Feature Branch**: `feat/<issue>-api-contract-integrity`

**Created**: 2026-08-14

**Status**: Draft

**Input**: Test fonctionnel de la plateforme (workflows API, vues, boutons) — mission de la session 2026-08-14. Découvert : drift inverse OpenAPI (endpoints documentés qui n'existent pas), routes kiosk réelles non documentées, bug SPA admin (endpoint Marketing OAuth erroné), garde CI ne couvrant qu'un sens.

## User Scenarios & Testing

### User Story 1 - La spec OpenAPI ne documente que des endpoints réellement exposés (Priority: P1)

Un intégrateur (mobile, kiosk, partenaire) qui suit `api/openapi.yaml` ne rencontre jamais de 404 « route inexistante » : chaque chemin/verbe documenté existe dans les routes Laravel.

**Why this priority**: La spec est le contrat canonique consommé par les apps mobile, kiosk, vitrine et les SDK générés (PR #2156). Documenter des routes mortes pousse les clients vers des 404.

**Independent Test**: Script de vérification sens inverse (OpenAPI→routes) : 0 chemin documenté absent des routes PHP (hors allowlist justifiée). `python3 dev-hub/tools/check-openapi-route-coverage.py` exécute les deux sens.

**Acceptance Scenarios**:

1. **Given** la spec actuelle avec `/exports/*`, `/partner/*`, `/bank-exports`, `/i18n/{locale}`, **When** on vérifie l'existence des routes, **Then** ces chemins sont corrigés (renommés) ou supprimés, alignés sur les routes réelles.
2. **Given** les verbes POST documentés pour `loans/approve`, `loans/disburse`, `expense-claims/approve`, `cabinet/documents/move`, **When** on compare aux routes, **Then** les verbes documentés correspondent (`PUT`, `PATCH`).
3. **Given** `/smart-attendance/sessions/{id}/validate`, **When** on vérifie les routes, **Then** remplacé par `/approve` et `/reject` (actions réelles).
4. **Given** la garde CI, **When** une PR ajoute un chemin OpenAPI inexistant côté routes, **Then** le job échoue (drift inverse bloquant).

### User Story 2 - Les endpoints kiosk réels sont documentés (Priority: P1)

L'app kiosk / bridge local peut s'intégrer via la spec : les 4 extensions device token-only (`employee-info`, `announcements`, `leave-balance`, `qr-punch`) sont documentées avec leur sécurité `X-Kiosk-Token`.

**Why this priority**: Ces endpoints sont consommés par le kiosk terrain (contrat décrit dans AGENTS.md) mais absents de la spec — un intégrateur ne peut pas les découvrir.

**Independent Test**: `grep` des 4 chemins dans `api/openapi.yaml` + présence dans le check inverse.

**Acceptance Scenarios**:

1. **Given** un kiosk device token, **When** un intégrateur lit la spec, **Then** les 4 endpoints kiosk extensions sont documentés avec `security: []` + paramètre `KioskTokenHeader`.
2. **Given** la spec mise à jour, **When** le check inverse tourne, **Then** ces chemins sont reconnus comme couverts (plus dans l'allowlist).

### User Story 3 - Le bouton « Enregistrer » Marketing OAuth de l'admin fonctionne (Priority: P1)

Le super-admin peut configurer les identifiants OAuth des réseaux sociaux depuis `front/admin-dashboard` : la sauvegarde aboutit (200) au lieu d'un 404.

**Why this priority**: C'est un bouton réellement cassé de la plateforme — la mission exige que tout soit fonctionnel.

**Independent Test**: `GET/PUT /api/v1/admin/platform/marketing/oauth-config` répondent ; l'appel SPA pointe vers ce chemin (scan frontend route check → 0 écart).

**Acceptance Scenarios**:

1. **Given** l'écran Marketing OAuth admin, **When** le super-admin enregistre une config, **Then** `PUT /api/v1/admin/platform/marketing/oauth-config` est appelé (pas `/platform/...`).
2. **Given** l'écran ouvert, **When** il se charge, **Then** la config existante est affichée (GET).

### User Story 4 - Propreté : pas de code mort dans les vues admin (Priority: P3)

Les vues admin ne contiennent ni fonctions mortes ni imports inutilisés (lint 0 warning sur les fichiers touchés).

**Why this priority**: Mission « tout doit être propre » — les 37 warnings actuels (dont 7 fonctions mortes SystemView) nuisent à la maintenabilité.

**Independent Test**: `npm run lint` dans `front/admin-dashboard` — 0 warning sur `SystemView.vue` et les fichiers nettoyés.

**Acceptance Scenarios**:

1. **Given** SystemView.vue, **When** on nettoie, **Then** aucune fonction non utilisée par le template ne subsiste.
2. **Given** le lint, **When** on le relance, **Then** le compte de warnings baisse significativement sans changer de comportement.

---

### Edge Cases

- `/export/pay-slips` existe déjà dans la spec (doublon potentiel lors du renommage `/exports/pay-slips`) → suppression de la copie plurielle.
- L'allowlist `openapi-coverage-allowlist.txt` contient des entrées pour les routes désormais documentées → retirer les entrées devenues couvertes pour garder la liste honnête.
- `info.version` de la spec (4.23.5) vs défaut app (4.24.0) → aligner.
- Le check inverse doit tolérer les chemins OpenAPI volontairement « documentés mais non routés » s'il y en a (ex. préfixes publics) — allowlist inverse si nécessaire.

## Requirements

### Functional Requirements

- **FR-001**: La spec OpenAPI ne référence aucun chemin/verbe absent des routes Laravel (hors allowlist inverse documentée).
- **FR-002**: Toutes les routes réelles consommées par les clients (mobile, kiosk, admin SPA) sont documentées, avec sécurité et paramètres exacts.
- **FR-003**: Le SPA admin `MarketingOAuthView.vue` appelle le chemin réel `/admin/platform/marketing/oauth-config`.
- **FR-004**: La garde CI `check-openapi-route-coverage.py` couvre les deux sens (PHP→OpenAPI et OpenAPI→PHP) et échoue sur drift nouveau.
- **FR-005**: `openapi.yaml` reste valide (parsable, Redocly) et `info.version` aligné sur l'app.
- **FR-006**: Aucune régression de comportement : les routes PHP ne changent PAS (correction documentaire + SPA, pas d'API).

### Key Entities

- `api/openapi.yaml` — spec canonique (contrat).
- `dev-hub/tools/check-openapi-route-coverage.py` — garde CI de couverture.
- `dev-hub/tools/openapi-coverage-allowlist.txt` — gaps connus PHP→OpenAPI.
- `front/admin-dashboard/src/views/marketing/MarketingOAuthView.vue` — écran SPA cassé.
- `front/admin-dashboard/src/views/system/SystemView.vue` — code mort.

## Success Criteria

### Measurable Outcomes

- **SC-001**: `check-openapi-route-coverage.py` (deux sens) → 0 drift nouveau, exit 0, y compris le sens inverse.
- **SC-002**: `frontend_route_check` (script d'audit) → 0 endpoint frontend sans route réelle.
- **SC-003**: `npm run lint` admin-dashboard → 0 erreur et warnings nettoyés sur les fichiers touchés.
- **SC-004**: Aucun changement de route PHP / aucun changement de comportement API.

## Assumptions

- La vérité est du côté des routes PHP et des clients réels : la spec doit refléter l'existant, pas l'inverse.
- Les chemins OpenAPI corrigés n'ont pas de $ref entrants (vérifié par audit).
- Le kiosk device-token endpoints restent `security: []` avec `KioskTokenHeader` (contrat AGENTS.md).
- Pas de changement de schéma de données ; le work est documentaire + 1 bug SPA + garde CI.
