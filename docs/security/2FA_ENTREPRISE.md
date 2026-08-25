# 2FA/TOTP des comptes entreprise

> Issue #5436 — étend la 2FA (jusqu'ici réservée aux SuperAdmin, `SuperAdminService`)
> à tous les comptes entreprise (employés, managers, RH, comptables).

## Parcours

1. **Enrôlement** (compte connecté) : `POST /api/v1/auth/2fa/enroll` → secret + QR
   (`otpauth://`, `TotpService`) ; `POST /api/v1/auth/2fa/confirm` avec le 1er code
   → activation + 8 **codes de récupération** (hachés en base, usage unique).
2. **Connexion** : `POST /api/v1/auth/login` avec identifiants valides + 2FA active
   → réponse `{mfa_challenge: true, mfa_challenge_token, mfa_challenge_expires_in}`
   (le token Sanctum créé par le login est immédiatement révoqué — jamais délivré).
   `POST /api/v1/auth/2fa/verify` (`challenge_token` + code TOTP **ou** code de
   récupération) → token Sanctum final (abilities tenant identiques au login).
   Le challenge est à usage unique, expire en 5 min (cache).
3. **Remember device** : `remember_device: true` au verify → cookie signé
   (HMAC app-key, 30 j, HttpOnly/Secure/SameSite) ; le login suivant avec ce
   cookie saute le challenge.
4. **Désactivation / régénération** : `POST /auth/2fa/disable` (code requis),
   `POST /auth/2fa/recovery-codes` (régénération).

## Politique tenant

Clé `CompanySetting` `mfa_required_roles` (rôles séparés par des virgules,
ex. `rh,principal,accountant`) : un compte dont le rôle (`role` ou
`manager_role`) est listé et qui n'a pas activé la 2FA est **bloqué au login**
(`403 TWO_FACTOR_REQUIRED`) — pas d'accès sans second facteur.

## Codes d'erreur (i18n ×4)

| Code | Statut | Signification |
|---|---|---|
| `TWO_FACTOR_INVALID` | 422 | Code TOTP / récupération invalide ou consommé |
| `TWO_FACTOR_REQUIRED` | 403 | Politique tenant : 2FA obligatoire non activée |
| `TWO_FACTOR_ALREADY_ENABLED` | 409 | Enrôlement/activation déjà effectués |
| `TWO_FACTOR_CHALLENGE_EXPIRED` | 401 | Challenge absent, expiré ou déjà consommé |

## Fichiers

- `api/app/Core/Auth/Infrastructure/Services/TotpService.php` (partagé),
  `TwoFactorAuthService.php` (orchestration), `Interfaces/Api/V1/TwoFactorAuthController.php`
- Migration tenant `2026_08_25_000001_add_two_fa_to_employees_table`
- `AuthService::login` → `tenant_schema` dans le résultat ; `AuthController::login` intercepte
- Tests : `api/tests/Feature/Auth/TwoFactorAuthTest.php` (7 scénarios)

## Notes

- `SuperAdminService` n'est pas modifié (rétrocompatibilité) ; le refactor vers
  `TotpService` est laissé à un nettoyage ultérieur.
- Pas de rupture API : les comptes sans 2FA conservent le login direct.
