#!/usr/bin/env python3
"""Replique locale de check-governance.ps1 (pwsh indisponible dans le sandbox).

Ne remplace pas la garde CI : sert uniquement a verifier localement, avant
push, que les memes assertions passent sur le diff BaseRef..HeadRef.
"""
import re
import subprocess
import sys
from pathlib import Path

BASE = sys.argv[1] if len(sys.argv) > 1 else "origin/main"
HEAD = sys.argv[2] if len(sys.argv) > 2 else "HEAD"

failures = []


def fail(msg):
    failures.append(msg)
    print(f"FAIL: {msg}")


def ok(msg):
    print(f"OK: {msg}")


changed = subprocess.run(
    ["git", "diff", "--name-only", BASE, HEAD],
    capture_output=True, text=True, check=True,
).stdout.splitlines()
changed = [c for c in changed if c.strip()]

print(f"Running governance checks on diff {BASE}..{HEAD} ({len(changed)} files)")

if not changed:
    ok("No changes detected.")
    sys.exit(0)

required = [
    "docs/archive/PILOTAGE.md",
    ".github/PULL_REQUEST_TEMPLATE.md",
    ".github/BRANCH_PROTECTION_REQUIRED.md",
    ".github/workflows/phpstan-baseline.yml",
    "api/phpstan.neon",
    "api/phpstan-baseline.neon",
    "docs/notes/archive/INDEX_CANONIQUE.md",
    "docs/notes/archive/BACKLOG_PHASE1_UNIQUE.md",
    "docs/GESTION_PROJET/GARDE_FOUS.md",
    "docs/GESTION_PROJET/CORRECTIONS.md",
    "docs/GESTION_PROJET/RUNBOOK_DEPLOY.md",
    "docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md",
    "docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md",
    "docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md",
    "docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md",
    "docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md",
    "docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md",
    "docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md",
    "docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md",
    "docs/notes/archive/EXECUTION_BLOCKERS_AND_NEXT.md",
    "docs/notes/archive/08_FEUILLE_DE_ROUTE.md",
    "docs/notes/archive/CU-01_ET_AGENTS.md",
    "docs/notes/archive/ARBORESCENCE_PROJET_COMPLET.md",
]
missing = [f for f in required if not Path(f).exists()]
if missing:
    fail(f"Missing required governance file(s): {missing}")
else:
    ok("All required governance files exist.")

historical = {
    "docs/notes/archive/BACKLOG_PHASE1_UNIQUE.md": r"REMPLACE PAR PILOTAGE\.md",
    "docs/notes/archive/INDEX_CANONIQUE.md": r"REMPLACE PAR PILOTAGE\.md",
    "docs/notes/archive/CONTEXTE_SESSION_IA.md": r"REMPLACE PAR PILOTAGE\.md",
    "docs/notes/archive/JOURNAL_DE_BORD.md": r"REMPLACE PAR PILOTAGE\.md",
    "docs/notes/archive/SUIVI_PROMPTS.md": r"REMPLACE PAR PILOTAGE\.md",
    "docs/notes/archive/08_FEUILLE_DE_ROUTE.md": r"REMPLACE PAR PILOTAGE\.md",
    "docs/notes/archive/CU-01_ET_AGENTS.md": r"REMPLACE PAR PILOTAGE\.md",
    "docs/notes/archive/ARBORESCENCE_PROJET_COMPLET.md": r"REMPLACE PAR PILOTAGE\.md",
}
bad = []
for path, pattern in historical.items():
    p = Path(path)
    if not p.exists():
        continue
    if not re.search(pattern, p.read_text(encoding="utf-8", errors="replace")):
        bad.append(path)
if bad:
    fail(f"Historical marker missing in {bad}")
else:
    ok("Historical canonical redirects are intact.")

bp = Path(".github/BRANCH_PROTECTION_REQUIRED.md").read_text(encoding="utf-8", errors="replace")
if not re.search(r"CodeQL \(Actions\)", bp):
    fail("Branch protection doc must reference CodeQL (Actions).")
elif re.search(r"CodeQL \(Backend\)", bp):
    fail("Branch protection doc still references the obsolete CodeQL (Backend) label.")
elif not re.search(r"Backend Quality \(Pint \+ PHP Syntax \+ PHPStan/Larastan\)", bp):
    fail("Branch protection doc must reference the PHPStan/Larastan quality gate.")
else:
    ok("Branch protection guidance matches the active checks.")

critical = re.compile(
    r"^(api/|mobile/|docs/dossierdeConception/|docs/GESTION_PROJET/|"
    r"docs/REFERENTIEL_PRODUIT/|docs/notes/archive/|docs/archive/PILOTAGE\.md|"
    r"\.github/|tools/check-governance\.ps1)"
)
if any(critical.match(c) for c in changed):
    if "CHANGELOG.md" not in changed:
        fail("Critical scope changed but CHANGELOG.md is not updated.")
    else:
        ok("CHANGELOG updated for critical scope.")
else:
    ok("No critical scope change requiring changelog.")

api_feature = any(
    re.match(r"^(api/app/Http/Controllers/|api/routes/|api/app/Policies/|api/app/Http/Requests/Api/)", c)
    for c in changed
)
mobile_feature = any(re.match(r"^(front/mobile_apps/)", c) for c in changed)
web_feature = any(
    re.match(r"^(front/admin-dashboard/src/|front/admin-dashboard/e2e/|front/admin-dashboard/playwright\.config\.js)", c)
    for c in changed
)


def assert_scenario(scenario_file, label):
    if scenario_file in changed or "docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md" in changed:
        ok(f"{label} scenario governance updated.")
    else:
        fail(f"{label} changed but neither {scenario_file} nor docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md was updated.")


if api_feature:
    assert_scenario("docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md", "API feature surface")
if mobile_feature:
    assert_scenario("docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md", "Mobile feature surface")
if web_feature:
    assert_scenario("docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md", "Web admin feature surface")

print()
if failures:
    print(f"RESULT: FAIL ({len(failures)} assertion(s))")
    sys.exit(1)
print("RESULT: PASS")
