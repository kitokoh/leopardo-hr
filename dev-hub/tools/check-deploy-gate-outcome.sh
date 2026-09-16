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
#     (`isApiPath` / `isWebPath`) sont la copie EXACTE des `paths:` des
#     workflows dont le gate exige la conclusion (`.github/workflows/tests.yml`
#     et `.github/workflows/web-ci.yml`). Une divergence rend le verdict faux,
#     dans les DEUX sens :
#       - trop étroite : le gate n'exige pas la conclusion d'un workflow qu'une
#         modification peut déclencher (couverture perdue) ;
#       - trop large : le gate exige la conclusion d'un workflow qu'AUCUN des
#         chemins modifiés ne peut déclencher — c'est #7528 (merge d7e11294,
#         CI-only : la liste `web` de paths-filters.yml, volontairement plus
#         large que les `paths:` de web-ci.yml, faisait exiger un run qui ne
#         pouvait pas exister → `no-runs` → rouge structurel de `main`).
#     `.github/paths-filters.yml` n'est donc PAS la référence de cette parité :
#     cette liste alimente le `detect-changes` de tests.yml, où « déclencher
#     plus » est la direction sûre. La garde vérifie séparément qu'elle couvre
#     encore tout ce que les workflows requis peuvent voir.
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
# Workflows dont le gate exige la conclusion : leur bloc `paths:` est la SEULE
# surface qui peut produire le run attendu (#7528).
TESTS_WORKFLOW=".github/workflows/tests.yml"
WEB_WORKFLOW=".github/workflows/web-ci.yml"

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

# Extrait le bloc `paths:` d'un workflow pour un événement donné
# ($1=fichier, $2=événement). Ne lit QUE les entrées de liste quotées, une par
# ligne, telles que les écrivent tests.yml et web-ci.yml.
extract_workflow_paths() {
  local file="$1" event="$2"
  awk -v ev="${event}" '
    $0 ~ "^  " ev ":[[:space:]]*$" { in_event = 1; next }
    in_event && /^  [a-z_]+:[[:space:]]*$/ { in_event = 0 }
    in_event && /^    paths:[[:space:]]*$/ { in_paths = 1; next }
    in_paths && /^[[:space:]]*-[[:space:]]*.+$/ { print; next }
    in_paths && /^[[:space:]]*$/ { next }
    in_paths { in_paths = 0 }
  ' "${file}" | sed -n "s/^[[:space:]]*-[[:space:]]*'\(.*\)'\$/\1/p"
}

# Extrait une liste de .github/paths-filters.yml ($1=fichier, $2=clé).
extract_filter_list() {
  local file="$1" key="$2"
  awk -v key="${key}" '
    $0 ~ "^" key ":[[:space:]]*$" { c = 1; next }
    /^[a-z_]+:[[:space:]]*$/ { c = 0 }
    c
  ' "${file}" | sed -n "s/^[[:space:]]*-[[:space:]]*'\(.*\)'\$/\1/p"
}

# Vérifie que le prédicat JS $1 de deploy-main.yml couvre EXACTEMENT les paths
# $3 du workflow $2 (dans les deux sens).
assert_gate_matches_workflow() {
  local fn="$1" label="$2" paths="$3" deploy="${4:-${DEPLOY_PATH}}"
  local body
  body="$(extract_js_arrow_body "${deploy}" "${fn}")"
  if [[ -z "${body}" ]]; then
    fail "${DEPLOY_PATH} : fonction ${fn} introuvable — surface du gate non vérifiable."
    return 1
  fi

  # (a) tout chemin qui déclenche le workflow doit être mesuré par le gate.
  local pattern
  while IFS= read -r pattern; do
    [[ -z "${pattern}" ]] && continue
    if [[ "${pattern}" == *'/**' ]]; then
      local prefix="${pattern%/**}"
      printf '%s\n' "${body}" | grep -qF "startsWith('${prefix}/')" \
        || fail "${DEPLOY_PATH} : ${fn} ne couvre pas « ${pattern} » (attendu : startsWith('${prefix}/')) — le gate n'exigerait pas la conclusion de ${label} qu'une telle modification déclenche pourtant."
    else
      printf '%s\n' "${body}" | grep -qF "p === '${pattern}'" \
        || fail "${DEPLOY_PATH} : ${fn} ne couvre pas « ${pattern} » (attendu : p === '${pattern}') — le gate n'exigerait pas la conclusion de ${label}."
    fi
  done <<< "${paths}"

  # (b) le gate ne doit exiger la conclusion de ${label} que pour des chemins
  #     que ${label} peut réellement déclencher (cause racine #7528).
  local literal
  while IFS= read -r literal; do
    [[ -z "${literal}" ]] && continue
    printf '%s\n' "${paths}" | grep -qxF "${literal}" \
      || fail "${DEPLOY_PATH} : ${fn} déclare « ${literal} », absent des paths: de ${label} — le gate exigerait une conclusion impossible à produire (cause racine #7528)."
  done <<< "$(printf '%s\n' "${body}" | sed -n "s/.*p === '\([^']*\)'.*/\1/p")"

  # (c) idem pour les préfixes (startsWith('x/')).
  local prefix_literal
  while IFS= read -r prefix_literal; do
    [[ -z "${prefix_literal}" ]] && continue
    printf '%s\n' "${paths}" | grep -qxF "${prefix_literal}/**" \
      || fail "${DEPLOY_PATH} : ${fn} couvre « ${prefix_literal}/** », absent des paths: de ${label} (cause racine #7528)."
  done <<< "$(printf '%s\n' "${body}" | sed -n "s/.*startsWith('\([^']*\)\/').*/\1/p")"

  return 0
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

  # --- 3. le gate n'exige que ce que les workflows peuvent produire --------
  # Issue #7528 (suite de #7511) : la surface du gate est celle des `paths:`
  # des workflows dont il exige la conclusion — et non celle, plus large, de
  # .github/paths-filters.yml, qui ne sert qu'au `detect-changes` de tests.yml.
  local tests_workflow="${root}/${TESTS_WORKFLOW}"
  local web_workflow="${root}/${WEB_WORKFLOW}"
  for f in "${tests_workflow}" "${web_workflow}"; do
    [[ -f "${f}" ]] || { fail "workflow introuvable : ${f#"${root}"/}"; return 1; }
  done

  local tests_paths web_paths
  tests_paths="$(extract_workflow_paths "${tests_workflow}" push)"
  web_paths="$(extract_workflow_paths "${web_workflow}" push)"
  if [[ -z "${tests_paths}" || -z "${web_paths}" ]]; then
    fail "bloc paths: illisible dans ${TESTS_WORKFLOW} ou ${WEB_WORKFLOW} (format modifié ?) — la garde ne peut plus comparer la surface du gate."
    return 1
  fi

  # Le gate lit des runs de PUSH sur main : un chemin déclenchable seulement en
  # pull_request produirait le même défaut « run impossible ».
  for f in "${tests_workflow}" "${web_workflow}"; do
    if [[ "$(extract_workflow_paths "${f}" pull_request)" != "$(extract_workflow_paths "${f}" push)" ]]; then
      fail "${f#"${root}"/} : les paths: de push et de pull_request diffèrent — le gate ne lit que des runs de push sur main."
    fi
  done

  # Les deux workflows doivent garder leur raison d'exister dans la surface du
  # gate (AC #2 et #3 de #7528 : aucun relâchement du déclenchement).
  printf '%s\n' "${tests_paths}" | grep -qxF 'api/**' \
    || fail "${TESTS_WORKFLOW} : le chemin « api/** » a disparu — une modification d'API ne déclencherait plus les tests, donc le gate n'aurait plus rien à exiger."
  printf '%s\n' "${web_paths}" | grep -qxF 'front/admin-dashboard/**' \
    || fail "${WEB_WORKFLOW} : le chemin « front/admin-dashboard/** » a disparu — une modification du super-admin ne déclencherait plus Web CI."

  # Parité exacte, dans les deux sens, entre les prédicats du gate et les
  # paths: des workflows requis.
  assert_gate_matches_workflow 'isApiPath' 'Tests - Leopardo RH' "${tests_paths}" "${deploy}" || true
  assert_gate_matches_workflow 'isWebPath' 'Web CI - Leopardo Admin' "${web_paths}" "${deploy}" || true

  # --- 4. .github/paths-filters.yml reste cohérent avec son rôle -----------
  # Cette liste décide si les jobs de tests TOURNENT (tests.yml detect-changes).
  # Elle doit donc couvrir au moins tout ce que les workflows peuvent voir —
  # sinon un workflow se déclencherait sans que ses tests soient lancés.
  local api_filter web_filter
  api_filter="$(extract_filter_list "${filters}" api)"
  web_filter="$(extract_filter_list "${filters}" web)"
  if [[ -z "${api_filter}" || -z "${web_filter}" ]]; then
    fail "${FILTERS_PATH} : listes api/web illisibles (format modifié ?) — la garde ne peut plus comparer."
    return 1
  fi

  # `api` : cette liste décide si les jobs de tests API tournent ; elle doit
  # couvrir la source d'API (sans quoi une modification d'API déclencherait le
  # workflow sans lancer les tests qui le justifient). Elle n'est PAS la copie
  # de tests.yml : elle ignore `front/admin-dashboard/**`, qui déclenche
  # tests.yml pour ses tests de contrat mais ne change pas le code d'API.
  printf '%s\n' "${api_filter}" | grep -qxF 'api/**' \
    || fail "${FILTERS_PATH} : le chemin « api/** » a disparu de la liste api — une modification d'API déclencherait tests.yml sans lancer les tests d'API."
  printf '%s\n' "${api_filter}" | grep -qxF '.github/workflows/tests.yml' \
    || fail "${FILTERS_PATH} : « .github/workflows/tests.yml » a disparu de la liste api — modifier le workflow ne relancerait pas ses propres tests."

  # `web` : superset ASSUMÉ (documenté dans le fichier) — il doit couvrir tout
  # ce que web-ci.yml voit, mais il n'est plus la source de la surface du gate.
  local pattern
  while IFS= read -r pattern; do
    [[ -z "${pattern}" ]] && continue
    printf '%s\n' "${web_filter}" | grep -qxF "${pattern}" \
      || fail "${FILTERS_PATH} : la liste « web » ne couvre plus « ${pattern} » (paths: de ${WEB_WORKFLOW}) — une modification web ne lancerait plus les tests."
  done <<< "${web_paths}"

  [[ ${errors} -eq ${before} ]] || return 1
  return 0
}

self_test() {
  local tmp
  tmp="$(mktemp -d)"
  trap 'rm -rf "${tmp:-}"' EXIT
  mkdir -p "${tmp}/.github/actions/verify-deploy-workflows" "${tmp}/.github/workflows"

  local -a fixtures=(
    "${ACTION_PATH}"
    "${DEPLOY_PATH}"
    "${FILTERS_PATH}"
    "${TESTS_WORKFLOW}"
    "${WEB_WORKFLOW}"
  )

  # --- cas sain -----------------------------------------------------------
  local f
  for f in "${fixtures[@]}"; do cp "${f}" "${tmp}/${f}"; done
  if ! ( check_filters_parity "${tmp}" ); then
    echo "::error::[deploy-gate --self-test] un dépôt conforme est refusé — garde trop stricte." >&2
    return 1
  fi

  # --- mutation 1 : le cas « rien à déployer » disparaît -------------------
  python3 - "${tmp}/${ACTION_PATH}" <<'PYS'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace('if (noRunExpected) {', 'if (false) {', 1)
open(p, 'w', encoding='utf-8').write(s)
PYS
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] mutation « noRunExpected neutralisé » non détectée." >&2
    return 1
  fi
  cp "${ACTION_PATH}" "${tmp}/${ACTION_PATH}"

  # --- mutation 2 : divergence de filtre du gate (cause racine #7511) ------
  python3 - "${tmp}/${DEPLOY_PATH}" <<'PYS'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace("p === '.github/workflows/phpstan-baseline.yml'", "p === '.github/workflows/phpstan-renamed.yml'", 1)
open(p, 'w', encoding='utf-8').write(s)
PYS
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] divergence des filtres api non détectée." >&2
    return 1
  fi
  cp "${DEPLOY_PATH}" "${tmp}/${DEPLOY_PATH}"

  # --- mutation 3 : le gate exige plus que le workflow ne peut produire ----
  # C'est le défaut EXACT de #7528 (web_changed=true sur un merge CI-only).
  python3 - "${tmp}/${DEPLOY_PATH}" <<'PYS'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace(
    "              p === '.github/workflows/web-ci.yml';",
    "              p === '.github/workflows/web-ci.yml' ||\n              p === '.github/workflows/deploy-main.yml';",
    1,
)
open(p, 'w', encoding='utf-8').write(s)
PYS
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] gate plus large que les paths: du workflow (#7528) non détecté." >&2
    return 1
  fi
  cp "${DEPLOY_PATH}" "${tmp}/${DEPLOY_PATH}"

  # --- mutation 4 : le workflow s'élargit, le gate devient trop étroit -----
  python3 - "${tmp}/${WEB_WORKFLOW}" <<'PYS'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace(
    "      - '.github/workflows/web-ci.yml'",
    "      - '.github/workflows/web-ci.yml'\n      - 'docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md'",
)
open(p, 'w', encoding='utf-8').write(s)
PYS
  if ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] gate devenu plus étroit que les paths: du workflow non détecté." >&2
    return 1
  fi
  cp "${WEB_WORKFLOW}" "${tmp}/${WEB_WORKFLOW}"

  # --- contrôle négatif : élargir la liste `web` de paths-filters.yml est une
  # divergence ASSUMÉE (rôle de détection) → la garde doit rester verte.
  python3 - "${tmp}/${FILTERS_PATH}" <<'PYS'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace("web:\n  - 'front/admin-dashboard/**'", "web:\n  - 'front/admin-dashboard/**'\n  - 'front/web/**'", 1)
open(p, 'w', encoding='utf-8').write(s)
PYS
  if ! ( check_filters_parity "${tmp}" ) 2>/dev/null; then
    echo "::error::[deploy-gate --self-test] élargir la liste `web` (détection) est refusé à tort — garde trop stricte sur une divergence assumée." >&2
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
  echo "✅  Verdict du gate de déploiement cohérent (api_changed mesuré, not-required ≠ no-runs, surface du gate = paths: des workflows requis)."
}

if [[ "${1:-}" == "--self-test" ]]; then
  self_test
else
  main "${1:-}"
fi
