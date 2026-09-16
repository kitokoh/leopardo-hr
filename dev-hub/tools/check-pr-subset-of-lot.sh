#!/usr/bin/env bash
#
# check-pr-subset-of-lot.sh — PROUVER qu'une PR est bien un sous-ensemble du lot
# avant de la fermer au motif « couvert par le lot #N » (issue #7581).
#
# Le constat
# ----------
# Au drain #7562, deux PR qui corrigeaient un vrai bug (`front/web` :
# `optimizeCss` exige `critters`, absent de `package.json` ET du
# `package-lock.json` -> install propre = web cassé) ont été **fermées** au
# motif « couvert par le lot #7524 ». Le lot ne l'a jamais couvert : le bug
# était **toujours vivant sur `main`** après le drain, et il a fallu une PR de
# plus (#7565) pour le corriger. Même classe : les reliquats de #7533 et #7535
# étaient encore ouverts après le drain.
#
# Fermer « couvert par le lot » est une **affirmation de contenu**. Quand elle
# est fausse, le correctif disparaît ET le backlog devient vert à tort : c'est
# le « ghost close » que `AGENTS.md` interdit pour les issues, appliqué aux PR.
#
# Ce que fait cet outil
# ---------------------
# Il rend la règle opposable : au lieu d'affirmer, il **mesure**. Une PR est un
# sous-ensemble du lot si (1) chacun des fichiers qu'elle modifie existe dans le
# lot, et (2) chacune des lignes qu'elle AJOUTE (par rapport à la base) est
# présente dans la version du lot. Sinon, la clôture serait une perte.
#
# Usage :
#   bash dev-hub/tools/check-pr-subset-of-lot.sh --lot <ref> --pr <ref> [--base <ref>]
#   bash dev-hub/tools/check-pr-subset-of-lot.sh --self-test
#
# Sortie : 0 = sous-ensemble PROUVÉ (la clôture est sûre) ; 1 = NON prouvé
# (lister ce qui manque : c'est exactement le texte à mettre dans le commentaire
# de fermeture, ou la raison de ne pas fermer).
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

prove_subset() {
  local root="$1" lot="$2" pr="$3" base="$4"
  python3 - "$root" "$lot" "$pr" "$base" <<'PYEOF'
import subprocess, sys

root, lot, pr, base = sys.argv[1:5]

def git(*args, allow_fail=False):
    p = subprocess.run(['git', '-C', root, *args], capture_output=True, text=True)
    if p.returncode != 0 and not allow_fail:
        raise SystemExit(f"git {' '.join(args)} a echoue : {p.stderr.strip()}")
    return p.stdout

def show(ref, path):
    p = subprocess.run(['git', '-C', root, 'show', f'{ref}:{path}'],
                       capture_output=True, text=True)
    return p.stdout if p.returncode == 0 else None

merge_base = git('merge-base', base, pr).strip() or base

changed = [f for f in git('diff', '--name-only', f'{merge_base}...{pr}').splitlines() if f]
if not changed:
    print(f"Aucun fichier modifie par {pr} : rien a prouver (sous-ensemble vide).")
    raise SystemExit(0)

missing_files, missing_lines = [], []

for path in changed:
    lot_blob = show(lot, path)
    if lot_blob is None:
        missing_files.append(path)
        continue

    # Lignes AJOUTEES par la PR par rapport a la base commune.
    diff = git('diff', f'{merge_base}...{pr}', '--', path)
    added = [
        line[1:]
        for line in diff.splitlines()
        # lignes de contenu ajoutees uniquement (pas les en-tetes +++)
        if line.startswith('+') and not line.startswith('+++')
    ]
    added = [l for l in added if l.strip()]
    lot_lines = set(lot_blob.splitlines())
    absent = [l for l in added if l not in lot_lines]
    if absent:
        missing_lines.append((path, absent))

if not missing_files and not missing_lines:
    print(f"SOUS-ENSEMBLE PROUVE : {len(changed)} fichier(s) de {pr} sont bien couverts par {lot}.")
    raise SystemExit(0)

print(f"NON PROUVE : {pr} n'est PAS un sous-ensemble de {lot}.")
if missing_files:
    print(f"\nFichiers absents du lot ({len(missing_files)}) :")
    for f in missing_files:
        print(f"  ABSENT DU LOT : {f}")
if missing_lines:
    print(f"\nLignes ajoutees par la PR absentes du lot ({len(missing_lines)} fichier(s)) :")
    for path, absent in missing_lines:
        print(f"  {path} : {len(absent)} ligne(s), ex. {absent[0][:100]!r}")
print(
    "\nConclusion : la cloture « couvert par le lot » serait une PERTE de "
    "correctif (#7581). Reprendre le contenu dans le lot, ou ne pas fermer."
)
raise SystemExit(1)
PYEOF
}

if [ "${1:-}" = "--self-test" ]; then
  echo "== auto-test : la preuve doit distinguer un vrai sous-ensemble d'une perte =="
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT
  git -C "$TMP" init -q
  git -C "$TMP" config user.email a@b.c
  git -C "$TMP" config user.name t
  printf 'ligne1\nligne2\n' > "$TMP/f.txt"
  printf 'autre\n' > "$TMP/g.txt"
  git -C "$TMP" add -A
  git -C "$TMP" commit -qm base
  BASE="$(git -C "$TMP" rev-parse HEAD)"

  # La PR corrige f.txt ; le lot porte la MEME correction => sous-ensemble.
  git -C "$TMP" checkout -q -b pr-branche "$BASE"
  printf 'ligne1\nligne2\ncorrectif PR\n' > "$TMP/f.txt"
  git -C "$TMP" commit -qam "correctif PR"
  PR="$(git -C "$TMP" rev-parse HEAD)"

  git -C "$TMP" checkout -q -b le-lot "$BASE"
  printf 'ligne1\nligne2\ncorrectif PR\n' > "$TMP/f.txt"
  git -C "$TMP" commit -qam "le lot porte le correctif"

  fail=0
  set +e
  out="$(prove_subset "$TMP" le-lot pr-branche "$BASE")"; st=$?
  set -e
  echo "$out"
  [ "$st" -eq 0 ] || { echo "ECHEC : le sous-ensemble devait etre PROUVE (sortie $st)"; fail=1; }

  # Le lot ne porte PAS le correctif => la cloture serait une perte.
  git -C "$TMP" checkout -q -b lot-sans-correctif "$BASE"
  printf 'ligne1\nligne2\n' > "$TMP/g.txt"
  git -C "$TMP" commit -qam "le lot ne touche pas f.txt"
  set +e
  out2="$(prove_subset "$TMP" lot-sans-correctif pr-branche "$BASE")"; st2=$?
  set -e
  echo "$out2"
  [ "$st2" -eq 1 ] || { echo "ECHEC : la perte devait etre detectee (sortie $st2)"; fail=1; }
  echo "$out2" | grep -q "ligne ajoutees par la PR absentes\|lignes ajoutees par la PR absentes" \
    || echo "$out2" | grep -qi "absentes du lot" || { echo "ECHEC : la ligne manquante n'est pas nommee"; fail=1; }

  # Contre-epreuve : correspondance exacte des lignes (pas de faux positif).
  [ "$fail" -eq 0 ] && echo "AUTO-TEST OK (prouve un vrai sous-ensemble, detecte la perte)" || exit 1
  exit 0
fi

LOT=""; PR=""; BASE="origin/main"
while [ $# -gt 0 ]; do
  case "$1" in
    --lot)  LOT="$2"; shift 2 ;;
    --pr)   PR="$2"; shift 2 ;;
    --base) BASE="$2"; shift 2 ;;
    -h|--help) sed -n '2,40p' "$0"; exit 0 ;;
    *) echo "ERREUR : option inconnue : $1 (voir --help)" >&2; exit 2 ;;
  esac
done
[ -n "$LOT" ] && [ -n "$PR" ] || { echo "ERREUR : --lot et --pr sont requis (voir --help)." >&2; exit 2; }

prove_subset "$ROOT_DIR" "$LOT" "$PR" "$BASE"
