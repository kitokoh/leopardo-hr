# Feature Specification: Mobile — parsing réseau discipliné dans settings (qa-expert10)

**Created**: 2026-08-15

**Status**: Draft

**Wave**: qa-expert10-2026-08-15 (audit 360° — kiosk, edge, infra, API, mobile, surfaces live)


**Input**: Audit Flutter du 2026-08-15 (résiduel #3406 — sites non couverts).

## User Scenarios & Testing

### US1 — Préférences de notification robustes (Priority: P2) — Issue #3595
En tant qu'utilisateur employee/manager/HR, je veux que mes préférences se chargent même si l'API renvoie le payload sans enveloppe `data`.

**Acceptance Scenarios**:
1. **Given** une réponse sans enveloppe, **When** GET notification preferences, **Then** pas de TypeError — `extractDataMap` tolère les 2 formes.

## Requirements
- FR-1: remplacer les 6 casts `['data'] as Map` par `extractDataMap(response.data)` (employee:113,129 ; manager:113,129 ; hr:66,82)
- FR-2: test de régression enveloppe absente pour chaque repository
