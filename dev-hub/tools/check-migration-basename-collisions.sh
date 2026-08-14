#!/usr/bin/env bash
# Issue #1962 — garde anti-collision des migrations Laravel (tenant/public).
#
# Laravel indexe les migrations par BASENAME (Migrator::getMigrationFiles →
# keyBy(getMigrationName)) : deux fichiers portant le même nom dans un même
# ensemble de chemins → le dernier dans l'ordre de glob écrase silencieusement
# l'autre, et la migration perdante n'est JAMAIS exécutée (dérive de schéma
# silencieuse — incident réel du 2026-08-14, fix #1957).
#
# Deux niveaux de détection, par répertoire de migrations :
#   1. BASENAME identiques (même fichier dupliqué) — collision keyBy stricte.
#   2. PRÉFIXE de séquence identique (YYYY_MM_DD_0000NN) — « collision de nom »
#      documentée par le projet (cf. #1957/#1962) : ordre de migration ambigu,
#      un agent peut croire sa migration exécutée alors qu'une autre du même
#      préfixe a pris sa place dans l'ordre de tri.
#
# Usage : dev-hub/tools/check-migration-basename-collisions.sh [REPERTOIRE]
# (argument optionnel : racine des migrations — pratique pour les tests)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MIGRATIONS_DIR="${1:-${ROOT}/api/database/migrations}"

if [[ ! -d "${MIGRATIONS_DIR}" ]]; then
  echo "::error::Répertoire de migrations introuvable : ${MIGRATIONS_DIR}"
  exit 1
fi

FAIL=0

for DIR in "${MIGRATIONS_DIR}"/*/; do
  [[ -d "${DIR}" ]] || continue

  # --- 1. Basenames strictement identiques (collision keyBy de Laravel) ---
  DUPES="$(find "${DIR}" -maxdepth 1 -name '*.php' -printf '%f\n' | sort | uniq -d || true)"
  if [[ -n "${DUPES}" ]]; then
    echo "::error::Collision de BASENAMES dans ${DIR} :"
    echo "${DUPES}" | sed 's/^/  /'
    FAIL=1
  fi

  # --- 2. Préfixes de séquence dupliqués (incident #1957) ---
  DUP_PREFIXES="$(find "${DIR}" -maxdepth 1 -name '*.php' -printf '%f\n' \
    | sed -nE 's/^([0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6})_.*\.php$/\1/p' \
    | sort | uniq -d || true)"
  if [[ -n "${DUP_PREFIXES}" ]]; then
    echo "::error::Préfixes de séquence dupliqués dans ${DIR} (issue #1962) :"
    echo "${DUP_PREFIXES}" | sed 's/^/  /'
    echo "  → deux migrations partagent le même préfixe YYYY_MM_DD_0000NN : ordre"
    echo "    ambigu et risque de collision (cf. incident 2026_08_14, fix #1957)."
    echo "    Renuméroter l'une des deux (ex. 000001 → 000004)."
    FAIL=1
  fi
done

# --- 3. Basenames identiques À TRAVERS l'arbre (idée #1974 : ensemble
# fusionnable par un futur `migrate --path` multiple ou un changement de
# chargement — la garde reste verte aujourd'hui, aucun doublon) ---
CROSS_DUPES="$(find "${MIGRATIONS_DIR}" -name '*.php' -printf '%f\n' | sort | uniq -d || true)"
if [[ -n "${CROSS_DUPES}" ]]; then
  echo "::error::Basenames dupliqués à travers l'arbre des migrations :"
  echo "${CROSS_DUPES}" | sed 's/^/  /'
  echo "  → deux répertoires portent le même fichier de migration : si ces chemins"
  echo "    sont migrés ensemble (migrate --path multiple), Laravel en ignorera un."
  FAIL=1
fi

if [[ "${FAIL}" -eq 1 ]]; then
  echo ""
  echo "Laravel indexe les migrations par basename (getMigrationFiles → keyBy) :"
  echo "la migration perdante est ignorée silencieusement. Renommez les doublons"
  echo "(timestamp séquentiel unique dans le répertoire)."
  exit 1
fi

echo "✅ Aucune collision de migrations (basenames + préfixes uniques)."
