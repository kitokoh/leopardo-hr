# Feature Specification: Contrat SSO — exposer validation_available=false

**Feature Branch**: `fix/2278-sso-validation-available`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2278

## Contexte
Validation SAML/OIDC non implémentée (audit #1694) ; callbacks → 501 explicite, config stockée inactive. Le contrat `GET /api/v1/sso/status` n'expose pas cette limite.

## User Stories & Testing

### User Story 1 — L'intégrateur sait que la validation SSO est indisponible (P1)
Un client configure SSO : `status` renvoie `enabled`, `provider` et `validation_available: false` → l'UI peut afficher « SSO en cours de déploiement » au lieu de promettre un login SSO.

**Acceptance Scenarios**:
1. Given une config SSO présente (inactive), When GET /sso/status (manager principal), Then payload contient `validation_available: false`.
2. Given aucun client/secret, When GET /sso/status, Then même shape avec les valeurs par défaut.

### User Story 2 — OpenAPI documente le 501 (P2)
**Acceptance Scenarios**:
1. Given openapi.yaml, When on consulte les callbacks saml/oidc, Then le 501 est documenté avec le code d'erreur.
