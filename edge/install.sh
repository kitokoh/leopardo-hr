#!/bin/bash
# ============================================================
# Leopardo Edge — Installation Script
# Usage: sudo EDGE_TOKEN=<TOKEN> bash install.sh --node-id <UUID>
#    ou: sudo bash install.sh --node-id <UUID> --token-file <FICHIER>
#
# #7653 : le jeton d'enrôlement n'est PLUS JAMAIS passé en argument
# (argv est visible dans `ps`, l'historique shell et les logs sudo).
# Sources acceptées : variable d'environnement EDGE_TOKEN, --token-file,
# ou saisie interactive masquée (stdin).
# ============================================================
set -euo pipefail

NODE_ID=""
TOKEN="${EDGE_TOKEN:-}"
TOKEN_FILE=""
# #7653 : URL cloud paramétrable aussi par env (aligné sur docker-compose.yml).
CLOUD_URL="${CLOUD_API_URL:-https://gestionemployerbackend.onrender.com}"
SYNC_INTERVAL=15
EDGE_DOMAIN="${EDGE_DOMAIN:-leopardo.local}"
# Clé publique RS256 épinglée hors bande (recommandé) pour vérifier la
# signature du manifeste d'intégrité (#7653).
MANIFEST_PUBKEY_FILE="${EDGE_MANIFEST_PUBKEY_FILE:-}"
INSECURE_MANIFEST=0

# Parse args
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --node-id) NODE_ID="$2"; shift ;;
        --token)
            # #7653 : refusé — un secret en argv fuit dans ps/history.
            echo "❌ --token n'est plus accepté (le jeton serait visible dans ps/history)." >&2
            echo "   Utilisez : EDGE_TOKEN=<TOKEN> (env), --token-file <fichier>, ou la saisie interactive." >&2
            exit 1
            ;;
        --token-file) TOKEN_FILE="$2"; shift ;;
        --cloud)   CLOUD_URL="$2"; shift ;;
        --interval) SYNC_INTERVAL="$2"; shift ;;
        --domain)  EDGE_DOMAIN="$2"; shift ;;
        --pubkey)  MANIFEST_PUBKEY_FILE="$2"; shift ;;
        --insecure-manifest) INSECURE_MANIFEST=1 ;;
        *) echo "Unknown param: $1"; exit 1 ;;
    esac
    shift
done

# Résolution du jeton : --token-file > env EDGE_TOKEN > saisie interactive.
if [[ -n "$TOKEN_FILE" ]]; then
    if [[ ! -s "$TOKEN_FILE" ]]; then
        echo "❌ Fichier de jeton introuvable ou vide : $TOKEN_FILE" >&2
        exit 1
    fi
    TOKEN="$(head -n1 "$TOKEN_FILE" | tr -d '[:space:]')"
fi
if [[ -z "$TOKEN" && -t 0 ]]; then
    read -rsp "Jeton d'enrôlement Edge (saisie masquée) : " TOKEN
    echo ""
fi

if [[ -z "$NODE_ID" || -z "$TOKEN" ]]; then
    echo "❌ --node-id est requis, et le jeton doit être fourni via EDGE_TOKEN (env), --token-file ou la saisie interactive." >&2
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
if ! command -v openssl &> /dev/null; then
    # #7653 : requis pour la vérification de signature du manifeste (et APP_KEY).
    echo "❌ openssl est requis." >&2
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

# ------------------------------------------------------------------
# #7653 — Clé publique RS256 pour la signature du manifeste.
# Recommandé : épingler la clé hors bande (--pubkey / EDGE_MANIFEST_PUBKEY_FILE,
# obtenue depuis le dashboard Leopardo) — la vérification protège alors
# aussi d'une compromission du cloud. À défaut, la clé de licence est
# téléchargée AVANT le manifeste (TOFU : trust-on-first-use, avertissement).
# ------------------------------------------------------------------
mkdir -p keys
if [[ -n "$MANIFEST_PUBKEY_FILE" ]]; then
    if [[ ! -s "$MANIFEST_PUBKEY_FILE" ]]; then
        echo "❌ Clé publique épinglée introuvable ou vide : $MANIFEST_PUBKEY_FILE" >&2
        exit 1
    fi
    cp "$MANIFEST_PUBKEY_FILE" keys/edge_license_public.pem
else
    curl -fsSL "$CLOUD_URL/api/v1/edge/license-public-key" -o keys/edge_license_public.pem
    echo "⚠️  Clé publique téléchargée depuis $CLOUD_URL (trust-on-first-use)."
    echo "   Pour un ancrage de confiance indépendant du cloud, fournissez la clé"
    echo "   hors bande : --pubkey <fichier> ou EDGE_MANIFEST_PUBKEY_FILE."
fi
if [[ ! -s keys/edge_license_public.pem ]] || ! grep -q 'BEGIN PUBLIC KEY' keys/edge_license_public.pem; then
    echo "❌ Clé publique de licence invalide — installation annulée." >&2
    exit 1
fi

# Manifeste d'intégrité (doit être disponible avant tout téléchargement).
curl -fsSL "$CLOUD_URL/api/v1/edge/download/sha256.txt" -o sha256.txt
if [[ ! -s sha256.txt ]] || ! grep -q 'install.sh' sha256.txt; then
    echo "❌ Manifeste d'intégrité indisponible depuis $CLOUD_URL — installation annulée." >&2
    exit 1
fi

# ------------------------------------------------------------------
# #7653 — Vérification de la signature RS256 du manifeste (fail-closed).
# Le manifeste était téléchargé depuis le MÊME endpoint que les fichiers
# qu'il vérifie : cela protégeait de la troncature, pas d'une altération
# côté serveur. sha256.txt.sig = signature SHA256-RSA (base64) des octets
# exacts de sha256.txt, vérifiée avec la clé publique ci-dessus.
# ------------------------------------------------------------------
if [[ "$INSECURE_MANIFEST" == "1" ]]; then
    echo "⚠️  --insecure-manifest : vérification de signature désactivée (réservé au développement)."
else
    if ! curl -fsSL "$CLOUD_URL/api/v1/edge/download/sha256.txt.sig" -o sha256.txt.sig.b64 || [[ ! -s sha256.txt.sig.b64 ]]; then
        echo "❌ Signature du manifeste (sha256.txt.sig) indisponible depuis $CLOUD_URL — installation annulée." >&2
        echo "   (Développement uniquement : --insecure-manifest pour passer outre.)" >&2
        exit 1
    fi
    openssl base64 -d -A -in sha256.txt.sig.b64 -out sha256.txt.sig
    if ! openssl dgst -sha256 -verify keys/edge_license_public.pem -signature sha256.txt.sig sha256.txt > /dev/null 2>&1; then
        echo "❌ Signature du manifeste INVALIDE — le manifeste ne provient pas du détenteur de la clé Leopardo." >&2
        echo "   Abandon — aucun fichier n'est installé." >&2
        exit 1
    fi
    rm -f sha256.txt.sig.b64 sha256.txt.sig
    echo "🔐 Signature du manifeste vérifiée (RS256)."
fi

# docker-compose.yml (serveur de confiance, vérifié par hash)
curl -fsSL "$CLOUD_URL/api/v1/edge/download/docker-compose.yml" -o docker-compose.yml
if [[ ! -s docker-compose.yml ]]; then
    echo "Echec du telechargement du docker-compose depuis $CLOUD_URL" >&2
    exit 1
fi
verify_download "docker-compose.yml" "docker-compose.yml"

# Caddyfile.edge (bind-mounté par edge-proxy ; vérifié par hash + contenu)
# #7966 : edge-api écoute désormais en :8080 non privilégié (image non-root).
curl -fsSL "$CLOUD_URL/api/v1/edge/download/Caddyfile.edge" -o Caddyfile.edge
if [[ ! -s Caddyfile.edge ]] || ! grep -q 'reverse_proxy edge-api:8080' Caddyfile.edge || ! grep -q 'reverse_proxy edge-ui:3000' Caddyfile.edge; then
    echo "Echec du telechargement ou de la verification de Caddyfile.edge depuis $CLOUD_URL" >&2
    exit 1
fi
verify_download "Caddyfile.edge" "Caddyfile.edge"

# #7653 : IP LAN pour laquelle la CA interne Caddy émet un certificat
# (en plus de EDGE_DOMAIN) — injectée dans le proxy via .env.
EDGE_LAN_IP=$(hostname -I | awk '{print $1}')
EDGE_LAN_IP=${EDGE_LAN_IP:-127.0.0.1}

# Generate .env
APP_KEY=$(openssl rand -base64 32)
cat > .env <<EOF
EDGE_NODE_ID=$NODE_ID
EDGE_TOKEN=$TOKEN
EDGE_APP_KEY=base64:$APP_KEY
CLOUD_API_URL=$CLOUD_URL
SYNC_INTERVAL=$SYNC_INTERVAL
FORCE_OFFLINE=false
EDGE_DOMAIN=$EDGE_DOMAIN
EDGE_LAN_IP=$EDGE_LAN_IP
EOF
# Issue #2751 — le chmod était DANS le heredoc (jamais exécuté) : le
# bearer EDGE_TOKEN restait lisible par tous. Le faire après écriture.
chmod 600 .env

# La clé publique de licence (source unique: edge/keys/edge_license_public.pem,
# #6604) a déjà été installée plus haut (#7653 : requise AVANT le manifeste).

# Start services (build local — les images sont construites, pas tirées : #6604)
docker compose up -d --build

echo ""
echo "✅ Leopardo Edge installed successfully!"
echo "   Web UI  : https://$EDGE_LAN_IP:7879  (ou https://$EDGE_DOMAIN:7879)"
echo "   API     : https://$EDGE_LAN_IP:7878"
echo "   Portail : https://$EDGE_LAN_IP"
echo "   Logs    : docker compose -f $INSTALL_DIR/docker-compose.yml logs -f"
echo ""
echo "🔐 TLS : le trafic LAN est chiffré par la CA interne Caddy (#7653)."
echo "   Exportez la CA racine à installer sur les kiosques/postes clients :"
echo "   docker compose -f $INSTALL_DIR/docker-compose.yml cp edge-proxy:/data/caddy/pki/authorities/local/root.crt ./leopardo-edge-ca.crt"
echo "   Procédure détaillée : edge/README.md, section « TLS sur le LAN »."
