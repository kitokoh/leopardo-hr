#!/usr/bin/env bash
# check-module-route-middleware.sh — garde CI issue #7959 (point 4)
#
# « Unifier le mécanisme de chargement des routes modules avec middleware de
# groupe imposé. »
#
# Constat de l'audit : 41 fichiers `api/routes/modules/*.php` recopiaient
# chacun la même pile de middlewares littérale
#
#     Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh',
#                        'tenant', 'throttle:api-plan'])
#
# Une pile recopiée 41 fois ne se met pas à jour : ajouter ou retirer un
# middleware transverse demande 41 corrections, et l'oubli d'un seul fichier
# laisse une route silencieusement moins protégée que ses voisines.
#
# Depuis #7959, la pile vit dans `App\Support\Routing\ModuleRoutes`
# (`ModuleRoutes::tenantModule('prefix', fn () => ...)`). Cette garde interdit
# la réintroduction de la pile littérale dans `routes/modules/`, pour que
# l'unification ne se re-défasse pas au prochain module ajouté.
#
# Elle n'interdit PAS un `Route::middleware([...])` légitime et différent
# (routes publiques, webhooks entrants, kiosque) : seuls les fichiers qui
# redéclarent la pile tenant *complète* sont signalés.
#
# Usage : bash dev-hub/tools/check-module-route-middleware.sh [api_dir]
# Exit codes : 0 = OK, 1 = pile dupliquée trouvée.

set -euo pipefail

API_DIR="${1:-api}"
MODULES_DIR="${API_DIR}/routes/modules"

if [[ ! -d "${MODULES_DIR}" ]]; then
  echo "!!  Répertoire '${MODULES_DIR}' introuvable." >&2
  exit 1
fi

# Motif de la pile tenant littérale EXACTE, dans l'ordre historique.
#
# Le `\]` final est essentiel : un module qui écrit la pile PUIS un middleware
# propre (`..., 'throttle:api-plan', 'module.cameras', 'api.manager']`) ne
# « recopie » pas la pile au sens de cette garde — il en hérite et l'étend,
# ce qui est le cas d'usage de `tenantModuleWith()`. Ne signaler que la liste
# exacte évite de crier sur ~21 fichiers légitimes et de rendre la garde
# inaudible (leçon de conception de `check-tests-no-frozen-dates.sh`, #8396).
# Les espaces internes sont tolérés : un reformatage ne doit pas suffire à
# passer.
PATTERN="throttle:api'[, ]+'auth:sanctum'[, ]+'token\.refresh'[, ]+'tenant'[, ]+'throttle:api-plan'\]"

if ! command -v grep >/dev/null 2>&1; then
  echo "!!  grep introuvable." >&2
  exit 1
fi

FOUND=$(grep -rEl "${PATTERN}" "${MODULES_DIR}" --include='*.php' || true)

if [[ -z "${FOUND}" ]]; then
  echo "OK  Aucune pile de middlewares tenant dupliquée dans ${MODULES_DIR} (garde #7959)."
  exit 0
fi

echo "x  Pile de middlewares tenant redéclarée à la main (issue #7959, point 4) :"
echo
while IFS= read -r f; do
  [[ -z "${f}" ]] && continue
  echo "     ${f}"
  grep -nE "${PATTERN}" "${f}" | sed 's/^/        /' || true
done <<< "${FOUND}"
echo
echo "  Cette pile est désormais définie en UN seul endroit :"
echo "      api/app/Support/Routing/ModuleRoutes.php"
echo
echo "  Remplacez le groupe littéral par l'appel au helper :"
echo
echo "      ModuleRoutes::tenantModule('mon-prefixe', function (): void {"
echo "          // ... routes ..."
echo "      });"
echo
echo "  Pour ajouter un middleware à la pile, utilisez"
echo "  ModuleRoutes::tenantModuleWith() plutôt que de recopier la liste :"
echo "  un module qui recopie la pile peut en oublier un maillon."
exit 1
