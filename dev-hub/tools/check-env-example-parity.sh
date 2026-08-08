#!/usr/bin/env bash
# check-env-example-parity.sh — Vérifie que toutes les clés env() utilisées
# dans api/config/ sont documentées dans api/.env.example (issue #1487).
#
# Usage : dev-hub/tools/check-env-example-parity.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#
# Échoue si une clé env('X') de config/ est absente de .env.example
# (ligne active `X=…`, les commentaires # ne comptent pas).
set -euo pipefail

API_DIR="${1:-api}"

if [[ ! -d "${API_DIR}/config" ]]; then
  echo "::error::${API_DIR}/config introuvable — API_DIR invalide ?"
  exit 1
fi

# clés utilisées dans config/ (y compris env() imbriqués)
used=$(grep -rhoE "env\(\s*['\"][A-Z0-9_]+['\"]" "${API_DIR}/config" \
       | sed -E "s/env\(\s*['\"]//; s/['\"]//" | sort -u)

# clés documentées dans .env.example (lignes actives)
doc=$(grep -vE "^\s*#|^\s*$" "${API_DIR}/.env.example" 2>/dev/null \
      | sed -E "s/^([A-Z0-9_]+)=.*/\1/" | sort -u)

missing=""
while IFS= read -r k; do
  [[ -z "${k}" ]] && continue
  if ! grep -qx "${k}" <<< "${doc}"; then
    missing+="${k}"$'\n'
  fi
done <<< "${used}"

if [[ -n "${missing}" ]]; then
  echo "::error::Clés env() utilisées dans config/ mais absentes de .env.example :"
  printf '::error::  - %s' "${missing}"
  echo "Mettez .env.example à jour (voir issue #1487)."
  exit 1
fi

echo "✓ Parité config/ ↔ .env.example : $(wc -l <<< "${used}") clés, 0 manquante."
exit 0
