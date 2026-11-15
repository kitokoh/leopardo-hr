#!/usr/bin/env python3
"""Genere deux testsuites Feature : classes MVP-fixture d'abord, les autres ensuite.

POURQUOI
--------
Mesure du 2026-11-14 (artefact `backend-test-reports` du run 43570213398,
`feature.xml`, 9137 testcases) :

  - 92 tests depassent 60 s et pesent 19 751 s sur 23 938 s de temps total (82 %) ;
  - 88 de ces 92 tests sont le PREMIER test de leur classe ;
  - les 89 classes lentes utilisent TOUTES le trait `CreatesMvpSchema`.

Autrement dit le cout n'est pas dans les tests : c'est la reconstruction de la
fixture MVP (~215 s de DDL), rejouee une fois par classe lente. Le marqueur
`shared_tenants.__mvp_fixture_ready` est detruit par les classes canoniques
(`RefreshTenantDatabase` fait `DROP SCHEMA shared_tenants CASCADE`), donc chaque
fois qu'une classe MVP suit une classe canonique, elle repaie la fixture.

Paratest distribue les fichiers par taille : les deux familles s'entrelacent,
d'ou 89 reconstructions. En lancant les classes MVP dans une invocation
SEPAREE, aucune classe canonique ne tombe entre elles : la fixture est
construite une fois par worker (4), puis reutilisee.

CE SCRIPT
---------
Ecrit deux testsuites `<file>` explicites dans `api/phpunit.xml` (entre ses
propres marqueurs BEGIN/END) :

  - `FeatureMvpFixture` : fichiers de `tests/Feature` qui utilisent le trait ;
  - `FeatureRest`       : tous les autres.

Invariants verifies par `--check` (et par la garde CI) :
  1. chaque fichier `*Test.php` de `tests/Feature` apparait EXACTEMENT une fois ;
  2. aucun fichier n'est perdu : c'est le seul risque reel de ce decoupage.

`phpunit.xml` hors zone n'est jamais touche (idempotent, comme
`dev-tools/generate-feature-shards.php`).

Usage :
    python3 dev-hub/tools/generate-feature-mvp-split.py            # ecrit
    python3 dev-hub/tools/generate-feature-mvp-split.py --check    # ne ecrit pas
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

BEGIN = "        <!-- BEGIN GENERATED FEATURE MVP SPLIT -->"
END = "        <!-- END GENERATED FEATURE MVP SPLIT -->"

# `use CreatesMvpSchema;`, `use Tests\Support\CreatesMvpSchema;` et la forme
# groupee `use CreatesMvpSchema, OtherTrait;` doivent toutes matcher. On ancre
# sur le mot-cle `use` en debut de ligne pour ne PAS attraper une simple
# mention dans un commentaire ou un docblock.
TRAIT_USE = re.compile(r"^use\s+[^;]*\bCreatesMvpSchema\b[^;]*;", re.MULTILINE)


def classer(feature_dir: Path) -> tuple[list[str], list[str]]:
    mvp: list[str] = []
    rest: list[str] = []
    for path in sorted(feature_dir.rglob("*Test.php")):
        src = path.read_text(encoding="utf-8", errors="replace")
        rel = path.relative_to(feature_dir.parent.parent).as_posix()
        (mvp if TRAIT_USE.search(src) else rest).append(rel)
    return mvp, rest


def zone(mvp: list[str], rest: list[str]) -> str:
    out = [BEGIN]
    out.append("        <!--")
    out.append("          Zone GENEREE (issue #9098) - ne pas editer a la main.")
    out.append("          Regenerer : python3 dev-hub/tools/generate-feature-mvp-split.py")
    out.append("")
    out.append("          Les classes qui construisent la fixture MVP (`CreatesMvpSchema`)")
    out.append("          tournent dans leur PROPRE invocation : sinon chaque classe MVP")
    out.append("          qui suit une classe canonique repaie ~215 s de DDL, parce que")
    out.append("          `RefreshTenantDatabase` fait `DROP SCHEMA shared_tenants CASCADE`")
    out.append("          et detruit le marqueur `__mvp_fixture_ready`.")
    out.append("")
    out.append(f"          FeatureMvpFixture : {len(mvp)} fichiers.")
    out.append(f"          FeatureRest       : {len(rest)} fichiers.")
    out.append("        -->")
    for name, files in (("FeatureMvpFixture", mvp), ("FeatureRest", rest)):
        out.append(f'        <testsuite name="{name}">')
        for rel in files:
            out.append(f"            <file>{rel}</file>")
        out.append("        </testsuite>")
    out.append(END)
    return "\n".join(out)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--api-dir", default="api")
    parser.add_argument("--check", action="store_true", help="ne rien ecrire, echouer si la zone est perimee")
    args = parser.parse_args()

    api = Path(args.api_dir)
    feature_dir = api / "tests" / "Feature"
    config = api / "phpunit.xml"
    if not feature_dir.is_dir():
        print(f"KO: {feature_dir} introuvable", file=sys.stderr)
        return 2
    if not config.is_file():
        print(f"KO: {config} introuvable", file=sys.stderr)
        return 2

    mvp, rest = classer(feature_dir)

    total = len(mvp) + len(rest)
    dupes = set(mvp) & set(rest)
    if dupes:
        print(f"KO: {len(dupes)} fichier(s) dans les deux suites (ex. {sorted(dupes)[0]})", file=sys.stderr)
        return 1
    if total == 0:
        print("KO: aucun fichier de test trouve (fail-closed)", file=sys.stderr)
        return 1

    # Verification statique des chemins : une testsuite `<file>` qui pointe un
    # chemin inexistant ne rougit pas, elle ne selectionne simplement rien.
    # C'est le second risque silencieux d'un decoupage, apres le fichier oublie.
    manquants = [rel for rel in (*mvp, *rest) if not (api / rel).is_file()]
    if manquants:
        print(
            f"KO: {len(manquants)} chemin(s) de test inexistant(s) (ex. {manquants[0]})",
            file=sys.stderr,
        )
        return 1

    nouvelle = zone(mvp, rest)
    actuel = config.read_text(encoding="utf-8")
    if BEGIN in actuel and END in actuel:
        avant = actuel.split(BEGIN, 1)[0]
        apres = actuel.split(END, 1)[1]
        attendu = avant + nouvelle + apres
    else:
        if "</testsuites>" not in actuel:
            print("KO: </testsuites> introuvable dans phpunit.xml", file=sys.stderr)
            return 1
        attendu = actuel.replace("    </testsuites>", nouvelle + "\n    </testsuites>", 1)

    if args.check:
        if attendu != actuel:
            print(
                "KO: la zone FeatureMvpFixture/FeatureRest est perimee "
                "(regenerer avec dev-hub/tools/generate-feature-mvp-split.py)",
                file=sys.stderr,
            )
            return 1
        print(f"OK: zone a jour ({len(mvp)} MVP, {len(rest)} canoniques, {total} fichiers).")
        return 0

    if attendu != actuel:
        config.write_text(attendu, encoding="utf-8", newline="")
    print(f"OK: {len(mvp)} fichiers MVP-fixture, {len(rest)} canoniques ({total} au total).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
