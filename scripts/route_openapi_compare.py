#!/usr/bin/env python3
"""Compare Laravel API routes against api/openapi.yaml.

Normalizes:
- module files are required inside Route::prefix('v1') in api.php
- nested Route::prefix('X')->group(...) blocks (growth, reports, predictions,
  features, edge/nodes)
- route parameter names ({id}, {employee}, ...) → {p}
"""
import re
from pathlib import Path

import yaml

API = Path("api")

ROUTE_CALL = re.compile(
    r"(?:Route::|->)(?P<verbs>get|post|put|patch|delete|any|apiResource|resource)"
    r"\(\s*(?P<args>[^;]*?)\)",
    re.S,
)
VERBS = {"get": ["get"], "post": ["post"], "put": ["put"], "patch": ["patch"],
         "delete": ["delete"], "any": ["get", "post", "put", "patch", "delete"]}
RESOURCE_SUFFIXES = {"get": ["", "/{id}"], "post": [""],
                     "put": ["/{id}"], "patch": ["/{id}"], "delete": ["/{id}"]}


def strip_comments(text: str) -> str:
    text = re.sub(r"/\*.*?\*/", "", text, flags=re.S)
    return re.sub(r"//[^\n]*", "", text)


def prefixes_for_file(text: str):
    """Return dict line_index -> active prefix list, using brace tracking.

    Handles both `Route::prefix('X')->group(...)` and chained
    `Route::middleware([...])->prefix('X')->group(...)` patterns.
    """
    PREFIX_GROUP = re.compile(r"prefix\(\s*['\"]([^'\"]+)['\"]\s*\)[^;]*?->group", re.S)
    lines = text.splitlines(keepends=True)
    # per-line brace depth after processing the line
    depth = 0
    depths = []
    for line in lines:
        depth += line.count("{") - line.count("}")
        depths.append(depth)

    # find group spans: for each prefix match, locate the next '{' and the
    # depth just after it; group closes on the first later line with depth < that
    spans = []  # (open_line, close_line, prefix)
    for m in PREFIX_GROUP.finditer(text):
        prefix = m.group(1)
        open_pos = text.find("{", m.end())
        if open_pos == -1:
            continue
        open_line = text[: open_pos].count("\n")
        # absolute brace depth right after the group's opening brace
        base = text[: open_pos + 1].count("{") - text[: open_pos + 1].count("}")
        close_line = None
        for li in range(open_line + 1, len(depths)):
            if depths[li] < base:
                close_line = li
                break
        if close_line is None:
            close_line = len(lines)
        spans.append((open_line, close_line, prefix))

    active = {}
    for i in range(len(lines)):
        active[i] = [p for (o, c, p) in spans if o <= i < c]
    return active


def extract_routes():
    routes = []  # (verb, path)
    files = [API / "routes" / "api.php", API / "routes" / "web.php",
             API / "routes" / "ai.php",
             API / "app" / "Modules" / "EdgeSync" / "routes" / "api.php"] + sorted(
        (API / "routes" / "modules").glob("*.php")
    )
    for f in files:
        text = strip_comments(f.read_text(encoding="utf-8", errors="replace"))
        name = f.name
        is_inside_v1 = name in ("api.php", "ai.php") or f.parent.name == "modules"
        is_web = name == "web.php"
        # EdgeSync module routes use explicit api/v1 prefixes
        full_prefix = f.parent.name == "routes" and f.parent.parent.name in ("EdgeSync",)
        prefixes = prefixes_for_file(text)
        lines = text.splitlines(keepends=True)
        for m in ROUTE_CALL.finditer(text):
            line_idx = text[: m.start()].count("\n")
            verbs_call = m.group("verbs")
            args = m.group("args")
            first = re.search(r"['\"]([^'\"]+)['\"]", args)
            if not first:
                continue
            uri = first.group(1)
            pre = prefixes.get(line_idx, [])
            for p in reversed(pre):
                uri = "/" + p.strip("/") + uri
            if is_inside_v1 and not uri.startswith("/v1"):
                uri = "/v1" + uri
            if full_prefix and not (uri.startswith("/api/v1") or uri.startswith("/v1")):
                uri = "/api/v1" + uri
            if is_web and not uri.startswith("/api/v1"):
                continue
            if verbs_call == "match":
                continue
            if verbs_call in ("apiResource", "resource"):
                base = uri.rstrip("/")
                for rv, suffixes in RESOURCE_SUFFIXES.items():
                    for s in suffixes:
                        routes.append((rv, base + s))
                continue
            for v in VERBS[verbs_call]:
                routes.append((v, uri))
    return routes


def norm_path(p: str) -> str:
    p = p.rstrip("/") or "/"
    # collapse {x} and {x:y} to {p}
    p = re.sub(r"\{[^}]*\}", "{p}", p)
    return p


def main():
    routes = [(v, norm_path(p)) for v, p in extract_routes()]
    spec = yaml.safe_load((API / "openapi.yaml").read_text(encoding="utf-8"))
    oa = {}
    for p, item in (spec.get("paths") or {}).items():
        for v in item:
            if v in ("get", "post", "put", "patch", "delete"):
                oa.setdefault(norm_path(p), set()).add(v)

    api_routes = set()
    for v, p in routes:
        if p.startswith("/api/v1"):
            api_routes.add((v, p[len("/api/v1"):]))
        elif p.startswith("/v1"):
            api_routes.add((v, p[len("/v1"):]))
        elif p.startswith("/api"):
            api_routes.add((v, p[len("/api"):]))
    oa_set = set()
    for p, verbs in oa.items():
        for v in verbs:
            oa_set.add((v, p))

    missing_in_oa = sorted(api_routes - oa_set)
    missing_in_routes = sorted(oa_set - api_routes)

    print(f"API routes PHP (verb+path, normalized): {len(api_routes)}")
    print(f"OpenAPI paths/verbs (v1, normalized): {len(oa_set)}")
    print(f"\n=== In PHP routes but MISSING in OpenAPI ({len(missing_in_oa)}) ===")
    for v, p in missing_in_oa:
        print(f"  {v.upper():6} {p}")
    print(f"\n=== In OpenAPI but MISSING in PHP routes ({len(missing_in_routes)}) ===")
    for v, p in missing_in_routes:
        print(f"  {v.upper():6} {p}")


if __name__ == "__main__":
    main()
