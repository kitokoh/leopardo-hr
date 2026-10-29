#!/usr/bin/env bash
# check-module-isolation-budget.sh — plafond de dette d'isolation (issue #7959, point 3)
#
# POURQUOI CE GARDE EXISTE
#
# `check-module-isolation.sh` (#5584 / #8059) bloque tout NOUVEL import croisé
# inter-module : la dette ne peut plus grossir. C'est nécessaire mais pas
# suffisant. Une allowlist est un stock, pas un flux : tant qu'aucun plafond ne
# baisse, la dette tolérée (46 arêtes au 2026-10-28) peut rester indéfiniment à
# son niveau, et une PR peut même « nettoyer » une arête puis en ajouter une
# autre ailleurs sans que rien ne le signale.
#
# Ce garde ajoute la seule chose qui manquait : un BUDGET DECROISSANT.
#
#   - le nombre d'arêtes de l'allowlist ne doit jamais dépasser le budget ;
#   - dès qu'une arête est résorbée, le budget doit être abaissé dans le même
#     commit — sinon la CI le rappelle. La dette ne peut donc que baisser.
#
# USAGE
#   bash dev-hub/tools/check-module-isolation-budget.sh
#   La variable MODULE_ISOLATION_BUDGET dans ce fichier est la source de vérité.
#
# SORTIE
#   0 — dette <= budget et plafond cohérent
#   1 — dette > budget (une arête a été ajoutée sans le déclarer)
#   1 — budget > dette (dette résorbée : abaisser le budget pour la verrouiller)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ALLOWLIST="${SCRIPT_DIR}/module-isolation-allowlist.txt"
HTTP_ALLOWLIST="${SCRIPT_DIR}/module-isolation-http-allowlist.txt"

# Budget courant. DOIT être abaissé à chaque arête résorbée.
MODULE_ISOLATION_BUDGET=46
HTTP_ISOLATION_BUDGET=$(grep -v '^#' "${HTTP_ALLOWLIST}" 2>/dev/null | grep -cv '^$' || echo 0)

count_edges() {
  # Les commentaires et lignes vides ne sont pas de la dette.
  grep -v '^#' "$1" 2>/dev/null | grep -cv '^$' || echo 0
}

modules_edges=$(count_edges "${ALLOWLIST}")
http_edges=$(count_edges "${HTTP_ALLOWLIST}")

status=0

echo "Isolation inter-module — dette tolérée vs budget (#7959)"
echo "  Modules/  : ${modules_edges} arêtes / budget ${MODULE_ISOLATION_BUDGET}"
echo "  app/Http  : ${http_edges} arêtes / budget ${HTTP_ISOLATION_BUDGET}"

if (( modules_edges > MODULE_ISOLATION_BUDGET )); then
  echo "❌ Dette Modules/ au-dessus du budget : ${modules_edges} > ${MODULE_ISOLATION_BUDGET}." >&2
  echo "   Corriger le code (contrat dans App\\Shared ou événement) — ne pas relever le budget." >&2
  status=1
fi

if (( modules_edges < MODULE_ISOLATION_BUDGET )); then
  echo "❌ Dette résorbée mais budget non abaissé : ${modules_edges} < ${MODULE_ISOLATION_BUDGET}." >&2
  echo "   Abaisser MODULE_ISOLATION_BUDGET à ${modules_edges} dans ce fichier pour verrouiller le gain." >&2
  status=1
fi

if (( http_edges > HTTP_ISOLATION_BUDGET )); then
  echo "❌ Dette app/Http au-dessus du budget : ${http_edges} > ${HTTP_ISOLATION_BUDGET}." >&2
  status=1
fi

if (( status == 0 )); then
  echo "✅ Dette d'isolation sous plafond et plafond à jour."
fi

exit "${status}"
