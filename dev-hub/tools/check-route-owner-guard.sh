#!/usr/bin/env bash
# check-route-owner-guard.sh — Garde CI « routes & Policies platform/tenant »
# (MAT-003, issue #5861)
#
# Vérifie automatiquement qu'une route platform n'est pas exposée dans
# l'espace tenant et inversement.
#
#   Couche 1 (statique, sans PHP) — scan des fichiers de routes :
#     - cohérence route-owners.json (fichiers déclarés existants, scopes valides) ;
#     - aucun contrôleur platform (namespace App\Modules\Platform\) référencé
#       dans un fichier de routes tenant (api/routes/modules/*, ai.php) —
#       sauf exceptions déclarées (tenant_controller_exceptions).
#
#   Couche 2 (route:list --json, PHP + vendor requis) :
#     - classification de CHAQUE route par middleware résolu
#       (Authenticate:super_admin_api → platform ; TenantMiddleware → tenant) ;
#     - BLOCKING :
#         · route platform hors prefixes déclarés (sauf platform_controller_exceptions) ;
#         · contrôleur App\Modules\Platform\ servi dans une route tenant (sauf exceptions) ;
#         · route tenant servie sous un prefix platform (/platform, /admin) — inverse ;
#     - WARNING (routes API uniquement) : contrôleur Platform*/Admin* dans une
#       route tenant non déclaré.
#
# Usage : dev-hub/tools/check-route-owner-guard.sh [repo_root]
# Prérequis : bash, jq. Couche 2 : php + api/vendor (ignorée sinon, non bloquante).
# Exit codes : 0 = OK, 1 = violation bloquante.

set -uo pipefail

ROOT="${1:-.}"
REGISTRY="${ROOT}/dev-hub/governance/route-owners.json"
API_DIR="${ROOT}/api"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::route-owners.json introuvable : ${REGISTRY} (MAT-003/#5861)." >&2
  exit 1
fi
if ! jq -e . "${REGISTRY}" >/dev/null 2>&1; then
  echo "::error::route-owners.json invalide (MAT-003/#5861)." >&2
  exit 1
fi

ERRORS=0
WARNS=0
fail() {
  ERRORS=$((ERRORS + 1))
  echo "::error::${1}" >&2
}
warn() {
  WARNS=$((WARNS + 1))
  echo "::warning::${1}" >&2
}

PLATFORM_GUARD=$(jq -r '.platform_guard_middleware' "${REGISTRY}")
TENANT_MW=$(jq -r '.tenant_middleware' "${REGISTRY}")

# ── Couche 1 : fichiers de routes déclarés + contrôles statiques ─────────────
while IFS=$'\t' read -r f scope; do
  if [[ ! -f "${ROOT}/${f}" ]]; then
    fail "Fichier de routes déclaré introuvable : ${f}"
    continue
  fi
  case "${scope}" in
    tenant|mixed|public|web|console) ;;
    *) fail "Scope invalide pour ${f} : ${scope} (tenant|mixed|public|web|console attendu)." ;;
  esac
done < <(jq -r '.route_files | to_entries[] | [.key, .value] | @tsv' "${REGISTRY}")

# Règle inverse : tout fichier de routes modules existant doit être déclaré.
while IFS= read -r f; do
  [[ -z "${f}" ]] && continue
  scope=$(jq -r --arg f "${f}" '.route_files[$f] // ""' "${REGISTRY}")
  if [[ -z "${scope}" ]]; then
    fail "Fichier de routes non déclaré dans route-owners.json : ${f} → ajoute-le avec son scope (MAT-003/#5861)."
  fi
done < <(find "${ROOT}/api/routes/modules" -maxdepth 1 -name '*.php' -printf 'api/routes/modules/%f\n' 2>/dev/null | sort)

TENANT_EXCEPTIONS=$(jq -r '.tenant_controller_exceptions[].controller' "${REGISTRY}")

while IFS=$'\t' read -r f scope; do
  [[ "${scope}" == "tenant" ]] || continue
  file="${ROOT}/${f}"
  while IFS= read -r cls; do
    [[ -z "${cls}" ]] && continue
    if ! grep -Fxq "${cls}" <<<"${TENANT_EXCEPTIONS}"; then
      fail "${f} : contrôleur platform référencé dans un fichier tenant — ${cls} (route platform exposée dans l'espace tenant ; ajoute à tenant_controller_exceptions si justifié, MAT-003/#5861)."
    fi
  done < <(grep -oE 'use App\\Modules\\Platform\\[A-Za-z0-9_\\]+Controller;' "${file}" 2>/dev/null | sed 's/use //; s/;//' || true)
done < <(jq -r '.route_files | to_entries[] | [.key, .value] | @tsv' "${REGISTRY}")

# ── Couche 2 : route:list (si PHP + vendor disponibles) ───────────────────────
ROUTE_JSON=""
if [[ -x "$(command -v php 2>/dev/null)" && -f "${API_DIR}/vendor/autoload.php" ]]; then
  ROUTE_JSON=$(cd "${API_DIR}" && php artisan route:list --json 2>/dev/null || true)
fi

if [[ -n "${ROUTE_JSON}" ]] && jq -e . <<<"${ROUTE_JSON}" >/dev/null 2>&1; then
  PLATFORM_EXCEPTIONS=$(jq -r '.platform_controller_exceptions[].controller' "${REGISTRY}")
  PREFIXES=$(jq -r '.platform_uri_prefixes[]' "${REGISTRY}")

  while IFS=$'\t' read -r uri middleware action; do
    scope="public"
    if grep -q "${PLATFORM_GUARD}" <<<"${middleware}"; then
      scope="platform"
    elif grep -q "${TENANT_MW}" <<<"${middleware}"; then
      scope="tenant"
    fi

    controller="${action%@*}"
    under_prefix=no
    while IFS= read -r p; do
      [[ "${uri}" == "${p}"* ]] && under_prefix=yes
    done <<<"${PREFIXES}"

    if [[ "${scope}" == "platform" ]]; then
      if [[ "${under_prefix}" == "no" ]]; then
        if [[ "${controller}" == "Closure" ]] || ! grep -Fxq "${controller}" <<<"${PLATFORM_EXCEPTIONS}"; then
          fail "Route platform hors prefixes ${PREFIXES//$'\n'/| } : ${uri} (${controller}) → déclare-la dans platform_controller_exceptions si justifié (MAT-003/#5861)."
        fi
      fi
    fi

    if [[ "${scope}" == "tenant" ]]; then
      if [[ "${under_prefix}" == "yes" ]]; then
        fail "Route tenant servie sous un prefix platform : ${uri} (${controller}) — surface tenant exposée dans l'espace platform (MAT-003/#5861)."
      fi
      if [[ "${controller}" != "Closure" && "${controller}" == App\\Modules\\Platform\\* ]]; then
        if ! grep -Fxq "${controller}" <<<"${TENANT_EXCEPTIONS}"; then
          fail "Route tenant servie par un contrôleur platform : ${uri} (${controller}) — surface platform exposée au tenant (MAT-003/#5861)."
        fi
      fi
      if [[ "${uri}" == api/* && "${controller}" != "Closure" ]]; then
        if [[ "${controller}" =~ \\Platform[A-Za-z0-9_]*Controller$ || "${controller}" =~ \\[A-Za-z0-9_]*Admin[A-Za-z0-9_]*Controller$ ]]; then
          if ! grep -Fxq "${controller}" <<<"${TENANT_EXCEPTIONS}"; then
            warn "Route tenant API avec contrôleur Platform*/Admin* non déclaré : ${uri} (${controller}) — vérifier le propriétaire (MAT-003/#5861)."
          fi
        fi
      fi
    fi
  done < <(jq -r '.[] | select(.action != null) | [.uri, (.middleware | join(",")), .action] | @tsv' <<<"${ROUTE_JSON}")
fi

# ── Résultat ──────────────────────────────────────────────────────────────────
if [[ "${ERRORS}" -gt 0 ]]; then
  echo "::error::Guard routes/Policies platform-tenant (MAT-003/#5861) : ${ERRORS} violation(s) bloquante(s)." >&2
  exit 1
fi

echo "✓ Routes & Policies platform/tenant cohérentes (MAT-003/#5861) : couche statique OK${ROUTE_JSON:+ + route:list analysé (${WARNS} avertissement(s))}."
exit 0
