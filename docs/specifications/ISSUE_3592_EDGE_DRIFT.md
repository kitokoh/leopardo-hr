# Mini-spécification — Issue #3592

## Objectif

4 constats edge, 4 correctifs : source unique du domaine cloud, image standalone utilisable, APP_KEY persistée, healthchecks réels.

## Constat & décisions

1. **Drift domaine cloud** — `api/config/edge.php` + `api/.env.example` (`api.leopardo.app`) vs `edge/install.sh` + `edge/docker-compose.yml` (`api.leopardo-rh.com`, NXDOMAIN #3452). Le backend réellement déployé est `gestionemployerbackend.onrender.com` (render.yaml #3531, config/cors.php, configs kiosk). → **Source unique** : `CLOUD_API_URL` défaut `https://gestionemployerbackend.onrender.com` partout (le client EdgeSync ajoute `/api/v1/edge-node/...` lui-même, `EdgeDaemonSyncClient`).
2. **Image standalone 502** — `edge/Dockerfile` copiait `Caddyfile.edge` qui proxy vers `edge-ui:3000`/`edge-api:80` (hôtes inexistants dans le conteneur unique). → Nouveau `edge/Caddyfile.standalone` : PWA (`/app/public/edge-web/`) en statique avec fallback SPA, `/api/*` en `php_server` FrankenPHP, `/health` OK. `edge/Dockerfile` pointe dessus. `Caddyfile.edge` reste le proxy du stack compose (edge-proxy).
3. **APP_KEY éphémère** — `docker-entrypoint.edge.sh` générait une clé à chaque boot sans la persister. → Persistance dans `/data/.env` au premier boot (création + `printf 'APP_KEY=...'`), rechargement au redémarrage (source du fichier si APP_KEY vide).
4. **Healthcheck superficiel** — `nginx.edge.conf` renvoyait un 200 statique sur `/api/edge/health` (chemin divergent). → Location `/api/v1/edge/health` (route réelle `EdgeController::health`, throttle 300/min) avec `try_files` vers `index.php` : la sonde exécute PHP + SQLite.

## Critères d'acceptation

1. `rg 'api\.leopardo\.app|api\.leopardo-rh\.com' api edge` → 0 résultat hors commentaires.
2. `caddy validate` OK sur `Caddyfile.standalone` et `Caddyfile.edge`.
3. Boot standalone : `APP_KEY` générée une fois puis rechargée depuis `/data/.env` (2e boot sans régénération).
4. `curl http://<edge>/api/v1/edge/health` → réponse JSON du contrôleur (200/503 selon SQLite), pas un 200 statique.
5. `sh -n edge/docker-entrypoint.edge.sh edge/install.sh` OK ; `git diff --check` OK.

## Plan de retour arrière

Réversion du commit ; les 4 changements sont additifs/alignants (aucune donnée supprimée ; une clé déjà générée en dur n'est pas affectée).
