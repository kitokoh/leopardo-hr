#!/usr/bin/env bash
# dev-stack-switch.sh — SHIM de compatibilité (épic #8670).
#
# La bascule multi-tiers vit désormais dans scripts/stack-switch.sh (#8672) :
# la logique dev y est conservée à l'identique, et le tier prod y ajoute ses
# garde-fous (aucun reseed, même base obligatoire).
#
# Ce shim conserve l'interface historique utilisée par dev-stack-switch.yml,
# le watchdog de dev-keepalive.yml et la documentation (#8659/#8662) : mêmes
# arguments, mêmes effets (variables DEV_* uniquement).
#
# Usage (inchangé) :
#   scripts/dev-stack-switch.sh <stack> [--force] [--seed] [--dry-run]
#
# Équivalent moderne :
#   scripts/stack-switch.sh <stack> --tier dev [--force] [--seed] [--dry-run]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

exec "$ROOT/scripts/stack-switch.sh" --tier dev "$@"
