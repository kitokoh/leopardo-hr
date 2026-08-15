# Feature Specification: Flux OIDC complet (authorize + callback + validation id_token)

**Feature Branch**: `feat/2231-oidc-flow`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issues**: #2231 (T006), #2197, #2251

## Contexte

Le SSO OIDC était un stub : `configure` gardait la config inactive et les
callbacks renvoyaient 501 (`SSOValidationNotImplementedException`). La
validation des ID tokens n'existait pas.

## User Stories & Testing

### User Story 1 — config OIDC complète → active (P1)
**Acceptance Scenarios**:
1. Given `provider=oidc` + `issuer, authorize_url, token_url, jwks_uri,
   redirect_uri, client_id` renseignés, When `POST /sso/configure`,
   Then `is_active=true` (la validation est implémentée).
2. Given une config SAML, When configure, Then toujours inactive (501 conservé).

### User Story 2 — authorize (P1)
**Acceptance Scenarios**:
1. Given `GET /sso/oidc/{companyId}/authorize`, When OIDC configuré,
   Then JSON `data.authorize_url` avec `response_type=code`, `client_id`,
   `redirect_uri`, `scope`, `state` (32 car.) et `nonce` (32 car.).
2. Given OIDC non configuré, When authorize, Then 422.

### User Story 3 — callback complet (P1)
**Acceptance Scenarios**:
1. Given state valide + code, When callback, Then échange du code au token
   endpoint, validation de l'id_token (signature JWKS RS256, iss, aud, exp,
   iat, nonce) et émission d'un token Sanctum pour l'employé (email).
2. Given state inconnu/consommé, When callback, Then 422 `SSO_STATE_INVALID`.
3. Given signature invalide / iss différent / token expiré, When callback,
   Then 422 (aucune session créée).
4. Given email sans employé actif, When callback, Then 422 `SSO_USER_NOT_FOUND`.
5. Given employé d'une autre entreprise, When callback, Then 422 `SSO_TENANT_MISMATCH`.
6. Given `id_token` passé directement (sans code), When callback,
   Then validation directe (sans échange de code).

## Contraintes

- Aucune dépendance externe : validation JWT en PHP pur (`openssl_verify`).
- JWKS lu depuis `jwks_uri` (cache 1 h) ; state/nonce en cache (TTL 10 min).
- SAML reste 501 (validation OneLogin séparée).
- `client_secret` jamais renvoyé au client (déjà chiffré au repos).
