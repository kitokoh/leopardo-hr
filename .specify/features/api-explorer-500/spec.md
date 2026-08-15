# Feature Specification: Réparation /api-explorer (500 — `<?php` littéral dans la vue blade)

**Feature Branch**: `fix/2265-api-explorer-blade`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2265

## Contexte
La page dev publique `/api-explorer` renvoie 500 sur la prod. Cause : `api/resources/views/docs/api-explorer.blade.php` (~L354) contient un `<?php` littéral dans une chaîne JS du snippet builder ; le template blade compilé est parsé par PHP → fatal parse error.

## User Stories & Testing

### User Story 1 — La page /api-explorer s'affiche et génère des snippets PHP valides (P1)
Un développeur ouvre `/api-explorer`, choisit un endpoint, copie le snippet PHP généré et l'exécute tel quel.

**Independent Test**: `GET /api-explorer` → 200 (assertion dans `OpenApiDocsTest`), le HTML contient `<?php`.

**Acceptance Scenarios**:
1. Given la vue blade, When elle est rendue par PHP, Then aucun parse error (HTTP 200).
2. Given un endpoint sélectionné côté client, When le snippet PHP est copié, Then il commence par `<?php` et est exécutable.

### User Story 2 — Pas de régression sur les autres pages docs (P1)
`/tester-guide` et `/docs` continuent de fonctionner.

**Acceptance Scenarios**:
1. Given la réparation, When on vérifie les 3 pages docs, Then toutes renvoient 200.
