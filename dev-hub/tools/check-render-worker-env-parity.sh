#!/usr/bin/env bash
# ============================================================
# check-render-worker-env-parity.sh — Parité env worker dev ↔ prod (issue #7977)
# ------------------------------------------------------------
# Constat (audit 2026-09-20, #7977) : le bloc commenté
# « leopardo-queue-worker-prod » de render.prod.yaml (état cible à
# dé-commenter dès ajout d'un moyen de paiement) était tronqué à 14 clés
# d'environnement contre 33 dans le bloc jumeau dev de render.yaml — le
# worker provisionné « tel quel » ne pouvait ni envoyer un e-mail ni une
# notification push, ni remonter à Sentry, et tournait en CACHE_STORE=file
# (invalidations perdues entre services, web en redis).
#
# Cette garde compare les ENSEMBLES de clés `envVars` des deux blocs
# worker commentés et refuse toute régression :
#   - toute clé présente dans le worker dev (render.yaml) DOIT exister
#     dans le worker prod (render.prod.yaml) ;
#   - les clés supplémentaires côté prod sont tolérées (alignement
#     intentionnel sur le web prod : SESSION_DRIVER, …) mais signalées ;
#   - le worker prod doit déclarer CACHE_STORE=redis (aligné web prod).
#
# Sortie : 0 = parité OK, 1 = dérive détectée.
# ============================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DEV_FILE="$ROOT/render.yaml"
PROD_FILE="$ROOT/render.prod.yaml"

extract_keys() {
    # Toutes les lignes commentées « # - key: XXX » des fichiers render
    # appartiennent au bloc worker (unique bloc commenté avec des envVars).
    grep -oE '^#[[:space:]]*- key: [A-Z0-9_]+' "$1" | awk '{print $NF}' | sort -u
}

dev_keys="$(extract_keys "$DEV_FILE")"
prod_keys="$(extract_keys "$PROD_FILE")"

if [[ -z "$dev_keys" ]]; then
    echo "ERREUR : aucune clé extraite de $DEV_FILE — le bloc worker dev a-t-il été supprimé ou renommé ?" >&2
    exit 1
fi

missing=0
while IFS= read -r key; do
    [[ -z "$key" ]] && continue
    if ! grep -qx "$key" <<< "$prod_keys"; then
        echo "MANQUANT côté worker prod : $key" >&2
        missing=1
    fi
done <<< "$dev_keys"

extra=0
while IFS= read -r key; do
    [[ -z "$key" ]] && continue
    if ! grep -qx "$key" <<< "$dev_keys"; then
        echo "note : clé supplémentaire côté prod (tolérée si intentionnelle) : $key"
        extra=1
    fi
done <<< "$prod_keys"

# CACHE_STORE=redis sur le worker prod (aligné web prod — #7977).
if ! grep -A1 '^#[[:space:]]*- key: CACHE_STORE' "$PROD_FILE" | grep -q 'value: redis'; then
    echo "ERREUR : le worker prod doit déclarer CACHE_STORE=redis (aligné sur le web prod)." >&2
    missing=1
fi

if [[ "$missing" -ne 0 ]]; then
    echo "ÉCHEC parité env worker dev ↔ prod — voir #7977." >&2
    exit 1
fi

dev_count="$(grep -c . <<< "$dev_keys")"
prod_count="$(grep -c . <<< "$prod_keys")"
echo "OK parité env worker : $prod_count clés prod ⊇ $dev_count clés dev (CACHE_STORE=redis vérifié)."
exit 0
