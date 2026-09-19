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
- `openssl` et `coreutils` (vérification d'intégrité et de signature du manifeste).
- Ports ouverts : `80` (redirection HTTPS + /health), `443` (proxy TLS), `7878` (API locale, TLS), `7879` (UI locale, TLS).
- Un nœud Edge enregistré dans le dashboard Leopardo → **Paramètres → Edge Nodes** (fournit `node-id` et `token`).

## Installation rapide (commande canonique, issue #3770)

Les assets d'installation sont servis par l'API Leopardo (Render) :

```bash
curl -fsSL https://gestionemployerbackend.onrender.com/api/v1/edge/install.sh -o install.sh
EDGE_TOKEN="<EDGE_TOKEN>" sudo --preserve-env=EDGE_TOKEN bash install.sh --node-id <UUID_DU_NODE>
```

Le jeton peut aussi être fourni par fichier (`--token-file <fichier>`) ou par
saisie interactive masquée (le script le demande si rien n'est fourni).
**`--token <TOKEN>` n'est plus accepté** (#7653) : un secret en argument de
ligne de commande est visible dans `ps`, l'historique shell et les logs sudo.

Options :

| Option | Défaut | Rôle |
|--------|--------|------|
| `--node-id` | requis | UUID du nœud Edge |
| `--token-file` | — | Fichier contenant le jeton Edge (alternative à `EDGE_TOKEN`) |
| `--cloud` | `https://gestionemployerbackend.onrender.com` (ou env `CLOUD_API_URL`) | API cloud (Render) — override pour un environnement de recette |
| `--interval` | `15` | Intervalle de synchronisation (minutes) |
| `--domain` | `leopardo.local` (ou env `EDGE_DOMAIN`) | Nom de site TLS émis par la CA interne |
| `--pubkey` | — | Clé publique RS256 épinglée hors bande (vérification du manifeste) |
| `--insecure-manifest` | — | Désactive la vérification de signature du manifeste (**développement uniquement**) |

Variables d'environnement : `EDGE_TOKEN` (jeton), `CLOUD_API_URL`,
`EDGE_DOMAIN`, `EDGE_MANIFEST_PUBKEY_FILE` (équivalent de `--pubkey`).

Obtenez le `node-id` et le `token` depuis votre dashboard Leopardo → **Paramètres → Edge Nodes**.

### Vérification d'intégrité et signature du manifeste (#7653)

Le script télécharge `docker-compose.yml` et `Caddyfile.edge` depuis l'API
(`/api/v1/edge/download/*`) et vérifie chaque fichier contre le manifeste
`/api/v1/edge/download/sha256.txt` avant toute écriture. Le manifeste est
lui-même **signé RS256** (`/api/v1/edge/download/sha256.txt.sig`, clé privée
de licence Edge côté cloud) : install.sh vérifie la signature avec la clé
publique **avant** d'utiliser le manifeste — fail-closed dans les deux cas,
aucun fichier non vérifié n'est installé.

Par défaut la clé publique est téléchargée depuis le cloud (trust-on-first-use).
Pour un ancrage de confiance indépendant du cloud, épinglez la clé hors bande :
`--pubkey <fichier>` (récupérée depuis le dashboard, transmise par un canal sûr).

### TLS sur le LAN (#7653)

Le trafic LAN (pointage, PII, biométrie ZKTeco) est chiffré **par défaut** :
Caddy émet les certificats via sa **CA interne** (`tls internal`) pour
`EDGE_DOMAIN` et l'IP LAN détectée à l'installation (`EDGE_LAN_IP` dans
`/opt/leopardo-edge/.env`). Le port `:80` ne sert plus que la redirection
HTTPS (+ `/health`), et les ports historiques `7878`/`7879` restent joignables
mais **en TLS via le proxy** (les conteneurs `edge-api`/`edge-ui` ne publient
plus aucun port en clair sur l'hôte).

Pour que les kiosques/postes clients acceptent les certificats, installez la
**CA racine** de l'Edge (elle vit dans le volume `caddy_data`) :

```bash
cd /opt/leopardo-edge
docker compose cp edge-proxy:/data/caddy/pki/authorities/local/root.crt ./leopardo-edge-ca.crt
```

- **Linux (kiosque)** : `sudo cp leopardo-edge-ca.crt /usr/local/share/ca-certificates/ && sudo update-ca-certificates`
- **Windows** : `certutil -addstore -f Root leopardo-edge-ca.crt`
- **Android (ZKTeco/kiosques)** : Paramètres → Sécurité → Installer un certificat → Certificat CA.

La CA est persistée dans le volume `caddy_data` : elle survit aux redémarrages
et mises à jour (ne supprimez pas ce volume, sinon les kiosques devront
réinstaller la nouvelle racine).

## Services

| Service         | Port  | Rôle                          |
|-----------------|-------|-------------------------------|
| `edge-api`      | —     | API Laravel locale (via proxy, TLS `:7878`) |
| `edge-ui`       | —     | Interface web locale (via proxy, TLS `:7879`) |
| `edge-sync`     | —     | Daemon de synchronisation     |
| `edge-proxy`    | 80/443/7878/7879 | Reverse proxy TLS (https://leopardo.local) |

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

- TLS interne par défaut sur le LAN (CA locale Caddy, #7653) — aucun trafic applicatif en clair
- Manifeste d'intégrité `sha256.txt` signé RS256, vérifié fail-closed par `install.sh` (#7653)
- Jeton d'enrôlement jamais en argv : env `EDGE_TOKEN`, `--token-file` ou saisie masquée (#7653)
- Licence JWT (RS256) signée par Leopardo Cloud
- Expiration automatique (30 jours par défaut)
- Renouvellement automatique si Internet disponible
- Token Edge unique par node (révocable depuis le Cloud)

## Stack Edge — référence unique (issue #6700)

- Le stack Edge s'exploite via **`edge/docker-compose.yml`** (canonique) — pas de
  duplication avec le compose racine (dev full-stack) ni `api/docker-compose.yml`
  (Sail legacy).
- Clé publique de licence : **`edge/keys/edge_license_public.pem`** (source
  unique ; `edge/license.pub` supprimé en #6698). Le mount compose pointe dessus.
- Build des images : `edge/publish.sh` (edge-api) ; l'UI offline vit dans
  `front/web-offline` (surface edge-ui — voir `docs/architecture/OFFLINE_SURFACES.md`).
