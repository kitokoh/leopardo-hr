#!/usr/bin/env bash
# ============================================================
# check-branch-protection-test.sh — Auto-test de la garde de protection de
# branche (#2011) et de son invariant « un check tiers n'est jamais requis »
# (issue #7480).
#
# Pourquoi ce fichier existe : le canonique commité
# (dev-hub/tools/branch-protection-canonical.json) est resté périmé entre #7096
# (retrait de `Backend Coverage` des checks requis) et #7480. La garde était donc
# ROUGE EN PERMANENCE — et son message de remédiation invitait à rejouer le
# canonique, c'est-à-dire à annuler #7096. Un check rouge permanent ne signale
# plus rien : c'est la leçon #3545 (« un skip silencieux ressemble à un succès »)
# vue de l'autre côté.
#
# Cas couverts :
#   1. protection réelle conforme au canonique                  → VERT ;
#   2. un check tiers (quota) rendu REQUIS sur main             → ROUGE (#7480) ;
#   3. le canonique lui-même déclare un check tiers requis      → ROUGE (#7480) ;
#   4. une dérive de protection (check requis ajouté)           → ROUGE (#2011).
#
# Usage : bash dev-hub/tools/check-branch-protection-test.sh
# ============================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-branch-protection.sh"
CANONICAL="$HERE/branch-protection-canonical.json"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

fail() { echo "FAIL : $1" >&2; exit 1; }

# Protection au format de l'API GitHub, dérivée du canonique.
api_shape() {
  jq '{
    required_status_checks: {strict: .required_status_checks.strict, contexts: .required_status_checks.contexts},
    enforce_admins: {enabled: .enforce_admins},
    required_pull_request_reviews: .required_pull_request_reviews,
    allow_force_pushes: {enabled: .allow_force_pushes},
    allow_deletions: {enabled: .allow_deletions}
  }' "$1"
}

run_guard() { # <fixture> [<guard>]
  local fixture="$1" guard="${2:-$GUARD}"
  set +e
  OUT="$(BRANCH_PROTECTION_FIXTURE="$fixture" bash "$guard" "kitokoh/leopardo-hr" main 2>&1)"
  STATUS=$?
  set -e
}

expect_pass() {
  if [[ "$STATUS" -ne 0 ]]; then
    printf '%s\n' "$OUT" >&2
    fail "cas $1 : la garde refuse une protection conforme"
  fi
  echo "ok: cas $1 vert — $2"
}

expect_fail() {
  if [[ "$STATUS" -eq 0 ]]; then
    fail "cas $1 : dérive non détectée — $2"
  fi
  printf '%s\n' "$OUT" | grep -qF "$3" \
    || { printf '%s\n' "$OUT" >&2; fail "cas $1 : motif attendu absent (« $3 »)"; }
  echo "ok: cas $1 rouge — $2"
}

# ── Cas 1 : conforme → VERT ──────────────────────────────────────────────────
api_shape "$CANONICAL" > "$TMP/ok.json"
run_guard "$TMP/ok.json"
expect_pass 1 "protection conforme au canonique"

# ── Cas 2 : un check tiers (quota) devient REQUIS → ROUGE ────────────────────
api_shape "$CANONICAL" \
  | jq '.required_status_checks.contexts += ["Workers Builds: gestionemploye"]' > "$TMP/tiers-requis.json"
run_guard "$TMP/tiers-requis.json"
expect_fail 2 "check tiers requis sur main" "exige le check tiers"

# ── Cas 3 : le CANONIQUE déclare un check tiers requis → ROUGE ───────────────
# (sinon la remédiation proposée par la garde le réinstallerait)
mkdir -p "$TMP/tree/dev-hub/tools"
cp "$GUARD" "$TMP/tree/dev-hub/tools/check-branch-protection.sh"
jq '.required_status_checks.contexts += ["Strix"]' "$CANONICAL" \
  > "$TMP/tree/dev-hub/tools/branch-protection-canonical.json"
jq '.required_status_checks.contexts += ["Strix"]' "$TMP/ok.json" > "$TMP/canon-tier.json"
run_guard "$TMP/canon-tier.json" "$TMP/tree/dev-hub/tools/check-branch-protection.sh"
expect_fail 3 "check tiers dans le canonique" "Le canonique déclare le check tiers"

# ── Cas 4 : dérive de protection (un check requis en plus) → ROUGE (#2011) ───
api_shape "$CANONICAL" \
  | jq '.required_status_checks.contexts += ["Check Pirate"]' > "$TMP/derive.json"
run_guard "$TMP/derive.json"
expect_fail 4 "check requis ajouté sans canonique" "DEVIE du canonique"

echo "PASS : check-branch-protection-test.sh"
