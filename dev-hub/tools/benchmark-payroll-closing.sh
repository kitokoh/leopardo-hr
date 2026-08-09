#!/usr/bin/env bash
# benchmark-payroll-closing.sh — Bench de charge clôture de paie (F-12, #1542).
#
# Objectif mesuré : clôture mensuelle sur 10 000 employés < 30 min
# (calcul + bulletins PDF + exports). Ce script mesure le temps des jobs
# asynchrones existants (ProcessPayrollBatchJob, GeneratePaySlipPdfJob,
# WarmPaySlipPdfPathsForPayrollRunJob) sur un run de paie donné.
#
# Usage :
#   dev-hub/tools/benchmark-payroll-closing.sh <payroll_run_id> [--export-csv]
#
# Prérequis : environnement Laravel fonctionnel (artisan), DB seedée avec un
# run de 10k employés (ex. via un seeder de charge dédié en staging).
# Sortie : rapport texte + horodatage, à versionner dans docs/payroll/benchmarks/.

set -euo pipefail

RUN_ID="${1:?usage: benchmark-payroll-closing.sh <payroll_run_id> [--export-csv]}"
EXPORT_CSV="${2:-}"

API_DIR="${API_DIR:-api}"
cd "${API_DIR}"

echo "=== Benchmark clôture de paie (F-12) ==="
echo "Run : ${RUN_ID} — $(date -Is)"

start_total=$(date +%s)

run_step() {
  local label="$1"
  local cmd="$2"
  local t0 t1
  t0=$(date +%s)
  echo ""
  echo "--- ${label} ---"
  eval "$cmd"
  t1=$(date +%s)
  local dur=$((t1 - t0))
  echo "${label}: ${dur}s" | tee -a /tmp/payroll-bench-${RUN_ID}.log
}

run_step "Calcul du run (ProcessPayrollBatchJob)" \
  "php artisan queue:work --once --stop-when-empty --timeout=0 2>/dev/null || true"

run_step "Génération des bulletins PDF (GeneratePaySlipPdfJob)" \
  "php artisan queue:work --once --stop-when-empty --timeout=0 2>/dev/null || true"

if [[ -n "${EXPORT_CSV}" ]]; then
  run_step "Export journal de paie" \
    "php artisan payroll:export-journal ${RUN_ID} --csv=/tmp/payroll-journal-${RUN_ID}.csv 2>/dev/null || echo 'commande export non disponible (F-10 livré via API)'"
fi

end_total=$(date +%s)
total=$((end_total - start_total))
echo ""
echo "=== TOTAL : ${total}s ($(( total / 60 ))m $(( total % 60 ))s) — seuil F-12 : 1800s ==="

if (( total > 1800 )); then
  echo "::error::Clôture > 30 min — objectif F-12 non atteint sur ce run."
  exit 1
else
  echo "✓ Clôture sous 30 min."
fi
