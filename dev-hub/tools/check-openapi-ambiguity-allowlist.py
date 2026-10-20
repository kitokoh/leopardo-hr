#!/usr/bin/env python3
"""Reject new Redocly ambiguous-path findings outside the reviewed allowlist.

Usage:
  python3 check-openapi-ambiguity-allowlist.py /tmp/openapi-redocly-default.json
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit("usage: check-openapi-ambiguity-allowlist.py REDOCLY_JSON")

report = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
allowlist = json.loads(
    (Path(__file__).with_name("openapi-ambiguity-allowlist.json")).read_text(
        encoding="utf-8"
    )
)
allowed = {frozenset(item["paths"]) for item in allowlist["pairs"]}
observed: set[frozenset[str]] = set()
unexpected: list[str] = []

for problem in report.get("problems", []):
    if problem.get("ruleId") != "no-ambiguous-paths":
        continue
    message = problem.get("message", "")
    marker = "Found two ambiguous paths: "
    if marker not in message:
        unexpected.append(message)
        continue
    pair = message.split(marker, 1)[1].rstrip(".")
    paths = [path.strip("`") for path in pair.split(" and ")]
    key = frozenset(paths)
    observed.add(key)
    if key not in allowed:
        unexpected.append(message)

missing = allowed - observed
if unexpected:
    print("Unexpected OpenAPI ambiguous paths:")
    for item in unexpected:
        print(f"- {item}")
    raise SystemExit(1)
if missing:
    print("Allowlisted ambiguity is no longer reported; remove stale entries:")
    for item in sorted(missing, key=str):
        print(f"- {sorted(item)}")
    raise SystemExit(1)

print(f"OpenAPI ambiguity allowlist: PASS ({len(observed)} reviewed pairs)")
