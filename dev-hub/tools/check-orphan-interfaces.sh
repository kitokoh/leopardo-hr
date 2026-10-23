#!/usr/bin/env bash
# check-orphan-interfaces.sh — Détecte les interfaces de contrat DDD sans implémentation.
#
# Usage : dev-hub/tools/check-orphan-interfaces.sh [--strict] [--allowlist <fichier>] [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#   --strict     : ignore l'allowlist (mesure la dette réelle, sortie 1 si dette)
#   --allowlist  : chemin alternatif de la liste d'exclusion
#
# Règle (issue #1492) : chaque interface de `Modules/*/Domain/Contracts/` doit
# avoir au moins une classe `implements` (dans api/app, y compris hors Modules :
# ex. app/Services/Payroll/CountryRules implémente Modules/Payroll
# CountryRulesInterface).
#
# Les orphelins CONNUS vivent dans dev-hub/tools/orphan-interfaces-allowlist.txt
# (une interface par ligne, '#' = commentaire), comme les autres allowlists du
# dépôt. Toute interface orpheline ABSENTE de la liste est bloquante ; une
# entrée dont l'interface n'est plus orpheline est également bloquante
# (anti-régression : purger la ligne fait partie du correctif, cf. #3596).
# Justification et catégorisation : docs/adr/GOV-001-interfaces-orphelines.md.
#
# Prérequis : ripgrep (`rg`). Sans lui, la détection retournerait TOUT en
# orphelin (faux positifs massifs) : le script échoue explicitement au lieu de
# mentir.
#
# Exit codes : 0 = OK (aucun nouvel orphelin), 1 = nouvel orphelin / entrée
# d'allowlist périmée / prérequis manquant.
set -euo pipefail

STRICT=0
ALLOWLIST=""
POSITIONAL=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --strict) STRICT=1; shift ;;
    --allowlist) ALLOWLIST="$2"; shift 2 ;;
    -h|--help) grep '^#' "$0" | head -30; exit 0 ;;
    *) POSITIONAL+=("$1"); shift ;;
  esac
done
set -- ${POSITIONAL[@]+"${POSITIONAL[@]}"}

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"
ALLOWLIST="${ALLOWLIST:-dev-hub/tools/orphan-interfaces-allowlist.txt}"

if ! command -v rg >/dev/null 2>&1; then
  echo "::error::ripgrep (rg) introuvable — la détection produirait des faux positifs. Installez ripgrep (voir .github/workflows/route-owner-and-orphan-interfaces-guard.yml)."
  exit 1
fi

if [[ ! -d "${API_DIR}/app/Modules" ]]; then
  echo "::error::${API_DIR}/app/Modules introuvable — API_DIR invalide ?"
  exit 1
fi

errors=0
legacy=0

# Environnement : override explicite (tests, mesure ponctuelle) prioritaire.
declare -A ALLOWED=()
if [[ "${STRICT}" -eq 0 ]]; then
  if [[ -n "${ALLOW_ORPHAN_INTERFACES:-}" ]]; then
    for name in ${ALLOW_ORPHAN_INTERFACES}; do ALLOWED["${name}"]=1; done
  elif [[ -f "${ALLOWLIST}" ]]; then
    while IFS= read -r line; do
      [[ -z "${line}" || "${line}" =~ ^[[:space:]]*# ]] && continue
      ALLOWED["${line//[[:space:]]/}"]=1
    done < "${ALLOWLIST}"
  else
    echo "::error::allowlist introuvable : ${ALLOWLIST} — la créer (une interface par ligne) ou passer --strict."
    exit 1
  fi
fi

orphans=()
for iface in "${API_DIR}"/app/Modules/*/Domain/Contracts/*Interface.php; do
  [[ -e "${iface}" ]] || continue
  name="$(basename "${iface}" .php)"
  # Fichiers contenant à la fois "implements" et le nom de l'interface, hors déclaration elle-même.
  if rg -l "implements" "${API_DIR}/app" --glob '*.php' 2>/dev/null \
      | xargs -r rg -l "\b${name}\b" 2>/dev/null \
      | grep -v "Domain/Contracts" | grep -q .; then
    : # implémentée
  else
    orphans+=("${name} (${iface#${API_DIR}/})")
  fi
done

new_orphans=()
declare -A STILL_ORPHAN=()
for o in ${orphans[@]+"${orphans[@]}"}; do
  short="${o%% *}"
  STILL_ORPHAN["${short}"]=1
  if [[ -n "${ALLOWED[${short}]:-}" ]]; then
    echo "::warning::Orphelin connu (allowlist) : ${o}"
    legacy=$((legacy + 1))
  else
    new_orphans+=("${o}")
  fi
done

if [[ ${#new_orphans[@]} -gt 0 ]]; then
  echo "::error::Interfaces de contrat SANS implémentation (NOUVEAUX orphelins) :"
  printf '::error::  - %s\n' "${new_orphans[@]}"
  echo "Implémentez l'interface, ou assumez-la explicitement dans ${ALLOWLIST}"
  echo "(justification écrite, cf. docs/adr/GOV-001-interfaces-orphelines.md — issue #1492)."
  errors=$((errors + ${#new_orphans[@]}))
fi

# Anti-staleness (#3596) : une entrée dont l'interface n'est PLUS orpheline
# (implémentée ou fichier supprimé) doit être purgée, sinon la liste ment.
if [[ "${STRICT}" -eq 0 && ${#ALLOWED[@]} -gt 0 ]]; then
  allow_keys=("${!ALLOWED[@]}")
  for entry in "${allow_keys[@]}"; do
    if [[ -z "${STILL_ORPHAN[${entry}]:-}" ]]; then
      echo "::error::Entrée allowlist orpheline : ${entry} — l'interface est désormais implémentée (ou le fichier a été supprimé/renommé), retirer la ligne de ${ALLOWLIST} dans la même PR (anti-régression #3596)."
      errors=$((errors + 1))
    fi
  done
fi

if [[ "${errors}" -gt 0 ]]; then
  echo "::error::Interfaces orphelines : ${errors} erreur(s) bloquante(s) (issue #1492)."
  exit 1
fi

echo "✓ ${legacy} orphelin(s) connu(s) (allowlist, ADR GOV-001) — 0 nouveau, OK"
exit 0
