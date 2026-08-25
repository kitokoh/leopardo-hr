# Spec — 2FA/TOTP comptes entreprise (#5436)

## Problème
La 2FA n'existe que pour SuperAdmin (`Core/Auth/Infrastructure/Services/SuperAdminService.php`). Les comptes entreprise (Employee) n'ont aucun second facteur : un mot de passe volé donne accès complet au tenant.

## Solution
1. **Service partagé `TotpService`** (Core/Auth) : factoriser secret/verify/QR depuis SuperAdminService (rétrocompat totale).
2. **Enrôlement** : `POST /api/v1/auth/2fa/enroll` (secret + QR `otpauth://`), `POST /api/v1/auth/2fa/confirm` (code → active), `POST /api/v1/auth/2fa/disable` (re-vérifie).
3. **Login challenge** : `POST /api/v1/auth/login` renvoie `mfa_challenge: true` + `challenge_token` (JWT court/cache, exp 5 min) si l'utilisateur a 2FA ; `POST /api/v1/auth/2fa/verify` (code TOTP + challenge_token → token Sanctum final). `TWO_FACTOR_REQUIRED` si mfa_required_roles et pas d'enrôlement.
4. **Recovery codes** : 8 codes hachés à l'enrôlement, usage unique, régénération, perte → reset par manager RH (endpoint dédié RBAC).
5. **Remember device** : cookie signé (30 j, HttpOnly/Secure/SameSite=Lax) — vérifié pendant le challenge ; révocable.
6. **Politique tenant** : `mfa_required_roles` (settings), appliquée au login (blocage avec code dédié).
7. **Gates** : i18n ×4 (clés `TWO_FACTOR_*`, parité), OpenAPI + SDK, tests Feature complets, CHANGELOG ×2, note SCENARIOS.

## DoD
- Test : enroll → login → challenge → verify → token ; code invalide → 403 `TWO_FACTOR_INVALID` ; recovery à usage unique ; remember device ; politique rôles ; désactivation ; SuperAdmin inchangé.
