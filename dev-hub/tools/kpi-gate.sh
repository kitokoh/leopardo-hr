#!/usr/bin/env bash
#
# #5158 (P1, ops/tooling) — Extraction automatisée des 9 KPI du gate J60
# + snapshot daté. Chaque KPI est extrait par un moyen reproductible : la
# décision A/B/C du bilan 60 jours doit pouvoir être RE-vérifiée par
# n'importe qui (agent froid, fondateur, QA).
#
# Usage :
#   GH_TOKEN=ghp_xxx ./kpi-gate.sh kitokoh/leopardo-hr [--date 2026-08-20] [--days 30] [--out docs/pilotes]
#
# KPI couverts (méthode détaillée dans docs/ops/MESURE_KPI.md) :
#   1  Conversion signup → dashboard (≥ 30 %)        — artisan pilot:kpi-report
#   2  Trial provisioning (< 2 min, cible < 30 s)     — artisan pilot:kpi-report
#   3  CI verte (100 % des runs des 10 j ouvrés)      — GitHub Actions API
#   4  Coverage Payroll (≥ 80 %)                      — check-run "Backend Coverage" + payroll-ci.yml
#   5  Pilotes actifs (≥ 2 / semaine)                 — artisan pilot:report (via pilot:kpi-report)
#   6  MRR (> 0)                                      — Stripe API (si STRIPE_SECRET_KEY)
#   7  Issues non-dependabot (≤ 10)                   — GitHub Issues API
#   8  Ratio fix/feat 60 j (≤ 2,5)                    — git log (depuis le clone)
#   9  Coût agents cumulé (≤ budget)                  — docs/OPS/BUDGET_AGENTS.md
#
# Sortie : snapshot markdown daté (défaut docs/pilotes/KPI_GATE_<date>.md)
# + code de sortie 0 (rapport, non bloquant).

set -euo pipefail

REPO="${1:?usage: kpi-gate.sh <owner/repo> [--date YYYY-MM-DD] [--days N] [--out DIR]}"
DATE="$(date +%Y-%m-%d)"
DAYS=30
OUT_DIR="docs/pilotes"

while [[ $# -gt 1 ]]; do
  case "$2" in
    --date) DATE="${3:?}"; shift 2 ;;
    --days) DAYS="${3:?}"; shift 2 ;;
    --out)  OUT_DIR="${3:?}"; shift 2 ;;
    *) shift ;;
  esac
done

if [[ -z "${GH_TOKEN:-}" ]]; then
  echo "Erreur : GH_TOKEN requis (lecture GitHub suffit)." >&2
  exit 1
fi

API="https://api.github.com"
AUTH="Authorization: Bearer ${GH_TOKEN}"
OUT_FILE="${OUT_DIR}/KPI_GATE_${DATE}.md"
mkdir -p "${OUT_DIR}"

verdict() { # valeur cible compare_better
  local val="$1" target="$2" op="${3:-ge}"
  if [[ "$val" == "n/a" || "$val" == "" ]]; then echo "⚠️ n/a"; return; fi
  if [[ "$op" == "le" ]]; then
    awk -v v="$val" -v t="$target" 'BEGIN{ exit !(v<=t) }' && echo "✅" || echo "❌"
  else
    awk -v v="$val" -v t="$target" 'BEGIN{ exit !(v>=t) }' && echo "✅" || echo "❌"
  fi
}

# ── KPI 1, 2, 5 — côté base (artisan) ─────────────────────────────────────
KPI_DB=""
if [[ -x api/artisan ]]; then
  KPI_DB="$(php artisan pilot:kpi-report --days="${DAYS}" --json 2>/dev/null || true)"
fi
if [[ -z "$KPI_DB" || "$KPI_DB" == "null" ]]; then
  KPI_DB="{\"error\":\"artisan indisponible — exécuter depuis api/ avec env applicative\"}"
fi
CONVERSION="$(echo "$KPI_DB" | jq -r '.kpi_1_conversion_signup_dashboard.rate_percent // "n/a"' 2>/dev/null || echo "n/a")"
PROV_P50="$(echo "$KPI_DB" | jq -r '.kpi_2_trial_provisioning.p50_seconds // "n/a"' 2>/dev/null || echo "n/a")"
PROV_UNDER_120="$(echo "$KPI_DB" | jq -r '.kpi_2_trial_provisioning.share_under_120s // "n/a"' 2>/dev/null || echo "n/a")"
PILOTES_ACTIFS="$(echo "$KPI_DB" | jq -r '.kpi_5_pilotes_actifs.active // "n/a"' 2>/dev/null || echo "n/a")"

# ── KPI 3 — CI verte (10 derniers jours ouvrés ≈ 14 calendaires) ──────────
SINCE_ISO="$(date -u -d "${DAYS} days ago" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || date -u -v-${DAYS}d +%Y-%m-%dT%H:%M:%SZ)"
RUNS="$(curl -s -H "$AUTH" "${API}/repos/${REPO}/actions/runs?per_page=100" | jq -r '.workflow_runs[] | select(.created_at >= "'${SINCE_ISO}'") | .conclusion' 2>/dev/null || true)"
TOTAL_RUNS="$(echo "$RUNS" | grep -c . || true)"
GREEN_RUNS="$(echo "$RUNS" | grep -c "^success$" || true)"
if [[ "$TOTAL_RUNS" -gt 0 ]]; then
  CI_PCT="$(awk -v g="$GREEN_RUNS" -v t="$TOTAL_RUNS" 'BEGIN{ printf "%.1f", g/t*100 }')"
else
  CI_PCT="n/a"
fi

# ── KPI 4 — Coverage Payroll (≥ 80 %) ─────────────────────────────────────
COV="n/a"
PAYROLL_RUN="$(curl -s -H "$AUTH" "${API}/repos/${REPO}/actions/workflows/payroll-ci.yml/runs?per_page=1")"
PAYROLL_CI="$(echo "$PAYROLL_RUN" | jq -r '.workflow_runs[0].conclusion // "n/a"' 2>/dev/null || echo "n/a")"
PAYROLL_RUN_ID="$(echo "$PAYROLL_RUN" | jq -r '.workflow_runs[0].id // ""' 2>/dev/null || true)"
if [[ -n "$PAYROLL_RUN_ID" ]]; then
  # Le job imprime « Payroll coverage: X% » dans les logs (payroll-ci.yml) :
  # on lit les logs du run pour extraire la valeur réelle.
  COV="$(curl -s -H "$AUTH" "${API}/repos/${REPO}/actions/runs/${PAYROLL_RUN_ID}/jobs" \
    | jq -r '.jobs[].id // empty' 2>/dev/null \
    | while read -r jid; do curl -sL -H "$AUTH" "${API}/repos/${REPO}/actions/jobs/${jid}/logs"; done \
    | grep -oE "Payroll coverage: [0-9]+(\.[0-9]+)?%" | head -1 | grep -oE "[0-9]+(\.[0-9]+)?" || echo "")"
  [[ "$COV" == "" ]] && COV="n/a"
fi

# ── KPI 6 — MRR (Stripe) ──────────────────────────────────────────────────
MRR="n/a"
if [[ -n "${STRIPE_SECRET_KEY:-}" ]]; then
  MRR_CENTS="$(curl -s -u "${STRIPE_SECRET_KEY}:" "https://api.stripe.com/v1/subscriptions?status=active&limit=100" | jq '[.data[] | .items.data[0].price.unit_amount // 0] | add' 2>/dev/null || echo "")"
  if [[ -n "$MRR_CENTS" ]]; then MRR="$(awk -v c="$MRR_CENTS" 'BEGIN{ printf "%.2f", c/100 }')"; fi
fi

# ── KPI 7 — Issues non-dependabot (≤ 10) ──────────────────────────────────
ISSUES_OPEN="$(curl -s -H "$AUTH" "${API}/repos/${REPO}/issues?state=open&per_page=100" | jq '[.[] | select(.pull_request == null) | select((.title | test("dependabot"; "i")) | not) | select(([.labels[].name] | index("dependencies")) == null)] | length' 2>/dev/null || echo "n/a")"

# ── KPI 8 — Ratio fix/feat 60 j (≤ 2,5) ───────────────────────────────────
RATIO="n/a"
if git rev-parse --git-dir >/dev/null 2>&1; then
  FIXES="$(git log --since="60 days ago" --pretty=%s | grep -ciE '^(fix|hotfix|fixup|bugfix)' || true)"
  FEATS="$(git log --since="60 days ago" --pretty=%s | grep -ciE '^(feat|feature)' || true)"
  if [[ "$FEATS" -gt 0 ]]; then RATIO="$(awk -v f="$FIXES" -v t="$FEATS" 'BEGIN{ printf "%.2f", f/t }')"; else RATIO="inf (0 feat)"; fi
fi

# ── KPI 9 — Coût agents (≤ budget) ────────────────────────────────────────
BUDGET_FILE="docs/OPS/BUDGET_AGENTS.md"
COUT="n/a"
if [[ -f "$BUDGET_FILE" ]]; then
  # Colonne « Consommé (mois courant) » — tolère les formats « 123 € » / « 123 »
  CONSOMME="$(grep -E '^\|' "$BUDGET_FILE" | grep -E 'Consommé|consommé' | grep -oE '[0-9]+([.,][0-9]+)?' | head -1 || true)"
  [[ -n "$CONSOMME" ]] && COUT="${CONSOMME}"
fi

# ── Rédaction du snapshot ─────────────────────────────────────────────────
{
  echo "# 📊 KPI Gate — Snapshot daté (${DATE})"
  echo ""
  echo "> Généré par \`dev-hub/tools/kpi-gate.sh ${REPO} --date ${DATE} --days ${DAYS}\` — méthode : \`docs/ops/MESURE_KPI.md\`."
  echo "> Ré-exécutable à l'identique par tout agent QA (GH_TOKEN en lecture)."
  echo ""
  echo "| # | KPI | Cible | Valeur | Verdict | Source |"
  echo "|---|---|---|---|---|---|"
  echo "| 1 | Conversion signup → dashboard | ≥ 30 % | ${CONVERSION} % | $(verdict "$CONVERSION" 30 ge) | \`pilot:kpi-report\` (company_requests) |"
  echo "| 2 | Trial provisioning | < 2 min (cible < 30 s) | p50 ${PROV_P50} s · ${PROV_UNDER_120} % < 2 min | $(verdict "$PROV_UNDER_120" 100 ge) | \`pilot:kpi-report\` (trial_provisionings) |"
  echo "| 3 | CI verte (${DAYS} j) | 100 % des runs | ${CI_PCT} % (${GREEN_RUNS}/${TOTAL_RUNS}) | $(verdict "$CI_PCT" 100 ge) | GitHub Actions API |"
  echo "| 4 | Coverage Payroll | ≥ 80 % | ${COV} % (dernier run payroll-ci : ${PAYROLL_CI}) | $(verdict "$COV" 80 ge) | check-run \`Backend Coverage\` + payroll-ci.yml |"
  echo "| 5 | Pilotes actifs / semaine | ≥ 2 | ${PILOTES_ACTIFS} | $(verdict "$PILOTES_ACTIFS" 2 ge) | \`pilot:report\` (7 j) |"
  echo "| 6 | MRR | > 0 | ${MRR} | $(verdict "$MRR" 0 ge) | Stripe API (si STRIPE_SECRET_KEY) |"
  echo "| 7 | Issues non-dependabot | ≤ 10 | ${ISSUES_OPEN} | $(verdict "$ISSUES_OPEN" 10 le) | GitHub Issues API |"
  echo "| 8 | Ratio fix/feat (60 j) | ≤ 2,5 | ${RATIO} | $(verdict "$RATIO" 2.5 le) | \`git log\` (60 j) |"
  echo "| 9 | Coût agents (mois courant) | ≤ budget | ${COUT} | ⚠️ | docs/OPS/BUDGET_AGENTS.md |"
  echo ""
  echo "## Notes"
  echo "- KPI 1/2/5 : nécessitent l'env applicative (base + Redis) — sinon « n/a »."
  echo "- KPI 6 : nécessite STRIPE_SECRET_KEY — jamais commitée."
  echo "- KPI 8 : exécuter depuis le clone git du repo."
  echo "- KPI 9 : tableau de suivi à remplir chaque vendredi (docs/OPS/BUDGET_AGENTS.md, issue #5148)."
} > "$OUT_FILE"

echo "Snapshot écrit : $OUT_FILE"
