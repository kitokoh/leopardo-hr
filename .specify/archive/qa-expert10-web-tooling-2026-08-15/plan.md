# Implementation Plan: Web/tooling

1. next.config / headers() vitrine : passer la CSP en enforce après audit des violations report-only ; idem `_headers` Cloudflare Pages admin.
2. FrankenPHP/Caddy : `expose_php = Off` (php.ini prod) ou suppression du header au middleware.
3. postman : supprimer les doublons login ; script jq de validation (méthode+url uniques) ajouté à dev-hub/tools.
