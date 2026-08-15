#!/bin/bash
# ============================================================
# Leopardo Edge — Installation Script
# Usage: sudo bash install.sh --node-id <UUID> --token <TOKEN>
# ============================================================
set -euo pipefail

NODE_ID=""
TOKEN=""
CLOUD_URL="https://gestionemployerbackend.onrender.com"
SYNC_INTERVAL=15

# Parse args
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --node-id) NODE_ID="$2"; shift ;;
        --token)   TOKEN="$2"; shift ;;
        --cloud)   CLOUD_URL="$2"; shift ;;
        --interval) SYNC_INTERVAL="$2"; shift ;;
        *) echo "Unknown param: $1"; exit 1 ;;
    esac
    shift
done

if [[ -z "$NODE_ID" || -z "$TOKEN" ]]; then
    echo "❌ --node-id and --token are required"
    exit 1
fi

echo "🐆 Installing Leopardo Edge..."
echo "   Node ID : $NODE_ID"
echo "   Cloud   : $CLOUD_URL"

# Check Docker
if ! command -v docker &> /dev/null; then
    echo "📦 Installing Docker..."
    # Issue #3964 : plus de pipe direct `curl | sh` — un échec de
    # téléchargement (réseau coupé, MITM, réponse tronquée) interprétait un
    # script partiel en root. On télécharge, on vérifie, puis on exécute.
    DOCKER_INSTALL_SCRIPT="$(mktemp)"
    if ! curl -fsSL https://get.docker.com -o "$DOCKER_INSTALL_SCRIPT"; then
        echo "❌ Échec du téléchargement du script d'installation Docker depuis https://get.docker.com" >&2
        rm -f "$DOCKER_INSTALL_SCRIPT"
        exit 1
    fi
    if [[ ! -s "$DOCKER_INSTALL_SCRIPT" ]] || ! head -1 "$DOCKER_INSTALL_SCRIPT" | grep -q '^#!/bin/sh'; then
        echo "❌ Le script d'installation Docker téléchargé est invalide ou vide" >&2
        rm -f "$DOCKER_INSTALL_SCRIPT"
        exit 1
    fi
    sh "$DOCKER_INSTALL_SCRIPT"
    rm -f "$DOCKER_INSTALL_SCRIPT"
fi

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "📦 Installing Docker Compose..."
    apt-get install -y docker-compose-plugin 2>/dev/null || \
    curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 \
        -o /usr/local/bin/docker-compose && chmod +x /usr/local/bin/docker-compose
fi

# Create install dir
INSTALL_DIR="/opt/leopardo-edge"
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# ------------------------------------------------------------------
# Téléchargement des assets avec vérification d'intégrité
# (issues #3591, #3770, #3529). Chaque fichier est vérifié contre le
# manifeste sha256.txt servi par l'API — fail-closed : aucune écriture
# si un hash ne correspond pas.
# ------------------------------------------------------------------
if ! command -v sha256sum &> /dev/null; then
    echo "❌ sha256sum est requis (paquet coreutils)." >&2
    exit 1
fi

verify_download() {
    # $1 = nom du fichier attendu dans le manifeste sha256.txt (JSON, #4007)
    # $2 = fichier local téléchargé
    # Le manifeste servi par l'API est un objet JSON :
    #   {"sha256":["<hash>  <fichier>", ...], "algorithm":"sha256"}
    # Extraction coreutils-only : la valeur de la clé est `"<64hex>  <fichier>"`.
    local expected_hash
    expected_hash=$(grep -oE '"[0-9a-f]{64}  [^"]*"' sha256.txt | grep -F " $1\"" | head -n1 | sed -E 's/^"([0-9a-f]{64})  .*/\1/')
    if [[ -z "$expected_hash" ]]; then
        echo "❌ $1 absent du manifeste d'intégrité servi par $CLOUD_URL." >&2
        exit 1
    fi
    local actual_hash
    actual_hash=$(sha256sum "$2" | awk '{ print $1 }')
    if [[ "$actual_hash" != "$expected_hash" ]]; then
        echo "❌ Vérification d'intégrité échouée pour $1 (attendu $expected_hash, obtenu $actual_hash)." >&2
        echo "   Abandon — aucun fichier non vérifié n'est installé." >&2
        exit 1
    fi
}

# Manifeste d'intégrité (doit être disponible avant tout téléchargement).
curl -fsSL "$CLOUD_URL/api/v1/edge/download/sha256.txt" -o sha256.txt
if [[ ! -s sha256.txt ]] || ! grep -q 'install.sh' sha256.txt; then
    echo "❌ Manifeste d'intégrité indisponible depuis $CLOUD_URL — installation annulée." >&2
    exit 1
fi

# docker-compose.yml (serveur de confiance, vérifié par hash)
curl -fsSL "$CLOUD_URL/api/v1/edge/download/docker-compose.yml" -o docker-compose.yml
if [[ ! -s docker-compose.yml ]]; then
    echo "Echec du telechargement du docker-compose depuis $CLOUD_URL" >&2
    exit 1
fi
verify_download "docker-compose.yml" "docker-compose.yml"

# Caddyfile.edge (bind-mounté par edge-proxy ; vérifié par hash + contenu)
curl -fsSL "$CLOUD_URL/api/v1/edge/download/Caddyfile.edge" -o Caddyfile.edge
if [[ ! -s Caddyfile.edge ]] || ! grep -q 'reverse_proxy edge-api:80' Caddyfile.edge || ! grep -q 'reverse_proxy edge-ui:3000' Caddyfile.edge; then
    echo "Echec du telechargement ou de la verification de Caddyfile.edge depuis $CLOUD_URL" >&2
    exit 1
fi
verify_download "Caddyfile.edge" "Caddyfile.edge"

# Generate .env
APP_KEY=$(openssl rand -base64 32)
cat > .env <<EOF
EDGE_NODE_ID=$NODE_ID
EDGE_TOKEN=$TOKEN
EDGE_APP_KEY=base64:$APP_KEY
CLOUD_API_URL=$CLOUD_URL
SYNC_INTERVAL=$SYNC_INTERVAL
FORCE_OFFLINE=false
EOF
# Issue #2751 — le chmod était DANS le heredoc (jamais exécuté) : le
# bearer EDGE_TOKEN restait lisible par tous. Le faire après écriture.
chmod 600 .env

# Download license public key
curl -fsSL "$CLOUD_URL/api/v1/edge/license-public-key" -o license.pub
if [[ ! -s license.pub ]]; then
    echo "Echec du telechargement de la cle publique de licence depuis $CLOUD_URL" >&2
    exit 1
fi

# Start services
docker compose pull
docker compose up -d

echo ""
echo "✅ Leopardo Edge installed successfully!"
echo "   Web UI  : http://$(hostname -I | awk '{print $1}'):7879"
echo "   API     : http://$(hostname -I | awk '{print $1}'):7878"
echo "   Logs    : docker compose -f $INSTALL_DIR/docker-compose.yml logs -f"
