#!/usr/bin/env bash
# ============================================================================
# check-deploy-gate-outcome.sh — garde du verdict du gate de déploiement
# (issue #7511, complément de #7457)
#
# Pourquoi : `deploy-main.yml` échouait à CHAQUE merge sur `main` qui ne
# touchait ni `api/**` ni `front/admin-dashboard/**` (constat : merge #7498).
# Les workflows requis (`tests.yml`, `web-ci.yml`) sont filtrés par
# `.github/paths-filters.yml` : sur un SHA hors de ces chemins, AUCUN run
# requis n'existe, le gate ne trouvait rien à lire et se déclarait « indécis »
# (`no-runs`) — alors que la seule conclusion correcte était « rien à
# déployer ». `main` restait donc rouge sans qu'aucun code ne soit fautif.
#
# Cette garde fige les trois invariants qui empêchent la régression :
#
#  1. l'action `verify-deploy-workflows` accepte la mesure `api_changed` et
#     qualifie l'absence de run requis en `not-required` (décision) quand
#     `api_changed=false` ET `web_changed=false` — et NON en `no-runs`
#     (indécision) ;
#  2. `deploy-main.yml` fournit cette mesure AVANT le gate et son job
#     « Deploy gate verdict » traite `not-required` comme une décision
#     (return 0), `no-runs` restant une indécision (exit 1) ;
#  3. les listes de chemins `api` et `web` de `deploy-main.yml`
#     (`isApiPath` / `isWebPath`) sont la copie EXACTE de celles de
#     `.github/paths-filters.yml` — une divergence (c'est la cause racine de
#     #7511) rend le verdict faux : le gate déduit d'un filtre qui n'est pas
#     celui qui déclenche réellement les workflows.
#
# Usage :
#   bash dev-hub/tools/check-deploy-gate-outcome.sh            # vérifie le dépôt
#   bash dev-hub/tools/check-deploy-gate-outcome.sh <racine>   # autre racine
#   bash dev-hub/tools/check-deploy-gate-outcome.sh --self-test
# ============================================================================
set -euo pipefail

ACTION_PATH=".github/actions/verify-deploy-workflows/action.yml"
DEPLOY_PATH=".github/workflows/deploy-main.yml"
FILTERS_PATH=".github/paths-filters.yml"

errors=0

fail() {
  echo "::error::[deploy-gate] $*" >&2
  errors=$((errors + 1))
}

# Extrait le corps d'une fonction fléchée JS `const <name> = (p) => ... ;`
# dans $1=file, $2=nom de fonction. Le corps va jusqu'au `;` de fin d'expression
# en début de ligne suivi d'une indentation inférieure ou égale.
extract_js_arrow_body() {
  local file="$1" fn="$2"
  awk -v fn="${fn}" '
    index($0, "const " fn " = (p) =>") > 0 { capture = 1 }
    capture {
      line = $0
      if (line ~ /;[[:space:]]*$/) { print line; exit }
      print line
    }
  ' "${file}"
}

check_filters_parity() {
  local root="$1"
  local action="${root}/${ACTION_PATH}"
  local deploy="${root}/${DEPLOY_PATH}"
  local filters="${root}/${FILTERS_PATH}"

  local before=${errors}

  [[ -f "${action}" ]] || { fail "action introuvable : ${ACTION_PATH}"; return 1; }
  [[ -f "${deploy}" ]] || { fail "workflow introuvable : ${DEPLOY_PATH}"; return 1; }
  [[ -f "${filters}" ]] || { fail "filtres introuvables : ${FILTERS_PATH}"; return 1; }

  # --- 1. l'action déclare et consomme api_changed -------------------------
  if ! grep -qE '^  api_changed:' "${action}"; then
    fail "${ACTION_PATH} : input « api_changed » absent — le gate ne peut plus distinguer « rien à déployer » d'une indécision (#7511)."
  fi
  if ! grep -qE 'INPUT_API_CHANGED:' "${action}"; then
    fail "${ACTION_PATH} : « INPUT_API_CHANGED » non transmis au script (core.getInput('api_changed') renverra vide)."
  fi
  if ! grep -q "getInput('api_changed')" "${action}"; then
    fail "${ACTION_PATH} : le script ne lit pas api_changed."
  fi
  if ! grep -qF 'noRunExpected =' "${action}" || ! grep -qF 'if (noRunExpected) {' "${action}"; then
    fail "${ACTION_PATH} : la garde « noRunExpected » est absente ou désactivée — un SHA hors api/web retomberait en « no-runs » (indécision) et rougirait main (#7511)."
  fi
  if ! grep -qE "setOutput\('gate_outcome', 'not-required'\)" "${action}"; then
    fail "${ACTION_PATH} : le cas sans run requis ne produit pas gate_outcome=not-required."
  fi
  # Le verdict « indécision » doit survivre pour les cas où l'API a réellement changé.
  if ! grep -qE "setOutput\('gate_outcome', 'no-runs'\)" "${action}"; then
    fail "${ACTION_PATH} : le verdict d'indécision « no-runs » a disparu — un vrai trou de couverture ne serait plus signalé."
  fi

  # --- 2. deploy-main.yml fournit la mesure et traite le verdict -----------
  if ! grep -qE "api_changed: \\\$\{\{ steps\.changes_api\.outputs\.api_changed" "${deploy}"; then
    fail "${DEPLOY_PATH} : le gate n'est pas alimenté par api_changed mesuré avant le checkout (#7511)."
  fi
  if ! grep -q "isApiPath" "${deploy}"; then
    fail "${DEPLOY_PATH} : isApiPath absent — api_changed ne peut pas être mesuré via l'API."
  fi

  local verdict_block
  verdict_block="$(awk '/Render the gate verdict/{c=1} c{print} /^  [a-z-]+:$/ && c && NR>1 {}' "${deploy}")"
  if ! printf '%s\n' "${verdict_block}" | grep -qE '^            not-required\)'; then
    fail "${DEPLOY_PATH} : le job de verdict ne traite plus « not-required » comme une décision (le gate vert redeviendrait rouge)."
  fi
  if ! printf '%s\n' "${verdict_block}" | grep -qE 'no-runs\|timeout'; then
    fail "${DEPLOY_PATH} : le job de verdict ne traite plus l'indécision « no-runs/timeout » (régression #7457)."
  fi
  # `not-required` doit être traité AVANT le motif générique d'échec, et sans exit 1.
  local notreq_line range_line
  notreq_line="$(printf '%s\n' "${verdict_block}" | grep -nE '^            not-required\)' | head -n 1 | cut -d: -f1)"
  range_line="$(printf '%s\n' "${verdict_block}" | grep -nE '^            no-runs\|timeout' | head -n 1 | cut -d: -f1)"
  if [[ -n "${notreq_line}" && -n "${range_line}" && "${notreq_line}" -ge "${range_line}" ]]; then
    fail "${DEPLOY_PATH} : « not-required » est traité après le motif d'indécision — il ne serait jamais atteint."
  fi

  # --- 3. parité stricte des filtres api/web -------------------------------
  local api_patterns web_patterns
  api_patterns="$(awk '/^api:[[:space:]]*$/{c=1;next} /^[a-z]+:[[:space:]]*$/{c=0} c' "${filters}" | sed -n "s/^[[:space:]]*-[[:space:]]*'\(.*\)'$/\1/p")"
  web_patterns="$(awk '/^web:[[:space:]]*$/{c=1;next} /^[a-z]+:[[:space:]]*$/{c=0} c' "${filters}" | sed -n "s/^[[:space:]]*-[[:space:]]*'\(.*\)'$/\1/p")"

  if [[ -z "${api_patterns}" || -z "${web_patterns}" ]]; then
    fail "${FILTERS_PATH} : listes api/web illisibles (format modifié ?) — la garde ne peut plus comparer."
    return 1
  fi

  local body_ok=1
  local body
  for pair in "api:isApiPath" "web:isWebPath"; do
    local key="${pair%%:*}" fn="${pair##*:}"
    body="$(extract_js_arrow_body "${deploy}" "${fn}")"
    if [[ -z "${body}" ]]; then
      fail "${DEPLOY_PATH} : fonction ${fn} introuvable — parité des filtres non vérifiable."
      body_ok=0
      continue
    fi
    local patterns="${api_patterns}"
    [[ "${key}" == "web" ]] && patterns="${web_patterns}"
    local pattern
    while IFS= read -r pattern; do
      [[ -z "${pattern}" ]] && continue
      if [[ "${pattern}" == *'/**' ]]; then
        local prefix="${pattern%/**}"
        if ! printf '%s\n' "${body}" | grep -qF "startsWith('${prefix}/')"; then
          fail "${DEPLOY_PATH} : ${fn} ne couvre pas « ${pattern} » (attendu : startsWith('${prefix}/')) — divergence avec ${FILTERS_PATH} (cause racine #7511)."
        fi
      else
        if ! printf '%s\n' "${body}" | grep -qF "p === '${pattern}'"; then
          fail "${DEPLOY_PATH} : ${fn} ne couvre pas « ${pattern} » (attendu : p === '${pattern}') — divergence avec ${FILTERS_PATH}."
        fi
      fi
    done <<< "${patterns}"
  done

  # Sens inverse : deploy-main ne doit pas inventer de chemin que les filtres ne
  # déclarent pas (sinon le verdict s'appuie sur un filtre qui n'existe pas).
  if [[ ${body_ok} -eq 1 ]]; then
    for pair in "api:isApiPath" "web:isWebPath"; do
      local key="${pair%%:*}" fn="${pair##*:}" patterns="${api_patterns}"
      [[ "${key}" == "web" ]] && patterns="${web_patterns}"
      body="$(extract_js_arrow_body "${deploy}" "${fn}")"
      local literal
      while IFS= read -r literal; do
        [[ -z "${literal}" ]] && continue
        case "${literal}" in
          *.github/workflows/tests.yml|*.github/workflows/phpstan-baseline.yml|*.github/workflows/web-ci.yml|*.github/workflows/deploy-main.yml|docs/GESTION_PROJET/*) ;;
          *) continue ;;
        esac
        if ! printf '%s\n' "${patterns}" | grep -qxF "${literal}"; then
          fail "${DEPLOY_PATH} : ${fn} déclare « ${literal} », absent de ${FILTERS_PATH} — le verdict s'appuierait sur un filtre inexistant."
        fi
      done <<< "$(printf '%s\n' "${body}" | sed -n "s/.*p === '\([^']*\)'.*/\1/p")"
    done
  fi

  [[ ${errors} -eq ${before} ]] || return 1
  return 0
}

self_test() {
  local tmp
  tmp="$(mktemp -d)"
  trap 'rm -rf "${tmp:-}"' EXIT
  mkdir -p "${tmp}/.github/actions/verify-deploy-workflows" "${tmp}/.github/workflows"

  # --- cas sain -----------------------------------------------------------
  cp "${ACTION_PATH}" "${tmp}/${ACTION_PATH}"
  cp "${DEPLOY_PATH}" "${tmp}/${DEPLOY_PATH}"
  cp "${FILTERS_PATH}" "${tmp}/${FILTERS_PATH}"
  if ! ( check_filters_parity "${tmp}" ); then
    echo "::error::[deploy-gate --self-test] un dépôt conforme est refusé — garde trop stricte." >&2
    return 1
  fi

  # --- mutation 1 : le cas « rien à déployer » disparaît -------------------
  python3 - "${tmp}/${ACTION_PATH}" <<'PY'
import re, sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace('if (noRunExpected) {', 'if (false) {', 1)
open(p, 'w', encoding='utf-8').write(s)
PY
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] mutation « noRunExpected neutralisé » non détectée." >&2
    return 1
  fi
  cp "${ACTION_PATH}" "${tmp}/${ACTION_PATH}"

  # --- mutation 2 : divergence de filtre (cause racine #7511) -------------
  python3 - "${tmp}/${DEPLOY_PATH}" <<'PY'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace("p === '.github/workflows/phpstan-baseline.yml'", "p === '.github/workflows/phpstan-renamed.yml'", 1)
open(p, 'w', encoding='utf-8').write(s)
PY
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] divergence des filtres api non détectée." >&2
    return 1
  fi

  echo "DEPLOY_GATE_GUARD_SELF_TEST_OK"
}

main() {
  local target_root="${1:-$(git rev-parse --show-toplevel 2>/dev/null || echo '.')}"
  check_filters_parity "${target_root}"

  if [[ ${errors} -gt 0 ]]; then
    echo "::error::[deploy-gate] ${errors} invariant(s) rompu(s) — voir ci-dessus."
    exit 1
  fi
  echo "✅  Verdict du gate de déploiement cohérent (api_changed, not-required, parité des filtres)."
}

if [[ "${1:-}" == "--self-test" ]]; then
  self_test
else
  main "${1:-}"
fi
