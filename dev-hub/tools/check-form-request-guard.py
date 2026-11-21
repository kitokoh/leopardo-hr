#!/usr/bin/env python3
"""Validation : FormRequest partout, dette existante figee (ratchet).

## La decision

Le depot a DEUX styles de validation qui coexistent sans regle :

  - 541 classes `*Request` (FormRequest) — le style cible ;
  - 437 appels `$request->validate([...])` en ligne dans 227 controleurs.

Ce n'est pas une preference esthetique. Un FormRequest porte ses regles dans
une classe nommee, testable seule, reutilisable par deux endpoints, et
`authorize()` y rend la permission explicite. Une validation en ligne est
anonyme : on ne peut pas la tester sans monter une requete HTTP complete, et
la meme regle recopiee dans deux controleurs derive silencieusement.

## Ce que cette garde fait

Elle REFUSE toute NOUVELLE validation en ligne. La dette existante est figee
dans `dev-hub/tools/form-request-guard-baseline.txt` : elle ne peut que
DECROITRE. Convertir un controleur = retirer sa ligne de la baseline.

C'est le meme mecanisme que `check-search-path-guard.py` (#9115) et
`check-phpstan-baseline-budget.sh` : on ne demande pas une migration big-bang
impossible a relire, on empeche la dette d'augmenter et on la fait baisser
lot par lot.

## Ce qu'elle n'a PAS a refuser

  - `Validator::make(...)` : ce n'est pas une validation de requete HTTP (les
    imports CSV valident ligne par ligne, sans FormRequest possible) ;
  - `$request->validate()` dans un FormRequest lui-meme (`rules()` n'existe
    pas toujours : `withValidator`/`after` peuvent valider dynamiquement) ;
  - un fichier de test.

## Usage

    python3 dev-hub/tools/check-form-request-guard.py            # verifie
    python3 dev-hub/tools/check-form-request-guard.py --update    # fige la dette
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
BASELINE = REPO / "dev-hub" / "tools" / "form-request-guard-baseline.txt"
SCAN_ROOT = "api/app/"

# Une validation de requete HTTP en ligne. `Validator::make()` est
# volontairement exclu : il ne valide pas une requete, et les imports de
# masse en ont un usage legitime (une ligne a la fois, sans FormRequest).
INLINE_VALIDATE = re.compile(r"\$request\s*->\s*validate\s*\(")


def find_inline() -> list[tuple[str, int]]:
    """[(fichier, ligne)] des validations en ligne dans les controleurs."""
    try:
        files = subprocess.check_output(
            ["grep", "-rl", "--include=*Controller.php", "$request->validate(", SCAN_ROOT],
            cwd=REPO,
            text=True,
        ).split()
    except subprocess.CalledProcessError:
        return []  # aucun fichier : dette nulle

    hits: list[tuple[str, int]] = []
    for rel in files:
        path = REPO / rel
        if not path.is_file():
            continue
        text = path.read_text(encoding="utf-8", errors="replace")
        for match in INLINE_VALIDATE.finditer(text):
            hits.append((rel, text[: match.start()].count("\n") + 1))
    return sorted(hits)


def key(entry: tuple[str, int]) -> str:
    return f"{entry[0]}:{entry[1]}"


def load_baseline() -> set[str]:
    if not BASELINE.is_file():
        return set()
    return {
        line.strip()
        for line in BASELINE.read_text(encoding="utf-8").splitlines()
        if line.strip() and not line.startswith("#")
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--update", action="store_true", help="fige la dette courante")
    args = parser.parse_args()

    current = {key(entry) for entry in find_inline()}

    if args.update:
        header = (
            "# Validation : `$request->validate()` en ligne dans un controleur.\n"
            "# Decision : FormRequest partout pour tout code NOUVEAU. Cette dette\n"
            "# ne doit que DECROITRE — retirer une ligne quand le controleur passe\n"
            "# a un FormRequest dedie.\n"
        )
        BASELINE.write_text(header + "\n".join(sorted(current)) + "\n", encoding="utf-8")
        print(f"baseline mise a jour : {len(current)} occurrence(s)")
        return 0

    baseline = load_baseline()
    new = sorted(current - baseline)
    fixed = sorted(baseline - current)

    if fixed:
        print(f"OK — {len(fixed)} occurrence(s) convertie(s) en FormRequest :")
        for entry in fixed:
            print(f"   - {entry}")

    if new:
        print(
            f"ECHEC — {len(new)} validation(s) en ligne NOUVELLE(S) :",
            file=sys.stderr,
        )
        for entry in new:
            print(f"   {entry}", file=sys.stderr)
        print(
            "\nDecision du depot : FormRequest partout. Creer une classe\n"
            "`app/.../Requests/XxxRequest.php` (regles dans `rules()`, permission\n"
            "dans `authorize()`) et la type-hinter sur la signature du controleur.\n"
            "Voir #9699 / #9706 pour le piege du `catch (\\Throwable)` autour d'un\n"
            "FormRequest instancie DANS le try.",
            file=sys.stderr,
        )
        return 1

    print(f"OK — aucune nouvelle validation en ligne (dette figee : {len(baseline)})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
