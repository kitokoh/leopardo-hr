#!/usr/bin/env python3
"""Issue #9804 — mesure l'etat de la distribution depuis `SUIVI.md`.

## Pourquoi mesurer au lieu de saisir

Un tableau de bord saisi a la main est faux des le lendemain, et il ne dit jamais
ce qu'il ne sait pas. Ce script lit `SUIVI.md` et **derive** les compteurs, puis
signale les incoherences :

  - une action `publie` sans URL (la regle de verite de l'espace) ;
  - une action `publie` sans date ;
  - un statut hors vocabulaire ;
  - un brief video reference dans `SUIVI.md` mais absent de `VIDEOS/briefs/`.

## Usage

    python3 docs/DISTRIBUTION/scripts/distribution-status.py
    python3 docs/DISTRIBUTION/scripts/distribution-status.py --check
    python3 docs/DISTRIBUTION/scripts/distribution-status.py --self-test

`--check` sort en 1 s'il existe au moins une incoherence : utilisable en garde CI.
"""

from __future__ import annotations

import argparse
import re
import sys
import tempfile
from pathlib import Path

DISTRIBUTION = Path(__file__).resolve().parents[1]
SUIVI = DISTRIBUTION / "SUIVI.md"
BRIEFS = DISTRIBUTION / "VIDEOS" / "briefs"

STATUSES = ("à faire", "fait", "publié", "abandonné")
COLUMNS = ("Action", "Catégorie", "Objet", "Cible", "Statut", "URL", "Date", "Owner")


def parse_rows(text: str) -> list[dict[str, str]]:
    """Extrait les lignes du tableau de suivi (ignore en-tete et separateur)."""
    rows: list[dict[str, str]] = []
    header_seen = False

    for line in text.splitlines():
        stripped = line.strip()
        if not stripped.startswith("|"):
            continue

        cells = [c.strip() for c in stripped.strip("|").split("|")]

        if not header_seen:
            if cells and cells[0] == "Action":
                header_seen = True
            continue

        # Ligne de separation |---|---|...
        if all(set(c) <= set("-: ") for c in cells):
            continue

        if len(cells) < len(COLUMNS):
            cells += [""] * (len(COLUMNS) - len(cells))

        rows.append(dict(zip(COLUMNS, cells)))

    return rows


def analyse(rows: list[dict[str, str]], briefs_dir: Path) -> tuple[dict, list[str]]:
    by_status: dict[str, int] = {s: 0 for s in STATUSES}
    by_category: dict[str, dict[str, int]] = {}
    problems: list[str] = []

    for row in rows:
        status = row["Statut"]
        category = row["Catégorie"] or "(sans categorie)"

        if status not in STATUSES:
            problems.append(
                f"statut inconnu « {status} » pour « {row['Action']} » "
                f"(attendu : {', '.join(STATUSES)})"
            )
            continue

        by_status[status] += 1
        by_category.setdefault(category, {s: 0 for s in STATUSES})[status] += 1

        url = row["URL"].strip()
        has_url = url not in ("", "—", "-")

        if status == "publié" and not has_url:
            problems.append(f"« {row['Action']} » est publié sans URL — la règle de vérité l'interdit")

        if status == "publié" and row["Date"].strip() in ("", "—", "-"):
            problems.append(f"« {row['Action']} » est publié sans date")

        # Un brief video doit exister sur disque si l'action le nomme.
        if category == "démonstration" or "vidéo" in row["Action"].lower():
            expected = f"{row['Catégorie']}__{row['Objet']}__{row['Cible']}"
            if briefs_dir.is_dir() and not any(briefs_dir.glob(f"*{row['Objet']}*")):
                problems.append(
                    f"« {row['Action']} » : aucun brief video pour l'objet « {row['Objet']} » "
                    f"(attendu dans {briefs_dir.name}/)"
                )

    return {"by_status": by_status, "by_category": by_category, "total": len(rows)}, problems


def self_test() -> int:
    failures = 0

    sample = """# Suivi

| Action | Catégorie | Objet | Cible | Statut | URL | Date | Owner |
|---|---|---|---|---|---|---|---|
| A | publicité | pointage | LinkedIn | à faire | — | — | Oui |
| B | référencement | produit | G2 | publié | https://x | 2026-11-01 | Humain |
| C | référencement | produit | G2 | publié | — | — | Humain |
| D | publicité | paie | X | termine | — | — | Oui |
"""
    rows = parse_rows(sample)
    data, problems = analyse(rows, Path("/nonexistent"))

    checks = [
        ("4 lignes lues", len(rows) == 4),
        ("statut à faire compte", data["by_status"]["à faire"] == 1),
        ("statut publié compte", data["by_status"]["publié"] == 2),
        ("statut inconnu non compte", data["by_status"]["fait"] == 0),
        ("publié sans URL signale", any("sans URL" in p for p in problems)),
        ("publié sans date signale", any("sans date" in p for p in problems)),
        ("statut inconnu signale", any("statut inconnu" in p for p in problems)),
        ("catégories agregees", "référencement" in data["by_category"]),
    ]

    for name, ok in checks:
        print(f"  [{'OK  ' if ok else 'FAIL'}] {name}")
        if not ok:
            failures += 1

    if failures:
        print(f"\nAUTOTEST ECHEC — {failures} cas sur {len(checks)}.", file=sys.stderr)
        return 1
    print(f"\nAUTOTEST OK — {len(checks)} cas.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--suivi", default=str(SUIVI))
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    suivi = Path(args.suivi)
    if not suivi.is_file():
        print(f"ERREUR — fichier introuvable : {suivi}", file=sys.stderr)
        return 2

    rows = parse_rows(suivi.read_text(encoding="utf-8"))
    data, problems = analyse(rows, BRIEFS)

    print(f"Actions suivies : {data['total']}")
    print("")
    print("Par statut :")
    for status in STATUSES:
        print(f"  {status:<12} {data['by_status'][status]:>3}")
    print("")
    print("Par categorie :")
    for category in sorted(data["by_category"]):
        counts = data["by_category"][category]
        detail = " · ".join(f"{s}={counts[s]}" for s in STATUSES if counts[s])
        print(f"  {category:<20} {detail}")

    if problems:
        print("")
        print(f"INCOHERENCES ({len(problems)}) :", file=sys.stderr)
        for problem in problems:
            print(f"  - {problem}", file=sys.stderr)
        return 1 if args.check else 0

    print("")
    print("Aucune incoherence.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
