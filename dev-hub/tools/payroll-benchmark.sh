#!/usr/bin/env bash
# payroll-benchmark.sh — Protocole de benchmark F-12 (#1542/#1594).
#
# Mesure la clôture mensuelle (calculate + validate-rh + lock) sur un jeu DZ
# réaliste généré par PayrollBenchmarkSeeder. À lancer dans l'environnement
# docker compose (api) ou un env local avec PG + Redis.
#
# Usage : dev-hub/tools/payroll-benchmark.sh [--employees=N] [--step=all]
#   ex.  dev-hub/tools/payroll-benchmark.sh --employees=1000
#
# Sortie :
#   - objectif F-12 vérifié (10 000 employés < 30 min => exit 1 sinon) ;
#   - garde de régression (> 20 % vs run précédent consigné => exit 1) ;
#   - consignation automatique du run dans docs/payroll/BENCHMARK.md.
set -euo pipefail

API_DIR="${1:-api}"
cd "${API_DIR}"

EMPLOYEES=10000
STEP=all
if [[ -n "${1:-}" && "$1" != -* ]]; then
  API_DIR="$1"; shift
fi
while [[ $# -gt 0 ]]; do
  case "$1" in
    --employees=*) EMPLOYEES="${1#*=}" ;;
    --step=*) STEP="${1#*=}" ;;
    *) echo "argument inconnu : $1" >&2; exit 2 ;;
  esac
  shift
done

RUN_LOG="/tmp/payroll-benchmark-run.log"
BENCH_DOC="../docs/payroll/BENCHMARK.md"

echo "→ payroll:benchmark --employees=${EMPLOYEES} --step=${STEP}"
echo "  (objectif F-12 : 10 000 employés < 30 min)"

# La sortie combinée (stdout + stderr, y compris time -v) est capturée dans
# RUN_LOG pour le parsing des métriques, tout en restant visible en direct.
/usr/bin/time -v php artisan payroll:benchmark --employees="${EMPLOYEES}" --step="${STEP}" > "${RUN_LOG}" 2>&1 \
  || { cat "${RUN_LOG}" >&2; exit 1; }
cat "${RUN_LOG}"

# ── Durée totale (métrique principale de régression) ─────────────────────────
TOTAL_SECONDS=$(grep -oE "Total clôture \([a-z-]+\): [0-9.]+s" "${RUN_LOG}" | grep -oE "[0-9.]+s$" | tr -d 's' | tail -1 || true)

echo ""
if [[ -z "${TOTAL_SECONDS}" ]]; then
  echo "::warning::Durée totale non détectée dans la sortie — garde F-12 et consignation ignorées."
else
  echo "→ Durée totale : ${TOTAL_SECONDS}s"

  # Objectif F-12 : clôture 10 000 employés < 30 min (1800 s).
  if awk "BEGIN{exit !(${TOTAL_SECONDS} > 1800)}"; then
    echo "::error::F-12 : clôture ${EMPLOYEES} employés = ${TOTAL_SECONDS}s > 1800s (objectif < 30 min) — optimisations requises."
    exit 1
  fi

  # Garde de régression : dégradation > 20 % vs le run précédent consigné.
  if [[ -f "${BENCH_DOC}" ]]; then
    # Dernière ligne de données de l'historique : | date | emp | step | calc | t/emp | mem | env | note |
    LAST_ROW=$(grep -E "^\\| [0-9]{4}-[0-9]{2}-[0-9]{2} " "${BENCH_DOC}" | tail -1 || true)
    if [[ -n "${LAST_ROW}" ]]; then
      # La note porte le total : « (auto) total=Ns » ; sinon on tombe sur la durée calculate (colonne 4).
      LAST_TOTAL=$(echo "${LAST_ROW}" | grep -oE "total=[0-9.]+s" | grep -oE "[0-9.]+" | head -1 || true)
      if [[ -z "${LAST_TOTAL}" ]]; then
        LAST_TOTAL=$(echo "${LAST_ROW}" | awk -F'|' '{gsub(/ /,"",$4); print $4}' | tr -d 's' || true)
      fi
      if [[ -n "${LAST_TOTAL}" ]] && awk "BEGIN{exit !(${TOTAL_SECONDS} > ${LAST_TOTAL} * 1.20)}"; then
        echo "::error::F-12 : régression de ${TOTAL_SECONDS}s vs ${LAST_TOTAL}s au run précédent (> 20 %) — ouvrir une issue de perf."
        exit 1
      fi
    fi
  fi

  # ── Consignation automatique du run dans BENCHMARK.md ───────────────────────
  RUN_DATE=$(date +%Y-%m-%d)
  CALC_DUR=$(grep "durée calculate" "${RUN_LOG}" | grep -oE "[0-9.]+s" | head -1 || true)
  EMP_DUR=$(grep "temps/employé" "${RUN_LOG}" | grep -oE "[0-9.]+ms" | head -1 || true)
  PEAK_MEM=$(grep "pic mémoire" "${RUN_LOG}" | grep -oE "[0-9.]+ Mo" | head -1 || true)
  ROW="| ${RUN_DATE} | ${EMPLOYEES} | ${STEP} | ${CALC_DUR:-n/a} | ${EMP_DUR:-n/a} | ${PEAK_MEM:-n/a} | local/docker | (auto) total=${TOTAL_SECONDS}s |"

  if [[ -f "${BENCH_DOC}" ]]; then
    if grep -q "^| ${RUN_DATE} | ${EMPLOYEES} | ${STEP} |" "${BENCH_DOC}"; then
      echo "→ Run déjà consigné pour ${RUN_DATE}/${EMPLOYEES}/${STEP} — BENCHMARK.md inchangé."
    elif grep -q "Généré par" "${BENCH_DOC}"; then
      awk -v row="${ROW}" '/^\\*Généré par/ { print row } { print }' "${BENCH_DOC}" > "${BENCH_DOC}.tmp" \
        && mv "${BENCH_DOC}.tmp" "${BENCH_DOC}"
      echo "→ Run consigné dans ${BENCH_DOC}"
    else
      echo "${ROW}" >> "${BENCH_DOC}"
      echo "→ Run consigné dans ${BENCH_DOC}"
    fi
  else
    echo "::warning::docs/payroll/BENCHMARK.md introuvable — run non consigné."
  fi
fi
