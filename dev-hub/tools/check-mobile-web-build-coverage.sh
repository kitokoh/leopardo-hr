#!/usr/bin/env bash
# check-mobile-web-build-coverage.sh — garde de couverture du job `flutter-build-web`
#
# ## Le défaut que cette garde ferme
#
# Le job `flutter-build-web` de `.github/workflows/mobile-apps-ci.yml` porte une
# matrice écrite À LA MAIN : une entrée par application. Rien ne relie cette
# liste au disque. Ajouter `front/mobile_apps/leopardo_<v>_app` sans ajouter sa
# ligne dans la matrice produit donc une application qui **n'est jamais
# compilée pour le web** — et le silence est total : la CI reste verte, le
# build local de l'app fonctionne, seule la cible web manque. C'est exactement
# le mode de défaillance que la mission « une app par verticale » rend
# probable : chaque nouvelle verticale est un oubli potentiel.
#
# Le dépôt a déjà payé ce prix côté distribution : `leopardo_accounting` et
# `leopardo_marketing` existaient sur disque mais dans AUCUNE matrice de
# distribution, donc aucun APK release n'était jamais produit pour elles
# (#9381). Cette garde applique la même leçon à la cible web, AVANT que le
# défaut ne se produise.
#
# ## Ce qu'elle vérifie
#
#   1. **Couverture** : toute app `front/mobile_apps/leopardo_*_app` sur disque
#      a une entrée `path:` dans la matrice du job `flutter-build-web`.
#   2. **Pas d'entrée morte** : toute entrée `path:` de la matrice pointe un
#      répertoire qui existe réellement.
#   3. **Artefact nommé** : chaque entrée déclare un `artifact:` non vide, et
#      deux entrées ne peuvent pas publier sous le même nom (GitHub refuse le
#      second upload, mais l'erreur arrive après 10 minutes de build).
#   4. **Forme du bootstrap** : chaque entrée déclare `multi_vertical:` à
#      `'true'` ou `'false'` — la vérification d'artefact du job branche dessus,
#      et une valeur absente ferait tomber le job dans la branche « app
#      dédiée » pour l'app multi-verticales.
#
# ## Pourquoi `leopardo_public` est traitée à part
#
# Elle ne s'appelle pas `leopardo_*_app` : c'est l'app multi-verticales
# historique. Elle est donc vérifiée par la règle 2 (entrée vivante) mais pas
# par la règle 1 (qui ne concerne que les apps dédiées).
#
# Usage : bash dev-hub/tools/check-mobile-web-build-coverage.sh [repo_root]
# Exit 0 = couverture complète ; exit 1 = au moins un écart.

set -euo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "${ROOT}"

WORKFLOW=".github/workflows/mobile-apps-ci.yml"
MOBILE_DIR="front/mobile_apps"

errors=0
fail() { echo "::error::$*"; errors=$((errors + 1)); }

if [[ ! -f "${WORKFLOW}" ]]; then
  fail "workflow introuvable : ${WORKFLOW}"
  exit 1
fi

# --- Extraction du bloc du job `flutter-build-web` ---------------------------
# Le job va de sa déclaration `  flutter-build-web:` jusqu'au prochain job de
# même niveau (deux espaces d'indentation suivis d'un identifiant et de `:`).
# On s'arrête aussi sur `# ---` de séparation, qui précède chaque job.
JOB_BLOCK="$(
  awk '
    /^  flutter-build-web:[[:space:]]*$/ { inside = 1; print; next }
    inside && /^  [a-zA-Z0-9_-]+:[[:space:]]*$/ { exit }
    inside { print }
  ' "${WORKFLOW}"
)"

if [[ -z "${JOB_BLOCK}" ]]; then
  fail "job 'flutter-build-web' introuvable dans ${WORKFLOW} — le nom du job a changé, la garde ne couvre plus rien"
  exit 1
fi

# --- Entrées de la matrice ---------------------------------------------------
# Chaque entrée porte `path: front/mobile_apps/<app>`. On garde l'ordre du
# fichier : les messages d'erreur citent l'app, pas un numéro de ligne.
MATRIX_PATHS="$(
  grep -oE "path:[[:space:]]*${MOBILE_DIR}/[a-z0-9_]+" <<< "${JOB_BLOCK}" \
    | sed -E "s|path:[[:space:]]*${MOBILE_DIR}/||" \
    | sort -u
)"

if [[ -z "${MATRIX_PATHS}" ]]; then
  fail "aucune entrée 'path: ${MOBILE_DIR}/…' dans le job flutter-build-web — la matrice a changé de forme"
  exit 1
fi

# --- 1. Toute app dédiée sur disque est couverte -----------------------------
DISK_APPS="$(
  find "${MOBILE_DIR}" -maxdepth 1 -type d -name 'leopardo_*_app' -printf '%f\n' \
    | sort
)"

if [[ -z "${DISK_APPS}" ]]; then
  fail "aucune app '${MOBILE_DIR}/leopardo_*_app' sur disque — chemin ou convention de nommage changé"
  exit 1
fi

while IFS= read -r app; do
  [[ -z "${app}" ]] && continue
  if ! grep -qx "${app}" <<< "${MATRIX_PATHS}"; then
    fail "app '${app}' présente sur disque mais ABSENTE de la matrice du job flutter-build-web — sa cible web n'est jamais compilée (ajouter une entrée 'path: ${MOBILE_DIR}/${app}')"
  fi
done <<< "${DISK_APPS}"

# --- 2. Aucune entrée morte --------------------------------------------------
while IFS= read -r app; do
  [[ -z "${app}" ]] && continue
  if [[ ! -d "${MOBILE_DIR}/${app}" ]]; then
    fail "la matrice du job flutter-build-web référence '${MOBILE_DIR}/${app}', qui n'existe pas — entrée morte, le job échouera au checkout"
  fi
done <<< "${MATRIX_PATHS}"

# --- 3. Artefacts nommés et uniques -----------------------------------------
ARTIFACTS="$(
  grep -oE "artifact:[[:space:]]*[a-z0-9-]+" <<< "${JOB_BLOCK}" \
    | sed -E "s|artifact:[[:space:]]*||" \
    | sort
)"

ARTIFACT_COUNT="$(grep -c . <<< "${ARTIFACTS}" || true)"
PATH_COUNT="$(grep -c . <<< "${MATRIX_PATHS}" || true)"

if [[ "${ARTIFACT_COUNT}" -ne "${PATH_COUNT}" ]]; then
  fail "la matrice déclare ${PATH_COUNT} entrée(s) 'path:' mais ${ARTIFACT_COUNT} 'artifact:' — une entrée sans artefact ne publie rien"
fi

DUPLICATES="$(uniq -d <<< "${ARTIFACTS}" || true)"
if [[ -n "${DUPLICATES}" ]]; then
  fail "nom(s) d'artefact dupliqué(s) : $(tr '\n' ' ' <<< "${DUPLICATES}")— le second upload échouerait après le build"
fi

# --- 4. `multi_vertical` déclaré sur chaque entrée ---------------------------
MULTI_COUNT="$(grep -cE "multi_vertical:[[:space:]]*'(true|false)'" <<< "${JOB_BLOCK}" || true)"
if [[ "${MULTI_COUNT}" -ne "${PATH_COUNT}" ]]; then
  fail "la matrice déclare ${PATH_COUNT} entrée(s) mais ${MULTI_COUNT} 'multi_vertical:' valide(s) — la vérification d'artefact branche sur cette valeur"
fi

# --- Verdict -----------------------------------------------------------------
if [[ "${errors}" -gt 0 ]]; then
  echo ""
  echo "Couverture du build web mobile : ${errors} écart(s)."
  exit 1
fi

DEDICATED_COUNT="$(grep -c . <<< "${DISK_APPS}" || true)"
echo "Couverture du build web mobile : OK (${PATH_COUNT} cibles dans la matrice, ${DEDICATED_COUNT} apps dédiées sur disque toutes couvertes, artefacts uniques)."
