#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# check-migration-collisions.sh — Garde anti-collision de basenames de
# migrations Laravel (issue #1962).
#
# Pourquoi : `Migrator::getMigrationFiles()` (Laravel 11+) indexe les
# migrations par BASENAME (`keyBy(getMigrationName($file))`). Si deux
# fichiers portent le MÊME basename dans l'ensemble des chemins migrés
# (ex. `database/migrations/` + `database/migrations/public/` + tenant/,
# ou un futur `--path` multiple), le dernier dans l'ordre de glob ÉCRASE
# silencieusement l'autre : la migration perdante n'est JAMAIS exécutée
# (dérive de schéma silencieuse en prod — incident réel 2026-08-14,
# corrigé par la PR #1957).
#
# Usage :
#   dev-hub/tools/check-migration-collisions.sh [chemin_des_migrations]
#   (défaut : api/database/migrations)
#
# Retour : 0 si aucune collision, 1 sinon (message explicite).
# ---------------------------------------------------------------------------
set -euo pipefail

MIGRATIONS_DIR="${1:-api/database/migrations}"

if [[ ! -d "${MIGRATIONS_DIR}" ]]; then
    echo "❌ Répertoire de migrations introuvable : ${MIGRATIONS_DIR}"
    exit 1
fi

# Basenames dupliqués à travers TOUT l'arbre de migrations. (Deux fichiers
# identiques dans le même répertoire sont impossibles sur un filesystem ;
# la collision réelle survient entre répertoires fusionnés par un même run.)
ACROSS=$(find "${MIGRATIONS_DIR}" -name '*.php' -printf '%f\n' | sort | uniq -d)

if [[ -n "${ACROSS}" ]]; then
    echo "❌ Collision de basename de migration dans ${MIGRATIONS_DIR}/** :"
    echo "${ACROSS}" | sed 's/^/    /'
    echo ""
    echo "Laravel indexe les migrations par basename (getMigrationFiles → keyBy) :"
    echo "le fichier perdant n'est jamais exécuté nulle part, sans avertissement."
    echo "Renommer chaque fichier en collision avec un horodatage UNIQUE"
    echo "(ex. ..._000008_... ) — voir l'incident #1962 / fix #1957."
    exit 1
fi

echo "✅ Aucune collision de basename de migration (${MIGRATIONS_DIR})."
exit 0
