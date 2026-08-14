#!/usr/bin/env bash
# Issue #1875 — garde CI du catalogue pays multi-pays (Playbook d'onboarding).
#
# Vérifie que le catalogue des règles de paie reste complet et sans doublon :
#   1. AUCUN doublon ISO entre classes dédiées et listes de membres de zone
#      (CEMAC/CEDEAO) — deux implémentations concurrentes pour un même code
#      seraient une régression silencieuse (le résolveur n'en voit qu'une).
#   2. REGISTRE complet : chaque pays à règles a une entrée CountryDefaults
#      (label/langue/devise/fuseau) — sinon le provisioning du tenant échoue
#      ou retombe sur un fallback silencieux.
#   3. FICHE PAYS : chaque pays `pilot`/`production` doit avoir
#      `docs/payroll/<CC>_COMPLIANCE.md` (valeurs légales sourcées).
#   4. GOLDEN : chaque pays `pilot`/`production` doit avoir un fichier
#      `api/tests/Feature/Payroll/Golden/Golden<CC>PayrollTest.php`
#      (cas calculés à la main, issue #1938).
#
# Allowlist héritée (dette documentée, suivi #1875/#1904) : MA/TN/FR/TR sont
# `pilot` mais pré-datent le playbook (pas de fiche pays ni de golden dédiés).
# Tout NOUVEAU pays (hors allowlist) doit satisfaire 1-4 dans la même PR.
#
# Usage : dev-hub/tools/check-country-catalog.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RULES_DIR="${ROOT}/api/app/Modules/Payroll/Infrastructure/Services/CountryRules"
DOCS_DIR="${ROOT}/docs/payroll"
GOLDEN_DIR="${ROOT}/api/tests/Feature/Payroll/Golden"
COUNTRY_DEFAULTS="${ROOT}/api/app/Support/CountryDefaults.php"

# Pays `pilot`/`production` historiques sans fiche/golden (dette #1875/#1904).
LEGACY_ALLOWLIST="MA TN FR TR"

FAIL=0

# ---------------------------------------------------------------------------
# 1. Extraire le registre pays → niveau de confiance depuis le code (source
#    de vérité : CountryRulesResolver + classes de règles).
# ---------------------------------------------------------------------------

# Classes dédiées : countryCode() retourne un littéral + confidenceLevel()
# retourne 'pilot'|'placeholder'|'production' en littéral.
declare -A DEDICATED_LEVEL=()
for F in "${RULES_DIR}"/*.php; do
  CODE="$(sed -n '/function countryCode/,/^    }/p' "$F" | grep -oE "return '[A-Z]{2}'" | head -1 | sed -nE "s/return '([A-Z]{2})'/\1/p" || true)"
  [[ -z "${CODE}" ]] && continue
  LEVEL="$(sed -n '/function confidenceLevel/,/^    }/p' "$F" | grep -oE "return '(pilot|placeholder|production)'" | head -1 | sed -nE "s/return '([a-z]+)'/\1/p" || true)"
  DEDICATED_LEVEL["${CODE}"]="${LEVEL:-placeholder}"
done

# Classes zone : les membres `pilot`/`production` vivent dans le
# `return in_array($this->memberCountryCode, [...])` de confidenceLevel().
declare -A ZONE_MEMBER_LEVEL=()
for ZONE in CedeaoPayrollRules CemacPayrollRules; do
  ZF="${RULES_DIR}/${ZONE}.php"
  MEMBERS="$(sed -nE "s/.*const MEMBER_COUNTRY_CODES = \\['([A-Z', ]+)'\\].*/\\1/p" "$ZF" | tr -d "'" | tr ',' ' ' || true)"
  PILOT_BLOCK="$(sed -n '/function confidenceLevel/,/^    }/p' "$ZF" | grep -oE "in_array\(\\\$this->memberCountryCode, \\['[A-Z', ]+\\]" | head -1 || true)"
  PILOT_MEMBERS="$(echo "${PILOT_BLOCK}" | sed -nE "s/.*\\['([A-Z', ]+)\\]/\\1/p" | tr -d "'" | tr ',' ' ' || true)"
  for M in ${MEMBERS}; do
    if echo " ${PILOT_MEMBERS} " | grep -q " ${M} "; then
      ZONE_MEMBER_LEVEL["${M}"]="pilot"
    else
      ZONE_MEMBER_LEVEL["${M}"]="placeholder"
    fi
  done
done

# ---------------------------------------------------------------------------
# 2. Vérifications
# ---------------------------------------------------------------------------

ALL_CODES="$(printf '%s\n' "${!DEDICATED_LEVEL[@]}" "${!ZONE_MEMBER_LEVEL[@]}" | sort)"

# --- 2.1 Aucun doublon ISO entre classes dédiées et membres de zone ---
DUPES="$(printf '%s\n' "${!DEDICATED_LEVEL[@]}" "${!ZONE_MEMBER_LEVEL[@]}" | sort | uniq -d || true)"
if [[ -n "${DUPES}" ]]; then
  echo "::error::Doublons de code pays entre classes de règles (issue #1875) :"
  echo "${DUPES}" | sed 's/^/  /'
  echo "  → une seule implémentation par ISO (classe dédiée OU membre de zone)."
  FAIL=1
fi

# --- 2.2 Registre complet (CountryDefaults) ---
DEFAULTS_KEYS="$(sed -nE "s/^\\s*'([A-Z]{2})' => \\[.*/\\1/p" "${COUNTRY_DEFAULTS}" || true)"
MISSING_REGISTRY=0
while IFS= read -r C; do
  [[ -z "${C}" ]] && continue
  if ! echo "${DEFAULTS_KEYS}" | grep -qx "${C}"; then
    echo "::error::Pays ${C} : aucune entrée dans CountryDefaults (label/langue/devise/fuseau manquants — issue #1875)."
    MISSING_REGISTRY=1
  fi
done <<< "${ALL_CODES}"
if [[ "${MISSING_REGISTRY}" -eq 1 ]]; then FAIL=1; fi

# --- 2.3 Fiche pays + 2.4 golden pour les pays pilot/production ---
while IFS= read -r C; do
  [[ -z "${C}" ]] && continue
  LEVEL="${DEDICATED_LEVEL[${C}]:-${ZONE_MEMBER_LEVEL[${C}]:-}}"
  [[ "${LEVEL}" == "placeholder" || -z "${LEVEL}" ]] && continue
  if echo "${LEGACY_ALLOWLIST}" | grep -qw "${C}"; then
    echo "::warning::Pays ${C} (${LEVEL}) : fiche/golden non requis (allowlist historique — dette #1875/#1904)."
    continue
  fi
  if [[ ! -f "${DOCS_DIR}/${C}_COMPLIANCE.md" ]]; then
    echo "::error::Pays ${C} (${LEVEL}) : fiche pays obligatoire manquante — docs/payroll/${C}_COMPLIANCE.md (playbook #1875)."
    FAIL=1
  fi
  if [[ ! -d "${GOLDEN_DIR}" ]]; then
    echo "::error::Dossier golden introuvable : ${GOLDEN_DIR}"
    FAIL=1
  elif ! find "${GOLDEN_DIR}" -maxdepth 1 -name "Golden*PayrollTest.php" -printf '%f\n' | grep -qi "Golden.*${C}.*PayrollTest"; then
    echo "::error::Pays ${C} (${LEVEL}) : golden test obligatoire manquant — Golden<CC>PayrollTest.php (issue #1875/#1938)."
    FAIL=1
  fi
done <<< "${ALL_CODES}"

if [[ "${FAIL}" -eq 1 ]]; then
  echo ""
  echo "Catalogue pays incomplet — suivre docs/specifications/PAYS_ONBOARDING_PLAYBOOK.md."
  exit 1
fi

NB="$(echo "${ALL_CODES}" | grep -c .)"
echo "✅ Catalogue pays OK (${NB} codes, doublons=0, registre complet, fiches+goldens couverts)."
