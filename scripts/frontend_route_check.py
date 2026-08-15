#!/usr/bin/env python3
"""Check frontend API endpoint strings against real PHP routes.

Usage: python3 scripts/frontend_route_check.py [dirs...]
"""
import re
import sys
from pathlib import Path

import yaml

API = Path("api")
ROOT = Path(".")


def load_php_routes():
    """Reuse the route extractor."""
    sys.path.insert(0, str(ROOT / "scripts"))
    from route_openapi_compare import extract_routes, norm_path
    routes = set()
    for v, p in extract_routes():
        if p.startswith("/api/v1"):
            routes.add((v, p[len("/api/v1"):]))
        elif p.startswith("/v1"):
            routes.add((v, p[len("/v1"):]))
        elif p.startswith("/api"):
            routes.add((v, p[len("/api"):]))
    return {(v, norm_path(p)) for v, p in routes}


VERBS = {"get", "post", "put", "patch", "delete"}
STR = r"['\"](/[^'\"\\s]{2,})['\"]"

def scan_file(path: Path, routes, findings):
    text = path.read_text(encoding="utf-8", errors="replace")
    # api.get('/x'), api.post('/x'), requestWithRetry('/x'), apiFetch('/x'),
    # http.get etc.
    patterns = [
        r"\.(?:get|post|put|patch|delete)\(\s*['\"]([^'\"\\s]{3,})['\"]",
        r"apiFetch\(\s*['\"]([^'\"\\s]{3,})['\"]",
        r"requestWithRetry\(\s*['\"]([^'\"\\s]{3,})['\"]",
        r"request\(\s*['\"]([^'\"\\s]{3,})['\"]",
        r"dio\.(?:get|post|put|patch|delete)\(\s*['\"]([^'\"\\s]{3,})['\"]",
    ]
    for pat in patterns:
        for m in re.finditer(pat, text):
            ep = m.group(1)
            if ep.startswith("http") or ep.startswith("/api/forms") or "{" not in ep and "$" in ep:
                continue
            # normalize
            rel = ep
            if rel.startswith("/api/v1"):
                rel = rel[len("/api/v1"):]
            elif rel.startswith("/v1"):
                rel = rel[len("/v1"):]
            if not rel.startswith("/"):
                continue
            if "{" in rel or "$" in rel:
                # template literal — normalize {x} params
                rel2 = re.sub(r"\$\{[^}]*\}", "{p}", rel)
                rel2 = re.sub(r"\{[^}]*\}", "{p}", rel2)
            else:
                rel2 = re.sub(r"\{[^}]*\}", "{p}", rel.rstrip("/") or "/")
            verb = None
            for v in VERBS:
                if v in pat:
                    verb = v
            key = (verb, rel2)
            # generic match: any verb acceptable when verb is unknown
            found = key in routes or any((v, rel2) in routes for v in VERBS)
            if not found:
                findings.append((str(path), ep, verb))


def main():
    dirs = [ROOT / "front/admin-dashboard/src", ROOT / "front/web/src"] + \
           list((ROOT / "front/mobile_apps").glob("*/lib"))
    routes = load_php_routes()
    findings = []
    files = []
    for d in dirs:
        if d.exists():
            files += list(d.rglob("*.ts")) + list(d.rglob("*.tsx")) + \
                     list(d.rglob("*.vue")) + list(d.rglob("*.js")) + list(d.rglob("*.dart"))
    seen = set()
    for f in files:
        if "node_modules" in str(f) or "/dist/" in str(f) or "/build/" in str(f):
            continue
        if any(x in str(f) for x in ["/test/", "/tests/", ".test.", ".spec."]):
            continue
        scan_file(f, routes, findings)
    print(f"Files scanned: {len(files)}")
    print(f"Potential dead/mismatched endpoint references: {len(findings)}")
    # dedupe
    uniq = {}
    for f, ep, verb in findings:
        key = (ep, verb)
        if key not in uniq:
            uniq[key] = []
        uniq[key].append(Path(f).parent.name)
    for (ep, verb), files in sorted(uniq.items()):
        dirs_short = sorted(set(Path(f).parts[-3] if len(Path(f).parts) >= 3 else f for f in files))
        print(f"  [{verb}] {ep}  <- {', '.join(dirs_short[:4])}")


if __name__ == "__main__":
    main()
