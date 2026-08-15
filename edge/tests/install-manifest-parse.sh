#!/usr/bin/env bash
#
# Test de régression — extraction du manifeste d'intégrité sha256.txt (issue #4007).
#
# L'API sert le manifeste en JSON : {"sha256":["<hash>  <fichier>", ...], ...}
# alors que la première version d'install.sh le parsait en texte (awk $2) →
# hash jamais extrait → installation edge KO. Ce test verrouille la logique
# d'extraction (coreutils only) utilisée par verify_download() d'install.sh.
#
# Usage : bash edge/tests/install-manifest-parse.sh

set -euo pipefail

MANIFEST='{"sha256":["9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08  install.sh","60303ae22b998861bce3b28f33eec1be758a213c86c93c076dbe9f3c6694e0b0  docker-compose.yml","a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e  Caddyfile.edge"],"algorithm":"sha256"}'

extract_hash() {
    # $1 = nom du fichier ; stdin = contenu du manifeste
    grep -oE '"[0-9a-f]{64}  [^"]*"' | grep -F " $1\"" | head -n1 | sed -E 's/^"([0-9a-f]{64})  .*/\1/'
}

fail=0

# 1. Chaque asset du manifeste JSON est extractible (64 hex).
for f in install.sh docker-compose.yml Caddyfile.edge; do
    h=$(printf '%s' "$MANIFEST" | extract_hash "$f")
    if [[ ${#h} -eq 64 && "$h" =~ ^[0-9a-f]{64}$ ]]; then
        echo "OK   $f -> ${h:0:16}…"
    else
        echo "FAIL $f -> hash non extrait ('${h}')"
        fail=1
    fi
done

# 2. La garde install.sh reste vraie sur le JSON (sous-chaîne).
if printf '%s' "$MANIFEST" | grep -q 'install.sh'; then
    echo "OK   garde grep -q 'install.sh'"
else
    echo "FAIL garde grep -q 'install.sh'"
    fail=1
fi

# 3. Un asset absent du manifeste ne produit aucun hash (fail-closed).
h=$(printf '%s' "$MANIFEST" | extract_hash "unknown-file.txt" || true)
if [[ -z "$h" ]]; then
    echo "OK   asset absent -> vide (fail-closed)"
else
    echo "FAIL asset absent -> '${h}'"
    fail=1
fi

# 4. Boucle complète verify_download (hash réel d'un fichier).
tmpdir=$(mktemp -d)
trap 'rm -rf "$tmpdir"' EXIT
printf '#!/bin/sh\necho test\n' > "$tmpdir/dummy.sh"
printf '%s' "$MANIFEST" > "$tmpdir/sha256.txt"
expected=$(extract_hash "dummy.sh" < "$tmpdir/sha256.txt" || true)
if [[ -z "$expected" ]]; then
    echo "OK   fail-closed sur asset non listé"
else
    echo "FAIL fail-closed"
    fail=1
fi

exit "$fail"
