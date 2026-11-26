#!/usr/bin/env python3
"""Reprise de l'échantillonnage : ne traite QUE les cas d'usage absents de
`_tests_runtime.json` (ou dont l'entrée n'a produit aucun test).

Même méthodologie que `_run_tests.py` (3 fichiers max par cas, 90 s par cas,
bases PostgreSQL dédiées par worker), mais :
  - les résultats déjà acquis sont conservés (aucun cas n'est rejoué) ;
  - la parallélisme est abaissé (2 workers) : le sandbox est limité à 2 Go par
    cgroup et un `-p 12` a déjà fait tuer le checkpointer PostgreSQL (OOM).

Sortie : `_tests_runtime.json` (fusion incrémentale, écrite après CHAQUE cas).
"""
import json
import os
import subprocess
import sys
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
    existing = {}
    if OUT.exists():
        existing = json.loads(OUT.read_text(encoding="utf-8"))

    todo = [uc for uc in ev
            if (existing.get(uc["id"]) or {}).get("tests", 0) == 0]
    print("déjà acquis : %d | à traiter : %d" % (len(existing), len(todo)), flush=True)

    buckets = [[] for _ in range(WORKERS)]
    for i, uc in enumerate(todo):
        buckets[i % WORKERS].append(uc)

    lock = __import__("threading").Lock()

    def work(bucket, worker):
        for uc in bucket:
            uc_id, res = run_uc(uc, worker)
            with lock:
                existing[uc_id] = res
                OUT.write_text(json.dumps(existing, indent=1, ensure_ascii=False),
                               encoding="utf-8")
            print("%s %s tests=%d fail=%d err=%d" % (
                uc_id, res["status"], res["tests"], res["failures"], res["errors"]),
                flush=True)

    with ThreadPoolExecutor(max_workers=WORKERS) as ex:
        futs = [ex.submit(work, b, i + 1) for i, b in enumerate(buckets)]
        for f in futs:
            f.result()

    print("écrit", OUT, "| cas:", len(existing), flush=True)


if __name__ == "__main__":
    main()
