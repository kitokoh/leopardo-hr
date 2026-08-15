# Mini-spécification — Issue #3242

## Objectif

Empêcher un endpoint OIDC configuré par un tenant de transformer le serveur API en proxy vers les réseaux privés, réservés ou de métadonnées.

## Périmètre

Les URLs `authorize_url`, `token_url` et `jwks_uri` sont contrôlées :

1. à l’écriture de la configuration SSO avec la règle `NotPrivateUrl` ;
2. à la lecture avant construction de l’URL d’autorisation et échange de token ;
3. immédiatement avant le téléchargement JWKS.

## Contrat de sécurité

Seuls les hôtes HTTPS publics et résolvables sont acceptés. Les hôtes localhost, RFC1918, loopback, link-local, réservés, non résolvables et les IP littérales privées sont refusés. Le contrôle runtime protège aussi contre une modification directe de la base ou un rebinding DNS entre l’enregistrement et le fetch.

## Critères d’acceptation

1. La configuration OIDC refuse les trois endpoints avec `NotPrivateUrl` lorsqu’ils ciblent une adresse privée ou non résolvable.
2. `OidcFlowService` revalide les trois hôtes avant tout flux.
3. `OidcIdTokenValidator` revalide `jwks_uri` juste avant `Http::get`.
4. Aucun appel HTTP OIDC ne précède le garde SSRF.
5. Les fichiers modifiés passent `php -l` et `git diff --check`.

## Plan de retour arrière

Réversion du commit ; aucun schéma ni secret existant n’est supprimé.

## Trace Spec Kit

Issue : #3242  
Branche : `fix/3242-oidc-ssrf-guard`  
Date : 2026-08-15
