#!/usr/bin/env bash
#
# check-tenancy-schema-mode.sh — garde CI du mode « schema-per-tenant » mort
# (ADR-0028, issue #8055).
#
# ADR-0028 acte le schema partage `shared_tenants` + `company_id` comme mode de
# tenancy UNIQUE et definitif. Le mode « un schema par tenant » est mort mais
# encore arme : du code de production teste encore `tenancy_type === 'schema'`.
#
# Cette garde empeche la dette de GROSSIR : toute NOUVELLE reference au mode
# mort hors de l'inventaire borne de l'ADR est refusee. L'inventaire est gele
# dans `dev-hub/tools/tenancy-schema-mode-allowlist.txt` et ne peut que
# decroitre (meme doctrine que les baselines PHPStan, #7655).
#
# Usage :
#   bash dev-hub/tools/check-tenancy-schema-mode.sh            # verifie
#   bash dev-hub/tools/check-tenancy-schema-mode.sh --update   # regenere
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

SCAN_DIR="api"
ALLOWLIST="dev-hub/tools/tenancy-schema-mode-allowlist.txt"

# Motifs qui trahissent une branche sur le mode mort.
PATTERN="tenancy_type[[:space:]]*[=!]==?[[:space:]]*'schema'|tenancy_type[[:space:]]*=[[:space:]]*'schema'|tenancy_type[[:space:]]*!=[[:space:]]*'shared'"

current="$(grep -rnP "${PATTERN}" "${SCAN_DIR}" --include='*.php' 2>/dev/null | sort || true)"

# #8944 — la comparaison est insensible au NUMERO DE LIGNE.
#
# L'inventaire etait indexe sur `fichier:ligne:contenu` : le moindre deplacement
# de code (extraction d'une action, ajout d'un import) changeait le numero et
# faisait apparaitre une « nouvelle » reference alors que le vestige etait
# exactement le meme. La garde bloquait donc des PR qui RETIRAIENT du code —
# l'inverse de son but. On compare desormais `fichier:contenu`, ce qui garde la
# propriete utile (aucune nouvelle occurrence textuelle) sans punir le
# deplacement de lignes.
normalize() {
  sed -E 's/^([^:]+):[0-9]+:/\1:/' | sort
}

current_norm="$(printf '%s\n' "${current}" | grep -v '^$' | normalize || true)"

if [[ "${1:-}" == "--update" ]]; then
  {
    echo "# References au mode « schema-per-tenant » mort — inventaire gele (ADR-0028, #8055)."
    echo "#"
    echo "# Chaque ligne est un vestige BORNE, pas un mode supporte : il protege"
    echo "# d'eventuels tenants historiques et disparaitra avec le nettoyage"
    echo "# physique (migration additive, apres audit prod chiffre valide owner)."
    echo "#"
    echo "# La garde check-tenancy-schema-mode.sh refuse toute NOUVELLE ligne :"
    echo "# cette liste ne peut que decroitre."
    echo "#"
    echo "# Regenerer : bash dev-hub/tools/check-tenancy-schema-mode.sh --update"
    echo "${current_norm}"
  } > "${ALLOWLIST}"
  echo "Inventaire regenere : ${ALLOWLIST} ($(printf '%s\n' "${current_norm}" | grep -c . || true) entrees)."
  exit 0
fi

if [[ ! -f "${ALLOWLIST}" ]]; then
  echo "ERREUR — inventaire absent : ${ALLOWLIST}" >&2
  echo "Generer avec : bash dev-hub/tools/check-tenancy-schema-mode.sh --update" >&2
  exit 1
fi

allowed="$(grep -v '^#' "${ALLOWLIST}" | grep -v '^[[:space:]]*$' | normalize || true)"

new_refs="$(comm -23 <(printf '%s\n' "${current_norm}" | grep -v '^$' | sort) <(printf '%s\n' "${allowed}" | grep -v '^$' | sort) || true)"

if [[ -n "${new_refs}" ]]; then
  echo "ERREUR — nouvelle reference au mode « schema-per-tenant » mort (ADR-0028, #8055) :" >&2
  echo "${new_refs}" | sed 's/^/  - /' >&2
  echo "" >&2
  echo "Le mode est mort : aucun tenant ne peut plus etre cree en schema dedie" >&2
  echo "(Company::booted() abort 422). Ne pas ajouter de branche — utiliser le" >&2
  echo "schema partage + company_id + global scope (BelongsToCompany)." >&2
  exit 1
fi

resolved="$(comm -13 <(printf '%s\n' "${current_norm}" | grep -v '^$' | sort) <(printf '%s\n' "${allowed}" | grep -v '^$' | sort) || true)"
if [[ -n "${resolved}" ]]; then
  echo "INFO — reference(s) disparue(s), retirer ces lignes de ${ALLOWLIST} :"
  echo "${resolved}" | sed 's/^/  - /'
fi

echo "OK — aucune nouvelle reference au mode « schema-per-tenant » mort (ADR-0028, #8055)."
