#!/usr/bin/env python3
"""Consolide les XML JUnit par shard en un rapport unique.

Déclare EXPLICITEMENT les shards non terminés (testsuite `SHARDS_NON_TERMINES`)
plutôt que de les omettre : un rapport qui ne dit pas ce qui manque est un
rapport faux.

Usage: python3 docs/recette/_consolidate_junit.py
"""
from __future__ import annotations

import re
import xml.etree.ElementTree as ET
from pathlib import Path

HERE = Path(__file__).resolve().parent
OUT = HERE / "rapport-junit"
API = HERE.parents[1] / "api"
CONSOLIDATED = OUT / "junit-feature-consolide.xml"

SKIP = {"Unit", "Arch", "Feature"}


def all_shards() -> list[str]:
    xml = (API / "phpunit.xml").read_text(encoding="utf-8")
    names = re.findall(r'<testsuite name="([^"]+)"', xml)
    return [n for n in names if n not in SKIP]


def main() -> int:
    shards = all_shards()
    root = ET.Element("testsuites", {"name": "Feature (consolidé)"})

    total = failures = errors = skipped = 0
    done: list[str] = []
    missing: list[str] = []

    for name in shards:
        p = OUT / f"{name}.xml"
        if not p.exists() or p.stat().st_size < 200:
            missing.append(name)
            continue
        try:
            tree = ET.parse(p)
        except ET.ParseError:
            missing.append(name)
            continue
        suites = tree.getroot().findall(".//testsuite")
        if not suites:
            missing.append(name)
            continue

        t = f = e = s = 0
        for su in suites:
            t += int(su.get("tests", 0) or 0)
            f += int(su.get("failures", 0) or 0)
            e += int(su.get("errors", 0) or 0)
            s += int(su.get("skipped", 0) or 0)

        ET.SubElement(
            root,
            "testsuite",
            {
                "name": name,
                "tests": str(t),
                "failures": str(f),
                "errors": str(e),
                "skipped": str(s),
            },
        )
        total += t
        failures += f
        errors += e
        skipped += s
        done.append(name)

    if missing:
        ET.SubElement(
            root,
            "testsuite",
            {
                "name": "SHARDS_NON_TERMINES",
                "tests": "0",
                "failures": "0",
                "errors": "0",
                "skipped": "0",
                "shards": ",".join(missing),
                "nombre": str(len(missing)),
            },
        )

    root.set("tests", str(total))
    root.set("failures", str(failures))
    root.set("errors", str(errors))
    root.set("skipped", str(skipped))
    root.set("shards_termines", str(len(done)))
    root.set("shards_total", str(len(shards)))

    ET.indent(root, space="  ")
    ET.ElementTree(root).write(CONSOLIDATED, encoding="utf-8", xml_declaration=True)

    print(f"shards terminés : {len(done)}/{len(shards)}")
    print(f"tests={total} failures={failures} errors={errors} skipped={skipped}")
    if missing:
        print(f"non terminés ({len(missing)}) : {', '.join(missing[:12])}"
              + (" …" if len(missing) > 12 else ""))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
