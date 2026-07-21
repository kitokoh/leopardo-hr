#!/usr/bin/env bash
#
# PA2-AUTO-004: signale toute PR qui ne reference aucun ID PA2-* dans son
# titre/sa description, sauf si elle est explicitement typee `docs:` ou
# `chore:` (convention deja documentee dans CONVENTIONS.md §4.2 pour les
# types de commit/PR qui ne correspondent a aucun ticket de backlog produit
# — ex. mise a jour de dependances, reformattage, typo doc ponctuelle).
#
# Contexte: le protocole multi-agent (docs/PLAN_ACTION2/01_MODE_EXECUTION_MULTI_AGENT.md)
# et le garde de claim (PA2-AUTO-011, check-plan-action2-claim.sh) supposent
# tous les deux qu'une PR portant sur le backlog PLAN_ACTION2 reference son
# ticket. Rien ne signalait une PR "PA2-like" (touchant du code produit)
# ouverte sans aucun ID, qui echappe alors silencieusement au suivi
# d'avancement (rapport hebdo PA2-AUTO-005, dashboard PA2-AUTO-007).
#
# Design volontairement non bloquant (warning `::warning`, exit 0) plutot
# qu'un hard-fail: contrairement au garde de claim (collision reelle,
# donneed objective), l'absence d'ID PA2-* est un signal de qualite de
# process, pas une erreur technique certaine — une PR de correctif urgent
# hors backlog planifie (hotfix production, PR d'un contributeur externe,
# dependabot) reste legitime sans ID. Bloquer aurait cree un point de
# friction sur des PR par ailleurs valides. Le signal reste visible dans le
# step summary / logs CI pour le reviewer humain.
#
# Usage: check-plan-action2-pr-id.sh <owner/repo> <pr_number>
# Necessite `gh` authentifie (GITHUB_TOKEN suffit: lecture pull-requests).

set -euo pipefail

REPO="${1:?usage: check-plan-action2-pr-id.sh <owner/repo> <pr_number>}"
PR_NUMBER="${2:?usage: check-plan-action2-pr-id.sh <owner/repo> <pr_number>}"

PR_JSON=$(gh api "repos/${REPO}/pulls/${PR_NUMBER}")
PR_TITLE=$(echo "$PR_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('title') or '')")
PR_BODY=$(echo "$PR_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('body') or '')")

PA2_ID=$(printf '%s\n%s' "$PR_TITLE" "$PR_BODY" | grep -oE 'PA2-[A-Z0-9]+-[0-9]{3}' | head -n1 || true)

if [[ -n "$PA2_ID" ]]; then
  echo "✅ ID PA2-* trouve dans la PR #${PR_NUMBER}: ${PA2_ID}"
  exit 0
fi

# Type conventionnel explicite (CONVENTIONS.md §4.2: feat/fix/docs/test/ci/
# refactor/perf/chore). Seuls docs: et chore: sont exempts par design ici:
# feat/fix/refactor/perf/test/ci touchant du code produit devraient en
# pratique toujours tracer un ticket PA2-* si le backlog est a jour.
CONVENTIONAL_TYPE=$(printf '%s' "$PR_TITLE" | grep -oE '^(docs|chore)(\([^)]*\))?!?:' | head -n1 || true)

if [[ -n "$CONVENTIONAL_TYPE" ]]; then
  echo "::notice::PR #${PR_NUMBER} sans ID PA2-* mais typee '${CONVENTIONAL_TYPE}' — hors perimetre backlog explicite, pas de signalement."
  exit 0
fi

echo "::warning::PR #${PR_NUMBER} ('${PR_TITLE}') ne reference aucun ID PA2-* dans son titre/sa description, et n'est pas explicitement typee docs:/chore:. Si cette PR livre un ticket du backlog PLAN_ACTION2, ajouter son ID (ex. 'PA2-XXX-000') au titre ou a la description pour rester visible dans le suivi (rapport hebdo, dashboard readiness)."
exit 0
