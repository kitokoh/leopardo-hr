#!/usr/bin/env bash
#
# check-providers-syntax.sh — garde CI « providers sains » (issue #5519).
#
# Exécute `php -l` sur tous les *ServiceProvider.php du backend : un bloc
# dupliqué ou une accolade parasite (régression merge #5377, corrigée 2× en
# #5495) casse artisan, PHPStan et les tests — ce garde l'attrape à la PR.
#
# Usage : bash dev-hub/tools/check-providers-syntax.sh [api_dir]
#         PHP_BIN env : binaire php alternatif (tests).
# Tests : bash dev-hub/tools/tests/check-providers-syntax.test.sh
#
set -euo pipefail

API_DIR="${1:-api}"
PHP_BIN="${PHP_BIN:-php}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

if ! command -v "${PHP_BIN}" >/dev/null 2>&1 && [[ ! -x "${PHP_BIN}" ]]; then
  echo "::error::Binaire PHP introuvable (PHP_BIN=${PHP_BIN}) — garde check-providers-syntax (issue #5519)." >&2
  exit 1
fi

mapfile -t PROVIDERS < <(find "${API_DIR}/app" -name "*ServiceProvider.php" | sort)
if [[ ${#PROVIDERS[@]} -eq 0 ]]; then
  echo "::error::Aucun *ServiceProvider.php trouvé sous ${API_DIR}/app (issue #5519)." >&2
  exit 1
fi

errors=0
for f in "${PROVIDERS[@]}"; do
  if ! "${PHP_BIN}" -l "${f}" >/dev/null 2>&1; then
    echo "::error::Provider avec erreur de syntaxe : ${f} (issue #5519)." >&2
    "${PHP_BIN}" -l "${f}" 2>&1 | tail -2 >&2 || true
    errors=$((errors + 1))
  fi
done

if [[ "${errors}" -eq 0 ]]; then
  echo "✅ ${#PROVIDERS[@]} ServiceProvider.php syntaxiquement sains."
  exit 0
fi

echo "::error::${errors} provider(s) cassé(s) — le bootstrap artisan/PHPStan/tests est en danger (issue #5519)." >&2
exit 1
