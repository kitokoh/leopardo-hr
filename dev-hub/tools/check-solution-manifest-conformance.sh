#!/usr/bin/env bash
# check-solution-manifest-conformance.sh — Garde CI « manifest de solution
# conforme au contrat Core et enregistré » (BOS-014, issue #8201).
#
# Verrouille l'anti-pattern #7220-bis (manifest sur un contrat LOCAL dupliqué,
# non conforme et/ou non enregistré au catalogue — vécu par TravelAgency,
# puis Delivery et RestaurantManager) :
#
#   1. CONTRAT CORE : tout fichier `*Manifest.php` de `api/app/Modules/*`
#      implémentant un `SolutionManifest` DOIT importer et viser le contrat
#      Core `App\Core\Solutions\Contracts\SolutionManifest` (FQCN ou import).
#   2. PAS DE NOUVEAU CONTRAT LOCAL : aucun
#      `api/app/Modules/*/Domain/Contracts/SolutionManifest.php` hors
#      allowlist legacy (Delivery, RestaurantManager — conservés DEPRECATED
#      le temps de la validation des activations, note de rollback de
#      l'issue ; leur retrait est une issue de suivi).
#   3. ENREGISTREMENT : le code déclaré par chaque manifest doit être
#      enregistré au `SolutionCatalogue` (`$catalogue->register('<code>'` ou
#      `->register(XxxManifest::CODE` + `public const CODE = '<code>'`) dans
#      un provider du module.
#
# Usage : dev-hub/tools/check-solution-manifest-conformance.sh [repo_root]
#         dev-hub/tools/check-solution-manifest-conformance.sh --self-test
# Prérequis : bash, grep, find, sed.
# Exit codes : 0 = OK, 1 = violation.

set -uo pipefail

fail() {
  ERRORS=$((ERRORS + 1))
  echo "::error::$1" >&2
}

# ── Self-test ────────────────────────────────────────────────────────────────
if [[ "${1:-}" == "--self-test" ]]; then
  TMP="$(mktemp -d)"
  trap 'rm -rf "${TMP}"' EXIT
  PROBE="${TMP}/probe/api/app/Modules/Probe/Domain"
  mkdir -p "${PROBE}/Manifests" "${PROBE}/Contracts" "${TMP}/probe/api/app/Modules/Probe/Providers"

  # (a) manifest sur contrat local + non enregistré → doit échouer (×3 règles)
  cat > "${PROBE}/Manifests/ProbeManifest.php" <<'PHP'
<?php
use App\Modules\Probe\Domain\Contracts\SolutionManifest;
final class ProbeManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'probe';
    }
}
PHP
  cat > "${PROBE}/Contracts/SolutionManifest.php" <<'PHP'
<?php
interface SolutionManifest {}
PHP

  if bash "$0" "${TMP}/probe" >/dev/null 2>&1; then
    echo "::error::self-test ÉCHEC : la sonde non conforme n'a PAS été détectée." >&2
    exit 1
  fi
  echo "✅ self-test OK : sonde non conforme détectée (contrat local + non enregistré)."
  exit 0
fi

ROOT="${1:-.}"
MODULES_DIR="${ROOT}/api/app/Modules"

if [[ ! -d "${MODULES_DIR}" ]]; then
  echo "::error::Répertoire modules introuvable : ${MODULES_DIR} (lancer depuis la racine du dépôt)." >&2
  exit 1
fi

ERRORS=0

# ── Règle 2 : aucun NOUVEAU contrat local de manifest ───────────────────────
# Allowlist legacy (BOS-014, note de rollback : conservés DEPRECATED jusqu'à
# validation des activations) — toute NOUVELLE entrée est refusée.
LEGACY_LOCAL_CONTRACTS="api/app/Modules/Delivery/Domain/Contracts/SolutionManifest.php api/app/Modules/RestaurantManager/Domain/Contracts/SolutionManifest.php"

while IFS= read -r -d '' contract; do
  rel="${contract#"${ROOT}"/}"
  if [[ " ${LEGACY_LOCAL_CONTRACTS} " != *" ${rel} "* ]]; then
    fail "Contrat LOCAL de manifest interdit : ${rel} — les manifests implémentent le contrat Core App\\Core\\Solutions\\Contracts\\SolutionManifest (BOS-014, anti-pattern #7220-bis)."
  fi
done < <(find "${MODULES_DIR}" -path '*/Domain/Contracts/SolutionManifest.php' -print0 2>/dev/null)

# ── Règles 1 & 3 : contrat Core + enregistrement catalogue ──────────────────
while IFS= read -r -d '' manifest; do
  rel="${manifest#"${ROOT}"/}"

  # Fichier concerné : implémente un SolutionManifest (quel qu'il soit).
  if ! grep -qE 'implements[[:space:]]+(\\?[A-Za-z\\]+[\\,])?[[:space:]]*SolutionManifest|implements[[:space:]]+SolutionManifest' "${manifest}"; then
    continue
  fi

  # Règle 1 : le contrat Core doit être importé/visé.
  if ! grep -q 'App\\Core\\Solutions\\Contracts\\SolutionManifest' "${manifest}"; then
    fail "Manifest non conforme au contrat Core v2 : ${rel} — importer App\\Core\\Solutions\\Contracts\\SolutionManifest (BOS-014)."
  fi

  class_name="$(basename "${manifest}" .php)"

  # Extraction du code déclaré : priorité à `public const CODE = '<code>'`,
  # sinon le `return '<code>';` de la méthode code().
  code=""
  if grep -qE "const CODE = '[a-z0-9_]+';" "${manifest}"; then
    code="$(grep -oE "const CODE = '[a-z0-9_]+';" "${manifest}" | head -1 | sed "s/const CODE = '//; s/';//")"
  else
    code="$(awk '/public function code\(\): string/{f=1} f && /return '\''[a-z0-9_]+'\'';/{print; exit}' "${manifest}" | sed "s/.*return '//; s/';.*//")"
  fi

  if [[ -z "${code}" ]]; then
    fail "Code de solution illisible dans ${rel} — déclarer `public const CODE = '<code>'` ou un `return '<code>';` simple dans code()."
    continue
  fi

  # Règle 3 : enregistrement au catalogue dans un provider du module.
  module_dir="$(dirname "${rel}" | cut -d/ -f1-4)"
  providers_dir="${ROOT}/${module_dir}/Providers"

  registered=0
  if [[ -d "${providers_dir}" ]]; then
    if grep -rEq -- "->register\(\s*'${code}'" "${providers_dir}" \
      || { grep -rEq -- "->register\(\s*${class_name}::CODE" "${providers_dir}" && grep -qE "const CODE = '${code}';" "${manifest}"; }; then
      registered=1
    fi
  fi

  if [[ "${registered}" -eq 0 ]]; then
    fail "Manifest non enregistré au SolutionCatalogue : ${rel} (code '${code}') — ajouter \`\$catalogue->register('${code}', ...)\` dans le provider du module (BOS-014)."
  fi
done < <(find "${MODULES_DIR}" -name '*Manifest.php' -print0 2>/dev/null)

if [[ ${ERRORS} -gt 0 ]]; then
  echo "" >&2
  echo "Found ${ERRORS} solution-manifest conformance violation(s) — BOS-014 / issue #8201." >&2
  exit 1
fi

echo "✅ All solution manifests conform to the Core contract and are registered (BOS-014)."
