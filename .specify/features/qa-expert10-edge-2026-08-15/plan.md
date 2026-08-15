# Implementation Plan: Edge install

1. `api/Dockerfile.prod` : copier `edge/install.sh`, `edge/docker-compose.yml`, `edge/keys/edge_license_public.pem` dans l'image (chemins attendus par `EdgeDownloadController`).
2. `edge/publish.sh` : ajouter build/tag/push de `edge-ui` (même version que edge-api).
3. Remplacer les domaines en dur par une constante doc + config (`api.leopardo-rh.com` partout ou variable unique).
4. Nouveau `Caddyfile.standalone` (servir /app/public/edge-web + reverse_proxy 127.0.0.1) utilisé par `edge/Dockerfile`.
5. Entrypoint : écrire APP_KEY générée dans `/data/.app_key` et la recharger aux boots suivants.
6. nginx.edge.conf : proxy `/api/edge/health` vers la vraie sonde ; healthchecks compose sur edge-ui/edge-sync/edge-proxy.
7. Test CI fumée : les 2 URLs de download répondent 200 sur l'image buildée.
