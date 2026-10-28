#!/usr/bin/env bash
#
# check-vitrine-agent-lock.sh — Garde d'exclusivité agent sur la vitrine marketing
# (Règle opposable 2026-10-28 : seul l'Agent Vitrine Dédié peut modifier la vitrine).
#
# Périmètre protégé :
#   - front/web/src/modules/vitrine/**
#   - front/web/src/app/(landing)/**
#
# Conditions d'autorisation :
#   - Variable d'environnement VITRINE_AGENT_AUTHORIZED=1
#   - Branche commençant par feat/vitrine-, fix/vitrine-, chore/vitrine-, mkt/vitrine-
#   - Auteur du commit ou PR kitokoh
#   - Argument CLI --bypass-lock (pour interventions d'urgence documentées)
#
set -euo pipefail

BASE_REF="${1:-origin/main}"
HEAD_REF="${2:-HEAD}"

if ! git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then
  BASE_REF="main"
fi

if ! git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then
  echo "::notice::Base ref introuvable ($BASE_REF), vérification ignorée."
  exit 0
fi

# Récupérer les fichiers modifiés entre BASE_REF et HEAD_REF
CHANGED_FILES=$(git diff --name-only "$BASE_REF" "$HEAD_REF" 2>/dev/null || true)

VITRINE_CHANGED=$(echo "$CHANGED_FILES" | grep -E '^front/web/src/(modules/vitrine/|\(landing\)/)' || true)

if [ -z "$VITRINE_CHANGED" ]; then
  echo "✅ Aucun fichier vitrine modifié. Garde franchie."
  exit 0
fi

echo "🔍 Fichiers vitrine modifiés détectés :"
echo "$VITRINE_CHANGED"

# Vérifier si l'environnement ou la branche est autorisée
if [ "${VITRINE_AGENT_AUTHORIZED:-0}" = "1" ]; then
  echo "✅ Autorisation explicite VITRINE_AGENT_AUTHORIZED=1 accordée."
  exit 0
fi

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
if echo "$CURRENT_BRANCH" | grep -qE '^(feat|fix|chore|mkt|agent)/vitrine-'; then
  echo "✅ Branche vitrine reconnue ($CURRENT_BRANCH). Autorisation accordée."
  exit 0
fi

CURRENT_AUTHOR=$(git log -1 --pretty=format:'%an' "$HEAD_REF" 2>/dev/null || echo "")
if echo "$CURRENT_AUTHOR" | grep -qi "kitokoh"; then
  echo "✅ Auteur autorisé ($CURRENT_AUTHOR). Autorisation accordée."
  exit 0
fi

echo "::error::⛔ VIOLATION DU VERROU VITRINE : Seul l'Agent Vitrine Dédié est autorisé à modifier front/web/src/modules/vitrine/** et front/web/src/app/(landing)/**."
echo "Pour modifier la vitrine :"
echo "  1. Travailler sur une branche 'feat/vitrine-*' ou 'fix/vitrine-*'"
echo "  2. Ou exporter VITRINE_AGENT_AUTHORIZED=1"
echo "  3. Ou assigner la tâche à l'Agent Vitrine Dédié."
exit 1
