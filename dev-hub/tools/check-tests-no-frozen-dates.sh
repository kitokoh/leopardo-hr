#!/usr/bin/env bash
# Issue #8396 — garde CI : interdiction des dates calendaires FIGÉES DANS LE PASSÉ
# dans les tests backend.
#
# Contexte (constat de l'issue) : le check « Backend Coverage » est rouge sur
# `main` avec ~364 échecs, dont une part de NON-DÉTERMINISME TEMPOREL. Exemple
# cité : `Accounting › refresh overdue marks past due documents` utilisait des
# dates figées (`2026-09-30`) devenues passées au fil du temps — le test passait
# le jour de son écriture et échouait des mois plus tard, sans qu'aucun code de
# production n'ait changé. C'est une « bombe à retardement » : le coût est payé
# par un tiers, longtemps après, sur un fichier qu'il n'a pas touché.
#
# L'issue demande explicitement une RÈGLE DURABLE (piste 2) : « interdire les
# dates calendaires figées dans les tests (toujours relatif à now() /
# Carbon::setTestNow) — le pattern va se reproduire ».
#
# ── Pourquoi la garde ne cible QUE le passé ──────────────────────────────────
# Un premier jet interdisant TOUTE date littérale aurait signalé ~2 380
# occurrences : l'immense majorité est légitime (bornes de période explicites,
# dates de naissance, échéances contractuelles, fixtures de calendrier). Une
# garde qui crie sur 2 380 lignes est désactivée dans la semaine — elle ne
# protège rien.
#
# Le danger réel est précis et détectable : une date ABSOLUE déjà PASSÉE, qui
# n'était donc pas passée quand le test a été écrit. Une date future n'est pas
# signalée (elle deviendra passée un jour, mais la garde la rattrapera alors —
# et c'est exactement le moment où le test commence à mentir).
#
# ── Échappatoires (volontaires) ──────────────────────────────────────────────
#   - `Carbon::setTestNow(...)` / `$this->travelTo(...)` / `freezeTime()` dans
#     le fichier : l'horloge est maîtrisée, les dates littérales sont alors
#     relatives à une origine fixée → le fichier est exempté ;
#   - marqueur `@frozen-date-ok` sur la ligne ou la ligne précédente : pour les
#     cas où la date passée est le SUJET du test (import d'historique, migration
#     de données anciennes, calcul d'arriérés) ;
#   - la garde est DIFF-BASED : seules les lignes NOUVELLES ou MODIFIÉES sont
#     jugées. Les occurrences pré-existantes relèvent de la campagne de
#     résorption (#8396), pas de cette garde — on ne bloque pas une PR sur une
#     dette qu'elle n'a pas introduite.
#
# Usage : dev-hub/tools/check-tests-no-frozen-dates.sh
#   Env : GITHUB_BASE_SHA / GITHUB_HEAD_SHA (défaut : merge-base origin/main,
#         puis HEAD~1 pour un simple commit local).
set -euo pipefail

# Racine analysée : surchargeable via GUARD_ROOT pour les tests du garde
# (dépôt jetable), sinon le dépôt qui contient ce script.
ROOT="${GUARD_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
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
import datetime
import re
import sys

# Date ISO complète : 2026-09-30. On exige le guillemet ouvrant pour ne pas
# matcher un fragment d'identifiant ou de version.
DATE_RE = re.compile(r"""['"](?P<y>\d{4})-(?P<m>\d{2})-(?P<d>\d{2})['"]""")

# Marqueur d'exemption, sur la ligne ou la ligne précédente.
EXEMPT_MARKER = "@frozen-date-ok"

# Signaux d'horloge maîtrisée : si le fichier en contient un, ses dates
# littérales sont relatives à une origine fixée, donc stables.
CLOCK_CONTROL = re.compile(
    r"setTestNow|travelTo|travelBack|freezeTime|freezeSecond|Carbon::setTestNow"
)

diff_path = sys.argv[1]
today = datetime.date.today()

cur_file = None
file_has_clock_control = False
added = []          # (line_number, text) du hunk courant
findings = []


def flush():
    """Juge les lignes ajoutées du hunk courant."""
    global added
    if not added or file_has_clock_control:
        added = []
        return

    for idx, (line_no, text) in enumerate(added):
        if EXEMPT_MARKER in text:
            continue
        # Marqueur sur la ligne précédente (commentaire au-dessus).
        if idx > 0 and EXEMPT_MARKER in added[idx - 1][1]:
            continue

        for m in DATE_RE.finditer(text):
            try:
                value = datetime.date(int(m.group("y")), int(m.group("m")), int(m.group("d")))
            except ValueError:
                continue  # date invalide : ce n'est pas une date, on ignore

            if value < today:
                findings.append((cur_file, line_no, m.group(0), value.isoformat()))
                break

    added = []


with open(diff_path, encoding="utf-8", errors="replace") as fh:
    for line in fh:
        line = line.rstrip("\n")

        if line.startswith("+++ "):
            flush()
            cur_file = line[4:]
            if cur_file.startswith("b/"):
                cur_file = cur_file[2:]
            file_has_clock_control = False
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
            body = line[1:]
            if CLOCK_CONTROL.search(body):
                file_has_clock_control = True
            added.append((new_line, body))
            new_line += 1
        elif line.startswith(" "):
            new_line += 1
        # lignes `-` : n'avancent pas le numéro côté nouveau fichier

flush()

for f, ln, snippet, iso in findings:
    print(
        f"::error file={f},line={ln}::{f}:{ln} date calendaire figée dans le passé "
        f"({snippet}, soit {iso} < aujourd'hui) — utiliser Carbon::setTestNow()/travelTo() "
        f"ou une date relative à now() (issue #8396). Marqueur d'exemption : {EXEMPT_MARKER}"
    )

sys.exit(1 if findings else 0)
PY
rc=$?
if [[ "${rc}" -ne 0 ]]; then
  echo "::error::Dates calendaires figées dans le passé interdites (issue #8396) — un test doit rester vrai demain."
  exit 1
fi

echo "✓ Tests : aucune nouvelle date calendaire figée dans le passé."
exit 0
