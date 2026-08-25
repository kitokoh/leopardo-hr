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
# Règle #5431 (récurrence #1962) : toute NOUVELLE migration doit porter une
# référence d'issue dans son nom — ex. `2026_08_24_000001_5422_create_x_table.php`
# ou préfixe `<issue>-` — sinon FAIL (whitelist `migrations-legacy-allowlist.txt`
# pour l'existant). Le numéro d'issue est unique par branche (protocole #2400),
# ce qui rend la collision de préfixes structurellement impossible.
#
# Usage : dev-hub/tools/check-migration-basename-collisions.sh [REPERTOIRE] [--remote]
# (argument optionnel : racine des migrations — pratique pour les tests ;
#  --remote : compare les préfixes avec les branches locales mod/*, fix/*,
#  chore/*, security/* — best-effort)
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

# --- 4. Référence d'issue obligatoire dans les NOUVELLES migrations (#5431) ---
ALLOWLIST_FILE="${ROOT}/dev-hub/tools/migrations-legacy-allowlist.txt"
if [[ ! -f "${ALLOWLIST_FILE}" ]]; then
  echo "::error::Fichier whitelist legacy introuvable : ${ALLOWLIST_FILE}"
  exit 1
fi

while IFS= read -r file; do
  base="$(basename "${file}")"
  grep -qxF "${base}" "${ALLOWLIST_FILE}" && continue
  # après le préfixe date_séquence, le slug doit contenir une réf. d'issue
  slug="${base#[0-9][0-9][0-9][0-9]_[0-9][0-9]_[0-9][0-9]_[0-9][0-9][0-9][0-9][0-9][0-9]_}"
  if [[ "${slug}" == "${base}" ]]; then
    # pas de préfixe date_séquence standard : migration hors convention
    echo "::error::${base} : hors convention de nommage (préfixe date_séquence attendu)."
    FAIL=1
    continue
  fi
  if ! [[ "${slug}" =~ ^[0-9]{4,5}_ ]] && ! [[ "${slug}" =~ _[0-9]{4,5}_ ]]; then
    echo "::error::${base} : NOUVELLE migration sans référence d'issue (règle #5431)."
    echo "  → ajoutez le numéro d'issue au nom, ex. 2026_08_24_000001_5422_create_x_table.php"
    echo "    (le numéro d'issue est unique par branche — protocole #2400)."
    FAIL=1
  fi
done < <(find "${MIGRATIONS_DIR}" -name '*.php' 2>/dev/null || true)

# --- 5. Collision de préfixes cross-branches (optionnel, --remote) ---
if [[ "${1:-}" == "--remote" || "${2:-}" == "--remote" ]]; then
  THIS_PREFIXES="$(find "${MIGRATIONS_DIR}" -name '*.php' -printf '%f\n' \
    | sed -nE 's/^([0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6})_.*\.php$/\1/p' | sort -u)"
  for ref in $(git for-each-ref --format='%(refname:short)' refs/remotes/origin 2>/dev/null \
      | grep -E '^(origin/)?(mod|fix|chore|security)/' || true); do
    [[ "${ref}" == "origin/main" ]] && continue
    REMOTE_PREFIXES="$(git ls-tree -r --name-only "${ref}" api/database/migrations 2>/dev/null \
      | sed -nE 's|.*/([0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6})_.*\.php$|\1|p' | sort -u || true)"
    for pfx in ${REMOTE_PREFIXES}; do
      if echo "${THIS_PREFIXES}" | grep -qx "${pfx}"; then
        echo "::error::Préfixe de migration ${pfx} partagé avec la branche ${ref} (règle #5431)."
        echo "  → renumérotez votre migration (ex. 000001 → 000004) pour éviter la collision au merge."
        FAIL=1
      fi
    done
  done
fi

if [[ "${FAIL}" -eq 1 ]]; then
  echo ""
  echo "Laravel indexe les migrations par basename (getMigrationFiles → keyBy) :"
  echo "la migration perdante est ignorée silencieusement. Renommez les doublons"
  echo "(timestamp séquentiel unique dans le répertoire)."
  exit 1
fi

echo "✅ Aucune collision de migrations (basenames + préfixes uniques)."
