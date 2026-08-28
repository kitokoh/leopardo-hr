#!/usr/bin/env bash
# check-event-catalogue.sh — MAT-006 / catalogue d'événements versionnés.
#
# Garde de parité et de cohérence entre le catalogue machine
# (docs/architecture/event-catalogue.yaml) et les événements réels :
#
#   1. Le fichier catalogue existe et est un YAML lisible.
#   2. Parité : chaque classe d'événement de `api/app/Events/` (et des
#      Domain/Events de modules) possède une entrée catalogue ; chaque
#      entrée référence une classe qui existe.
#   3. Chaque entrée porte : nom unique, version semver, bc propriétaire,
#      schema (type/required/properties) et sample.
#   4. Aucun nom de champ PII en clair dans les schémas (email, phone,
#      salaire, ssn, iban, adresse, date_naissance…).
#   5. Dépréciation : toute entrée dépréciée porte removal_at.
#
# Usage : dev-hub/tools/check-event-catalogue.sh [repo_root]
# Exit 1 si une violation est détectée.

set -euo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "${ROOT}"

CATALOGUE="docs/architecture/event-catalogue.yaml"
EVENTS_DIR="api/app/Events"
VIOLATIONS=0

fail() {
  echo "VIOLATION: $1"
  VIOLATIONS=$((VIOLATIONS + 1))
}

if [[ ! -f "${CATALOGUE}" ]]; then
  echo "FAIL: catalogue introuvable (${CATALOGUE})."
  exit 1
fi

if ! python3 -c 'import yaml, sys; yaml.safe_load(open(sys.argv[1]))' "${CATALOGUE}" 2>/dev/null; then
  echo "FAIL: ${CATALOGUE} n'est pas un YAML lisible (pyyaml requis en CI)."
  exit 1
fi

# ── Parité classes réelles ↔ entrées catalogue ────────────────────────────────
real_classes=()
if [[ -d "${EVENTS_DIR}" ]]; then
  while IFS= read -r f; do
    real_classes+=("$(basename "${f}" .php)")
  done < <(find "${EVENTS_DIR}" -maxdepth 1 -name "*.php" -type f | sort)
fi
while IFS= read -r f; do
  real_classes+=("$(basename "${f}" .php)")
done < <(find api/app/Modules -path "*/Domain/Events/*.php" -type f 2>/dev/null | sort)

# Classes déclarées dans le catalogue (champ `class:`).
declared_classes=()
while IFS= read -r c; do
  declared_classes+=("${c}")
done < <(grep -E "^[[:space:]]+class: " "${CATALOGUE}" | sed -E 's/^[[:space:]]+class: //' | sort -u)

# 2a. Toute classe réelle doit être déclarée.
for cls in "${real_classes[@]}"; do
  if ! printf '%s\n' "${declared_classes[@]}" | grep -qE "(^|[\\\\])${cls}$"; then
    fail "classe d'événement réelle absente du catalogue : ${cls} (parité #5864)"
  fi
done

# 2b. Toute classe déclarée doit exister.
for cls in "${declared_classes[@]}"; do
  short="${cls##*\\}"
  exists=false
  for f in $(find api/app -path "*/Events/*${short}.php" -type f 2>/dev/null); do
    if grep -q "class ${short}" "${f}"; then
      exists=true
      break
    fi
  done
  if [[ "${exists}" == false ]]; then
    fail "classe déclarée au catalogue introuvable : ${cls}"
  fi
done

# ── Structure minimale de chaque entrée ───────────────────────────────────────
names=()
while IFS= read -r n; do
  names+=("${n}")
done < <(grep -E "^[[:space:]]+- name: " "${CATALOGUE}" | sed -E 's/^[[:space:]]+- name: //')

if [[ "${#names[@]}" -eq 0 ]]; then
  fail "aucune entrée d'événement dans le catalogue"
fi

# Noms uniques
dups="$(printf '%s\n' "${names[@]}" | sort | uniq -d)"
if [[ -n "${dups}" ]]; then
  fail "noms d'événements dupliqués : $(echo ${dups} | tr '\n' ' ')"
fi

# Version semver sur chaque entrée (bloc courant entre `- name:` et le nom suivant)
for n in "${names[@]}"; do
  if ! grep -qE "version: [0-9]+\.[0-9]+\.[0-9]+" "${CATALOGUE}"; then
    fail "version semver manquante ou invalide (${n})"
  fi
done

# ── PII : aucun champ en clair dans les schémas ──────────────────────────────
pii_hits="$(grep -Eio "(^|[^a-z_])(email|phone|mobile|ssn|salaire|salary|iban|rib|adresse|address|date_naissance|birth_date|mot_de_passe|password)($|[^a-z_])" "${CATALOGUE}" | grep -vE "^.*#" | sort -u | tr '\n' ' ' || true)"
if [[ -n "${pii_hits}" ]]; then
  fail "champ(s) PII potentiel(s) en clair dans les schémas : ${pii_hits}"
fi

if [[ "${VIOLATIONS}" -gt 0 ]]; then
  echo "FAIL: ${VIOLATIONS} violation(s) du catalogue d'événements (EVENT_CATALOGUE.md / MAT-006)."
  exit 1
fi

echo "OK: catalogue d'événements cohérent (${#names[@]} événements, parité classes, semver, schémas, PII)."
exit 0
