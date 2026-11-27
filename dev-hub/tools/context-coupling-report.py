#!/usr/bin/env python3
"""Rapport de couplage inter-contextes (#9977).

## Pourquoi un générateur, et pas un document figé

L'issue #9977 demande « un rapport de couplage est produit ». Un rapport
commité en markdown serait faux dès la première arête ajoutée, et personne ne
le régénérerait : c'est exactement le mode de défaillance que l'audit reproche
à la documentation de ce dépôt (1 918 `.md` pour 452 k lignes).

Ce script **calcule** le rapport depuis la matrice versionnée
(`dev-hub/governance/bounded-context-dependencies.json`), qui est elle-même
vérifiée par `check-bounded-context-dependencies.sh` en CI. Il tourne donc sur
une donnée qui ne peut pas dériver silencieusement.

## Ce qu'il mesure

  - arêtes par contexte (sortantes = ce que je dépends, entrantes = qui dépend
    de moi) ;
  - les contextes les plus importés, et par qui ;
  - les cycles (A→B et B→A), qui sont le vrai défaut : deux contextes qui se
    connaissent mutuellement ne sont pas des bounded contexts ;
  - les fichiers > 25 Ko, qui concentrent des responsabilités que le découpage
    en modules était censé séparer.

## Usage

    python3 dev-hub/tools/context-coupling-report.py
    python3 dev-hub/tools/context-coupling-report.py --json
    python3 dev-hub/tools/context-coupling-report.py --self-test
"""
from __future__ import annotations

import argparse
import collections
import json
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
MATRIX = ROOT / "dev-hub/governance/bounded-context-dependencies.json"
# Budget de couplage : les chiffres MESURÉS sur main au 2026-11-27 (#9977).
# Une garde qui échoue sur l'état actuel ne serait jamais verte (leçon #3545) ;
# une garde qui ne borne rien ne protège rien. On gèle donc l'existant et on
# interdit la dérive. Réduire = baisser le budget dans le même commit.
BUDGET = ROOT / "dev-hub/governance/context-coupling-budget.json"
REGISTRY = ROOT / "dev-hub/governance/bounded-context-registry.json"
MODULES = ROOT / "api/app/Modules"

# Seuils. Un fichier de plus de 25 Ko dans une architecture « découpée en
# contextes » concentre des responsabilités que le découpage devait séparer.
BIG_FILE_BYTES = 25 * 1024


def load_edges() -> list[dict]:
    data = json.loads(MATRIX.read_text(encoding="utf-8"))
    return list(data.get("edges", []))


def load_names() -> dict[str, str]:
    """Code BC → nom lisible, depuis le registre (fallback : le code)."""
    if not REGISTRY.is_file():
        return {}
    try:
        data = json.loads(REGISTRY.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return {}

    out: dict[str, str] = {}
    # Le registre (MAT-001) porte la clé `bounded_contexts`, pas `contexts`.
    # Une résolution silencieusement vide afficherait des codes nus (`BC-03`)
    # là où le lecteur a besoin du nom (`IDENTITY`) — un rapport de couplage
    # illisible ne sert à rien.
    contexts = data.get("bounded_contexts") or data.get("contexts") or []
    if isinstance(contexts, dict):
        contexts = contexts.get("bounded_contexts", []) or []
    for c in contexts:
        if isinstance(c, dict):
            code = c.get("code") or c.get("id")
            name = c.get("name") or c.get("context") or code
            if code:
                out[str(code)] = str(name)
    return out


def find_big_files() -> list[tuple[int, str]]:
    if not MODULES.is_dir():
        return []
    found: list[tuple[int, str]] = []
    for p in MODULES.rglob("*.php"):
        try:
            size = p.stat().st_size
        except OSError:
            continue
        if size > BIG_FILE_BYTES:
            found.append((size, str(p.relative_to(ROOT))))
    return sorted(found, reverse=True)


def analyse(edges: list[dict]) -> dict:
    out = collections.Counter(e["from"] for e in edges)
    incoming = collections.Counter(e["to"] for e in edges)

    nodes = sorted(set(out) | set(incoming))
    pairs = {(e["from"], e["to"]) for e in edges}

    # Cycles de longueur 2 : le seul cas qu'une matrice d'arêtes permet de
    # détecter sans construire le graphe complet. C'est aussi le plus
    # révélateur : A→B et B→A signifient que la frontière n'existe pas.
    mutual = sorted({tuple(sorted(p)) for p in pairs if (p[1], p[0]) in pairs})

    return {
        "edges": len(edges),
        "contexts": len(nodes),
        "density": round(len(edges) / len(nodes), 2) if nodes else 0.0,
        "out": dict(out.most_common()),
        "incoming": dict(incoming.most_common()),
        "mutual_pairs": mutual,
        "documented": sum(1 for e in edges if (e.get("note") or "").strip()),
    }


def report(as_json: bool) -> int:
    if not MATRIX.is_file():
        print(f"::error::Matrice introuvable : {MATRIX}", file=sys.stderr)
        return 2

    edges = load_edges()
    names = load_names()
    a = analyse(edges)
    big = find_big_files()

    def label(code: str) -> str:
        name = names.get(code)
        return f"{code} ({name})" if name and name != code else code

    if as_json:
        print(json.dumps({**a, "big_files": [
            {"bytes": s, "path": p} for s, p in big
        ]}, indent=2, ensure_ascii=False))
        return 0

    print("# Rapport de couplage inter-contextes")
    print()
    print("> Généré par `dev-hub/tools/context-coupling-report.py` depuis "
          "`bounded-context-dependencies.json`. **Ne pas éditer à la main.**")
    print()
    print(f"- Arêtes autorisées : **{a['edges']}**")
    print(f"- Contextes impliqués : **{a['contexts']}**")
    print(f"- Densité moyenne : **{a['density']} dépendances sortantes par contexte**")
    print(f"- Arêtes justifiées par une note : **{a['documented']}/{a['edges']}**")
    print()

    print("## Les 8 contextes les plus importés")
    print()
    print("| Contexte | Arêtes entrantes | Dépendants |")
    print("|---|---|---|")
    for code, n in list(a["incoming"].items())[:8]:
        importers = sorted({e["from"] for e in edges if e["to"] == code})
        print(f"| {label(code)} | {n} | {', '.join(importers)} |")
    print()

    print("## Les 5 contextes qui dépendent le plus")
    print()
    print("| Contexte | Arêtes sortantes |")
    print("|---|---|")
    for code, n in list(a["out"].items())[:5]:
        print(f"| {label(code)} | {n} |")
    print()

    print("## Cycles de longueur 2")
    print()
    if a["mutual_pairs"]:
        print(f"**{len(a['mutual_pairs'])} paire(s) se connaissent mutuellement** — "
              "la frontière de contexte n'existe pas :")
        print()
        for x, y in a["mutual_pairs"]:
            print(f"- {label(x)} ⇄ {label(y)}")
    else:
        print("Aucune. Le graphe est orienté sans réciprocité à deux nœuds.")
    print()

    print(f"## Fichiers > {BIG_FILE_BYTES // 1024} Ko")
    print()
    if big:
        print("| Taille | Fichier |")
        print("|---|---|")
        for size, path in big:
            print(f"| {size // 1024} Ko | `{path}` |")
    else:
        print("Aucun.")
    print()

    print("## Lecture")
    print()
    print("Un contexte très importé (haut du premier tableau) n'est pas "
          "forcément un défaut : `IDENTITY` et `TENANT` sont des socles, "
          "légitimement partagés. Le défaut est ailleurs :")
    print()
    print("- un contexte **métier** très importé (paie, facturation) signifie que "
          "sa logique fuit dans ses voisins ;")
    print("- un **cycle** signifie que la frontière est nominale ;")
    print("- un fichier > 25 Ko dans un module censé être découpé signifie que "
          "le découpage s'arrête au dossier.")
    return 0


def check_budget() -> int:
    """Échoue si le couplage a DÉPASSÉ le budget gelé (#9977).

    Trois métriques bornées :
      - `edges`          : arêtes autorisées. Objectif #9977 : -20 %.
      - `mutual_pairs`   : paires A⇄B. C'est la métrique qui compte : une arête
                           supplémentaire dans un graphe déjà cyclique n'aggrave
                           pas la frontière, une NOUVELLE réciprocité si.
      - `big_files`      : fichiers > 25 Ko.

    Le budget ne peut que descendre : le relever exige de modifier ce fichier
    dans une PR, donc d'écrire pourquoi. C'est la différence entre une règle et
    une intention.
    """
    if not BUDGET.is_file():
        print(f"::error::Budget de couplage introuvable : {BUDGET}", file=sys.stderr)
        return 2

    budget = json.loads(BUDGET.read_text(encoding="utf-8"))
    edges = load_edges()
    a = analyse(edges)
    big = find_big_files()

    problems: list[str] = []

    max_edges = int(budget["max_edges"])
    if a["edges"] > max_edges:
        problems.append(
            f"arêtes {a['edges']} > budget {max_edges} : {a['edges'] - max_edges} "
            "dépendance(s) inter-contexte(s) ajoutée(s) sans réduire ailleurs. "
            "Passer par un contrat/événement de domaine, ou justifier et relever "
            "le budget dans la PR."
        )

    max_pairs = int(budget["max_mutual_pairs"])
    if len(a["mutual_pairs"]) > max_pairs:
        new_pairs = [f"{x}⇄{y}" for x, y in a["mutual_pairs"] if f"{x}⇄{y}" not in budget.get("mutual_pairs", [])]
        problems.append(
            f"paires réciproques {len(a['mutual_pairs'])} > budget {max_pairs}. "
            f"Nouvelles : {', '.join(new_pairs) or '(non identifiées)'}. "
            "Une réciprocité A⇄B signifie que la frontière n'existe pas : "
            "extraire le contrat commun dans un module partagé plutôt que "
            "d'importer dans les deux sens."
        )

    max_big = int(budget["max_big_files"])
    if len(big) > max_big:
        problems.append(
            f"fichiers > 25 Ko : {len(big)} > budget {max_big} "
            f"({', '.join(p for _, p in big)})"
        )

    for p in problems:
        print(f"::error::{p}")

    if problems:
        print(f"\n❌ Couplage au-delà du budget ({len(problems)} dérive(s)).")
        return 1

    print(
        f"✅ Couplage dans le budget : "
        f"{a['edges']} arêtes (max {max_edges}), "
        f"{len(a['mutual_pairs'])} paires réciproques (max {max_pairs}), "
        f"{len(big)} fichiers > 25 Ko (max {max_big})."
    )
    return 0


def self_test() -> int:
    failures = 0

    def check(name: str, ok: bool) -> None:
        nonlocal failures
        if not ok:
            failures += 1
            print(f"::error::self-test '{name}'")

    edges = [
        {"from": "BC-01", "to": "BC-02", "note": "x"},
        {"from": "BC-02", "to": "BC-01", "note": "y"},
        {"from": "BC-03", "to": "BC-02", "note": ""},
        {"from": "BC-01", "to": "BC-04", "note": "z"},
    ]
    a = analyse(edges)
    check("compte d'arêtes", a["edges"] == 4)
    check("contextes", a["contexts"] == 4)
    check("plus importé = BC-02", next(iter(a["incoming"])) == "BC-02")
    check("cycle détecté", a["mutual_pairs"] == [("BC-01", "BC-02")])
    check("justification comptée", a["documented"] == 3)

    # Pas de cycle quand le graphe est un DAG.
    acyclic = [
        {"from": "A", "to": "B", "note": "x"},
        {"from": "B", "to": "C", "note": "x"},
        {"from": "A", "to": "C", "note": "x"},
    ]
    check("DAG sans cycle", analyse(acyclic)["mutual_pairs"] == [])

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 6/6.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--json", action="store_true")
    parser.add_argument("--check", action="store_true", help="Vérifier le budget (#9977)")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if args.self_test:
        return self_test()
    if args.check:
        return check_budget()
    return report(as_json=args.json)


if __name__ == "__main__":
    sys.exit(main())
