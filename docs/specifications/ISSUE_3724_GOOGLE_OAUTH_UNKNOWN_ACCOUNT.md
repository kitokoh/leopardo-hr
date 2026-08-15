# ISSUE #3724 — Google OAuth : compte inconnu auto-provisionné sans invitation

> Spec-kit — audit 360° 2026-08-15, constat A-01. Branche : `fix/3724-google-oauth-unknown-account-401`.

## Problème

`AuthController::handleGoogleCallback` crée silencieusement un compte `ordinary`
**actif** avec token Sanctum valide quand l'email Google ne correspond à aucun
employé — sans invitation (#2617) ni feature gate. Ces comptes sans compagnie
passent `TenantMiddleware` puis échouent en 500 sur les endpoints tenant
(`helpers.php`) ou produisent des requêtes non scopées. Incohérent avec
`handleGoogleToken` qui répond 401 sur email inconnu.

## Décision

Le flux d'invitation (#2617, `RegisterAction`) crée **toujours** une ligne
`employees` avant l'inscription (`user_invitations.employee_id NOT NULL`) : un
invité valide possède donc déjà un enregistrement employé et n'emprunte jamais
le chemin de création du callback. Le chemin `Employee::create` du callback ne
sert que les environnements de démo. Il est donc placé derrière le feature
gate existant `DEMO_MODE_ENABLED` (`config('app.demo_mode_enabled')`).

## Comportement cible

| Cas | Avant | Après |
|-----|-------|-------|
| Email inconnu, `DEMO_MODE_ENABLED=false` (défaut, prod) | 201 + compte actif | **401 `UNKNOWN_ACCOUNT`**, aucune création |
| Email inconnu, `DEMO_MODE_ENABLED=true` (démo/staging) | 201 + compte actif | 201 + compte actif (inchangé) |
| Employé existant actif (invité provisionné) | 200 + token | 200 + token (inchangé) |
| Employé suspendu / société suspendue | 403 | 403 (inchangé) |
| `state` invalide | 400 | 400 (inchangé) |

## Critères d'acceptation

1. Email inconnu sans feature gate → 401 JSON `{error: UNKNOWN_ACCOUNT}` et
   **aucune** ligne `employees` créée (test de régression).
2. Email inconnu avec `demo_mode_enabled=true` → 201 (test mis à jour).
3. Employé existant actif → 200 (test existant conservé).
4. PHPStan strict level 8 vert, Pint vert.
5. Parité de surface d'erreur avec `handleGoogleToken` (401 JSON, pas de fuite
   d'exception — cf. #3725).

## Hors périmètre

- Pas de nouveau paramètre `invitation_token` sur le callback : l'invitation
  crée déjà la ligne employé en amont (voir Décision).
- `handleGoogleToken` (mobile) déjà conforme : 401 `EMPLOYEE_NOT_FOUND`.
