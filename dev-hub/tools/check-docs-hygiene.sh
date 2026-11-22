#!/usr/bin/env bash
#
# check-docs-hygiene.sh — garde de propreté documentaire (issue #9772)
#
# Empêche la récidive des défauts constatés le 2026-11-22 (issues #9770/#9771) :
#
#   1. Registre ADR (docs/architecture/adr/README.md) corrompu : deux ADR
#      partageaient l'identifiant `0028`, la ligne `0010` était tronquée (cellules
#      orphelines sur la ligne suivante) et 5 ADR présentes sur le disque
#      n'étaient pas enregistrées. Le registre est l'index officiel : il ne peut
#      pas mentir sur ce qui existe.
#   2. Dossiers de `docs/` en collision de casse (`docs/decisions` vs
#      `docs/DECISIONS`) — interdits par la règle de nommage de docs/README.md §1.
#   3. Fichiers en vrac à la racine de `docs/` : seuls `README.md` et
#      `QUICKSTART.md` sont autorisés (docs/README.md §1). Les fichiers
#      historiques restants sont une dette déclarée dans
#      `dev-hub/tools/docs-root-allowlist.txt` — ratchet : un fichier NOUVEAU
#      échoue, un fichier déplacé doit sortir de la liste.
#   4. Hygiène de la racine du dépôt : délègue à `check-root-hygiene.sh`, qui
#      existait mais n'était câblée dans AUCUN workflow — d'où 3 fichiers
#      parasites sur `main` (issue #9771).
#
# Usage   : bash dev-hub/tools/check-docs-hygiene.sh [--root DIR]
#           `--root` sert à l'auto-test (fixture isolée, section 4 ignorée).
# Sortie  : 0 = propre, 1 = au moins une violation (::error:: sur stdout).
#
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ROOT="$REPO_ROOT"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --root) ROOT="$2"; shift 2 ;;
    *) shift ;;
  esac
done

violations=0
err() { echo "::error::$*"; violations=$((violations + 1)); }

ADR_DIR="$ROOT/docs/architecture/adr"
ALLOWLIST="$REPO_ROOT/dev-hub/tools/docs-root-allowlist.txt"

# --- 1. Registre ADR --------------------------------------------------------
echo "== Registre ADR =="
if [[ ! -d "$ADR_DIR" ]]; then
  err "dossier ADR introuvable : docs/architecture/adr"
else
  reg="$ADR_DIR/README.md"
  if [[ ! -f "$reg" ]]; then
    err "registre ADR introuvable : docs/architecture/adr/README.md"
  else
    mapfile -t adr_files < <(find "$ADR_DIR" -maxdepth 1 -type f -name '[0-9][0-9][0-9][0-9]-*.md' -printf '%f\n' | sort)
    mapfile -t adr_ids < <(printf '%s\n' "${adr_files[@]:-}" | cut -c1-4 | sed '/^$/d')

    dup_ids="$(printf '%s\n' "${adr_ids[@]:-}" | sed '/^$/d' | sort | uniq -d)"
    while read -r d; do
      [[ -n "$d" ]] && err "identifiant ADR en doublon : $d"
    done <<< "$dup_ids"

    mapfile -t reg_lines < <(grep -E '^\| *\[[0-9]{4}\]\(' "$reg" || true)
    mapfile -t reg_ids < <(printf '%s\n' "${reg_lines[@]:-}" | grep -oE '\[[0-9]{4}\]' | tr -d '[]' | sed '/^$/d')
    mapfile -t reg_targets < <(printf '%s\n' "${reg_lines[@]:-}" | grep -oE '\([0-9]{4}-[^)]+\.md\)' | tr -d '()' | sed '/^$/d')

    dup_reg="$(printf '%s\n' "${reg_ids[@]:-}" | sed '/^$/d' | sort | uniq -d)"
    while read -r d; do
      [[ -n "$d" ]] && err "entree ADR en doublon dans le registre : $d"
    done <<< "$dup_reg"

    for f in "${adr_files[@]:-}"; do
      [[ -z "$f" ]] && continue
      id="${f:0:4}"
      if ! printf '%s\n' "${reg_ids[@]:-}" | grep -qx "$id"; then
        err "ADR absente du registre : docs/architecture/adr/$f"
      fi
    done

    for t in "${reg_targets[@]:-}"; do
      [[ -z "$t" ]] && continue
      if [[ ! -f "$ADR_DIR/$t" ]]; then
        err "le registre pointe une ADR inexistante : $t"
      fi
    done

    while IFS=: read -r ln _; do
      [[ -z "$ln" ]] && continue
      content="$(sed -n "${ln}p" "$reg")"
      if [[ "$(printf '%s' "$content" | tr -cd '|' | wc -c)" -ne 4 ]]; then
        err "ligne de tableau malformee dans le registre ADR (ligne $ln)"
      fi
    done <<< "$(grep -nE '^\|' "$reg" || true)"
  fi
fi

# --- 2. Collisions de casse entre dossiers de docs/ -------------------------
echo "== Dossiers docs/ (collision de casse) =="
if [[ -d "$ROOT/docs" ]]; then
  while read -r k; do
    [[ -z "$k" ]] && continue
    mapfile -t hits < <(find "$ROOT/docs" -maxdepth 1 -mindepth 1 -type d -printf '%f\n' | awk -v key="$k" 'tolower($0) == key')
    if [[ "${#hits[@]}" -gt 1 ]]; then
      err "collision de casse dans docs/ : $(printf '%s ' "${hits[@]}")"
    fi
  done < <(find "$ROOT/docs" -maxdepth 1 -mindepth 1 -type d -printf '%f\n' | tr 'A-Z' 'a-z' | sort | uniq -d)
fi

# --- 3. Fichiers en vrac à la racine de docs/ (ratchet) ---------------------
echo "== Racine de docs/ =="
if [[ -d "$ROOT/docs" ]]; then
  if [[ ! -f "$ALLOWLIST" ]]; then
    err "allowlist introuvable : dev-hub/tools/docs-root-allowlist.txt"
  else
    while read -r f; do
      [[ -z "$f" ]] && continue
      if ! grep -qxF "$f" "$ALLOWLIST"; then
        err "fichier non declare a la racine de docs/ : docs/$f (regle docs/README.md §1 : README.md et QUICKSTART.md)"
      fi
    done < <(find "$ROOT/docs" -maxdepth 1 -type f -printf '%f\n' | sort)
  fi
fi

# --- 4. Hygiène de la racine du dépôt (garde existante, enfin câblée) -------
if [[ "$ROOT" == "$REPO_ROOT" && -f "$REPO_ROOT/dev-hub/tools/check-root-hygiene.sh" ]]; then
  echo "== Racine du depot =="
  if ! out="$(bash "$REPO_ROOT/dev-hub/tools/check-root-hygiene.sh" 2>&1)"; then
    while read -r l; do
      [[ -n "$l" ]] && err "racine du depot : $l"
    done <<< "$out"
  fi
fi

echo
if [[ "$violations" -gt 0 ]]; then
  echo "$violations violation(s) de proprete documentaire."
  exit 1
fi
echo "Proprete documentaire OK."
exit 0
