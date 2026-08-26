#!/usr/bin/env bash
# check-mvp-schema-parity.sh — Garde de parité CreatesMvpSchema ↔ migrations tenant
# (anti-régression « relation ... does not exist », issue #5443, suite #5418).
#
# Pourquoi : la fixture manuelle api/tests/Support/CreatesMvpSchema.php sert encore
# à une partie des tests Feature (RBAC, HR étendu...). Quand une migration tenant
# crée une table absente de la fixture, les tests qui passent par CreatesMvpSchema
# explosent en CI avec SQLSTATE 42P01 « relation "x" does not exist » — la
# régression n'était visible qu'en CI (cas #5418 : employee_documents).
# check-test-schema-drift.sh mesure la dérive en WARNING non bloquant ; ce garde-ci
# rend BLOQUANTE toute NOUVELLE table tenant récente absente de la fixture.
#
# Usage : dev-hub/tools/check-mvp-schema-parity.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#   MVP_SCHEMA_PARITY_CUTOFF : date butoir des migrations scrutées (défaut 2026-06-01,
#   format YYYY-MM-DD — les tables antérieures sont de la dette pré-existante #1489/#1586,
#   hors périmètre de ce garde).
#
# Règles :
#   1. Collecte les tables créées (Schema::create) et altérées (Schema::table)
#      par les migrations tenant dont la date de préfixe >= cutoff.
#   2. Tables présentes dans la fixture : Schema::create('x') (public) et
#      tenantTable('x') / moduleTable('x') (tenant).
#   3. Une table créée récente ABSENTE de la fixture :
#        - allowlist datée (dérive mesurée le 2026-08-25, à résorber via F-13 #1543) -> ::warning
#        - couverte par RefreshTenantDatabase dans au moins un test existant -> ::warning
#        - sinon -> ::error + exit 1 (la PR doit ajouter la table à la fixture)
#   4. Une table seulement ALTERÉE récente (pas créée par une migration >= cutoff),
#      absente de la fixture -> ::warning informatif (table pré-existante).
#
# Anti-régression #5443 : toute migration tenant récente ajoutant une table doit
# l'ajouter à CreatesMvpSchema (pattern moduleTable(), cf. employee_documents dans
# createPostSprintModuleTables()) dans la même PR.
set -uo pipefail

API_DIR="${1:-api}"
CUTOFF="${MVP_SCHEMA_PARITY_CUTOFF:-2026-06-01}"
TENANT_DIR="${API_DIR}/database/migrations/tenant"
FIXTURE="${API_DIR}/tests/Support/CreatesMvpSchema.php"

if [[ ! -d "${TENANT_DIR}" ]]; then
  echo "::error::${TENANT_DIR} introuvable"
  exit 1
fi
if [[ ! -f "${FIXTURE}" ]]; then
  echo "::error::${FIXTURE} introuvable — la garde ne peut pas vérifier la parité"
  exit 1
fi

# ── 1. Tables créées/altérées par les migrations tenant >= cutoff ─────────────
created_recent=""
altered_recent=""
while IFS= read -r f; do
  date_prefix=$(basename "${f}" | grep -oE '^[0-9]{4}_[0-9]{2}_[0-9]{2}')
  [[ -n "${date_prefix}" ]] || continue
  norm=$(printf '%s' "${date_prefix}" | tr '_' '-')
  if [[ "${norm}" < "${CUTOFF}" ]]; then
    continue
  fi
  created_recent+=$(grep -rhoE "Schema::create\(\s*['\"][a-z_]+" "${f}" \
                    | sed -E "s/Schema::create\(\s*['\"]//")
  created_recent+=$'\n'
  altered_recent+=$(grep -rhoE "Schema::table\(\s*['\"][a-z_]+" "${f}" \
                    | sed -E "s/Schema::table\(\s*['\"]//")
  altered_recent+=$'\n'
done < <(find "${TENANT_DIR}" -maxdepth 1 -name '*.php' | sort)

created_recent=$(printf '%s' "${created_recent}" | sed '/^$/d' | sort -u)
altered_recent=$(printf '%s' "${altered_recent}" | sed '/^$/d' | sort -u)
# Tables altérées qui ne sont PAS créées par une migration récente (tables pré-existantes)
altered_only=$(comm -23 <(printf '%s' "${altered_recent}") <(printf '%s' "${created_recent}"))

# ── 2. Tables de la fixture CreatesMvpSchema ───────────────────────────────────
fixture_tables=$(grep -rhoE "Schema::create\(\s*['\"][a-z_]+" "${FIXTURE}" \
                 | sed -E "s/Schema::create\(\s*['\"]//" | sort -u)
fixture_tables+=$'\n'$(grep -rhoE "(tenantTable|moduleTable)\(\s*['\"][a-z_]+" "${FIXTURE}" \
                 | sed -E "s/(tenantTable|moduleTable)\(\s*['\"]//" | sort -u)
fixture_tables=$(printf '%s' "${fixture_tables}" | sed '/^$/d' | sort -u)

# ── 3. Dérive bloquante ────────────────────────────────────────────────────────
# Allowlist datée — dérive MESURÉE sur main le 2026-08-25 (issue #5443) : tables
# créées par des migrations tenant >= 2026-06-01 absentes de CreatesMvpSchema.
# Dette pré-existante à résorber via la migration des tests vers RefreshTenantDatabase
# (F-13, #1543) ou l'ajout à la fixture — NE PAS étendre cette liste pour une
# nouvelle table : ajouter la table à la fixture dans la PR.
ALLOWLIST_DRIFT=(
  # F-13 #1543 / #5511 — allowlist vidée le 2026-08-25 : les tables tenant
  # récentes sont désormais couvertes par la fixture CreatesMvpSchema ou par
  # des tests RefreshTenantDatabase (voir check-test-schema-drift.sh).
)

missing_recent=$(comm -23 <(printf '%s' "${created_recent}") <(printf '%s' "${fixture_tables}"))

FAIL=0
HARD_MISSING=()
SOFT_MISSING=()
if [[ -n "${missing_recent}" ]]; then
  while IFS= read -r t; do
    [[ -n "${t}" ]] || continue
    if printf '%s\n' "${ALLOWLIST_DRIFT[@]}" | grep -qx "${t}"; then
      SOFT_MISSING+=("    - ${t} (dérive pré-existante allowlistée — à résorber via F-13 #1543)")
    elif grep -rl "RefreshTenantDatabase" "${API_DIR}/tests" --include='*Test.php' 2>/dev/null \
         | xargs grep -l "${t}" 2>/dev/null | grep -q .; then
      SOFT_MISSING+=("    - ${t} (couverte par RefreshTenantDatabase dans un test existant)")
    else
      HARD_MISSING+=("    - ${t}")
      FAIL=1
    fi
  done < <(printf '%s' "${missing_recent}")
fi

if [[ "${#HARD_MISSING[@]}" -gt 0 ]]; then
  echo "::error::Parité CreatesMvpSchema ↔ migrations tenant (issue #5443) : ${#HARD_MISSING[@]} table(s) récente(s) absente(s) de la fixture :"
  for l in "${HARD_MISSING[@]}"; do echo "::error::${l}"; done
  echo "::error::→ Ajouter la table à api/tests/Support/CreatesMvpSchema.php (pattern moduleTable(), cf. createPostSprintModuleTables()) dans la même PR que la migration."
  echo "::error::→ Détail : les tests utilisant CreatesMvpSchema échoueront en CI avec « relation ... does not exist » (régression #5418)."
fi

# ── 4. Warns (soft) ────────────────────────────────────────────────────────────
if [[ "${#SOFT_MISSING[@]}" -gt 0 ]]; then
  echo "::warning::Tables tenant récentes absentes de CreatesMvpSchema (non bloquant) :"
  for l in "${SOFT_MISSING[@]}"; do echo "::warning::${l}"; done
fi
if [[ -n "${altered_only}" ]]; then
  altered_soft=$(comm -23 <(printf '%s' "${altered_only}") <(printf '%s' "${fixture_tables}"))
  if [[ -n "${altered_soft}" ]]; then
    echo "::warning::Tables altérées par des migrations récentes, absentes de la fixture (tables pré-existantes — informatif) :"
    while IFS= read -r l; do echo "::warning::    - ${l}"; done < <(printf '%s' "${altered_soft}")
  fi
fi

if [[ "${FAIL}" -eq 1 ]]; then
  exit 1
fi

created_count=0
[[ -n "${created_recent}" ]] && created_count=$(printf '%s' "${created_recent}" | sed '/^$/d' | wc -l | tr -d ' ')
echo "✓ Parité CreatesMvpSchema ↔ migrations tenant OK (cutoff ${CUTOFF}, ${created_count} table(s) récente(s) créée(s))"
exit 0
