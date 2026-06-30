#!/bin/bash
# ============================================================
# Leopardo Edge — Installation Script
# Usage: sudo bash install.sh --node-id <UUID> --token <TOKEN>
# ============================================================
set -e

NODE_ID=""
TOKEN=""
CLOUD_URL="https://api.leopardo.app"
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
    curl -fsSL https://get.docker.com | sh
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

# Download docker-compose from cloud
curl -fsSL "$CLOUD_URL/edge/download/docker-compose.yml" -o docker-compose.yml

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

# Download license public key
curl -fsSL "$CLOUD_URL/edge/license-public-key" -o license.pub

# Start services
docker compose pull
docker compose up -d

echo ""
echo "✅ Leopardo Edge installed successfully!"
echo "   Web UI  : http://$(hostname -I | awk '{print $1}'):7879"
echo "   API     : http://$(hostname -I | awk '{print $1}'):7878"
echo "   Logs    : docker compose -f $INSTALL_DIR/docker-compose.yml logs -f"
