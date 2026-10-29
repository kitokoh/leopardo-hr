#!/usr/bin/env python3
"""Triage des échecs backend à partir d'un artefact JUnit PHPUnit (#8396).

Une suite de tests ne dit presque jamais « N bugs » : elle dit « N échecs ».
Sur une campagne comme #8396 (364 échecs au constat, 264 au triage du
2026-10-29) la seule façon honnête de découper le travail est de regrouper les
échecs par **cause commune**, pas par classe de test : sept échecs Marketing
avaient une seule cause (défaut SQL non hydraté), et 45 autres relevaient de
contrats d'API dérivés. Lire 264 messages d'assertion ne le montre pas ; le
graphe d'appel, si.

Ce que l'outil fait :

  1. lit un JUnit XML PHPUnit (fichier ou dossier, récursivement) ;
  2. pour chaque `<failure>` / `<error>`, extrait la **première frame projet**
     de la pile (`tests/`, `app/`, `database/`, `routes/`, `config/`) — c'est
     elle qui dit OÙ le contrat a cassé, alors que la dernière frame dit
     seulement où l'assertion a échoué (souvent dans le vendor) ;
  3. construit une signature `(module, classe d'exception, message, frame)` et
     compte les échecs par signature ;
  4. imprime un tableau Markdown classé par volume, prêt à coller dans une
     issue ou dans le résumé d'un job GitHub Actions.

Découpage du module : `app/Modules/<Module>` ou `tests/Feature/<Module>` ; le
socle (`app/Core`, `app/Shared`, `tests/Unit`) remonte en `Core`/`Shared`/`Unit`.
Un échec dont la pile ne porte aucune frame projet (erreur de boot, exception
levée dans le vendor) est classé `(aucune frame projet)` : c'est un signal en
soi, pas un trou qu'on bouche par défaut.

Ce que l'outil NE fait PAS, volontairement : il ne dit pas si un échec est une
régression ou une dette préexistante. Cette qualification exige un stash du
diff et un rejeu (voir #8035) — un outil qui devinerait produirait des « c'est
preexistant » faux, plus coûteux que pas de réponse.

Usage :
    python3 dev-hub/tools/triage-junit.py api/storage/test-results
    python3 dev-hub/tools/triage-junit.py feature.xml unit.xml --format json
    python3 dev-hub/tools/triage-junit.py results/ --module Marketing --list-tests
    python3 dev-hub/tools/triage-junit.py results/ --output triage.md

Options :
    --format {markdown,text,json}  rendu (défaut : markdown)
    --top N                        ne détaille que les N premiers clusters (défaut : 40)
    --module NOM                  ne garde que les échecs de ce module (insensible à la casse)
    --list-tests                  liste les tests de chaque cluster affiché
    --min-count N                 masque les clusters sous N échecs
    --output FICHIER              écrit le rendu dans un fichier au lieu de stdout
    --fail-over N                 code de sortie 1 si le total dépasse N (garde optionnelle)

Sortie JSON : { "totals": {...}, "clusters": [ { signature, count, tests } ] }
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import xml.etree.ElementTree as ET
from dataclasses import dataclass, field
from pathlib import Path

# Chemins qui désignent du code du dépôt (et non le vendor) : la première
# frame rencontrée dans cet ordre est celle qu'on cite.
PROJECT_PREFIXES = (
    "tests/",
    "app/",
    "database/",
    "routes/",
    "config/",
    "bootstrap/",
)

# Bruit à normaliser dans un message avant de s'en servir comme signature :
# UUID, hash long, chemins absolus de CI, temporaires d'exécution.
_NOISE_PATTERNS = (
    (re.compile(r"[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"), "<uuid>"),
    (re.compile(r"\b[0-9a-f]{32,}\b"), "<hash>"),
    (re.compile(r"/home/runner/work/[^\s\"']+"), "<ci-path>"),
    (re.compile(r"/tmp/[A-Za-z0-9_./-]+"), "<tmp>"),
    (re.compile(r"\s+"), " "),
)

# Le `/` final est essentiel : sans lui, un test isolé de premier niveau
# (`tests/Feature/HealthEndpointTest.php`) serait pris pour un module nommé
# « HealthEndpointTest » et le tri par module deviendrait illisible.
_MODULE_FROM_APP = re.compile(r"app/Modules/([A-Za-z0-9_]+)/")
_MODULE_FROM_TEST = re.compile(r"tests/(?:Feature|Unit)/([A-Za-z0-9_]+)/")
_CORE_FROM_APP = re.compile(r"app/(Core|Shared)/([A-Za-z0-9_]+)")


@dataclass
class Failure:
    """Un échec isolé, réduit à ce qui permet de le regrouper."""

    test: str
    class_name: str
    exception: str
    message: str
    location: str
    module: str


@dataclass
class Cluster:
    """Un ensemble d'échecs partageant la même signature."""

    module: str
    exception: str
    message: str
    location: str
    tests: list[str] = field(default_factory=list)

    @property
    def count(self) -> int:
        return len(self.tests)

    def as_dict(self) -> dict[str, object]:
        return {
            "module": self.module,
            "exception": self.exception,
            "message": self.message,
            "location": self.location,
            "count": self.count,
            "tests": self.tests,
        }


def normalize_message(message: str) -> str:
    """Réduit un message d'échec à sa partie stable (celle qui décrit la cause)."""
    text = message
    for pattern, replacement in _NOISE_PATTERNS:
        text = pattern.sub(replacement, text)
    return text.strip()[:300]


def module_of(location: str) -> str:
    """Déduit le module responsable d'une frame projet."""
    match = _MODULE_FROM_APP.search(location)
    if match:
        return match.group(1)

    match = _CORE_FROM_APP.search(location)
    if match:
        return f"{match.group(1)}/{match.group(2)}"

    match = _MODULE_FROM_TEST.search(location)
    if match:
        return match.group(1)

    if location.startswith("tests/Unit"):
        return "Unit"

    return "Racine"


def project_location(stack: str) -> str:
    """Première frame du dépôt dans la pile (`file:line`), sinon la dernière frame."""
    frames = [line.strip()[3:].strip() for line in stack.splitlines() if line.strip().startswith("at ")]
    for frame in frames:
        if frame.startswith(PROJECT_PREFIXES):
            return frame
    return frames[-1] if frames else "(aucune frame)"


def clean_message(raw: str, test_name: str) -> str:
    """PHPUnit préfixe le texte d'échec par le nom du test : on le retire.

    Sans ce nettoyage, chaque message serait unique (le nom du test s'y trouve)
    et aucun regroupement ne serait possible.
    """
    text = raw.strip()
    if text.startswith(test_name):
        text = text[len(test_name):]
    first_block = text.splitlines()[0] if text.splitlines() else ""
    return first_block.strip()


def parse_failure(testcase: ET.Element) -> Failure | None:
    """Extrait le `failure`/`error` d'un `<testcase>` (None si le test est vert)."""
    node = testcase.find("failure")
    if node is None:
        node = testcase.find("error")
    if node is None:
        return None

    raw = node.text or ""
    test_name = testcase.get("name") or "(test sans nom)"
    stack = "\n".join(line for line in raw.splitlines() if line.strip().startswith("at "))
    location = project_location(stack)

    return Failure(
        test=test_name,
        class_name=testcase.get("class") or testcase.get("classname") or "",
        exception=(node.get("type") or "Failure").split("\\")[-1],
        message=normalize_message(clean_message(raw, test_name)),
        location=location,
        module=module_of(location),
    )


def iter_xml(paths: list[str]) -> list[Path]:
    """Résout les fichiers XML d'entrée (fichier explicite ou dossier récursif)."""
    files: list[Path] = []
    for raw in paths:
        path = Path(raw)
        if path.is_dir():
            files.extend(sorted(path.rglob("*.xml")))
        elif path.is_file():
            files.append(path)
        else:
            raise SystemExit(f"triage-junit: entrée introuvable : {raw}")
    if not files:
        raise SystemExit("triage-junit: aucun fichier JUnit XML trouvé.")
    return files


def collect(files: list[Path]) -> tuple[int, int, list[Failure]]:
    """Lit les suites : (nombre de tests, nombre de suites, échecs).

    Les `<testsuite>` PHPUnit sont **imbriquées** : chaque niveau rédéclare un
    attribut `tests` qui couvre ses enfants. Additionner ces attributs triple le
    total (8 517 tests réels devenaient 21 349). On compte donc les `<testcase>`,
    qui n'apparaissent qu'une fois quel que soit l'imbrication.
    """
    total_tests = 0
    total_suites = 0
    failures: list[Failure] = []

    for path in files:
        try:
            root = ET.parse(path).getroot()
        except ET.ParseError as exc:
            print(f"triage-junit: XML illisible ignoré ({path}) : {exc}", file=sys.stderr)
            continue

        for testcase in root.iter("testcase"):
            total_tests += 1
            failure = parse_failure(testcase)
            if failure is not None:
                failures.append(failure)

        for suite in root.iter("testsuite"):
            if suite.find("testcase") is not None:
                total_suites += 1

    return total_tests, total_suites, failures


def clusterize(failures: list[Failure]) -> list[Cluster]:
    """Groupe les échecs par signature, du plus volumineux au plus petit."""
    buckets: dict[tuple[str, str, str, str], Cluster] = {}
    for failure in failures:
        key = (failure.module, failure.exception, failure.message, failure.location)
        cluster = buckets.get(key)
        if cluster is None:
            cluster = Cluster(
                module=failure.module,
                exception=failure.exception,
                message=failure.message,
                location=failure.location,
            )
            buckets[key] = cluster
        cluster.tests.append(f"{failure.class_name}::{failure.test}")

    return sorted(
        buckets.values(),
        key=lambda cluster: (-cluster.count, cluster.module, cluster.location, cluster.message),
    )


def module_summary(clusters: list[Cluster]) -> list[tuple[str, int, int]]:
    """(module, échecs, clusters) trié par volume décroissant."""
    totals: dict[str, tuple[int, int]] = {}
    for cluster in clusters:
        count, clusters_count = totals.get(cluster.module, (0, 0))
        totals[cluster.module] = (count + cluster.count, clusters_count + 1)
    return sorted(
        ((module, count, clusters_count) for module, (count, clusters_count) in totals.items()),
        key=lambda row: (-row[1], row[0]),
    )


def render_markdown(
    total_tests: int,
    total_suites: int,
    failures: list[Failure],
    clusters: list[Cluster],
    args: argparse.Namespace,
) -> str:
    lines: list[str] = []
    lines.append(f"## Triage JUnit — {len(failures)} échec(s) / {total_tests} test(s)")
    lines.append("")
    lines.append(
        f"Source : {total_suites} suite(s) JUnit · {len(clusters)} signature(s) distincte(s). "
        "Une signature = une cause probable : c'est l'unité de travail, pas le nombre de tests."
    )
    lines.append("")

    if not clusters:
        lines.append("Aucun échec : rien à trier.")
        return "\n".join(lines) + "\n"

    lines.append("### Échecs par module")
    lines.append("")
    lines.append("| Module | Échecs | Signatures |")
    lines.append("|---|---:|---:|")
    for module, count, clusters_count in module_summary(clusters):
        lines.append(f"| `{module}` | {count} | {clusters_count} |")
    lines.append("")

    lines.append(f"### Signatures classées (top {min(args.top, len(clusters))} sur {len(clusters)})")
    lines.append("")
    lines.append("| # | Module | Signature | Localisation | Échecs |")
    lines.append("|---:|---|---|---|---:|")
    for index, cluster in enumerate(clusters[: args.top], start=1):
        signature = f"`{cluster.exception}` — {cluster.message}".replace("|", "\\|")
        lines.append(
            f"| {index} | `{cluster.module}` | {signature} | `{cluster.location}` | {cluster.count} |"
        )
    lines.append("")

    if args.list_tests:
        lines.append("### Tests concernés")
        lines.append("")
        for cluster in clusters[: args.top]:
            lines.append(f"**`{cluster.location}`** — {cluster.message}")
            for test in cluster.tests:
                lines.append(f"- `{test}`")
            lines.append("")

    return "\n".join(lines) + "\n"


def render_text(
    total_tests: int,
    failures: list[Failure],
    clusters: list[Cluster],
    args: argparse.Namespace,
) -> str:
    lines: list[str] = [
        f"échecs={len(failures)} tests={total_tests} signatures={len(clusters)}",
        "",
    ]
    for index, cluster in enumerate(clusters[: args.top], start=1):
        lines.append(
            f"{index:>3}. [{cluster.count:>3}] {cluster.module} — {cluster.exception}: "
            f"{cluster.message}  ({cluster.location})"
        )
        if args.list_tests:
            for test in cluster.tests:
                lines.append(f"       - {test}")
    return "\n".join(lines) + "\n"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Trie des échecs backend à partir d'un JUnit XML PHPUnit (clusters par cause).",
    )
    parser.add_argument("paths", nargs="+", help="Fichier(s) XML ou dossier(s) de résultats")
    parser.add_argument("--format", choices=("markdown", "text", "json"), default="markdown")
    parser.add_argument("--top", type=int, default=40)
    parser.add_argument("--module", default=None)
    parser.add_argument("--list-tests", action="store_true")
    parser.add_argument("--min-count", type=int, default=1)
    parser.add_argument("--output", default=None)
    parser.add_argument("--fail-over", type=int, default=None)
    args = parser.parse_args(argv)

    # Windows ouvrait stdout en cp1252 dès que la sortie était redirigée : le
    # tableau partait en mojibake avant d'atteindre `$GITHUB_STEP_SUMMARY`.
    for stream in (sys.stdout, sys.stderr):
        reconfigure = getattr(stream, "reconfigure", None)
        if reconfigure is not None:
            reconfigure(encoding="utf-8")

    files = iter_xml(args.paths)
    total_tests, total_suites, failures = collect(files)

    if args.module:
        wanted = args.module.lower()
        failures = [failure for failure in failures if failure.module.lower() == wanted]

    clusters = [cluster for cluster in clusterize(failures) if cluster.count >= args.min_count]

    if args.format == "json":
        payload = {
            "totals": {
                "failures": len(failures),
                "tests": total_tests,
                "suites": total_suites,
                "signatures": len(clusters),
            },
            "clusters": [cluster.as_dict() for cluster in clusters[: args.top]],
        }
        rendered = json.dumps(payload, indent=2, ensure_ascii=False) + "\n"
    elif args.format == "text":
        rendered = render_text(total_tests, failures, clusters, args)
    else:
        rendered = render_markdown(total_tests, total_suites, failures, clusters, args)

    if args.output:
        Path(args.output).write_text(rendered, encoding="utf-8")
        print(f"triage-junit: {len(failures)} échec(s), {len(clusters)} signature(s) → {args.output}")
    else:
        sys.stdout.write(rendered)

    if args.fail_over is not None and len(failures) > args.fail_over:
        print(
            f"triage-junit: {len(failures)} échec(s) > seuil {args.fail_over}",
            file=sys.stderr,
        )
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
