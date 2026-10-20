#!/usr/bin/env bash
# dev-stack-health.sh — SHIM de compatibilité (épic #8670).
#
# La sonde multi-tiers vit désormais dans scripts/stack-health.sh (#8672) :
# elle gère dev ET prod à partir de deploy/<tier>-stacks.json.
#
# Ce shim conserve l'interface historique utilisée par dev-keepalive.yml, le
# watchdog et la documentation (#8659/#8661) : mêmes options, même sortie
# JSONL, mêmes codes de retour. Aucun appelant n'a besoin d'être modifié.
#
# Usage (inchangé) :
#   scripts/dev-stack-health.sh [--stack <id>] [--active] [--pretty]
#
# Équivalent moderne :
#   scripts/stack-health.sh --tier dev [--stack <id>] [--active] [--pretty]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

exec "$ROOT/scripts/stack-health.sh" --tier dev "$@"
