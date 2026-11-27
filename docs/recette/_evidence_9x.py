#!/usr/bin/env python3
"""Complète les preuves des cas UC-90..UC-96 (ajoutés lors de la passe
d'exhaustivité et jusqu'ici notés « sur pièces »).

Ajoute à `_evidence.json` les fichiers de tests réellement présents, puis
exécute un échantillon et fusionne le résultat dans `_tests_runtime.json`.
Les sondes HTTP, elles, sont lues depuis `/tmp/probes90.json` et fusionnées
dans `_runtime.json`.
"""
import json
import os
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

HERE = Path(__file__).resolve().parent
API = Path("/workspace/leopardo-hr/api")

UC_TESTS = {
    "UC-90": ["tests/Feature/AI/AIActionProposalHttpTest.php",
              "tests/Feature/AI/AIWorkflowTest.php",
              "tests/Feature/AI/AIWriteActionConfirmationTest.php"],
    "UC-91": ["tests/Feature/Contracts/ContractLifecycleTest.php",
              "tests/Feature/Contracts/ContractTemplatesEndpointTest.php",
              "tests/Feature/Contracts/ExpiringContractsAlertTest.php"],
    "UC-92": ["tests/Feature/AuditLogExportTest.php",
              "tests/Feature/Audit/AuditLogTest.php"],
    "UC-93": ["tests/Feature/AttendanceMonthlyExportTest.php",
              "tests/Feature/BankExportControllerTest.php"],
    "UC-94": ["tests/Feature/Billing/PartnerApplyTest.php",
              "tests/Feature/GrowthPartnerTenantIsolationTest.php"],
    "UC-95": ["tests/Feature/EduManager/TrainingControllerTest.php",
              "tests/Feature/EduManager"],
    "UC-96": ["tests/Feature/Auth/TwoFactorAuthTest.php",
              "tests/Feature/Auth/LastAdministratorAndPlatformMfaTest.php"],
}

TITLES = {
    "UC-90": "Assistant IA — chat, proposition d'action et confirmation",
    "UC-91": "Contrats — modèle pays, signature et alerte d'expiration",
    "UC-92": "Audit — journal global, accès aux données sensibles et export",
    "UC-93": "Exports — employés, pointage, bulletins, absences et rapports RH",
    "UC-94": "Partenaires — candidature, taux de commission et reversement",
    "UC-95": "Formation — cours, sessions et inscriptions",
    "UC-96": "Sécurité — double authentification (2FA) et MFA plateforme",
}


def run(files):
    files = [f for f in files if (API / f).exists()][:3]
    if not files:
        return {"files": 0, "tests": 0, "failures": 0, "errors": 0, "skipped": 0,
                "status": "no-tests", "detail": []}
    junit = Path("/tmp/junit_uc9x.xml")
    if junit.exists():
        junit.unlink()
    env = dict(os.environ)
    env["DB_DATABASE"] = "leopardo_test_w1"
    env["APP_ENV"] = "testing"
    try:
        subprocess.run([str(API / "vendor/bin/phpunit"), "--no-coverage",
                        "--log-junit", str(junit)] + files,
                       cwd=str(API), env=env, timeout=150,
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except subprocess.TimeoutExpired:
        return {"files": len(files), "tests": 0, "failures": 0, "errors": 0,
                "skipped": 0, "status": "timeout", "detail": []}
    if not junit.exists():
        return {"files": len(files), "tests": 0, "failures": 0, "errors": 0,
                "skipped": 0, "status": "no-report", "detail": []}
    root = ET.parse(junit).getroot()
    tests = failures = errors = skipped = 0
    detail = []
    for tc in root.iter("testcase"):
        tests += 1
        bad = None
        for ch in tc:
            if ch.tag == "failure":
                failures += 1
                bad = "failure"
            elif ch.tag == "error":
                errors += 1
                bad = "error"
            elif ch.tag == "skipped":
                skipped += 1
        if bad:
            detail.append({"test": "%s::%s" % (tc.get("class") or "", tc.get("name") or ""),
                           "kind": bad})
    status = "pass" if (failures == 0 and errors == 0 and tests) else (
        "fail" if tests else "empty")
    return {"files": len(files), "tests": tests, "failures": failures,
            "errors": errors, "skipped": skipped, "status": status,
            "detail": detail[:8]}


ev = json.loads((HERE / "_evidence.json").read_text(encoding="utf-8"))
by_id = {e["id"]: e for e in ev}
tests_rt = json.loads((HERE / "_tests_runtime.json").read_text(encoding="utf-8"))
rt = json.loads((HERE / "_runtime.json").read_text(encoding="utf-8"))
rt_cases = {c["id"]: c for c in rt["cases"]}
probes = json.loads(Path("/tmp/probes90.json").read_text())

for uc, files in UC_TESTS.items():
    present = [f for f in files if (API / f).exists()]
    ev.append({"id": uc, "title": TITLES[uc], "modules": [], "endpoints": [],
               "declared": 0, "found": 0, "tests": present})
    by_id[uc] = ev[-1]
    r = run(files)
    tests_rt[uc] = r
    print(uc, "tests=%d fail=%d err=%d status=%s files=%d" % (
        r["tests"], r["failures"], r["errors"], r["status"], r["files"]))

    pr = probes.get(uc) or []
    if pr:
        ok = sum(1 for x in pr if 200 <= x["status"] < 300)
        rt_cases[uc] = {"id": uc, "title": TITLES[uc], "probed": len(pr), "ok": ok,
                        "results": pr}
    elif uc not in rt_cases:
        rt_cases[uc] = {"id": uc, "title": TITLES[uc], "probed": 0, "ok": 0, "results": []}

rt["cases"] = list(rt_cases.values())
(HERE / "_evidence.json").write_text(json.dumps(ev, ensure_ascii=False, indent=1), encoding="utf-8")
(HERE / "_tests_runtime.json").write_text(json.dumps(tests_rt, ensure_ascii=False, indent=1), encoding="utf-8")
(HERE / "_runtime.json").write_text(json.dumps(rt, ensure_ascii=False, indent=1), encoding="utf-8")
print("evidence:", len(ev), "| tests_runtime:", len(tests_rt), "| runtime:", len(rt["cases"]))
