#!/usr/bin/env bash
#
# #9856 — Mesure de la dette PHPStan RÉELLE, sans baseline.
#
# ## Pourquoi ce script existe
#
# `phpstan.neon` inclut `phpstan-baseline.neon` (644 erreurs gelées) et
# `phpstan.ci.neon` en ajoute deux autres (1 145 + 6). Le dépôt peut donc
# annoncer « niveau max, 0 erreur » alors qu'une part substantielle du code
# n'est pas réellement vérifiée : la baseline est une liste d'exceptions, pas
# une preuve de qualité.
#
# Ce script mesure ce que la baseline masque. Il lance PHPStan avec une
# configuration qui N'INCLUT AUCUNE baseline, et compte les erreurs réelles.
#
# ## Pourquoi par lots
#
# Un run unique sur `app/` dépasse la mémoire d'un runner modeste (observé :
# SIGKILL du worker à 2 Go et à 3 Go). On analyse donc chaque répertoire de
# premier niveau séparément, en mono-processus, et on agrège. Le total est
# identique à un run unique, mais chaque lot tient en mémoire.
#
# ## Usage
#
#   dev-tools/phpstan-debt-report.sh              # rapport lisible
#   dev-tools/phpstan-debt-report.sh --json       # JSON agrégé sur stdout
#   dev-tools/phpstan-debt-report.sh --top 20     # top 20 messages
#
# Sortie : total d'erreurs, fichiers touchés, et répartition par message.
# Code de sortie : 0 toujours (c'est un rapport, pas un garde-fou de CI).

set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

JSON_MODE=0
TOP=10
while [ $# -gt 0 ]; do
    case "$1" in
        --json) JSON_MODE=1; shift ;;
        --top) TOP="${2:-10}"; shift 2 ;;
        *) echo "Option inconnue : $1" >&2; exit 2 ;;
    esac
done

PHPSTAN="vendor/bin/phpstan"
[ -x "$PHPSTAN" ] || { echo "PHPStan introuvable : $PHPSTAN" >&2; exit 1; }

TMPDIR_REPORT="$(mktemp -d)"
trap 'rm -rf "$TMPDIR_REPORT"' EXIT

# Configuration SANS baseline : on repart de phpstan.neon mais on retire
# l'include de la baseline. On garde le bootstrap et l'extension Larastan,
# sinon l'analyse ne résout pas les types du framework.
cat > "$TMPDIR_REPORT/no-baseline.neon" <<EOF
includes:
    - $(pwd)/phpstan-flags.neon
    - $(pwd)/vendor/larastan/larastan/extension.neon

parameters:
    bootstrapFiles:
        - $(pwd)/phpstan-bootstrap.php
    level: max
    tmpDir: $(pwd)/storage/phpstan-debt
    reportUnmatchedIgnoredErrors: false
    parallel:
        maximumNumberOfProcesses: 1
        processTimeout: 1800.0
EOF

# Lots : chaque répertoire de premier niveau sous app/, plus routes/ et tests/.
LOTS=()
for d in app/*/; do
    [ -d "$d" ] && LOTS+=("${d%/}")
done
[ -d routes ] && LOTS+=("routes")
[ -d tests ] && LOTS+=("tests")

TOTAL=0
FILES=0
: > "$TMPDIR_REPORT/all-messages.txt"

for lot in "${LOTS[@]}"; do
    out="$TMPDIR_REPORT/$(echo "$lot" | tr '/' '_').json"
    php -d memory_limit=3G "$PHPSTAN" analyse \
        --configuration="$TMPDIR_REPORT/no-baseline.neon" \
        --no-progress --error-format=json --memory-limit=3G \
        "$lot" > "$out" 2>"$TMPDIR_REPORT/err.log"

    if ! python3 -c "import json,sys; json.load(open('$out'))" 2>/dev/null; then
        echo "  [lot ignoré : analyse incomplète] $lot" >&2
        continue
    fi

    read -r n f < <(python3 - "$out" <<'PY'
import json, sys
d = json.load(open(sys.argv[1]))
print(d["totals"]["file_errors"], len(d["files"]))
PY
)
    TOTAL=$((TOTAL + n))
    FILES=$((FILES + f))

    python3 - "$out" >> "$TMPDIR_REPORT/all-messages.txt" <<'PY'
import json, sys
d = json.load(open(sys.argv[1]))
for path, info in d["files"].items():
    for m in info["messages"]:
        print(m["message"].replace("\n", " ")[:120])
PY

    [ "$JSON_MODE" -eq 0 ] && printf '  %-28s %6d erreurs / %4d fichiers\n' "$lot" "$n" "$f"
done

if [ "$JSON_MODE" -eq 1 ]; then
    python3 - "$TOTAL" "$FILES" "$TMPDIR_REPORT/all-messages.txt" <<'PY'
import json, sys
from collections import Counter
total, files, path = int(sys.argv[1]), int(sys.argv[2]), sys.argv[3]
c = Counter(l.strip() for l in open(path) if l.strip())
print(json.dumps({
    "total_errors": total,
    "files_with_errors": files,
    "top_messages": [{"count": n, "message": m} for m, n in c.most_common(20)],
}, indent=2, ensure_ascii=False))
PY
else
    echo
    echo "TOTAL (sans baseline) : $TOTAL erreurs dans $FILES fichiers"
    echo
    echo "Top $TOP des messages :"
    sort "$TMPDIR_REPORT/all-messages.txt" | uniq -c | sort -rn | head -"$TOP" \
        | sed 's/^ *\([0-9]*\) /\1 × /'
fi
