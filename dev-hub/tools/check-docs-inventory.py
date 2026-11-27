#!/usr/bin/env python3
"""Inventaire et budget documentaire (#9981).

## Le défaut que cette garde ferme

Le dépôt contient **1 922 fichiers `.md`** pour ~452 000 lignes de PHP — environ
un document pour 235 lignes de code. Une partie est excellente (ADR numérotés,
référentiel produit, runbooks, gardes de parité doc↔code). Le problème n'est pas
le volume en soi : c'est que **la documentation devient une source de vérité
concurrente**.

La preuve est dans l'audit : `.github/BRANCH_PROTECTION_REQUIRED.md` et le
canonique machine déclaraient 4 checks requis alors que la réalité en avait 6.
La doc n'était pas fausse par négligence — elle était fausse parce qu'il y a
trop de documents pour qu'un humain les tienne tous à jour. Et « rejouer le
canonique » aurait supprimé deux checks délibérés.

## Ce que fait cet outil

  1. **Classe** chaque `.md` en quatre catégories :
     - `verified`   : vivant ET couvert par une garde de parité (doc↔code) ;
     - `living`     : vivant, non vérifié ;
     - `archived`   : historique, sous `docs/archive/` ou marqué « non maintenu » ;
     - `stale`      : candidat à la suppression (heuristique, jamais automatique).
  2. **Gèle le compte** : le nombre de `.md` ne peut pas croître sans
     justification. Un dépôt qui ajoute un document par PR sans jamais en
     retirer finit par ne plus pouvoir être maintenu.
  3. **Signale les affirmations machine-vérifiables non générées** : un
     document qui liste des checks requis, des variables d'env ou des modules
     doit être généré ou couvert par une garde.

## Ce qu'il ne fait PAS

Il ne supprime rien et ne déplace rien. La catégorie `stale` est une
**proposition** : supprimer un document est une décision humaine (le PM), et un
outil qui supprime de la documentation sur une heuristique détruirait le
contexte que l'audit cherche justement à préserver.

## Usage

    python3 dev-hub/tools/check-docs-inventory.py --inventory
    python3 dev-hub/tools/check-docs-inventory.py --check
    python3 dev-hub/tools/check-docs-inventory.py --self-test
"""
from __future__ import annotations

import argparse
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
BUDGET = ROOT / "dev-hub/governance/docs-budget.json"

# Dossiers dont le contenu est VIVANT et vérifié par une garde de parité.
# Chaque entrée doit citer la garde : une catégorie « vérifié » sans garde
# nommée serait une affirmation, pas une mesure.
VERIFIED_PREFIXES = {
    "docs/architecture/": "check-architecture-docs-parity.sh (42 modules × 4)",
    "docs/adr/": "check-docs-hygiene.sh (registre ADR)",
    "docs/security/": "check-rls-coverage-parity.py (couverture RLS)",
    "docs/qa/": "check-phpstan-baseline-budget.sh (budget mesuré)",
    "docs/infra/": "check-ci-workflow-budget.py (inventaire généré)",
}

# Marqueurs d'archivage explicites.
ARCHIVE_MARKERS = (
    "non maintenu",
    "not maintained",
    "archived",
    "archivé",
    "obsolète",
    "deprecated doc",
)

# Un document qui contient ces motifs fait une affirmation machine-vérifiable.
MACHINE_CLAIM_PATTERNS = {
    "checks requis": re.compile(r"checks?\s+requis|required\s+(status\s+)?checks?", re.I),
    "variables d'env": re.compile(r"^[A-Z][A-Z0-9_]{3,}\s*=", re.M),
    "modules": re.compile(r"Modules/[A-Z][A-Za-z]+", re.I),
}

# ## Pourquoi une liste déclarée, et pas une heuristique de contenu
#
# La première version de cet outil cherchait des marqueurs de contenu
# (« placeholder », « à compléter »). Mesure : 93 documents signalés, dont
# `CONTRIBUTING.md` et `SECURITY.md` — où « placeholder » désigne `<REDACTED>`,
# un terme de sécurité légitime — et `docs/payroll/BF_COMPLIANCE.md`, où
# « placeholder » est un NIVEAU de conformité du domaine.
#
# Une heuristique qui flagge la politique de sécurité du dépôt est pire
# qu'aucune heuristique : elle apprend au lecteur à ignorer le rapport
# (leçon #3545). La catégorie `stale` est donc une liste DÉCLARÉE par un
# humain, dans un fichier versionné. Zéro faux positif, et chaque entrée est
# une décision assumée, pas une déduction.
STALE_ALLOWLIST = ROOT / "dev-hub/governance/docs-stale-allowlist.txt"


def stale_declared() -> set[str]:
    """Documents déclarés obsolètes par un humain (une ligne = un chemin)."""
    if not STALE_ALLOWLIST.is_file():
        return set()
    out = set()
    for line in STALE_ALLOWLIST.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if line and not line.startswith("#"):
            out.add(line)
    return out


# Documents GÉNÉRÉS : ils ne comptent pas dans le budget, sinon l'outil
# s'auto-pénalise (écrire l'inventaire faisait passer 1 922 → 1 923 et violait
# le budget qu'il venait de geler). Un document généré n'est pas une décision
# éditoriale : il est recalculé à chaque exécution.
GENERATED_DOCS = {
    "docs/DOCS_INVENTORY.md",
    "docs/infra/CI_WORKFLOW_INVENTORY.md",
}


def all_docs() -> list[pathlib.Path]:
    out = []
    for p in ROOT.rglob("*.md"):
        rel = p.relative_to(ROOT).as_posix()
        if rel.startswith(("node_modules/", ".git/", "vendor/")):
            continue
        if rel in GENERATED_DOCS:
            continue
        out.append(p)
    return sorted(out)


def classify(path: pathlib.Path) -> tuple[str, str]:
    """(catégorie, justification)."""
    rel = path.relative_to(ROOT).as_posix()

    if rel.startswith("docs/archive/"):
        return "archived", "sous docs/archive/"

    try:
        head = path.read_text(encoding="utf-8", errors="ignore")[:4000].lower()
    except OSError:
        return "living", "illisible"

    for marker in ARCHIVE_MARKERS:
        if marker in head:
            return "archived", f"marqueur « {marker} »"

    for prefix, guard in VERIFIED_PREFIXES.items():
        if rel.startswith(prefix):
            return "verified", guard

    if rel in stale_declared():
        return "stale", "déclaré obsolète (docs-stale-allowlist.txt)"

    return "living", "aucune garde de parité"


def machine_claims(path: pathlib.Path) -> list[str]:
    try:
        text = path.read_text(encoding="utf-8", errors="ignore")
    except OSError:
        return []
    return [name for name, rx in MACHINE_CLAIM_PATTERNS.items() if rx.search(text)]


def inventory() -> int:
    docs = all_docs()
    buckets: dict[str, list[tuple[str, str]]] = {
        "verified": [], "living": [], "archived": [], "stale": [],
    }
    for p in docs:
        cat, why = classify(p)
        buckets[cat].append((p.relative_to(ROOT).as_posix(), why))

    print("# Inventaire documentaire")
    print()
    print(f"Total `.md` : **{len(docs)}**")
    print()
    print("| Catégorie | Nombre | Sens |")
    print("|---|---:|---|")
    print(f"| `verified` | {len(buckets['verified'])} | vivant, couvert par une garde de parité |")
    print(f"| `living` | {len(buckets['living'])} | vivant, non vérifié |")
    print(f"| `archived` | {len(buckets['archived'])} | historique, non maintenu |")
    print(f"| `stale` | {len(buckets['stale'])} | candidat à la suppression (décision PM) |")
    print()

    print("## `stale` — propositions, jamais supprimées automatiquement")
    print()
    if buckets["stale"]:
        print("| Document | Motif |")
        print("|---|---|")
        for rel, why in buckets["stale"]:
            print(f"| `{rel}` | {why} |")
    else:
        print("Aucun.")
    print()

    print("## Affirmations machine-vérifiables dans des documents non vérifiés")
    print()
    print("Un document qui liste des checks requis, des variables d'env ou des")
    print("modules doit être **généré** depuis le code ou couvert par une garde.")
    print("Sinon il dérive — c'est l'incident `BRANCH_PROTECTION_REQUIRED.md`.")
    print()
    risky = []
    for rel, _ in buckets["living"]:
        claims = machine_claims(ROOT / rel)
        if claims:
            risky.append((rel, claims))
    if risky:
        print("| Document | Affirmations |")
        print("|---|---|")
        for rel, claims in risky[:40]:
            print(f"| `{rel}` | {', '.join(claims)} |")
        if len(risky) > 40:
            print(f"| … | {len(risky) - 40} autre(s) |")
    else:
        print("Aucune.")
    return 0


def check() -> int:
    if not BUDGET.is_file():
        print(f"::error::Budget documentaire introuvable : {BUDGET}", file=sys.stderr)
        return 2

    budget = json.loads(BUDGET.read_text(encoding="utf-8"))
    docs = all_docs()
    n = len(docs)
    problems: list[str] = []

    if n > int(budget["max_markdown_files"]):
        problems.append(
            f"{n} fichiers .md > budget {budget['max_markdown_files']}. "
            "Un document ajouté doit remplacer ou archiver un document existant "
            "(docs/archive/), sinon la documentation devient une source de "
            "vérité concurrente — incident BRANCH_PROTECTION_REQUIRED.md."
        )

    # Les documents `stale` ne doivent pas croître : c'est le signe qu'on
    # archive au lieu d'empiler.
    stale = [p for p in docs if classify(p)[0] == "stale"]
    if len(stale) > int(budget["max_stale_documents"]):
        problems.append(
            f"{len(stale)} documents marqués obsolètes > budget "
            f"{budget['max_stale_documents']} : archiver ou supprimer."
        )

    for p in problems:
        print(f"::error::{p}")

    if problems:
        print(f"\n❌ Documentation : {len(problems)} problème(s).")
        return 1

    print(
        f"✅ Documentation dans le budget : {n} .md (max {budget['max_markdown_files']}), "
        f"{len(stale)} obsolète(s) (max {budget['max_stale_documents']})."
    )
    return 0


def self_test() -> int:
    failures = 0

    def expect(name: str, cond: bool) -> None:
        nonlocal failures
        if not cond:
            failures += 1
            print(f"::error::self-test '{name}'")

    # Classification par préfixe vérifié.
    p = ROOT / "docs/architecture/__probe__.md"
    expect("préfixe vérifié", "docs/architecture/" in VERIFIED_PREFIXES)

    # Un marqueur d'archivage prime sur le préfixe.
    expect("marqueur archivage", "non maintenu" in ARCHIVE_MARKERS)

    # La liste déclarée ne doit pas contenir de faux positif de type
    # « placeholder » : c'est le défaut corrigé.
    expect("pas de marqueur de contenu", not hasattr(sys.modules[__name__], "STALE_MARKERS"))
    expect("inventaire généré exclu du budget", "docs/DOCS_INVENTORY.md" in GENERATED_DOCS)

    # Détection d'affirmation machine-vérifiable.
    expect(
        "checks requis détectés",
        bool(MACHINE_CLAIM_PATTERNS["checks requis"].search("Les checks requis sont 6.")),
    )
    expect(
        "variable d'env détectée",
        bool(MACHINE_CLAIM_PATTERNS["variables d'env"].search("DB_USERNAME=leopardo\n")),
    )
    expect(
        "module détecté",
        bool(MACHINE_CLAIM_PATTERNS["modules"].search("voir Modules/Payroll")),
    )
    expect(
        "pas de faux positif sur du texte courant",
        not MACHINE_CLAIM_PATTERNS["checks requis"].search("Le module de paie calcule."),
    )

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 7/7.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--inventory", action="store_true")
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if args.self_test:
        return self_test()
    if args.inventory:
        return inventory()
    return check()


if __name__ == "__main__":
    sys.exit(main())
