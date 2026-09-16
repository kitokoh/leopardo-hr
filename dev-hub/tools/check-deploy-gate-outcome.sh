#!/usr/bin/env bash
# ============================================================================
# check-deploy-gate-outcome.sh — garde du verdict du gate de déploiement
# (issues #7511 et #7528)
#
# Pourquoi : `deploy-main.yml` échouait à CHAQUE merge sur `main` qui ne
# touchait pas les chemins des workflows requis (#7511, constat merge #7498),
# puis à chaque merge ne touchant QUE des fichiers CI (#7528, constat merge
# `d7e11294`) — dans les deux cas, deux causes racines distinctes :
#
#  #7511 — le gate ne mesurait pas `api_changed` : sur un SHA hors `api/**` et
#          `front/admin-dashboard/**`, les workflows requis sont filtrés par
#          chemin, donc aucun run n'existe — et le gate concluait « indécision »
#          (`no-runs`, rouge) au lieu de « rien à déployer » (`not-required`).
#  #7528 — `web_changed` était déduit de la liste `web` de
#          `.github/paths-filters.yml`, volontairement PLUS LARGE que les
#          `paths:` de `web-ci.yml` (elle ajoute `deploy-main.yml` et deux docs).
#          Un merge ne touchant que des fichiers CI affichait donc
#          `web_changed=true` alors qu'aucun run `Web CI - Leopardo Admin` ne
#          pouvait exister → même rouge par construction.
#
# Cette garde fige les invariants qui empêchent le retour des deux :
#
#  1. l'action `verify-deploy-workflows` accepte la mesure `api_changed` et
#     qualifie l'absence de run requis en `not-required` (décision) quand
#     `api_changed=false` ET `web_changed=false` — et NON en `no-runs` ;
#  2. `deploy-main.yml` fournit cette mesure AVANT le gate et son job
#     « Deploy gate verdict » traite `not-required` comme une décision
#     (return 0), `no-runs` restant une indécision (exit 1) ;
#  3. **le gate n'exige que ce que le workflow correspondant peut produire** :
#     les prédicats `isApiPath` / `isWebPath` de `deploy-main.yml` doivent être
#     la copie EXACTE des `paths:` de `tests.yml` et `web-ci.yml` — les
#     workflows dont le gate attend la conclusion. C'est la divergence de ces
#     listes qui a produit #7511 puis #7528.
#
# Usage :
#   bash dev-hub/tools/check-deploy-gate-outcome.sh            # vérifie le dépôt
#   bash dev-hub/tools/check-deploy-gate-outcome.sh <racine>   # autre racine
#   bash dev-hub/tools/check-deploy-gate-outcome.sh --self-test
# ============================================================================
set -euo pipefail

ACTION_PATH=".github/actions/verify-deploy-workflows/action.yml"
DEPLOY_PATH=".github/workflows/deploy-main.yml"
TESTS_PATH=".github/workflows/tests.yml"
WEB_CI_PATH=".github/workflows/web-ci.yml"

errors=0

fail() {
  echo "::error::[deploy-gate] $*" >&2
  errors=$((errors + 1))
}

# Extrait le corps d'une fonction fléchée JS `const <name> = (p) => ... ;`
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

# Extrait les `paths:` du bloc `push:` d'un workflow (liste YAML de chaînes).
workflow_push_paths() {
  python3 - "$1" <<'PYEXTRACT'
import re
import sys

path = sys.argv[1]
lines = open(path, encoding="utf-8").read().split("\n")

def find_block(start_index, indent):
    """Indices des lignes du bloc indenté plus profondément que `indent`."""
    out = []
    for i in range(start_index, len(lines)):
        line = lines[i]
        if not line.strip():
            continue
        cur = len(line) - len(line.lstrip())
        if cur <= indent:
            break
        out.append((i, line, cur))
    return out

# Trouver `on:` (indent 0), puis `push:` (indent > 0), puis `paths:` dedans.
on_idx = next(i for i, l in enumerate(lines) if re.match(r"^on:\s*$", l))
push_idx = None
for i, line, cur in find_block(on_idx + 1, 0):
    if re.match(r"^\s*push:\s*$", line):
        push_idx = i
        push_indent = cur
        break
if push_idx is None:
    sys.exit(0)

for i, line, cur in find_block(push_idx + 1, push_indent):
    if re.match(r"^\s*paths:\s*$", line):
        paths_indent = cur
        for _, item, item_indent in find_block(i + 1, paths_indent):
            m = re.match(r"^\s*-\s*['\"]?([^'\"]+?)['\"]?\s*$", item)
            if m:
                print(m.group(1).strip())
        break
PYEXTRACT
}

# Vérifie que l'ensemble des chemins traités par les prédicats du gate couvre
# EXACTEMENT les déclencheurs des workflows dont il attend la conclusion
# (issues #7511 / #7528) :
#   - l'union (isApiPath ∪ isWebPath) ⊇ paths: de tests.yml ET de web-ci.yml
#     (le gate ne doit pas laisser passer un SHA qui aurait dû déclencher un
#     run — défaut #7511) ;
#   - et ⊆ : aucun chemin traité par le gate ne doit venir d'ailleurs (défaut
#     #7528 : `deploy-main.yml` + 2 docs hérités d'une liste plus large, sans
#     run possible).
check_gate_paths() {
  local deploy="$1" api_body="$2" web_body="$3"         tests_paths="$4" web_paths="$5"

  local p
  # Union : chaque déclencheur des deux workflows doit être vu par au moins un
  # des deux prédicats du gate (sinon un run réel n'est pas exigé, #7511).
  local any
  while IFS= read -r p; do
    [[ -z "${p}" ]] && continue
    js_covers_path "${api_body}" "${p}" && continue
    js_covers_path "${web_body}" "${p}" && continue
    fail "${DEPLOY_PATH} : « ${p} » déclenche un workflow requis mais n'est couvert par aucun prédicat du gate (#7511)."
  done <<< "$(printf '%s\n%s\n' "${tests_paths}" "${web_paths}")"

  # Sens inverse : un fichier que le gate traite ne doit pas être un chemin que
  # ni tests.yml ni web-ci.yml ne déclenchent (défaut #7528 : deploy-main.yml et
  # deux docs hérités de la liste `web`, plus large, de paths-filters.yml).
  while IFS= read -r p; do
    [[ -z "${p}" ]] && continue
    if ! printf '%s\n%s\n' "${tests_paths}" "${web_paths}" | grep -qxF "${p}"; then
      fail "${DEPLOY_PATH} : le gate traite « ${p} » alors qu'aucun workflow requis ne se déclenche dessus — _changed serait vrai sans run possible (#7528)."
    fi
  done <<< "$(printf '%s\n%s\n' \
      "$(printf '%s\n' "${api_body}" | sed -n "s/.*p === '\\([^']*\\)'.*/\\1/p")" \
      "$(printf '%s\n' "${web_body}" | sed -n "s/.*p === '\\([^']*\\)'.*/\\1/p")")"
}

# Le prédicat JS couvre-t-il ce chemin ? (`**` → préfixe, sinon égalité)
js_covers_path() {
  local body="$1" pattern="$2"
  if [[ "${pattern}" == *'/**' ]]; then
    printf '%s\n' "${body}" | grep -qF "startsWith('${pattern%/**}/')"
  else
    printf '%s\n' "${body}" | grep -qF "p === '${pattern}'"
  fi
}

check_filters_parity() {
  local root="$1"
  local action="${root}/${ACTION_PATH}"
  local deploy="${root}/${DEPLOY_PATH}"
  local tests_wf="${root}/${TESTS_PATH}"
  local web_wf="${root}/${WEB_CI_PATH}"
  local before=${errors}

  [[ -f "${action}" ]] || { fail "action introuvable : ${ACTION_PATH}"; return 1; }
  [[ -f "${deploy}" ]] || { fail "workflow introuvable : ${DEPLOY_PATH}"; return 1; }
  [[ -f "${tests_wf}" ]] || { fail "workflow introuvable : ${TESTS_PATH}"; return 1; }
  [[ -f "${web_wf}" ]] || { fail "workflow introuvable : ${WEB_CI_PATH}"; return 1; }

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
  verdict_block="$(awk '/Render the gate verdict/{c=1} c{print}' "${deploy}")"
  if ! printf '%s\n' "${verdict_block}" | grep -qE '^            not-required\)'; then
    fail "${DEPLOY_PATH} : le job de verdict ne traite plus « not-required » comme une décision (le gate vert redeviendrait rouge)."
  fi
  if ! printf '%s\n' "${verdict_block}" | grep -qE 'no-runs\|timeout'; then
    fail "${DEPLOY_PATH} : le job de verdict ne traite plus l'indécision « no-runs/timeout » (régression #7457)."
  fi
  local notreq_line range_line
  notreq_line="$(printf '%s\n' "${verdict_block}" | grep -nE '^            not-required\)' | head -n 1 | cut -d: -f1)"
  range_line="$(printf '%s\n' "${verdict_block}" | grep -nE '^            no-runs\|timeout' | head -n 1 | cut -d: -f1)"
  if [[ -n "${notreq_line}" && -n "${range_line}" && "${notreq_line}" -ge "${range_line}" ]]; then
    fail "${DEPLOY_PATH} : « not-required » est traité après le motif d'indécision — il ne serait jamais atteint."
  fi

  # --- 2bis. #7559 : « pending » = budget épuisé ALORS QUE des runs requis
  # tournent — ce n'est pas une indécision, mais ce n'est pas un succès muet
  # non plus : la décision est « différé », le fond restant couvert par
  # deploy-drift-guard.yml (30 min) et le rattrapage deploy-main-catchup.yml.
  if ! grep -qE "setOutput\('gate_outcome', 'pending'\)" "${action}"; then
    fail "${ACTION_PATH} : le cas « budget épuisé alors que des runs requis tournent » ne produit pas gate_outcome=pending — un merge api/** rougit main 30 min plus tard (#7559)."
  fi
  if ! grep -qE 'lastPending.length > 0' "${action}"; then
    fail "${ACTION_PATH} : la distinction « runs requis encore en cours » vs « aucun run » (lastPending) est absente (#7559)."
  fi
  if ! grep -qE "DEPLOY_GATE_BUDGET_MINUTES" "${action}"; then
    fail "${ACTION_PATH} : le budget d'attente n'est plus paramétrable — le test comportemental ne peut plus épuiser le budget (#7559)."
  fi
  # #7559 (suite) — « en vol » doit couvrir TOUT ce qui n'est pas `completed`
  # (`requested`, `waiting`, `pending`, `queued`, `in_progress`). Le prédicat
  # restreint produisait un motif `tests-null` refusé par le job de verdict
  # (constat de production : run 35085884633).
  if ! grep -qE "run.status !== 'completed'" "${action}"; then
    fail "${ACTION_PATH} : le prédicat « runs en vol » ne couvre plus tous les statuts non terminés — un run \`requested\` ferait conclure le gate sur un run sans conclusion (motif \`tests-null\`, #7559)."
  fi
  if grep -qE "gateOutcome = \`tests-\$\{testsConclusion\}\`" "${action}"; then
    fail "${ACTION_PATH} : un motif \`tests-${conclusion}\` peut être produit avec une conclusion nulle — le job de verdict refuse tout motif inconnu (#7559)."
  fi
  if ! printf '%s\n' "${verdict_block}" | grep -qE '^            pending\)'; then
    fail "${DEPLOY_PATH} : le job de verdict ne traite plus « pending » comme une décision (le différé redeviendrait rouge — #7559)."
  fi
  local pending_line
  pending_line="$(printf '%s\n' "${verdict_block}" | grep -nE '^            pending\)' | head -n 1 | cut -d: -f1)"
  if [[ -n "${pending_line}" && -n "${range_line}" && "${pending_line}" -ge "${range_line}" ]]; then
    fail "${DEPLOY_PATH} : « pending » est traité après le motif d'indécision — il ne serait jamais atteint (#7559)."
  fi

  # --- 3. parité : le gate n'exige que ce que le workflow peut produire ----
  local api_patterns web_patterns
  api_patterns="$(workflow_push_paths "${tests_wf}")"
  web_patterns="$(workflow_push_paths "${web_wf}")"

  if [[ -z "${api_patterns}" ]]; then
    fail "${TESTS_PATH} : aucun `paths:` sur `push:` lisible — la garde ne peut plus comparer au filtre api du gate."
  fi
  if [[ -z "${web_patterns}" ]]; then
    fail "${WEB_CI_PATH} : aucun `paths:` sur `push:` lisible — la garde ne peut plus comparer au filtre web du gate."
  fi

  local api_body web_body
  api_body="$(extract_js_arrow_body "${deploy}" "isApiPath")"
  web_body="$(extract_js_arrow_body "${deploy}" "isWebPath")"
  if [[ -z "${api_body}" || -z "${web_body}" ]]; then
    fail "${DEPLOY_PATH} : prédicats isApiPath/isWebPath introuvables — parité des filtres non vérifiable."
  else
    check_gate_paths "${deploy}" "${api_body}" "${web_body}" "${api_patterns}" "${web_patterns}"
  fi

  [[ ${errors} -eq ${before} ]] || return 1
  return 0
}

self_test() {
  local tmp
  tmp="$(mktemp -d)"
  trap 'rm -rf "${tmp:-}"' EXIT
  mkdir -p "${tmp}/.github/actions/verify-deploy-workflows" "${tmp}/.github/workflows"

  cp "${ACTION_PATH}" "${tmp}/${ACTION_PATH}"
  cp "${DEPLOY_PATH}" "${tmp}/${DEPLOY_PATH}"
  cp "${TESTS_PATH}" "${tmp}/${TESTS_PATH}"
  cp "${WEB_CI_PATH}" "${tmp}/${WEB_CI_PATH}"
  if ! ( check_filters_parity "${tmp}" ); then
    echo "::error::[deploy-gate --self-test] un dépôt conforme est refusé — garde trop stricte." >&2
    return 1
  fi

  # --- mutation 1 : le cas « rien à déployer » disparaît -------------------
  python3 - "${tmp}/${ACTION_PATH}" <<'PYSELFTEST'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace('if (noRunExpected) {', 'if (false) {', 1)
open(p, 'w', encoding='utf-8').write(s)
PYSELFTEST
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] mutation « noRunExpected neutralisé » non détectée." >&2
    return 1
  fi
  cp "${ACTION_PATH}" "${tmp}/${ACTION_PATH}"

  # --- mutation 2 : divergence du filtre api -------------------------------
  python3 - "${tmp}/${DEPLOY_PATH}" <<'PYSELFTEST'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace("p === '.github/workflows/phpstan-baseline.yml'", "p === '.github/workflows/phpstan-renamed.yml'", 1)
open(p, 'w', encoding='utf-8').write(s)
PYSELFTEST
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] divergence des filtres api non détectée." >&2
    return 1
  fi
  cp "${DEPLOY_PATH}" "${tmp}/${DEPLOY_PATH}"

  # --- mutation 3 (régression #7528) : le gate re-exige deploy-main.yml ----
  python3 - "${tmp}/${DEPLOY_PATH}" <<'PYSELFTEST'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace("""            const isWebPath = (p) =>
              p.startsWith('front/admin-dashboard/') ||
              p === '.github/workflows/web-ci.yml';""",
"""            const isWebPath = (p) =>
              p.startsWith('front/admin-dashboard/') ||
              p === '.github/workflows/web-ci.yml' ||
              p === '.github/workflows/deploy-main.yml';""", 1)
open(p, 'w', encoding='utf-8').write(s)
PYSELFTEST
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] la régression #7528 (chemin exigé sans workflow possible) n'est pas détectée." >&2
    return 1
  fi
  cp "${DEPLOY_PATH}" "${tmp}/${DEPLOY_PATH}"

  # --- mutation 4 (#7559) : le différé est retraité en indécision ----------
  python3 - "${tmp}/${ACTION_PATH}" <<'PYSELFTEST'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace("setOutput('gate_outcome', 'pending')", "setOutput('gate_outcome', 'timeout')", 1)
open(p, 'w', encoding='utf-8').write(s)
PYSELFTEST
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] la régression #7559 (budget épuisé avec runs en cours retraité en indécision) n'est pas détectée." >&2
    return 1
  fi
  cp "${ACTION_PATH}" "${tmp}/${ACTION_PATH}"

  # --- mutation 5 (#7559, suite) : le prédicat « en vol » redevient étroit ---
  python3 - "${tmp}/${ACTION_PATH}" <<'PYSELFTEST'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace("run.status !== 'completed'", "run.status === 'queued' || run.status === 'in_progress'", 1)
open(p, 'w', encoding='utf-8').write(s)
PYSELFTEST
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] la régression #7559 (statuts `requested`/`waiting` retirés du prédicat « en vol ») n'est pas détectée." >&2
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
  echo "✅  Verdict du gate de déploiement cohérent (api_changed, not-required, pending ≠ timeout, et le gate n'exige que ce que tests.yml/web-ci.yml peuvent produire)."
}

if [[ "${1:-}" == "--self-test" ]]; then
  self_test
else
  main "${1:-}"
fi
