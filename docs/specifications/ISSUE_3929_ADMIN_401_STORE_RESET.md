# Issue #3929 — Admin : un 401 en cours de session fige la SPA

**Issue**: [#3929](https://github.com/kitokoh/leopardo-hr/issues/3929)
**Branche**: `fix/3929-admin-401-store-reset`
**Statut**: Implémenté (PR en revue)

## Constat (audit 360°, 2026-08-15)

`src/services/api.js:183-197` : l'intercepteur 401 appelle `removeAuthToken()`
(vide `sessionStorage` + headers axios) mais ne touche **pas** aux refs du
store Pinia `stores/auth.js:26-30` — `token.value`/`user.value` restent
positionnés, donc `isAuthenticated` reste `true`. Il pousse ensuite vers
`/login`, mais le guard `router/index.js:374`
(`to.name === 'login' && authStore.isAuthenticated → next('/')`) rebondit
systématiquement vers `/`. Le commentaire api.js:191 (« le router redirige
déjà vers /login via le guard ») est faux : le guard ne peut pas, le store
est périmé.

## Impact

Expiration de token, changement de mot de passe ou révocation → boucle de
toasts « Session expirée » + navigation annulée ; l'app est inutilisable
jusqu'au hard reload.

## Correctif

1. `stores/auth.js` : nouvelle méthode **`clearSession()`** — remise à zéro
   synchrone (refs `token`/`user` → null, storage, header axios). `logout()`
   la réutilise dans son `finally`.
2. `services/api.js` : le handler 401 reset le store via import dynamique
   (`import('@/stores/auth')` — évite la dépendance circulaire statique
   store→api) avant la navigation SPA vers `/login`.

## Critères d'acceptation

1. Après un 401, `isAuthenticated` repasse à `false` sans reload et `/login`
   est accessible (pas de rebond vers `/`).
2. `npm run lint` : 0 erreur ; `npm run build` : vert.
3. Entrée CHANGELOG (`### Fixed`).
