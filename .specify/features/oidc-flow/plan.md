# Plan technique — Flux OIDC

## Analyse

`SSOService` gère la config (table publique `company_sso_configs`, JSONB
`config`, secrets chiffrés). Les callbacks 501 et la config forcée inactive
étaient des gardes de l'audit #1694 tant que la validation n'existait pas.

## Architecture

- **`SSOProviderConfig`** : + champs OIDC (`issuer`, `authorize_url`,
  `token_url`, `jwks_uri`, `redirect_uri`, `scopes`) + `isOidcFlowReady()`.
- **`OidcIdTokenValidator`** (nouveau) : parse JWT base64url, allowlist
  RS256/384/512, `openssl_verify` contre le JWKS (kid → n/e → SPKI DER, ou
  x5c), claims iss/aud/exp/iat/nonce avec skew 60 s. JWKS en cache 1 h.
- **`OidcFlowService`** (nouveau) : `buildAuthorizeUrl()` (state+nonce en
  cache 10 min, URL IdP) et `complete()` (state → échange code → validation
  → email → `AuthService::loginViaEmail()` → token Sanctum). Vérifie que
  l'employé appartient bien à la companyId du callback.
- **`AuthService::loginViaEmail()`** (nouveau) : résolution cross-schema
  `public.user_lookups` + gardes (statut compte/employé) + création token
  avec abilities tenant — même socle que `login()` sans mot de passe.
- **`SSOService`** : `configureSSO` active la config OIDC quand le flux est
  complet ; `handleOIDCCallback` délègue à `OidcFlowService` (backward-compat).
- **`SSOController`** : `oidcAuthorize` (JSON `data.authorize_url`) ;
  `oidcCallback` réécrit (422 avec codes d'erreur `SSO_*`).
- **Routes** : `GET /sso/oidc/{companyId}/authorize`.

## Décisions

1. Aucune nouvelle dépendance Composer (le repo n'a pas de lib JWT) — DER SPKI
   construit à la main (RSAPublicKey) puis `openssl_verify`.
2. Pas d'injection de `OidcFlowService` dans `SSOService` (cycle DI) : le
   contrôleur injecte directement `OidcFlowService`.
3. `client_secret` n'est passé qu'à l'échange de code (jamais en réponse).
4. SAML hors périmètre (501 conservé) — issue séparée.

## Tests

- `api/tests/Feature/SSOOidcFlowTest.php` : config active, authorize (URL +
  state/nonce), flux complet avec échange de code (Http::fake + vraie paire
  RSA), id_token direct, state invalide, signature invalide, iss différent,
  token expiré, employé inconnu, employé d'une autre entreprise, non configuré.
