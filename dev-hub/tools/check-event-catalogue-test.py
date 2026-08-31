#!/usr/bin/env python3
"""
Self-test for check-event-catalogue.py (issue #5864 / MAT-006).

Verifies the guard actually catches the failure modes it claims to catch,
using throwaway fixtures under a temp directory — never touches the real
repo catalogue or api/app/Events.

Usage: python3 dev-hub/tools/check-event-catalogue-test.py
Exit codes: 0 = all self-tests passed, 1 = a self-test failed.
"""

from __future__ import annotations

import importlib.util
import json
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
CHECKER = SCRIPT_DIR / "check-event-catalogue.py"


def make_repo(tmp: Path, event_php: str | None, catalogue: dict | None) -> None:
    events_dir = tmp / "api" / "app" / "Events"
    events_dir.mkdir(parents=True, exist_ok=True)
    governance_dir = tmp / "dev-hub" / "governance"
    governance_dir.mkdir(parents=True, exist_ok=True)

    if event_php is not None:
        (events_dir / "SampleCreated.php").write_text(event_php, encoding="utf-8")

    if catalogue is not None:
        (governance_dir / "event-catalogue.json").write_text(
            json.dumps(catalogue, indent=2), encoding="utf-8"
        )


def run_checker(tmp: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(CHECKER), str(tmp)],
        capture_output=True,
        text=True,
    )


SAMPLE_EVENT_PHP = """<?php

namespace App\\Events;

use App\\Core\\Auth\\Domain\\Models\\Employee;
use Illuminate\\Foundation\\Events\\Dispatchable;

class SampleCreated
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee) {}
}
"""

MATCHING_CATALOGUE = {
    "events": [
        {
            "name": "SampleCreated",
            "owner": "BC-04 HR",
            "version": 1,
            "status": "active",
            "payload": {"employee": "App\\Core\\Auth\\Domain\\Models\\Employee"},
            "consumers": [],
        }
    ]
}


def test_matching_catalogue_passes() -> str | None:
    with tempfile.TemporaryDirectory() as tmp_str:
        tmp = Path(tmp_str)
        make_repo(tmp, SAMPLE_EVENT_PHP, MATCHING_CATALOGUE)
        result = run_checker(tmp)
        if result.returncode != 0:
            return f"expected exit 0, got {result.returncode}: {result.stderr}"
    return None


def test_missing_catalogue_entry_fails() -> str | None:
    with tempfile.TemporaryDirectory() as tmp_str:
        tmp = Path(tmp_str)
        make_repo(tmp, SAMPLE_EVENT_PHP, {"events": []})
        result = run_checker(tmp)
        if result.returncode == 0:
            return "expected non-zero exit when event is missing from catalogue"
        if "absent du catalogue" not in result.stderr:
            return f"expected 'absent du catalogue' message, got: {result.stderr}"
    return None


def test_breaking_type_change_fails() -> str | None:
    with tempfile.TemporaryDirectory() as tmp_str:
        tmp = Path(tmp_str)
        drifted_catalogue = json.loads(json.dumps(MATCHING_CATALOGUE))
        drifted_catalogue["events"][0]["payload"]["employee"] = "App\\Core\\Auth\\Domain\\Models\\SuperAdmin"
        make_repo(tmp, SAMPLE_EVENT_PHP, drifted_catalogue)
        result = run_checker(tmp)
        if result.returncode == 0:
            return "expected non-zero exit on breaking payload type change"
        if "breaking change non documenté" not in result.stderr:
            return f"expected 'breaking change non documenté' message, got: {result.stderr}"
    return None


def test_removed_event_without_status_fails() -> str | None:
    with tempfile.TemporaryDirectory() as tmp_str:
        tmp = Path(tmp_str)
        # Event file absent, but catalogue still lists it as active.
        make_repo(tmp, None, MATCHING_CATALOGUE)
        result = run_checker(tmp)
        if result.returncode == 0:
            return "expected non-zero exit when catalogued event has no source file"
    return None


def test_removed_event_with_status_removed_passes() -> str | None:
    with tempfile.TemporaryDirectory() as tmp_str:
        tmp = Path(tmp_str)
        removed_catalogue = json.loads(json.dumps(MATCHING_CATALOGUE))
        removed_catalogue["events"][0]["status"] = "removed"
        make_repo(tmp, None, removed_catalogue)
        result = run_checker(tmp)
        if result.returncode != 0:
            return f"expected exit 0 when removed event is marked status=removed: {result.stderr}"
    return None


def main() -> int:
    if not CHECKER.is_file():
        print(f"::error::Checker introuvable : {CHECKER}", file=sys.stderr)
        return 1

    tests = [
        test_matching_catalogue_passes,
        test_missing_catalogue_entry_fails,
        test_breaking_type_change_fails,
        test_removed_event_without_status_fails,
        test_removed_event_with_status_removed_passes,
    ]

    failures = 0
    for test in tests:
        failure = test()
        if failure is None:
            print(f"✅  {test.__name__}")
        else:
            print(f"❌  {test.__name__}: {failure}", file=sys.stderr)
            failures += 1

    if failures:
        print(f"\n{failures}/{len(tests)} self-test(s) failed.", file=sys.stderr)
        return 1

    print(f"\nAll {len(tests)} self-tests passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
