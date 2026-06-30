# Leopardo Edge Node

Nœud Edge offline pour Leopardo RH — permet les pointages en **mode déconnecté**
with sync automatique dès le retour de la connexion internet.

## Architecture

```
Internet         LAN / Site client
──────────       ───────────────────────────────────────
Cloud API   ←→   Edge Node (Docker)  ←→  Mobile apps
(Laravel)        ├── Laravel         ←→  PWA web (leopardo.local)
                 ├── SQLite          ←→  ZKTeco kiosk
                 ├── Caddy (80/443)
                 └── Scheduler (cron)
```

## Démarrage rapide

### Option 1 — Script d'installation automatique

```bash
curl -fsSL https://api.leopardo-rh.com/edge/install.sh | bash
```

### Option 2 — Manuel

```bash
# 1. Cloner ou télécharger docker-compose.yml
curl -fsSL https://api.leopardo-rh.com/edge/download/docker-compose.yml -o docker-compose.yml
curl -fsSL https://api.leopardo-rh.com/edge/download/env-example -o .env.edge

# 2. Générer les clés RS256
mkdir -p keys
openssl genrsa -out keys/edge_license_private.pem 2048
openssl rsa -in keys/edge_license_private.pem -pubout -out keys/edge_license_public.pem

# 3. Remplir .env.edge (EDGE_NODE_ID, EDGE_TOKEN, CLOUD_API_URL, etc.)
nano .env.edge

# 4. Démarrer
docker compose --env-file .env.edge up -d

# Vérifier
docker compose logs -f
curl http://leopardo.local/api/v1/edge/health
```

## Accès

| URL | Description |
|-----|-------------|
| `http://leopardo.local` | Interface PWA web offline |
| `http://leopardo.local/api/v1/edge/health` | Health check |
| `http://leopardo.local/api/v1/edge/heartbeat` | Heartbeat → Cloud |

## Variables d'environnement clés

| Variable | Description | Obligatoire |
|----------|-------------|-------------|
| `EDGE_NODE_ID` | ID unique du nœud (fourni par le Cloud) | ✅ |
| `EDGE_TOKEN` | JWT d'authentification nœud | ✅ |
| `CLOUD_API_URL` | URL du Cloud parent | ✅ |
| `EDGE_LICENSE_PRIVATE_KEY` | Clé privée RS256 (PEM) | ✅ |
| `EDGE_LICENSE_PUBLIC_KEY` | Clé publique RS256 (PEM) | ✅ |
| `EDGE_LICENSE_TTL_DAYS` | Durée validité licence (jours) | défaut: 30 |
| `EDGE_SILENCE_THRESHOLD_MINUTES` | Seuil alerte silence (min) | défaut: 30 |
| `SERVER_NAME` | Nom DNS Caddy | défaut: `leopardo.local, :80` |

## Build image Docker

```bash
# Depuis la racine du repo
docker build \
  -t leopardo/edge-api:1.0.0 \
  -f edge/Dockerfile \
  .

# Push Docker Hub
docker push leopardo/edge-api:1.0.0
```

## Migrations Edge (SQLite)

Les migrations Edge sont dans `api/database/migrations/edge/` et sont
exécutées automatiquement au démarrage du conteneur via l'entrypoint.

## Monitoring

Le cron interne surveille les nœuds silencieux :

```bash
# Manuel
docker exec leopardo-edge php artisan edge:detect-silent-nodes

# Dry-run (ne notifie pas)
docker exec leopardo-edge php artisan edge:detect-silent-nodes --dry-run

# Seuil custom
docker exec leopardo-edge php artisan edge:detect-silent-nodes --threshold=15
```
