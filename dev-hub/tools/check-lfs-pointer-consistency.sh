#!/usr/bin/env bash
# Garde CI — cohérence des pointeurs LFS sous assets/ (issue #6605).
# Échoue si un oid sha256 est dupliqué sous assets/ (anti-réintroduction
# des 85 doublons purgés) ou si un pointeur LFS n'est pas un pointeur
# valide (fichier corrompu/partiel).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ASSETS="${ROOT}/assets"
mapfile -t POINTERS < <(find "${ASSETS}" -type f \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' -o -name '*.webp' -o -name '*.webm' -o -name '*.mp4' -o -name '*.gif' \) -print)

declare -A SEEN
DUPS=0
UNIQ=0
for f in "${POINTERS[@]}"; do
  if grep -q '^version https://git-lfs' "${f}"; then
    oid="$(sed -n 's/^oid sha256:\([0-9a-f]\{64\}\)$/\1/p' "${f}" | head -n1)"
    if [[ -z "${oid}" ]]; then
      echo "::error::Pointeur LFS invalide (oid manquant) : ${f}"
      exit 1
    fi
    if [[ -v SEEN["${oid}"] ]]; then
      echo "::error::oid LFS dupliqué sous assets/ : ${oid} (${SEEN["${oid}"]} et ${f}) — dédupliquer (#6605)."
      DUPS=$((DUPS+1))
    else
      SEEN["${oid}"]="${f}"
      UNIQ=$((UNIQ+1))
    fi
  fi
done

if [[ "${DUPS}" -gt 0 ]]; then
  echo "::error::check-lfs-pointer-consistency : ${DUPS} oid LFS dupliqué(s) sous assets/."
  exit 1
fi
echo "check-lfs-pointer-consistency : OK — ${UNIQ} oid LFS uniques sous assets/."
