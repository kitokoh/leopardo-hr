#!/usr/bin/env python3
"""Issue #9801 — genere les sections mesurees de `docs/ARCHITECTURE_STATUS.md`.

## Pourquoi ce script existe

`docs/ARCHITECTURE_STATUS.md` est le document de statut d'architecture le plus lu
du depot. Il etait **redige a la main**, et il a derive :

  - trois comptages de modules contradictoires dans le meme fichier
    (41 au titre §1, « 25 modules actifs » au titre §3, « 16 » dans le tableau §3) ;
  - un tableau DDD qui sous-declare des modules reels (`Catalog`, `Showcase`,
    `Pages`, `Retail`, `Communication`, `Fleet`, `Geo` declares sans couches
    alors que toutes leurs couches existent) ;
  - une roadmap declarant « ✅ Fait » un dossier absent
    (`api/tests/Feature/Growth` n'existe pas) ;
  - un coverage gate declare « required » et « strict » alors que la protection
    de branche reelle porte 4 contextes requis, dont il ne fait pas partie.

La cause racine n'est pas la negligence : c'est qu'un document de statut
**redige** est faux des le prochain commit. Ce script le rend **mesure** : les
sections 1, 3 et 5 sont recalculees depuis le disque, et `--check` echoue quand
le fichier commite diverge du disque.

## Trois zones, pas une

Les sections mesurees ne sont pas contigues dans le document (§2 routes et §4
CI/CD restent redigees entre elles). Le script gere donc **trois paires de
marqueurs**, une par section, et ne remplace que chacune de ces zones. Le reste
du document — §2, §4, les notes, l'historique — n'est jamais touche.

## Chemins de couches

Les couches ne sont pas toutes a la racine du module : `Contracts` et
`Exceptions` vivent sous `Domain/`, `DTOs` sous `Application/`. Mesurer
`<module>/Contracts` donnerait « absent » partout — c'est exactement l'erreur
qui a produit le tableau sous-declare.

## Usage

    python3 dev-hub/tools/generate-architecture-status.py            # ecrit
    python3 dev-hub/tools/generate-architecture-status.py --check    # ne ecrit pas
    python3 dev-hub/tools/generate-architecture-status.py --self-test
"""

from __future__ import annotations

import argparse
import sys
import tempfile
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_DOC = REPO_ROOT / "docs" / "ARCHITECTURE_STATUS.md"
API_ROOT = REPO_ROOT / "api"

# Une paire de marqueurs par section mesuree.
ZONES: dict[str, tuple[str, str]] = {
    "s1": (
        "<!-- BEGIN GENERATED ARCHITECTURE STATUS S1 -->",
        "<!-- END GENERATED ARCHITECTURE STATUS S1 -->",
    ),
    "s3": (
        "<!-- BEGIN GENERATED ARCHITECTURE STATUS S3 -->",
        "<!-- END GENERATED ARCHITECTURE STATUS S3 -->",
    ),
    "s5": (
        "<!-- BEGIN GENERATED ARCHITECTURE STATUS S5 -->",
        "<!-- END GENERATED ARCHITECTURE STATUS S5 -->",
    ),
}

# (libelle de colonne, chemin relatif au module)
LAYERS: list[tuple[str, str]] = [
    ("Domain", "Domain"),
    ("Contracts", "Domain/Contracts"),
    ("Exceptions", "Domain/Exceptions"),
    ("Application", "Application"),
    ("DTOs", "Application/DTOs"),
    ("Infra", "Infrastructure"),
    ("Interfaces", "Interfaces"),
    ("Providers", "Providers"),
]

# Modules dont l'absence de couche est une DECISION, pas un retard. Sans cette
# liste, le rapport les presenterait comme des trous a combler — ce qui est
# precisement l'erreur de lecture que l'audit a relevee.
DECIDED_ABSENCES: dict[str, str] = {
    "Absence": "facade HTTP assumee (PA2-ARCH-002, ADR-0031) — domaine propriete de Planning",
    "Restaurant": "fournisseur de contenu (ADR-0020) — Application/Infra/Interfaces N/A intentionnel",
    "EdgeSync": "structure specialisee synchro/offline — pas de Contracts/Exceptions/DTOs",
}

# Roadmap : (libelle, chemin a tester, sens du test, priorite, effort)
#   sens "exists"  -> la ligne est « Fait » si le chemin existe
#   sens "absent"  -> la ligne est « Fait » si le chemin N'EXISTE PAS
ROADMAP: list[tuple[str, str, str, str, str]] = [
    ("Supprimer `app/Models/` doublons", "api/app/Models", "absent", "P1", "Eleve"),
    ("Finaliser `app/DTOs/` racine", "api/app/DTOs", "absent", "P1", "Faible"),
    ("Peupler `app/Shared/` (Traits/Attributes/Enums)", "api/app/Shared", "exists", "P2", "Moyen"),
    ("Migrer `Core/Tenant/` (TenantManager)", "api/app/Core/Tenant", "exists", "P2", "Moyen"),
    ("Tests Feature pour Platform, Onboarding", "api/tests/Feature/Platform", "exists", "P1", "Moyen"),
    ("PHPStan via `phpstan-modules.neon`", "api/phpstan-modules.neon", "exists", "P2", "Moyen"),
    ("OpenAPI canonique", "api/openapi.yaml", "exists", "P2", "Faible"),
    ("i18n backend via `lang/`", "api/lang/fr", "exists", "P3", "Moyen"),
]


def module_layers(api_root: Path, module: str) -> list[bool]:
    base = api_root / "app" / "Modules" / module
    result = []
    for _label, rel in LAYERS:
        target = base / rel
        result.append(target.is_dir() and any(target.rglob("*.php")))
    return result


def module_has_tests(api_root: Path, module: str) -> bool:
    for kind in ("Feature", "Unit"):
        target = api_root / "tests" / kind / module
        if target.is_dir() and any(target.rglob("*Test.php")):
            return True
    return False


def measure(api_root: Path) -> dict:
    modules_dir = api_root / "app" / "Modules"
    if not modules_dir.is_dir():
        raise FileNotFoundError(modules_dir)

    modules = sorted(d.name for d in modules_dir.iterdir() if d.is_dir())

    rows = []
    for module in modules:
        rows.append(
            {
                "name": module,
                "layers": module_layers(api_root, module),
                "tests": module_has_tests(api_root, module),
            }
        )

    return {
        "modules": modules,
        "rows": rows,
        "no_tests": [r["name"] for r in rows if not r["tests"]],
        "no_application": [r["name"] for r in rows if not r["layers"][3]],
        "total_tests": sum(1 for _ in (api_root / "tests").rglob("*Test.php")),
    }


def render_s1(data: dict) -> str:
    begin, end = ZONES["s1"]
    out: list[str] = [begin, ""]
    out.append(f"## 1. Tableau de l'etat DDD — {len(data['modules'])} modules actifs")
    out.append("")
    out.append("| Module | " + " | ".join(label for label, _ in LAYERS) + " | Tests |")
    out.append("|---|" + ":---:|" * (len(LAYERS) + 1))
    for row in data["rows"]:
        cells = " | ".join("✅" if c else "—" for c in row["layers"])
        tests = "✅" if row["tests"] else "⚠️"
        out.append(f"| **{row['name']}** | {cells} | {tests} |")
    out.append("")
    out.append(
        "> Tableau **regenere depuis le disque** par "
        "`dev-hub/tools/generate-architecture-status.py` (issue #9801). "
        "Ne pas editer a la main : la CI echoue si le fichier diverge du disque."
    )
    out.append(">")
    out.append(
        "> ✅ = au moins un fichier PHP dans la couche ; — = couche absente/vide ; "
        "⚠️ = aucun dossier de tests dedie sous `api/tests/{Feature,Unit}/`."
    )
    out.append(">")
    out.append(
        "> Chemins mesures : `Domain/`, `Domain/Contracts/`, `Domain/Exceptions/`, "
        "`Application/`, `Application/DTOs/`, `Infrastructure/`, `Interfaces/`, `Providers/`."
    )
    if DECIDED_ABSENCES:
        out.append(">")
        out.append("> Absences **decidees** (pas des retards) :")
        for module, reason in sorted(DECIDED_ABSENCES.items()):
            out.append(f"> - `{module}` — {reason}.")
    out.append("")
    out.append(end)
    return "\n".join(out)


def render_s3(data: dict) -> str:
    begin, end = ZONES["s3"]
    out: list[str] = [begin, ""]
    out.append("## 3. Metriques — etat courant mesure")
    out.append("")
    out.append("| Indicateur | Valeur |")
    out.append("|---|---|")
    out.append(f"| Modules DDD actifs | **{len(data['modules'])}** |")
    out.append(f"| Fichiers de test sur disque | {data['total_tests']} |")
    out.append(
        f"| Modules sans dossier de tests dedie | {len(data['no_tests'])} "
        f"({', '.join('`' + m + '`' for m in data['no_tests']) or '—'}) |"
    )
    out.append(
        f"| Modules sans couche `Application` | {len(data['no_application'])} "
        f"({', '.join('`' + m + '`' for m in data['no_application']) or '—'}) |"
    )
    out.append("")
    out.append(
        "> L'historique par phase (Phase 1→4, chantier clos 2026-07) est conserve "
        "ci-dessous a titre d'archive. Il ne decrit **pas** l'etat courant : le lire "
        "comme tel etait la source des trois comptages contradictoires releves par "
        "l'audit du 2026-11-22."
    )
    out.append("")
    out.append(end)
    return "\n".join(out)


def render_s5(data: dict, repo_root: Path) -> str:
    begin, end = ZONES["s5"]
    out: list[str] = [begin, ""]
    out.append("## 5. Roadmap restante — chaque ligne re-verifiee sur le disque")
    out.append("")
    out.append("| Item | Priorite | Effort | Statut |")
    out.append("|---|:---:|:---:|---|")
    for label, rel, sense, priority, effort in ROADMAP:
        exists = (repo_root / rel).exists()
        done = exists if sense == "exists" else not exists
        status = "✅ Fait" if done else "⏳ A faire"
        out.append(f"| {label} | {priority} | {effort} | {status} |")
    out.append("")
    out.append(
        "> Statuts **mesures** : `✅ Fait` signifie que le chemin attendu existe "
        "(ou a bien disparu, pour les suppressions). Une ligne « Fait » dont le "
        "chemin est absent est un faux positif — c'est ce que la garde empeche."
    )
    out.append("")
    out.append(end)
    return "\n".join(out)


def apply_zone(text: str, key: str, generated: str) -> str:
    begin, end = ZONES[key]
    start = text.find(begin)
    stop = text.find(end)

    if start == -1 or stop == -1 or stop < start:
        raise ValueError(f"Marqueurs {key} absents ou desordonnes")

    stop += len(end)
    return text[:start] + generated + text[stop:]


def regenerate(doc_text: str, data: dict, repo_root: Path) -> str:
    text = apply_zone(doc_text, "s1", render_s1(data))
    text = apply_zone(text, "s3", render_s3(data))
    text = apply_zone(text, "s5", render_s5(data, repo_root))
    return text


def self_test() -> int:
    """Prouve que la mesure distingue une couche presente d'une couche absente."""
    failures = 0

    with tempfile.TemporaryDirectory() as tmp:
        api = Path(tmp) / "api"
        mod = api / "app" / "Modules" / "Demo"
        (mod / "Domain" / "Contracts").mkdir(parents=True)
        (mod / "Domain" / "Contracts" / "X.php").write_text("<?php\n")
        (mod / "Application" / "DTOs").mkdir(parents=True)
        (mod / "Application" / "DTOs" / "Y.php").write_text("<?php\n")
        (api / "tests" / "Feature" / "Demo").mkdir(parents=True)
        (api / "tests" / "Feature" / "Demo" / "DemoTest.php").write_text("<?php\n")

        data = measure(api)
        layers = data["rows"][0]["layers"]

        checks = [
            ("Domain detecte", layers[0] is True),
            ("Contracts sous Domain/ detecte", layers[1] is True),
            ("Exceptions absent", layers[2] is False),
            ("Application detecte", layers[3] is True),
            ("DTOs sous Application/ detecte", layers[4] is True),
            ("Infrastructure absent", layers[5] is False),
            ("tests dedies detectes", data["rows"][0]["tests"] is True),
            ("module sans tests non liste", data["no_tests"] == []),
        ]

        for name, ok in checks:
            print(f"  [{'OK  ' if ok else 'FAIL'}] {name}")
            if not ok:
                failures += 1

    # Les trois zones doivent etre remplacees independamment, et le texte hors
    # zone doit survivre a l'operation.
    doc = (
        "AVANT\n"
        + ZONES["s1"][0] + "\nVIEUX S1\n" + ZONES["s1"][1] + "\n"
        + "MILIEU\n"
        + ZONES["s3"][0] + "\nVIEUX S3\n" + ZONES["s3"][1] + "\n"
        + ZONES["s5"][0] + "\nVIEUX S5\n" + ZONES["s5"][1] + "\n"
        + "APRES\n"
    )
    fake = {
        "modules": ["Demo"],
        "rows": [{"name": "Demo", "layers": [True] * 8, "tests": True}],
        "no_tests": [],
        "no_application": [],
        "total_tests": 1,
    }
    result = regenerate(doc, fake, Path("/nonexistent"))
    zone_checks = [
        ("texte avant conserve", result.startswith("AVANT\n")),
        ("texte milieu conserve", "MILIEU" in result),
        ("texte apres conserve", result.rstrip().endswith("APRES")),
        ("S1 remplace", "VIEUX S1" not in result),
        ("S3 remplace", "VIEUX S3" not in result),
        ("S5 remplace", "VIEUX S5" not in result),
    ]
    for name, ok in zone_checks:
        print(f"  [{'OK  ' if ok else 'FAIL'}] {name}")
        if not ok:
            failures += 1

    total = 8 + len(zone_checks)
    if failures:
        print(f"\nAUTOTEST ECHEC — {failures} cas sur {total}.", file=sys.stderr)
        return 1
    print(f"\nAUTOTEST OK — {total} cas.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--doc", default=str(DEFAULT_DOC))
    parser.add_argument("--api-root", default=str(API_ROOT))
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    doc_path = Path(args.doc)
    api_root = Path(args.api_root)

    if not doc_path.is_file():
        print(f"ERREUR — document introuvable : {doc_path}", file=sys.stderr)
        return 2

    try:
        data = measure(api_root)
        current = doc_path.read_text(encoding="utf-8")
        updated = regenerate(current, data, REPO_ROOT)
    except (FileNotFoundError, ValueError) as exc:
        print(f"ERREUR — {exc}", file=sys.stderr)
        return 2

    if args.check:
        if current == updated:
            print(
                f"OK : {doc_path.name} est a jour "
                f"({len(data['modules'])} modules, {data['total_tests']} fichiers de test)."
            )
            return 0
        print(
            f"ECART : {doc_path.name} diverge du disque.\n"
            f"Regenerer : python3 dev-hub/tools/generate-architecture-status.py",
            file=sys.stderr,
        )
        return 1

    if current != updated:
        doc_path.write_text(updated, encoding="utf-8")

    print(f"OK : {doc_path.name} regenere.")
    print(f"  Modules            : {len(data['modules'])}")
    print(f"  Fichiers de test   : {data['total_tests']}")
    print(f"  Modules sans tests : {len(data['no_tests'])}")
    print(f"  Sans Application   : {len(data['no_application'])}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
