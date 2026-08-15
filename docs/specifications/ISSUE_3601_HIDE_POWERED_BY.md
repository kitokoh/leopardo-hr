# Mini-spécification — Issue #3601 (volet divulgation de version)

## Objectif

Retirer les headers `x-powered-by: PHP/8.4.x` et `x-render-origin-server: FrankenPHP Caddy` exposés par l'API (divulgation de version facilitant le ciblage de CVE).

## Périmètre

Ce commit traite le volet **divulgation de version** (point 2 de l'issue). Le volet CSP report-only → enforce (point 1) nécessite des nonces/hashes Next.js + Vite et un plan de déploiement par étapes : il reste ouvert dans l'issue (workstream séparé, risque de casse élevé).

## Décision

`api/Caddyfile` (utilisé par l'image Docker API, `COPY api/Caddyfile /etc/caddy/Caddyfile`) : directives `header -Server` et `header -X-Powered-By` dans le bloc du site — Caddy retire ces headers au niveau réponse, y compris ceux posés par PHP/FrankenPHP.

## Critères d'acceptation

1. `GET /api/v1/health` en prod ne renvoie plus `x-powered-by` ni `Server: Caddy/FrankenPHP`.
2. Aucun autre header de sécurité n'est affecté (HSTS, X-Frame-Options, nosniff, referrer-policy conservés).
3. `caddy validate` OK sur la config (CI).

## Plan de retour arrière

Réversion du commit ; aucun changement applicatif.
