# Issue #3941 — Google Sign-In : vérification serveur de l'identité Google

**Issue**: [#3941](https://github.com/kitokoh/leopardo-hr/issues/3941)
**Branche**: `fix/3941-google-signin-token-verification`
**Statut**: Implémenté (PR en revue)

## Constat (audit 360°, 2026-08-15)

`POST /api/v1/user/google-signin` (route publique, throttle seul) accepte
`google_id` + `email` fournis par le client et émet un token Sanctum sans
**aucune** vérification de l'identité Google :

```php
$user = User::where('google_id', $googleId)->first();
if (! $user) { $user = User::where('email', $email)->first(); }
...
$result = $this->issueToken($user, 'Google Sign-In');
```

Un attaquant postant `{google_id: "x", email: "victime@…"}` reçoit un token
valide pour le compte de la victime **et** écrase son `google_id` (verrouillage
de la connexion Google légitime). Aucune preuve cryptographique n'est exigée —
à la différence de `AuthController::handleGoogleToken` (Socialite
`userFromToken`) et du flux SSO OIDC (`OidcIdTokenValidator`, signature JWKS).

## Correctif

1. **Nouveau `GoogleIdentityVerifier`** (`api/app/Core/Auth/Infrastructure/Services/`)
   — valide un ID token Google via `OidcIdTokenValidator` :
   - `iss` = `https://accounts.google.com`
   - `jwks_uri` = `https://www.googleapis.com/oauth2/v3/certs`
   - signature RS256 vérifiée contre le JWKS Google (cache 1 h)
   - `aud` ∈ liste configurable (`GOOGLE_CLIENT_ID`, `GOOGLE_WEB_CLIENT_ID`,
     `GOOGLE_ANDROID_CLIENT_ID`, `GOOGLE_IOS_CLIENT_ID`) ; si aucune n'est
     configurée (dev/démo), la signature + `iss` + `exp` restent obligatoires
   - `email_verified === true` obligatoire (fail-closed)
   - retourne les claims vérifiés (`sub`, `email`, `name`, `picture`)
2. **`OidcIdTokenValidator`** : paramètre optionnel `audiences` (rétro-compatible ;
   comportement inchangé si absent).
3. **`UserAuthService::googleSignIn(string $idToken, ?string $deviceName)`** :
   l'identité (google_id, email, nom, avatar) est **dérivée du token vérifié**,
   plus jamais des champs client. Le lookup/création et les gardes de statut
   (#2618) restent identiques.
4. **`UserAuthController::googleSignIn`** : `id_token` requis ; toute erreur de
   vérification → `GoogleTokenInvalidException` (401, `GOOGLE_TOKEN_INVALID`)
   via le renderer global `DomainException`.
5. **Mobile (3 apps)** : `user_login_screen.dart` envoie
   `idToken: account.idToken` (du plugin `google_sign_in`) → provider →
   repository. Les champs `google_id`/`email` ne sont plus transmis.

## Critères d'acceptation

1. `POST /user/google-signin` sans `id_token` → 422.
2. `id_token` forgé / mauvaise signature / `iss` non Google / expiré /
   `email_verified=false` → 401 `GOOGLE_TOKEN_INVALID`, aucun token émis.
3. `id_token` valide signé Google → token émis ; l'email/le nom du compte
   proviennent des claims vérifiés (un `email` client différent est ignoré).
4. Compte suspendu → 403 `ACCOUNT_SUSPENDED` (régression #2618 préservée).
5. Tests : `UserAuthServiceTest` (unitaire, JWKS fakyé + tokens signés),
   `UserAuthGoogleSignInSecurityTest` (feature, scénarios 1-4).
6. `phpstan` strict vert, Pint vert, entrée CHANGELOG (`### Fixed`).
