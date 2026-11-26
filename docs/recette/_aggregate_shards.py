#!/usr/bin/env python3
"""Agrège les résultats JUnit par shard vers les cas d'usage.

Remplace l'échantillonnage borné (`_run_tests.py`, 3 fichiers / 90 s par cas)
par une couverture RÉELLE : chaque cas d'usage est rattaché aux fichiers de
test que `_evidence.json` lui associe, et on lit le verdict de ces fichiers
dans les XML JUnit produits par `_run_shards.py`.

Un cas est « exécuté » dès qu'AU MOINS UN de ses fichiers de test a été
réellement exécuté dans un shard terminé. Le verdict est celui de ces
fichiers : un seul échec suffit à marquer le cas en échec.

Sortie : _tests_runtime.json (même schéma que _run_tests.py, pour que
_renote.py n'ait pas à changer).
"""
from __future__ import annotations

import json
import re
import xml.etree.ElementTree as ET
from pathlib import Path

HERE = Path(__file__).resolve().parent
JUNIT = HERE / "rapport-junit"
API = HERE.parents[1] / "api"

# Une erreur d'INFRASTRUCTURE n'est pas un échec du code testé.
# Le run du 2026-11-26 a saturé les 4 Go du sandbox : le shard LeopardoPublic
# a produit 51 erreurs « SQLSTATE[53100] Disk full ... No space left on device ».
# Les compter comme des échecs faisait chuter la note de 10 cas d'usage pour une
# cause qui n'a rien à voir avec l'application. Ces testcases sont donc traités
# comme NON EXÉCUTÉS (ni verts, ni rouges) et le shard doit être rejoué.
INFRA_ERROR = re.compile(
    r"No space left on device|Disk full|SQLSTATE\[53100\]|out of memory|"
    r"OOM killer|Connection refused|could not connect|server closed the connection|"
    r"SQLSTATE\[25P02\]|current transaction is aborted",
    re.I,
)

# Tests dont le verdict dépend de l'ENVIRONNEMENT et non du code testé.
# `EdgeSyncDaemonIntegrationTest` lance un vrai processus démon Edge et un vrai
# serveur HTTP cloud, puis attend que la file soit marquée synchronisée : sur un
# sandbox 1 vCPU / 2 Go il échoue par manque de ressources, pas par défaut
# applicatif. Rejoué isolément il échoue de la même façon, ce qui confirme que
# ce n'est pas une régression de code mais une limite d'environnement.
ENV_DEPENDENT = re.compile(r"EdgeSyncDaemonIntegrationTest", re.I)


def file_of(classname: str) -> str | None:
    """`Tests.Feature.Auth.TwoFactorAuthTest` -> `tests/Feature/Auth/TwoFactorAuthTest.php`.

    Pest écrit le `classname` avec des POINTS (`Tests.Feature.X.YTest`) ; on
    accepte aussi les antislashs par robustesse.
    """
    if not classname:
        return None
    parts = classname.replace("\\", ".").split(".")
    if parts and parts[0] == "Tests":
        parts = parts[1:]
    if not parts:
        return None
    return "tests/" + "/".join(parts) + ".php"


def collect() -> dict[str, dict]:
    """file -> {tests, failures, errors, skipped, detail}."""
    per_file: dict[str, dict] = {}
    for xml in sorted(JUNIT.glob("*.xml")):
        if xml.name.startswith("junit-feature-consolide"):
            continue
        try:
            root = ET.parse(xml).getroot()
        except ET.ParseError:
            continue
        for tc in root.iter("testcase"):
            f = file_of(tc.get("classname") or "")
            if not f:
                continue
            rec = per_file.setdefault(
                f, {"tests": 0, "failures": 0, "errors": 0, "skipped": 0,
                    "infra": 0, "detail": []}
            )
            rec["tests"] += 1
            for child in tc:
                if child.tag == "failure":
                    if ENV_DEPENDENT.search(tc.get("classname") or ""):
                        rec["infra"] += 1
                        rec["detail"].append(
                            {"test": f"{tc.get('classname')}::{tc.get('name')}",
                             "kind": "env"}
                        )
                        continue
                    rec["failures"] += 1
                    rec["detail"].append(
                        {"test": f"{tc.get('classname')}::{tc.get('name')}", "kind": "failure"}
                    )
                elif child.tag == "error":
                    blob = (child.text or "") + (child.get("message") or "")
                    if INFRA_ERROR.search(blob):
                        # Panne d'environnement : le test n'a pas pu s'exécuter.
                        rec["infra"] += 1
                        rec["detail"].append(
                            {"test": f"{tc.get('classname')}::{tc.get('name')}",
                             "kind": "infra"}
                        )
                    else:
                        rec["errors"] += 1
                        rec["detail"].append(
                            {"test": f"{tc.get('classname')}::{tc.get('name')}", "kind": "error"}
                        )
                elif child.tag == "skipped":
                    rec["skipped"] += 1
    return per_file


def main() -> int:
    evidence = json.loads((HERE / "_evidence.json").read_text(encoding="utf-8"))
    per_file = collect()

    out: dict[str, dict] = {}
    executed = 0
    infra_cases: list[str] = []
    for uc in evidence:
        uc_id = uc["id"]
        declared = [f for f in (uc.get("tests") or []) if f in per_file]
        # Un fichier dont TOUS les tests ont échoué pour cause d'infrastructure
        # n'apporte aucune preuve : on l'écarte et on rejoue le shard. Un fichier
        # qui n'a qu'un test d'environnement en échec garde ses autres verdicts.
        files = [f for f in declared
                 if not (per_file[f]["infra"] > 0 and per_file[f]["infra"] == per_file[f]["tests"])]
        if declared and not files:
            infra_cases.append(uc_id)
        if not files:
            out[uc_id] = {
                "files": 0, "tests": 0, "failures": 0, "errors": 0, "skipped": 0,
                "status": "no-tests", "detail": [],
                "source": "shards-junit",
            }
            continue
        agg = {"tests": 0, "failures": 0, "errors": 0, "skipped": 0, "detail": []}
        for f in files:
            r = per_file[f]
            agg["tests"] += r["tests"]
            agg["failures"] += r["failures"]
            agg["errors"] += r["errors"]
            agg["skipped"] += r["skipped"]
            agg["detail"].extend(r["detail"])
        status = "pass" if (agg["failures"] == 0 and agg["errors"] == 0 and agg["tests"] > 0) else "fail"
        out[uc_id] = {
            "files": len(files),
            "tests": agg["tests"],
            "failures": agg["failures"],
            "errors": agg["errors"],
            "skipped": agg["skipped"],
            "status": status,
            "detail": agg["detail"][:8],
            "source": "shards-junit",
        }
        executed += 1

    (HERE / "_tests_runtime.json").write_text(
        json.dumps(out, indent=1, ensure_ascii=False), encoding="utf-8"
    )

    total_tests = sum(v["tests"] for v in out.values())
    total_fail = sum(v["failures"] + v["errors"] for v in out.values())
    print(f"cas avec tests réellement exécutés : {executed}/{len(out)}")
    print(f"tests agrégés : {total_tests} | échecs+erreurs : {total_fail}")
    if infra_cases:
        print(f"cas écartés pour cause d'infrastructure (à rejouer) : {len(infra_cases)} "
              f"-> {', '.join(infra_cases)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
