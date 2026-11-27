#!/usr/bin/env python3
"""Exécute un ÉCHANTILLON de tests par cas d'usage (budget de temps borné).

Contrainte mesurée : la suite Feature complète compte 9 655 tests et tourne à
~0,8 test/s sur ce sandbox (1 vCPU) — soit ~5 h, incompatible avec la fenêtre
d'exécution. On exécute donc, pour chaque cas d'usage, un échantillon borné de
ses fichiers de tests (les 3 premiers, plafonnés à 90 s), ce qui prouve que la
chaîne de test du module s'exécute réellement sur une base PostgreSQL/PostGIS
réelle, sans prétendre couvrir 100 % des assertions.

Sortie : _tests_runtime.json
"""
import json
import os
import subprocess
import xml.etree.ElementTree as ET
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

HERE = Path(__file__).resolve().parent
API = Path("/workspace/leopardo-hr/api")
OUT = HERE / "_tests_runtime.json"
WORKERS = 2
MAX_FILES = 3
PER_UC_TIMEOUT = 90


def run_uc(uc, worker):
    uc_id = uc["id"]
    files = [f for f in (uc.get("tests") or []) if (API / f).exists()][:MAX_FILES]
    if not files:
        return uc_id, {"files": 0, "tests": 0, "failures": 0, "errors": 0,
                       "skipped": 0, "status": "no-tests", "detail": []}
    junit = Path("/tmp") / ("junit_%s.xml" % uc_id)
    if junit.exists():
        junit.unlink()
    env = dict(os.environ)
    env["DB_DATABASE"] = "leopardo_test_w%d" % worker
    env["APP_ENV"] = "testing"
    cmd = [str(API / "vendor/bin/phpunit"), "--no-coverage",
           "--log-junit", str(junit)] + files
    timed_out = False
    try:
        subprocess.run(cmd, cwd=str(API), env=env, timeout=PER_UC_TIMEOUT,
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except subprocess.TimeoutExpired:
        timed_out = True
    if not junit.exists():
        return uc_id, {"files": len(files), "tests": 0, "failures": 0, "errors": 0,
                       "skipped": 0, "status": "timeout" if timed_out else "no-report",
                       "detail": []}
    try:
        root = ET.parse(junit).getroot()
    except Exception:  # noqa: BLE001
        return uc_id, {"files": len(files), "tests": 0, "failures": 0, "errors": 0,
                       "skipped": 0, "status": "bad-report", "detail": []}
    tests = failures = errors = skipped = 0
    detail = []
    for tc in root.iter("testcase"):
        tests += 1
        bad = None
        for child in tc:
            if child.tag == "failure":
                failures += 1
                bad = "failure"
            elif child.tag == "error":
                errors += 1
                bad = "error"
            elif child.tag == "skipped":
                skipped += 1
                bad = "skipped"
        if bad in ("failure", "error"):
            detail.append({"test": "%s::%s" % (tc.get("class") or "", tc.get("name") or ""),
                           "kind": bad})
    status = "pass" if (failures == 0 and errors == 0 and tests > 0) else (
        "fail" if tests > 0 else "empty")
    return uc_id, {"files": len(files), "tests": tests, "failures": failures,
                   "errors": errors, "skipped": skipped, "status": status,
                   "detail": detail[:8]}


def main():
    ev = json.loads((HERE / "_evidence.json").read_text(encoding="utf-8"))
    results = {}
    buckets = [[] for _ in range(WORKERS)]
    for i, uc in enumerate(ev):
        buckets[i % WORKERS].append(uc)

    def work(bucket, worker):
        out = {}
        for uc in bucket:
            uc_id, res = run_uc(uc, worker)
            out[uc_id] = res
            print("%s %s tests=%d fail=%d err=%d" % (
                uc_id, res["status"], res["tests"], res["failures"], res["errors"]), flush=True)
        return out

    with ThreadPoolExecutor(max_workers=WORKERS) as ex:
        futs = [ex.submit(work, b, i + 1) for i, b in enumerate(buckets)]
        for f in futs:
            results.update(f.result())

    OUT.write_text(json.dumps(results, indent=1, ensure_ascii=False), encoding="utf-8")
    print("écrit", OUT)


if __name__ == "__main__":
    main()
