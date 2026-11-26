#!/usr/bin/env python3
"""Résolveur d'endpoints v2 : index GLOBAL de toutes les routes déclarées
(api/routes/**), puis correspondance par suffixe de segments normalisés.

Sortie : _endpoints.json + rapport lisible.
"""
import importlib.util
import json
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
API = HERE.parent.parent / "api"

spec = importlib.util.spec_from_file_location("bp", HERE / "build_portal.py")
bp = importlib.util.module_from_spec(spec)
spec.loader.exec_module(bp)

PARAM = re.compile(r"\{[^}]+\}")


def norm(p: str) -> str:
    p = PARAM.sub("{}", p)
    p = re.sub(r"/+", "/", p)
    return p.strip("/")


def build_index():
    """Toutes les routes, avec le préfixe de fichier appliqué."""
    idx = []
    for p in sorted((API / "routes").rglob("*.php")):
        txt = p.read_text(encoding="utf-8", errors="ignore")
        prefixes = re.findall(r"prefix\(\s*'([^']+)'\s*\)", txt)
        base = prefixes[0] if prefixes else ""
        for m in re.finditer(r"Route::(?:get|post|put|patch|delete)\(\s*'([^']*)'", txt):
            idx.append(norm(f"{base}/{m.group(1)}"))
    return sorted(set(idx))


INDEX = build_index()


def endpoints_of(uc):
    blob = " ".join(uc.get("steps", []) + [uc.get("expected", ""), uc.get("pre", "")])
    found = re.findall(r"`(/(?:api/)?[A-Za-z0-9_\-/{}.]+)`", blob)
    found += re.findall(r"(?<![\w`])(/(?:api/v1|api)/[A-Za-z0-9_\-/{}.]+)", blob)
    out = []
    for f in found:
        f = f.rstrip(".,;:)")
        if f not in out:
            out.append(f)
    return out


def matches(ep: str):
    e = norm(re.sub(r"^/?(api/v1|api|v1)/", "", ep))
    if not e:
        return None
    eseg = e.split("/")
    for r in INDEX:
        rseg = r.split("/")
        k = min(len(eseg), len(rseg), 2)
        if k and eseg[-k:] == rseg[-k:]:
            return r
    return None


def main():
    rows = []
    for uc in bp.USE_CASES:
        eps = endpoints_of(uc)
        checked = []
        for e in eps:
            hit = matches(e)
            checked.append({"path": e, "exists": hit is not None, "route": hit})
        rows.append({
            "id": uc["id"], "title": uc["title"], "modules": uc["modules"],
            "endpoints": checked, "declared": len(checked),
            "found": sum(1 for c in checked if c["exists"]),
        })
    (HERE / "_endpoints.json").write_text(json.dumps(rows, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"index: {len(INDEX)} routes déclarées\n")
    for r in rows:
        miss = [c["path"] for c in r["endpoints"] if not c["exists"]]
        if not r["declared"]:
            flag = "-- "
        elif miss:
            flag = "MISS"
        else:
            flag = "OK  "
        print(f"{flag} {r['id']} {r['found']}/{r['declared']}  {r['title'][:44]}")
        for m in miss:
            print(f"        absent: {m}")


if __name__ == "__main__":
    main()
