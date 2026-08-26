#!/usr/bin/env bash
# Issue #5585 — garde CI : interdiction des assertions de statut multi-valeurs.
#
# `assertContains($response->status(), [200, 403, 404])` passe « presque quoi
# qu'il arrive » : il masque les vraies régressions (ex. isolation tenant) et
# gonfle artificiellement la couverture (gate 65 %). Chaque endpoint doit
# asserter un contrat DÉTERMINISTE (un seul statut attendu).
#
# La garde est DIFF-BASED : elle ne fait échouer que les LIGNES NOUVELLES ou
# MODIFIÉES introduisant le pattern (les occurrences pré-existantes sont de la
# dette suivie séparément — ne pas les régulariser ici, c'est un scope dédié).
#
# Usage : dev-hub/tools/check-tests-deterministic.sh
#   Env : GITHUB_BASE_SHA / GITHUB_HEAD_SHA (défaut : merge-base origin/main,
#         puis HEAD~1 pour un simple commit local).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

BASE_SHA="${GITHUB_BASE_SHA:-}"
HEAD_SHA="${GITHUB_HEAD_SHA:-}"

if [[ -z "${HEAD_SHA}" || "${HEAD_SHA}" == "0000000000000000000000000000000000000000" ]]; then
  HEAD_SHA="$(git rev-parse HEAD)"
fi

if [[ -z "${BASE_SHA}" || "${BASE_SHA}" == "0000000000000000000000000000000000000000" ]]; then
  if git merge-base HEAD origin/main >/dev/null 2>&1; then
    BASE_SHA="$(git merge-base HEAD origin/main)"
  elif git rev-parse HEAD~1 >/dev/null 2>&1; then
    BASE_SHA="$(git rev-parse HEAD~1)"
  else
    BASE_SHA="$(git rev-parse HEAD)"
  fi
fi

TMP_DIFF="$(mktemp)"
trap 'rm -f "${TMP_DIFF}"' EXIT

git diff --unified=0 "${BASE_SHA}" "${HEAD_SHA}" -- 'api/tests/**/*.php' > "${TMP_DIFF}"

python3 - "${TMP_DIFF}" <<'PY'
import re
import sys

pattern = re.compile(
    # assertContains(…->status()/getStatusCode(), [au moins 2 valeurs])
    # (le `,` dans le tableau ⇒ 2+ éléments ⇒ statut « au choix » interdit).
    r"assertContains(?:Equals)?\s*\([^()\n]*?(?:->\s*(?:status|getStatusCode)\(\))\s*,\s*\[[^\]\n]*,[^\]]*\]"
)
diff_path = sys.argv[1]
cur_file = None
added = []          # (line_number, text) pour le hunk courant
findings = []

def flush():
    global added
    if not added:
        return
    # Le pattern peut s'étaler sur plusieurs lignes ajoutées (array sur la
    # ligne suivante) : on joint les lignes + du hunk et on retire les sauts.
    joined = " ".join(t for _, t in added)
    m = pattern.search(joined)
    if m:
        findings.append((cur_file, added[0][0], m.group(0)[:140]))
    added = []

with open(diff_path, encoding="utf-8", errors="replace") as fh:
    for line in fh:
        line = line.rstrip("\n")
        if line.startswith("+++ "):
            cur_file = line[4:]
            if cur_file.startswith("b/"):
                cur_file = cur_file[2:]
            flush()
            continue
        if line.startswith("--- "):
            continue
        m = re.match(r"^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@", line)
        if m:
            flush()
            new_line = int(m.group(1))
            continue
        if line.startswith("+"):
            if line.startswith("+++"):
                continue
            added.append((new_line, line[1:]))
            new_line += 1
        elif line.startswith(" "):
            new_line += 1
        # lignes `-` (suppressions) : n'avancent pas le numéro côté nouveau fichier

flush()

for f, ln, snippet in findings:
    print(f"::error file={f},line={ln}::{f}:{ln} assertion de statut multi-valeurs (issue #5585) : {snippet}")

sys.exit(1 if findings else 0)
PY
rc=$?
if [[ "${rc}" -ne 0 ]]; then
  echo "::error::Assertions de statut multi-valeurs interdites (issue #5585) — asserter UN seul statut déterministe par test."
  exit 1
fi

echo "✓ Tests : aucune nouvelle assertion de statut multi-valeurs."
exit 0
