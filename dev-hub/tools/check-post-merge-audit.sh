#!/usr/bin/env bash
#
# PA2-AUTO-010: audit post-merge automatique.
#
# Pourquoi: rien ne verifiait automatiquement, une fois une PR mergee dans
# `main`, si les artefacts que le backlog considere obligatoires pour tout
# changement de comportement avaient bien ete mis a jour dans le meme merge
# (CONVENTIONS.md §4.3: "CHANGELOG.md obligatoire pour tout changement de
# comportement" ; CONVENTIONS.md §7: "Tout nouvel endpoint DOIT etre
# documente dans openapi.yaml"). Le template de PR (PA2-AUTO-006) rappelle
# ces obligations au moment de la redaction mais aucun garde ne les
# verifiait apres coup sur le merge commit reel.
#
# Ce script compare les fichiers touches par un merge commit (les deux
# parents d'un merge de PR, ou HEAD~1..HEAD en fallback pour un push simple)
# a trois attentes:
#   1. CHANGELOG.md doit avoir change si des fichiers produit (api/**,
#      front/**, shared/**) ont change et que le commit n'est pas
#      explicitement type docs:/chore: (memes types exemptes que
#      PA2-AUTO-004 pour rester coherent).
#   2. api/openapi.yaml doit avoir change si des controllers ou fichiers de
#      routes API ont change (api/app/**/Controllers/**, api/routes/**),
#      sauf si le diff ne touche que des fichiers non-controller (services,
#      modeles) ou si le commit ne touche aucun controller/route.
#   3. Un catalogue i18n pertinent doit avoir change si le diff touche une
#      surface frontend qui a deja un catalogue centralise
#      (front/web/src/lib/i18n, front/admin-dashboard/src/i18n,
#      front/mobile_apps/*/lib/l10n, api/lang, shared/i18n) — verifie
#      independamment par surface, une PR qui ne touche que le web ne doit
#      pas exiger un changement du catalogue mobile.
#
# Design volontairement non bloquant (::warning, exit 0 dans tous les cas)
# — comme PA2-AUTO-004: un ecart peut etre legitime (renommage de fichier,
# refactor sans changement de comportement visible, README interne d'un
# module), et bloquer aurait cree un point de friction sur des merges par
# ailleurs valides. Le signal reste visible dans le step summary / logs CI.
#
# Usage:
#   check-post-merge-audit.sh <owner/repo> [<base_sha> <head_sha>]
#
# Sans base/head explicites, utilise HEAD~1 et HEAD (adapte a un push sur
# main declenchant ce script juste apres un merge commit).
#
# Necessite `git` (diff local, aucun appel API GitHub necessaire).

set -euo pipefail

REPO="${1:?usage: check-post-merge-audit.sh <owner/repo> [base_sha] [head_sha]}"
BASE_SHA="${2:-HEAD~1}"
HEAD_SHA="${3:-HEAD}"

echo "Post-merge audit (PA2-AUTO-010) pour ${REPO}: diff ${BASE_SHA}..${HEAD_SHA}"

CHANGED_FILES=$(git diff --name-only "${BASE_SHA}" "${HEAD_SHA}")

if [[ -z "$CHANGED_FILES" ]]; then
  echo "Aucun fichier modifie dans ce diff — rien a auditer."
  exit 0
fi

HEAD_SUBJECT=$(git log -1 --format=%s "${HEAD_SHA}")

FINDINGS=0

warn() {
  echo "::warning::$1"
  FINDINGS=$((FINDINGS + 1))
}

note() {
  echo "::notice::$1"
}

# --- 1. CHANGELOG.md ---------------------------------------------------
CONVENTIONAL_TYPE=$(printf '%s' "$HEAD_SUBJECT" | grep -oE '^(docs|chore)(\([^)]*\))?!?:' | head -n1 || true)

PRODUCT_FILES=$(printf '%s\n' "$CHANGED_FILES" | grep -E '^(api|front|shared)/' || true)

if [[ -n "$PRODUCT_FILES" ]] && ! printf '%s\n' "$CHANGED_FILES" | grep -qx "CHANGELOG.md"; then
  if [[ -n "$CONVENTIONAL_TYPE" ]]; then
    note "CHANGELOG.md non modifie mais commit type '${CONVENTIONAL_TYPE}' (docs:/chore: exempt par convention, CONVENTIONS.md §4.2)."
  else
    warn "CHANGELOG.md non modifie alors que ce merge touche du code produit ($(printf '%s\n' "$PRODUCT_FILES" | wc -l | tr -d ' ') fichier(s) sous api/, front/ ou shared/). CONVENTIONS.md §4.3 exige une entree CHANGELOG.md pour tout changement de comportement."
  fi
else
  echo "OK: CHANGELOG.md a jour ou aucun fichier produit touche."
fi

# --- 2. api/openapi.yaml -------------------------------------------------
CONTROLLER_OR_ROUTE_FILES=$(printf '%s\n' "$CHANGED_FILES" | grep -E '^api/(routes/|app/.*Controllers?/)' || true)

if [[ -n "$CONTROLLER_OR_ROUTE_FILES" ]] && ! printf '%s\n' "$CHANGED_FILES" | grep -qx "api/openapi.yaml"; then
  warn "api/openapi.yaml non modifie alors que ce merge touche des controllers/routes API: $(printf '%s\n' "$CONTROLLER_OR_ROUTE_FILES" | tr '\n' ' '). CONVENTIONS.md §7 exige que tout nouvel endpoint soit documente dans openapi.yaml — verifier si ce changement ajoute/modifie reellement un contrat public (sinon ecart legitime: refactor interne, correction de bug sans changement de signature)."
else
  echo "OK: api/openapi.yaml a jour ou aucun controller/route API touche."
fi

# --- 3. Catalogues i18n --------------------------------------------------
declare -A I18N_SURFACES=(
  ["front/web/src"]="front/web/src/lib/i18n/locales"
  ["front/admin-dashboard/src"]="front/admin-dashboard/src/i18n/locales"
  ["front/mobile_apps/leopardo_employee/lib"]="front/mobile_apps/leopardo_employee/lib/l10n"
  ["front/mobile_apps/leopardo_manager/lib"]="front/mobile_apps/leopardo_manager/lib/l10n"
  ["front/mobile_apps/leopardo_hr/lib"]="front/mobile_apps/leopardo_hr/lib/l10n"
  ["front/mobile_apps/leopardo_platform_admin/lib"]="front/mobile_apps/leopardo_platform_admin/lib/l10n"
  ["front/mobile_apps/leopardo_core/lib"]="front/mobile_apps/leopardo_core/lib/l10n"
  ["api/resources/views/pdf"]="api/lang"
  ["api/resources/views/emails"]="api/lang"
)

for surface in "${!I18N_SURFACES[@]}"; do
  catalog="${I18N_SURFACES[$surface]}"

  SURFACE_FILES=$(printf '%s\n' "$CHANGED_FILES" | grep -E "^${surface}/" || true)
  # Ignore le catalogue lui-meme dans le comptage "surface touchee": si seul
  # le catalogue a change, ce n'est pas une extraction de texte en dur qui
  # aurait du s'accompagner d'un changement de catalogue.
  SURFACE_FILES_EXCL_CATALOG=$(printf '%s\n' "$SURFACE_FILES" | grep -v -E "^${catalog}/" || true)

  if [[ -z "$SURFACE_FILES_EXCL_CATALOG" ]]; then
    continue
  fi

  CATALOG_TOUCHED=$(printf '%s\n' "$CHANGED_FILES" | grep -E "^${catalog}/" || true)

  if [[ -z "$CATALOG_TOUCHED" ]]; then
    warn "Surface i18n '${surface}' modifiee ($(printf '%s\n' "$SURFACE_FILES_EXCL_CATALOG" | wc -l | tr -d ' ') fichier(s)) sans changement correspondant dans '${catalog}'. Verifier si ce merge introduit du texte en dur nouveau (PA2-I18N-*) ou si le changement ne touche pas de texte visible (refactor, style, logique)."
  fi
done

echo "OK: catalogues i18n verifies par surface touchee."

echo "---"
if [[ "$FINDINGS" -gt 0 ]]; then
  echo "Post-merge audit: ${FINDINGS} ecart(s) signale(s) ci-dessus (non bloquant)."
else
  echo "Post-merge audit: aucun ecart signale."
fi

exit 0
