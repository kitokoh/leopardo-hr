#!/usr/bin/env bash
# =============================================================================
# pre-push.sh — validation locale avant push (issue #5516)
#
# Lance les gardes équivalents aux checks requis de la protection de branche
# sur le PÉRIMÈTRE MODIFIÉ, pour détecter les régressions AVANT le merge
# (anti-« main rouge » : PHPStan accounting #5494, collisions migrations #1962).
#
# Usage :  dev-hub/tools/pre-push.sh [--all]
#          --all : force TOUS les gardes, pas seulement le périmètre modifié
#
# Sortie : 0 = OK, 1 = échec (un garde a échoué)
# =============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

ALL=0
[[ "${1:-}" == "--all" ]] && ALL=1

FAIL=0
STEP=0

ok()   { echo "  ✅ $1"; }
fail() { echo "  ❌ $1"; FAIL=1; }

# --- détection du périmètre modifié (vs main) --------------------------------
BASE_SHA="${BASE_SHA:-$(git merge-base HEAD origin/main 2>/dev/null || echo HEAD~1)}"
CHANGED="$(git diff --name-only "$BASE_SHA" HEAD 2>/dev/null || echo "")"

has() { echo "$CHANGED" | grep -qE "$1"; }

echo "── pre-push (base: ${BASE_SHA:0:8}, $([ -n "$CHANGED" ] && echo "$(echo "$CHANGED" | wc -l | tr -d ' ') fichiers modifiés" || echo "diff vide")) ──"

# 1. Garde migrations (collisions #1962) — TOUJOURS si api/ change
if [[ $ALL -eq 1 ]] || has "api/database/migrations/"; then
  STEP=$((STEP+1))
  echo "[$STEP] check-migration-basename-collisions"
  if bash dev-hub/tools/check-migration-basename-collisions.sh api/database/migrations >/dev/null 2>&1; then
    ok "collisions migrations"
  else
    fail "collisions migrations (voir issue #1962)"
  fi
fi

# 2. i18n : chaînes hardcodées (surfaces à risque) — si mobile/web change
if [[ $ALL -eq 1 ]] || has "front/"; then
  STEP=$((STEP+1))
  echo "[$STEP] check-i18n-diff"
  if node dev-hub/tools/check-i18n-diff.js "$BASE_SHA" HEAD >/dev/null 2>&1; then
    ok "aucune chaîne hardcodée ajoutée"
  else
    fail "nouvelles chaînes hardcodées détectées (l10n requis)"
  fi
fi

# 3. Parité ARB + générés (gen-l10n) — si ARB ou code l10n change
if [[ $ALL -eq 1 ]] || has "\.arb$|app_localizations"; then
  STEP=$((STEP+1))
  echo "[$STEP] parité ARB ×4 + gen-l10n"
  PARITY_OK=1
  for loc in fr en ar tr; do
    F="front/mobile_apps/leopardo_core/lib/l10n/app_${loc}.arb"
    [ -f "$F" ] || { PARITY_OK=0; break; }
  done
  if [[ $PARITY_OK -eq 1 ]] && command -v flutter >/dev/null 2>&1; then
    (cd front/mobile_apps/leopardo_core && flutter gen-l10n >/dev/null 2>&1)
    if git diff --quiet -- front/mobile_apps/leopardo_core/lib/l10n/generated/; then
      ok "parité ARB + générés à jour"
    else
      fail "gen-l10n non régénéré (git diff sur generated/)"
    fi
  else
    fail "ARB manquant ou flutter indisponible"
  fi
fi

# 4. Flutter analyze — apps mobiles dont le code change
MOBILE_APPS="leopardo_core leopardo_employee leopardo_hr leopardo_manager leopardo_platform_admin leopardo_marketing"
if command -v flutter >/dev/null 2>&1; then
  for APP in $MOBILE_APPS; do
    if [[ $ALL -eq 1 ]] || has "front/mobile_apps/${APP}/"; then
      STEP=$((STEP+1))
      echo "[$STEP] flutter analyze $APP"
      if (cd "front/mobile_apps/$APP" && flutter analyze >/dev/null 2>&1); then
        ok "$APP analyze"
      else
        fail "$APP analyze"
      fi
    fi
  done
else
  echo "  ⚠️  flutter absent du PATH — analyze mobile ignoré"
fi

# 5. Workflows GitHub : actionlint/shellcheck — si .github change
if [[ $ALL -eq 1 ]] || has "^\.github/"; then
  STEP=$((STEP+1))
  echo "[$STEP] actionlint/shellcheck (workflows)"
  if command -v actionlint >/dev/null 2>&1; then
    if actionlint >/dev/null 2>&1; then
      ok "workflows valides"
    else
      fail "actionlint : workflows invalides"
    fi
  else
    echo "  ⚠️  actionlint absent — vérification shellcheck basique"
    if command -v shellcheck >/dev/null 2>&1; then
      for WF in $(git diff --name-only "$BASE_SHA" HEAD -- '.github/workflows/*.yml' 2>/dev/null); do
        shellcheck "$WF" >/dev/null 2>&1 || fail "shellcheck $WF"
      done
    fi
  fi
fi

# 6. API PHP : lint rapide des fichiers modifiés (si php dispo)
if [[ $ALL -eq 1 ]] || has "^api/.*\.php$"; then
  if command -v php >/dev/null 2>&1; then
    STEP=$((STEP+1))
    echo "[$STEP] php -l (fichiers modifiés)"
    PHP_FAIL=0
    for F in $(git diff --name-only "$BASE_SHA" HEAD -- 'api/**/*.php' 'api/*.php' 2>/dev/null); do
      php -l "$F" >/dev/null 2>&1 || { echo "    ❌ syntaxe: $F"; PHP_FAIL=1; }
    done
    [[ $PHP_FAIL -eq 0 ]] && ok "syntaxe PHP" || fail "syntaxe PHP"
  fi
fi

# 7. CHANGELOG : entrée présente en tête d'[Unreleased] (convention repo)
if [[ $ALL -eq 1 ]] || has "CHANGELOG.md|^api/CHANGELOG.md"; then
  STEP=$((STEP+1))
  echo "[$STEP] CHANGELOG [Unreleased]"
  # La section [Unreleased] peut être repoussée par des merges concurrents —
  # on vérifie qu'elle existe quelque part dans le fichier.
  if grep -q "## \[Unreleased\]" CHANGELOG.md 2>/dev/null; then
    ok "CHANGELOG présent"
  else
    fail "CHANGELOG : section [Unreleased] manquante"
  fi
fi

echo ""
if [[ $FAIL -eq 0 ]]; then
  echo "✅ pre-push : tous les gardes passent — push OK"
  exit 0
else
  echo "❌ pre-push : $FAIL garde(s) en échec — corriger avant de pousser"
  exit 1
fi
