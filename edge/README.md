# Leopardo Edge

Composant local installé chez le client pour le fonctionnement **offline-first**.

## Architecture

```
[Mobiles employés]
       ↓ WiFi local
[Leopardo Edge]  ←→  [Leopardo Cloud]
       ↓
[SQLite local]
```

## Prérequis

- **Docker** (Engine + Compose) sur la machine cible (Linux x86_64).
- Ports ouverts : `80` (proxy), `7878` (API locale), `7879` (UI locale).
- Un nœud Edge enregistré dans le dashboard Leopardo → **Paramètres → Edge Nodes** (fournit `node-id` et `token`).

## Installation rapide (commande canonique, issue #3770)

Les assets d'installation sont servis par l'API Leopardo (Render) :

```bash
curl -fsSL https://gestionemployerbackend.onrender.com/api/v1/edge/install.sh \
  | sudo bash -s -- --node-id <UUID_DU_NODE> --token <EDGE_TOKEN>
```

ou, une fois le script téléchargé :

```bash
sudo bash install.sh \
  --node-id <UUID_DU_NODE> \
  --token   <EDGE_TOKEN>
```

Options :

| Option | Défaut | Rôle |
|--------|--------|------|
| `--node-id` | requis | UUID du nœud Edge |
| `--token` | requis | Jeton Edge (bearer, conservé en `600` dans `/opt/leopardo-edge/.env`) |
| `--cloud` | `https://gestionemployerbackend.onrender.com` | API cloud (Render) — override pour un environnement de recette |
| `--interval` | `15` | Intervalle de synchronisation (minutes) |

Obtenez le `node-id` et le `token` depuis votre dashboard Leopardo → **Paramètres → Edge Nodes**.

### Vérification d'intégrité

Le script télécharge `docker-compose.yml` et `Caddyfile.edge` depuis l'API
(`/api/v1/edge/download/*`) et vérifie chaque fichier contre le manifeste
`/api/v1/edge/download/sha256.txt` avant toute écriture. En cas d'échec de
hash, l'installation s'arrête (fail-closed) : aucun fichier non vérifié n'est
installé. Le docker-compose téléchargé pointe par défaut vers
`https://gestionemployerbackend.onrender.com` (API cloud Render).

## Services

| Service         | Port  | Rôle                          |
|-----------------|-------|-------------------------------|
| `edge-api`      | 7878  | API Laravel locale            |
| `edge-ui`       | 7879  | Interface web locale          |
| `edge-sync`     | —     | Daemon de synchronisation     |
| `edge-proxy`    | 80    | Reverse proxy (leopardo.local)|

## Modes de fonctionnement

| Mode       | Internet requis | Sync Cloud |
|------------|-----------------|------------|
| `cloud`    | Oui             | Temps réel |
| `hybrid`   | Optionnel        | Différée   |
| `offline`  | Non             | Jamais     |

## Commandes utiles

```bash
# Voir les logs
docker compose logs -f

# Forcer une synchronisation
docker compose exec edge-api php artisan edge:sync-daemon

# Statut des services
docker compose ps
```

## Dockerfiles

This directory has three Dockerfiles with distinct roles — do not assume they are interchangeable:

| File                  | Used by                          | Purpose                                                        |
|-----------------------|-----------------------------------|-----------------------------------------------------------------|
| `Dockerfile.edge`     | edge-api + edge-sync                | Image PHP 8.4 Alpine + SQLite — buildé par `docker compose up --build` (#6604) |
| `front/web-offline/Dockerfile` | edge-ui            | PWA Next.js static export — buildé localement par le compose (#6604) |
| `Dockerfile.publish`  | `edge/publish.sh`                 | Production image published to Docker Hub as `leopardo/edge-api` |
| `Dockerfile`          | *(not currently wired in)*        | Standalone FrankenPHP + embedded PWA reference image; build manually if needed |

## Sécurité

- Licence JWT (RS256) signée par Leopardo Cloud
- Expiration automatique (30 jours par défaut)
- Renouvellement automatique si Internet disponible
- Token Edge unique par node (révocable depuis le Cloud)
