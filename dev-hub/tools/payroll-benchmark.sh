#!/usr/bin/env bash
# payroll-benchmark.sh — Protocole de benchmark F-12 (#1542/#1594).
#
# Mesure la clôture mensuelle (calculate + validate-rh + lock) sur un jeu DZ
# réaliste généré par PayrollBenchmarkSeeder. À lancer dans l'environnement
# docker compose (api) ou un env local avec PG + Redis.
#
# Usage : dev-hub/tools/payroll-benchmark.sh [--employees=N] [--step=all]
#   ex.  dev-hub/tools/payroll-benchmark.sh --employees=1000
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

echo "→ payroll:benchmark --employees=${EMPLOYEES} --step=${STEP}"
echo "  (objectif F-12 : 10 000 employés < 30 min)"

# Barrière N+1 grossière : on surveille les requêtes SQL par seconde via les
# logs Laravel est trop lourd ici — on s'appuie sur les métriques de la
# commande (temps/employé) et on loggue le run.
/usr/bin/time -v php artisan payroll:benchmark --employees="${EMPLOYEES}" --step="${STEP}" 2> /tmp/payroll-benchmark-time.log \
  || { cat /tmp/payroll-benchmark-time.log >&2; exit 1; }

echo ""
echo "→ Consignez le run dans docs/payroll/BENCHMARK.md (section Historique des runs)."
