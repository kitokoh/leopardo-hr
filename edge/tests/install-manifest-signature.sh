#!/usr/bin/env bash
#
# Test de régression — signature RS256 du manifeste d'intégrité (issue #7653).
#
# Le manifeste sha256.txt était téléchargé depuis le MÊME endpoint que les
# fichiers qu'il vérifie : cela protégeait de la troncature, pas d'une
# altération côté serveur. install.sh vérifie désormais sha256.txt.sig
# (signature SHA256-RSA en base64, servie par l'API cloud) avec la clé
# publique RS256 — fail-closed. Ce test verrouille la logique openssl
# EXACTE utilisée par install.sh, et deux gardes de posture du script :
#   - le jeton d'enrôlement n'est plus jamais accepté en argv (--token) ;
#   - la vérification de signature est bien présente et fail-closed.
#
# Usage : bash edge/tests/install-manifest-signature.sh

set -euo pipefail

EDGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fail=0

tmpdir=$(mktemp -d)
trap 'rm -rf "$tmpdir"' EXIT
cd "$tmpdir"

# Paire RS256 éphémère (même génération que edge/keys/README.md).
openssl genrsa -out private.pem 2048 2>/dev/null
openssl rsa -in private.pem -pubout -out public.pem 2>/dev/null

MANIFEST='{"sha256":["9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08  install.sh"],"algorithm":"sha256"}'
printf '%s' "$MANIFEST" > sha256.txt

# Signature côté "cloud" : SHA256-RSA des octets exacts, encodée base64
# (miroir de EdgeDownloadController::sha256Signature()).
openssl dgst -sha256 -sign private.pem -out sig.bin sha256.txt
openssl base64 -A -in sig.bin -out sha256.txt.sig.b64

# Vérification côté "install.sh" (logique EXACTE du script).
verify_manifest() {
    openssl base64 -d -A -in sha256.txt.sig.b64 -out sha256.txt.sig
    openssl dgst -sha256 -verify public.pem -signature sha256.txt.sig sha256.txt > /dev/null 2>&1
}

# 1. Manifeste authentique → vérification OK.
if verify_manifest; then
    echo "OK   signature valide acceptée"
else
    echo "FAIL signature valide refusée"
    fail=1
fi

# 2. Manifeste altéré (un hash modifié) → refus (fail-closed).
printf '%s' "${MANIFEST/9f86/ffff}" > sha256.txt
if verify_manifest; then
    echo "FAIL manifeste altéré accepté"
    fail=1
else
    echo "OK   manifeste altéré refusé (fail-closed)"
fi
printf '%s' "$MANIFEST" > sha256.txt

# 3. Signature d'une AUTRE clé → refus.
openssl genrsa -out other.pem 2048 2>/dev/null
openssl dgst -sha256 -sign other.pem -out sig.bin sha256.txt
openssl base64 -A -in sig.bin -out sha256.txt.sig.b64
if verify_manifest; then
    echo "FAIL signature d'une clé étrangère acceptée"
    fail=1
else
    echo "OK   signature d'une clé étrangère refusée"
fi

# 4. install.sh refuse le jeton en argv (--token) — posture #7653.
if grep -qE "^\s*--token\)" "$EDGE_DIR/install.sh" \
    && grep -A3 -E "^\s*--token\)" "$EDGE_DIR/install.sh" | grep -q "n'est plus accepté"; then
    echo "OK   install.sh refuse --token (argv)"
else
    echo "FAIL install.sh doit refuser explicitement --token"
    fail=1
fi
if grep -qE '^\s*--token\)\s*TOKEN=' "$EDGE_DIR/install.sh"; then
    echo "FAIL install.sh lit encore le jeton depuis argv"
    fail=1
else
    echo "OK   aucun TOKEN= assigné depuis argv"
fi

# 5. install.sh vérifie bien la signature du manifeste (fail-closed).
if grep -q 'sha256.txt.sig' "$EDGE_DIR/install.sh" \
    && grep -q 'openssl dgst -sha256 -verify' "$EDGE_DIR/install.sh"; then
    echo "OK   install.sh vérifie la signature du manifeste"
else
    echo "FAIL install.sh ne vérifie pas la signature du manifeste"
    fail=1
fi

# 6. Le Caddyfile Edge sert en TLS interne, plus de contenu applicatif en :80.
if grep -q 'tls internal' "$EDGE_DIR/Caddyfile.edge" && grep -q 'local_certs' "$EDGE_DIR/Caddyfile.edge"; then
    echo "OK   Caddyfile.edge en TLS interne (local_certs)"
else
    echo "FAIL Caddyfile.edge sans TLS interne"
    fail=1
fi

exit "$fail"
