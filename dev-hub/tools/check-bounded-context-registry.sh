#!/usr/bin/env bash
# check-bounded-context-registry.sh — Garde CI « Registre des bounded contexts » (MAT-001, issue #5859)
#
# Vérifie la cohérence entre le registre canonique machine-readable
# (dev-hub/governance/bounded-context-registry.json) et l'état réel du repo :
#
#   1. Structure JSON valide + champs obligatoires présents (code, name,
#      owner, responsibility, priority, paths) + codes uniques.
#   2. Intégrité des dépendances : chaque `dependencies` référence un code
#      existant du registre.
#   3. Chemins `status=active` : doivent exister dans le dépôt ; les chemins
#      `status=planned` peuvent ne pas exister encore.
#   4. Couverture : tout répertoire métier présent (api/app/Modules/*,
#      api/app/Solutions/*, api/app/Core/{Auth,Tenant,Feature},
#      api/app/Contracts/*, api/app/Policies, api/app/Jobs, api/app/AI) doit
#      être déclaré par au moins un BC — sinon le guard échoue avec un
#      message actionnable (« ajoute le chemin au BC correspondant »).
#   5. CODEOWNERS cohérent : tout chemin actif du registre a une ligne
#      CODEOWNERS dédiée ; toute ligne CODEOWNERS Modules/Core/Contracts/AI
#      correspond à un chemin actif du registre (hors exceptions partagées).
#   6. Routes et répertoires de migrations déclarés : doivent exister.
#
# Usage : dev-hub/tools/check-bounded-context-registry.sh [repo_root]
# Prérequis : bash, jq, grep, find.
# Exit codes : 0 = OK, 1 = violation.

set -uo pipefail

ROOT="${1:-.}"
REGISTRY="${ROOT}/dev-hub/governance/bounded-context-registry.json"
CODEOWNERS="${ROOT}/CODEOWNERS"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre introuvable : ${REGISTRY} (MAT-001 / issue #5859)." >&2
  exit 1
fi

ERRORS=0
fail() {
  ERRORS=$((ERRORS + 1))
  echo "::error::${1}" >&2
}

# ── 1. Structure JSON + champs obligatoires + codes uniques ─────────────────
if ! jq -e . "${REGISTRY}" >/dev/null 2>&1; then
  echo "::error::Registre JSON invalide : ${REGISTRY}" >&2
  exit 1
fi

CODES=$(jq -r '.bounded_contexts[].code' "${REGISTRY}")
DUPES=$(printf '%s\n' "${CODES}" | sort | uniq -d)
if [[ -n "${DUPES}" ]]; then
  fail "Codes de bounded context dupliqués dans le registre : $(echo "${DUPES}" | tr '\n' ' ')"
fi

REQUIRED_FIELDS="code name context responsibility owner priority status paths"
while IFS= read -r idx; do
  for field in ${REQUIRED_FIELDS}; do
    if ! jq -e --argjson i "${idx}" --arg f "${field}" '.bounded_contexts[$i] | has($f)' "${REGISTRY}" >/dev/null 2>&1 \
       || ! jq -e --argjson i "${idx}" --arg f "${field}" '.bounded_contexts[$i][$f] | if type=="array" then length > 0 else . != null and . != "" end' "${REGISTRY}" >/dev/null 2>&1; then
      code=$(jq -r --argjson i "${idx}" '.bounded_contexts[$i].code // "??"' "${REGISTRY}")
      fail "BC ${code} (entrée ${idx}) : champ obligatoire '${field}' absent ou vide."
    fi
  done
  if ! jq -e --argjson i "${idx}" '.bounded_contexts[$i].paths | any(.status == "active" or .status == "planned")' "${REGISTRY}" >/dev/null 2>&1; then
    code=$(jq -r --argjson i "${idx}" '.bounded_contexts[$i].code // "??"' "${REGISTRY}")
    fail "BC ${code} : chaque chemin doit porter un status 'active' ou 'planned'."
  fi
done < <(jq -r '.bounded_contexts | to_entries[] | .key' "${REGISTRY}")

# ── 2. Intégrité des dépendances ─────────────────────────────────────────────
while IFS=$'\t' read -r code dep; do
  if ! grep -qx "${dep}" <<<"${CODES}"; then
    fail "BC ${code} : dépendance '${dep}' inconnue dans le registre."
  fi
done < <(jq -r '.bounded_contexts[] as $bc | $bc.dependencies[]? | [$bc.code, .] | @tsv' "${REGISTRY}")

# ── 3. Chemins actifs doivent exister ────────────────────────────────────────
while IFS=$'\t' read -r code path status; do
  if [[ "${status}" == "active" && ! -d "${ROOT}/${path}" ]]; then
    fail "BC ${code} : chemin actif introuvable — ${path}"
  fi
done < <(jq -r '.bounded_contexts[] as $bc | $bc.paths[] | [$bc.code, .path, .status] | @tsv' "${REGISTRY}")

# ── 4. Couverture : tout répertoire métier doit être déclaré ─────────────────
claimed_tsv=$(jq -r '.bounded_contexts[].paths[] | select(.status == "active") | .path' "${REGISTRY}" | sort -u)
claim() { grep -Fxq "$1" <<<"${claimed_tsv}"; }

coverage_dirs() {
  find "${ROOT}/api/app/Modules" -maxdepth 1 -mindepth 1 -type d -printf 'api/app/Modules/%f\n' 2>/dev/null | sort
  if [[ -d "${ROOT}/api/app/Solutions" ]]; then
    find "${ROOT}/api/app/Solutions" -maxdepth 1 -mindepth 1 -type d -printf 'api/app/Solutions/%f\n' 2>/dev/null | sort
  fi
  for d in api/app/Core/Auth api/app/Core/Tenant api/app/Core/Feature api/app/AI api/app/Jobs api/app/Policies; do
    [[ -d "${ROOT}/${d}" ]] && echo "${d}"
  done
  find "${ROOT}/api/app/Contracts" -maxdepth 1 -mindepth 1 -type d -printf 'api/app/Contracts/%f\n' 2>/dev/null | sort
}

while IFS= read -r d; do
  [[ -z "${d}" ]] && continue
  # Chemins partagés documentés (hors propriété BC unique)
  if jq -e --arg p "${d}" '._shared_exceptions | any(.path == $p)' "${REGISTRY}" >/dev/null 2>&1; then
    continue
  fi
  if ! claim "${d}"; then
    fail "Répertoire métier non déclaré dans le registre : ${d} → ajoute-le au BC correspondant dans dev-hub/governance/bounded-context-registry.json (MAT-001)."
  fi
done < <(coverage_dirs)

# ── 5. CODEOWNERS cohérent ───────────────────────────────────────────────────
if [[ ! -f "${CODEOWNERS}" ]]; then
  fail "CODEOWNERS introuvable : ${CODEOWNERS}"
else
  # 5a. Tout chemin actif du registre a une ligne CODEOWNERS dédiée
  while IFS=$'\t' read -r code path; do
    pattern="/${path}/"
    if ! grep -qE "^${pattern//./\\.}[[:space:]]" "${CODEOWNERS}"; then
      fail "BC ${code} : chemin actif sans ligne CODEOWNERS dédiée — ajoute '${pattern} @kitokoh' dans CODEOWNERS."
    fi
  done < <(jq -r '.bounded_contexts[] as $bc | $bc.paths[] | select(.status == "active") | [$bc.code, .path] | @tsv' "${REGISTRY}")

  # 5b. Toute ligne CODEOWNERS Modules/Core/Contracts/AI/Jobs/Policies correspond à un chemin actif
  while IFS= read -r line; do
    pattern=$(awk '{print $1}' <<<"${line}")
    rel="${pattern#/}"
    rel="${rel%/}"
    if jq -e --arg p "${rel}" '._shared_exceptions | any(.path == $p)' "${REGISTRY}" >/dev/null 2>&1; then
      continue
    fi
    if ! grep -Fxq "${rel}" <<<"${claimed_tsv}"; then
      fail "Ligne CODEOWNERS sans BC actif correspondant : ${pattern} → ajoute '${rel}' au registre (status active) ou retire la ligne."
    fi
  done < <(grep -E '^/api/app/(Modules/[^/]+|Core/[^/]+|Contracts/[^/]+|AI|Jobs|Policies)/' "${CODEOWNERS}")
fi

# ── 6. Routes et migrations déclarées doivent exister ────────────────────────
while IFS=$'\t' read -r code r; do
  if [[ ! -f "${ROOT}/${r}" ]]; then
    fail "BC ${code} : fichier de routes déclaré introuvable — ${r}"
  fi
done < <(jq -r '.bounded_contexts[] as $bc | $bc.routes[]? | [$bc.code, .] | @tsv' "${REGISTRY}")

while IFS=$'\t' read -r code m; do
  if [[ ! -d "${ROOT}/${m}" ]]; then
    fail "BC ${code} : répertoire de migrations déclaré introuvable — ${m}"
  fi
done < <(jq -r '.bounded_contexts[] as $bc | $bc.migrations[]? | [$bc.code, .] | @tsv' "${REGISTRY}")

# ── Résultat ─────────────────────────────────────────────────────────────────
if [[ "${ERRORS}" -gt 0 ]]; then
  echo "::error::Registre des bounded contexts (MAT-001 / #5859) : ${ERRORS} violation(s)." >&2
  exit 1
fi

echo "✓ Registre des bounded contexts cohérent (MAT-001 / #5859) : $(jq '.bounded_contexts | length' "${REGISTRY}") BCs, chemins, routes, migrations, CODEOWNERS."
exit 0
