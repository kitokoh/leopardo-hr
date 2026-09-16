#!/usr/bin/env bash
#
# check-json-catalogs-parse.sh — aucun fichier JSON/ARB suivi ne doit être
# syntaxiquement cassé (issue #7583).
#
# Le piège mesuré (drain #7562, documenté §6.3 du protocole des lots)
# -------------------------------------------------------------------
# Résoudre un conflit de catalogue i18n avec `git merge-file --union` produit du
# JSON **invalide** : `--union` concatène les deux côtés ligne à ligne, donc
# duplique des virgules et des clés. 13 à 17 fichiers cassés, et les
# synchronisateurs du dépôt (`shared/i18n/sync/*.js`) plantaient au démarrage.
#
# Ce qui était déjà couvert, et ce qui ne l'était pas
# ---------------------------------------------------
# - `shared/i18n/locales/*.json`, les catalogues générés web/admin et
#   `versions.json` : couverts par `shared/i18n/validators/validate.js` (il les
#   lit, donc un JSON cassé le fait échouer) — mais **seulement** ces chemins ;
# - les 4 `.arb` mobiles : couverts par `check-i18n-catalog-parity.sh` ;
# - **tout le reste** — `package.json`, `composer.json`, les configs, les
#   contrats `dev-hub/tools/*.json`, `budget.json`, `lighthouserc.json`… — n'est
#   validé par AUCUNE garde. Et la garde de parité est filtrée par `paths:` : un
#   JSON cassé hors de ces chemins ne déclenche même pas le workflow.
#
# Cette garde comble ce trou : elle parse **tous** les JSON/ARB suivis. Coût :
# quelques dizaines de millisecondes sur ~200 fichiers — d'où son branchement
# dans un check déjà exécuté à chaque PR plutôt qu'un workflow de plus.
#
# Usage :
#   bash dev-hub/tools/check-json-catalogs-parse.sh
#   bash dev-hub/tools/check-json-catalogs-parse.sh --self-test
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Parse (et rapporte) tous les JSON/ARB suivis par git sous $1.
# Sortie : une ligne "<fichier> :: <erreur>" par fichier invalide ; exit 1 si au moins un.
run_check() {
  local root="$1"
  python3 - "$root" <<'PYEOF'
import json, subprocess, sys, os

root = sys.argv[1]

def tracked():
    out = subprocess.run(
        ['git', '-C', root, 'ls-files', '-z', '*.json', '*.arb'],
        capture_output=True, text=True, check=True,
    ).stdout
    return [p for p in out.split('\0') if p]

bad = []
files = tracked()
for rel in files:
    path = os.path.join(root, rel)
    if not os.path.isfile(path):
        continue  # supprimé dans l'arbre de travail : hors périmètre
    try:
        with open(path, encoding='utf-8') as fh:
            json.load(fh)
    except json.JSONDecodeError as exc:
        bad.append(f"{rel} :: {exc}")
    except OSError as exc:
        bad.append(f"{rel} :: illisible ({exc})")

print(f"JSON/ARB analyses : {len(files)}")
for line in bad:
    print(f"CASSE : {line}")
sys.exit(1 if bad else 0)
PYEOF
}

if [ "${1:-}" = "--self-test" ]; then
  echo "== auto-test : la garde doit distinguer un JSON valide d'un JSON casse =="
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT
  git -C "$TMP" init -q
  git -C "$TMP" config user.email a@b.c
  git -C "$TMP" config user.name t

  # valide
  printf '{"a": 1, "b": [1, 2]}\n' > "$TMP/ok.json"
  # le dégât typique de `--union` : virgule dupliquée dans un tableau
  printf '{"a": 1,,\n "b": 2}\n' > "$TMP/casse.json"
  # l'ARB mobile est couvert aussi
  printf '{"@@locale": "fr",}\n' > "$TMP/app_fr.arb"
  git -C "$TMP" add -A

  set +e
  output="$(run_check "$TMP")"
  status=$?
  set -e
  echo "$output"

  fail=0
  [ "$status" -eq 1 ] || { echo "ECHEC : la garde devait sortir 1 avec des fichiers casses (obtenu $status)"; fail=1; }
  echo "$output" | grep -q "CASSE : casse.json" || { echo "ECHEC : casse.json non signale"; fail=1; }
  echo "$output" | grep -q "CASSE : app_fr.arb"  || { echo "ECHEC : app_fr.arb non signale"; fail=1; }
  echo "$output" | grep -q "CASSE : ok.json"     && { echo "ECHEC : ok.json signale a tort"; fail=1; }

  # Contre-épreuve : tout valide => la garde doit passer (sinon elle est toujours rouge).
  printf '{"a": 1, "b": [1, 2]}\n' > "$TMP/casse.json"
  printf '{"@@locale": "fr"}\n' > "$TMP/app_fr.arb"
  git -C "$TMP" add -A
  set +e
  run_check "$TMP" > /dev/null
  status2=$?
  set -e
  [ "$status2" -eq 0 ] || { echo "ECHEC : la garde reste rouge sur un arbre sain (obtenu $status2)"; fail=1; }

  [ "$fail" -eq 0 ] && echo "AUTO-TEST OK (rouge sur JSON casse, vert sur arbre sain)" || exit 1
  exit 0
fi

set +e
run_check "$ROOT_DIR"
status=$?
set -e

if [ "$status" -ne 0 ]; then
  echo ""
  echo "Un fichier JSON/ARB suivi ne se parse pas. Cause la plus frequente : un"
  echo "conflit resolu avec 'git merge-file --union' (voir §6.3 du protocole des"
  echo "lots). Methode qui marche : merge PROFOND des deux cotes"
  echo "(json.loads + json.dumps), puis REGENERER par shared/i18n/sync/*.js."
  exit 1
fi

echo "Tous les JSON/ARB suivis se parsent."
