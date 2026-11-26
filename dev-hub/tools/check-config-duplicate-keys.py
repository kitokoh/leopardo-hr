#!/usr/bin/env python3
"""Garde CI — aucune clé de configuration PHP dupliquée dans un même tableau.

## Le défaut que cette garde ferme

En PHP, un tableau littéral avec deux fois la même clé **ne lève aucune erreur** :
la dernière valeur gagne silencieusement. Dans `api/config/`, cela signifie qu'une
variable d'environnement documentée peut être purement et simplement ignorée.

Constat mesuré sur `main` (audit architecture 2026-11-26) :

  - `api/config/security.php` déclarait **deux fois** `zkteco_device_per_minute`
    et **deux fois** `public_careers_per_minute` (blocs copiés à deux endroits) ;
  - `api/config/services.php` déclarait **trois fois** `mail_bounce_webhook`,
    avec trois corps différents — dont un `env(..., '')` final qui écrasait les
    deux précédents.

Aucun test ne pouvait le voir : `config()` renvoie une valeur, elle existe, elle
est juste la mauvaise. C'est exactement le genre de défaut qu'un ratchet CI doit
attraper, pas une revue humaine.

## Ce que la garde vérifie

Pour chaque fichier `api/config/*.php`, on analyse les tableaux littéraires
imbriqués et on signale toute clé `'...' =>` répétée **dans le même tableau**
(même chemin de clés). Deux clés identiques dans deux tableaux frères différents
ne sont PAS un doublon.

## Usage

    python3 dev-hub/tools/check-config-duplicate-keys.py            # vérifie
    python3 dev-hub/tools/check-config-duplicate-keys.py --self-test # auto-test
    python3 dev-hub/tools/check-config-duplicate-keys.py --verbose

Exit 0 = propre, 1 = au moins un doublon, 2 = erreur technique.
"""
from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
CONFIG_GLOB = "api/config/*.php"

# Une clé de tableau littéral en début de ligne logique : 'nom' => ...
KEY_RE = re.compile(r"^\s*'([A-Za-z0-9_.\-]+)'\s*=>")


def strip_php_comments(text: str) -> str:
    """Retire les commentaires //, # et /* */ en préservant les chaînes.

    Sans cela, une clé citée dans un commentaire (« la clé `x` n'existait pas »)
    serait comptée comme une vraie clé — le faux positif le plus probable.
    """
    out: list[str] = []
    i = 0
    n = len(text)
    quote: str | None = None
    while i < n:
        ch = text[i]
        if quote is not None:
            out.append(ch)
            if ch == "\\" and i + 1 < n:
                out.append(text[i + 1])
                i += 2
                continue
            if ch == quote:
                quote = None
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
            out.append(ch)
            i += 1
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "/":
            while i < n and text[i] != "\n":
                i += 1
            continue
        if ch == "#":
            while i < n and text[i] != "\n":
                i += 1
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "*":
            i += 2
            while i + 1 < n and not (text[i] == "*" and text[i + 1] == "/"):
                i += 1
            i += 2
            continue
        out.append(ch)
        i += 1
    return "".join(out)


def find_duplicates(text: str) -> list[tuple[str, int, list[int]]]:
    """Retourne [(chemin_de_clés, ligne_de_la_1re_occurrence, [lignes])].

    Analyse caractère par caractère plutôt que ligne par ligne : un tableau
    littéral peut tenir sur une seule ligne (`['a' => 1, 'a' => 2]`), et une
    garde qui ne verrait que la première clé d'une ligne laisserait passer
    exactement le cas le plus simple.
    """
    clean = strip_php_comments(text)

    # Pile de tableaux ouverts : (clé_ouvrante_ou_None, {clé: 1re_ligne})
    stack: list[tuple[str | None, dict[str, int]]] = [(None, {})]
    duplicates: list[tuple[str, int, list[int]]] = []

    i = 0
    n = len(clean)
    line = 1
    while i < n:
        ch = clean[i]

        if ch == "\n":
            line += 1
            i += 1
            continue

        if ch == "[":
            # Le tableau qui s'ouvre hérite de la clé qui le précède, s'il y en a une.
            stack.append((None, {}))
            i += 1
            continue

        if ch == "]":
            if len(stack) > 1:
                stack.pop()
            i += 1
            continue

        if ch in ("'", '"'):
            quote = ch
            j = i + 1
            buf: list[str] = []
            while j < n:
                if clean[j] == "\\" and j + 1 < n:
                    buf.append(clean[j + 1])
                    j += 2
                    continue
                if clean[j] == quote:
                    break
                if clean[j] == "\n":
                    line += 1
                buf.append(clean[j])
                j += 1
            key = "".join(buf)
            k = j + 1
            while k < n and clean[k] in " \t":
                k += 1
            is_key = clean.startswith("=>", k)
            if is_key:
                seen = stack[-1][1]
                if key in seen:
                    path = [p for p, _ in stack[1:] if p] + [key]
                    duplicates.append((".".join(path), seen[key], [line]))
                else:
                    seen[key] = line
                # La clé nomme le tableau qui s'ouvre juste après `=>`.
                k += 2
                while k < n and clean[k] in " \t\r\n":
                    if clean[k] == "\n":
                        line += 1
                    k += 1
                if k < n and clean[k] == "[":
                    stack.append((key, {}))
                    i = k + 1
                    continue
                i = k
                continue
            i = j + 1
            continue

        i += 1

    return duplicates


def check(verbose: bool = False) -> int:
    files = sorted(ROOT.glob(CONFIG_GLOB))
    if not files:
        print(f"::error::Aucun fichier de configuration trouvé ({CONFIG_GLOB}).", file=sys.stderr)
        return 2

    total = 0
    for path in files:
        rel = path.relative_to(ROOT)
        dups = find_duplicates(path.read_text(encoding="utf-8", errors="ignore"))
        for key_path, first_line, dup_lines in dups:
            total += 1
            print(
                f"::error file={rel},line={dup_lines[0]}::"
                f"Clé de configuration dupliquée '{key_path}' "
                f"(déjà déclarée ligne {first_line}). "
                f"En PHP la DERNIÈRE valeur gagne silencieusement : "
                f"supprimer le doublon, ou renommer la clé si les deux sont voulues."
            )
        if verbose and not dups:
            print(f"  ok  {rel}")

    if total:
        print(f"\n❌ {total} clé(s) de configuration dupliquée(s) — voir ci-dessus.")
        return 1

    print(f"✅ Aucune clé de configuration dupliquée ({len(files)} fichiers analysés).")
    return 0


def self_test() -> int:
    """Une garde cassée ne doit pas pouvoir passer pour un vert."""
    failures = 0

    def expect(name: str, text: str, want: int) -> None:
        nonlocal failures
        got = len(find_duplicates(text))
        if got != want:
            failures += 1
            print(f"::error::self-test '{name}' : attendu {want} doublon(s), obtenu {got}")

    expect("doublon simple", "<?php return ['a' => 1, 'a' => 2];", 1)
    expect("aucun doublon", "<?php return ['a' => 1, 'b' => 2];", 0)
    expect(
        "même clé dans deux tableaux frères (pas un doublon)",
        "<?php return ['x' => ['a' => 1], 'y' => ['a' => 2]];",
        0,
    )
    expect(
        "doublon imbriqué",
        "<?php return ['x' => ['a' => 1, 'a' => 2]];",
        1,
    )
    expect(
        "clé citée dans un commentaire (faux positif à éviter)",
        "<?php return [\n  // 'a' => 1\n  'a' => 2,\n];",
        0,
    )
    expect(
        "clé citée dans un bloc /* */",
        "<?php return [\n  /* 'a' => 1 */\n  'a' => 2,\n];",
        0,
    )
    expect(
        "trois occurrences",
        "<?php return ['a' => 1, 'a' => 2, 'a' => 3];",
        2,
    )

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 7/7.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--verbose", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        return self_test()
    return check(verbose=args.verbose)


if __name__ == "__main__":
    sys.exit(main())
