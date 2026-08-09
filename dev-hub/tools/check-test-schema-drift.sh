#!/usr/bin/env bash
# check-test-schema-drift.sh — Mesure la dérive entre les migrations réelles et
# le schéma de test manuel CreatesMvpSchema (issues #1489, #1586).
#
# Usage : dev-hub/tools/check-test-schema-drift.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#
# Mode RAPPORT (non bloquant) : liste les tables créées par les migrations
# (database/migrations/public + tenant) absentes du schéma manuel
# api/tests/Support/CreatesMvpSchema.php. Exit 0 toujours ; les tables
# manquantes sont affichées en warning ::warning::.
#
# Détection des tables :
#   - côté migrations : `Schema::create('name')` (public/tenant)
#   - côté fixture : `Schema::create('name')` (public) et les appels dynamiques
#     `Schema::create($this->tenantTable('name'))` / `moduleTable('name')`
#     (tenant) — corrigé dans l'issue #1586 : sans ces appels dynamiques, la
#     dérive était artificiellement surévaluée (tables tenant comptées absentes
#     alors qu'elles sont créées via le helper).
#
# L'objectif à terme : supprimer CreatesMvpSchema au profit des vraies
# migrations (RefreshTenantDatabase) — voir issues #1489 / #1543 (F-13).
set -uo pipefail
# ── Mode rapport F-13 (#1543) : % tests du noyau sur les vraies migrations ──
if [[ "${1:-}" == "--report-f13" ]]; then
  API_DIR="${2:-api}"
  FEATURE_DIR="${API_DIR}/tests/Feature"
  CORE_MODULES="Payroll HR Attendance SmartAttendance Contracts"
  total=0; real=0; manual=0
  for mod in $CORE_MODULES; do
    dir="${FEATURE_DIR}/${mod}"
    [[ -d "${dir}" ]] || continue
    while IFS= read -r f; do
      total=$((total + 1))
      if grep -q "RefreshTenantDatabase" "${f}"; then
        real=$((real + 1))
      elif grep -q "CreatesMvpSchema" "${f}"; then
        manual=$((manual + 1))
      else
        real=$((real + 1))   # ni l'un ni l'autre (Golden, unit-like) => pas de schéma manuel
      fi
    done < <(find "${dir}" -name "*Test.php" | sort)
  done
  pct=0
  if [[ "${total}" -gt 0 ]]; then
    pct=$((real * 100 / total))
  fi
  echo "F-13 (#1543) — tests du noyau sur vraies migrations :"
  echo "  total=${total} refresh=${real} manual=${manual}  =>  ${pct}% sur migrations réelles (cible ≥ 80%)"
  if [[ "${pct}" -ge 80 ]]; then
    echo "  OK Seuil F-13 atteint (≥ 80%)."
  else
    echo "  WARN Seuil F-13 non atteint — reste ${manual} fichier(s) sur le schéma manuel."
  fi
  exit 0
fi

API_DIR="${1:-api}"

if [[ ! -d "${API_DIR}/database/migrations" ]]; then
  echo "::error::${API_DIR}/database/migrations introuvable"
  exit 1
fi

# --- Côté migrations ---------------------------------------------------------
# Tables public : `Schema::create('x')` dans database/migrations/public (+ racine)
migrated_public=$(grep -rhoE "Schema::create\(['\"][a-z_]+" "${API_DIR}/database/migrations" \
                  | sed -E "s/Schema::create\(['\"]//" | sort -u)
# Tables tenant : `Schema::create('x')` dans database/migrations/tenant
migrated_tenant=$(grep -rhoE "Schema::create\(['\"][a-z_]+" "${API_DIR}/database/migrations/tenant" \
                  | sed -E "s/Schema::create\(['\"]//" | sort -u)
# Les tables tenant sont aussi détectées par le scan global ; on les retire
# du groupe public pour une classification nette.
migrated_public=$(comm -23 <(echo "${migrated_public}") <(echo "${migrated_tenant}"))

# --- Côté fixture CreatesMvpSchema -------------------------------------------
FIXTURE="${API_DIR}/tests/Support/CreatesMvpSchema.php"
fixture_public=""
fixture_tenant=""
if [[ -f "${FIXTURE}" ]]; then
  # public : Schema::create('x')
  fixture_public=$(grep -rhoE "Schema::create\(['\"][a-z_]+" "${FIXTURE}" \
                   | sed -E "s/Schema::create\(['\"]//" | sort -u)
  # tenant : Schema::create($this->tenantTable('x')) / moduleTable('x')
  fixture_tenant=$(grep -rhoE "(tenantTable|moduleTable)\(['\"][a-z_]+" "${FIXTURE}" \
                   | sed -E "s/(tenantTable|moduleTable)\(['\"]//" | sort -u)
fi

missing_public=$(comm -23 <(echo "${migrated_public}") <(echo "${fixture_public}"))
missing_tenant=$(comm -23 <(echo "${migrated_tenant}") <(echo "${fixture_tenant}"))

n_public=$(grep -c . <<< "${missing_public}" 2>/dev/null || true)
n_tenant=$(grep -c . <<< "${missing_tenant}" 2>/dev/null || true)
total=$((n_public + n_tenant))

if [[ "${total}" -gt 0 ]]; then
  echo "::warning::Drift schéma de test (issues #1489/#1586) : ${total} table(s) créée(s) par les migrations mais absentes de CreatesMvpSchema :"
  if [[ "${n_public}" -gt 0 ]]; then
    echo "::warning::  [public] ${n_public} manquante(s) :"
    printf '::warning::    - %s\n' "${missing_public}"
  fi
  if [[ "${n_tenant}" -gt 0 ]]; then
    echo "::warning::  [tenant] ${n_tenant} manquante(s) :"
    printf '::warning::    - %s\n' "${missing_tenant}"
  fi
  echo "→ ces tables ne sont pas couvertes par les tests utilisant CreatesMvpSchema."
  echo "→ cible : migrer les tests vers RefreshTenantDatabase (migrations réelles, F-13 #1543)."
else
  echo "✓ Schéma de test aligné sur les migrations ($(grep -c . <<< "${migrated_public}") tables public + $(grep -c . <<< "${migrated_tenant}") tables tenant)."
fi

exit 0
