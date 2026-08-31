#!/usr/bin/env python3
"""resolve-registry-conflicts.py — resout par UNION (superset) les conflits
de merge recurrents sur les registres de gouvernance additifs :
  - dev-hub/tools/golden-journeys.json   (solutions{}, journeys[])
  - dev-hub/tools/pilot-gates.json       (pilots[] keyed by .solution)
  - dev-hub/tools/runbook-registry.json  (union par cle de 1er niveau)

Usage (pendant un merge en conflit sur ces fichiers) :
    python3 dev-hub/tools/resolve-registry-conflicts.py --ours HEAD --theirs MERGE_HEAD
    git add dev-hub/tools/*.json && git commit --no-edit

Sans args, prend les versions cote index (stage 2 = ours, stage 3 = theirs)
du merge en cours et ecrit la version unionnee dans le working tree.
Aucune entree n'est jamais supprimee : ours ecrase theirs cle par cle,
toute cle presente d'un seul cote est conservee. Deterministe.
"""
from __future__ import annotations
import argparse, json, subprocess, sys
from pathlib import Path

FILES = [
    "dev-hub/tools/golden-journeys.json",
    "dev-hub/tools/pilot-gates.json",
    "dev-hub/tools/runbook-registry.json",
]

def show(ref: str, path: str):
    r = subprocess.run(["git", "show", f"{ref}:{path}"], capture_output=True, text=True)
    if r.returncode != 0:
        return None
    try:
        return json.loads(r.stdout)
    except json.JSONDecodeError:
        return None

def stage(num: int, path: str):
    r = subprocess.run(["git", "show", f":{num}:{path}"], capture_output=True, text=True)
    if r.returncode != 0:
        return None
    try:
        return json.loads(r.stdout)
    except json.JSONDecodeError:
        return None

def union_list_by_key(a, b, key):
    """union de deux listes de dicts, dedupliquees par item[key], ours (a) prioritaire."""
    out, seen = [], set()
    for item in (a or []) + (b or []):
        k = item.get(key) if isinstance(item, dict) else item
        if k in seen:
            continue
        seen.add(k)
        out.append(item)
    return out

def merge_golden(ours, theirs):
    base = dict(ours or theirs or {})
    sol = {}
    for src in (theirs, ours):  # ours applied last -> wins
        for k, v in (src or {}).get("solutions", {}).items():
            sol[k] = v
    base["solutions"] = sol
    jo = (ours or {}).get("journeys", [])
    jt = (theirs or {}).get("journeys", [])
    if isinstance(jo, list) or isinstance(jt, list):
        base["journeys"] = union_list_by_key(jo if isinstance(jo, list) else list(jo.values()),
                                             jt if isinstance(jt, list) else list(jt.values()), "id")
    else:
        merged = {}
        for src in (jt, jo):
            merged.update(src or {})
        base["journeys"] = merged
    return base

def _pilot_key(item):
    if not isinstance(item, dict):
        return item
    return item.get("solution") or item.get("id") or item.get("name") or json.dumps(item, sort_keys=True)

def merge_pilots(ours, theirs):
    base = dict(ours or theirs or {})
    out, seen = [], set()
    for item in ((ours or {}).get("pilots", []) + (theirs or {}).get("pilots", [])):
        k = _pilot_key(item)
        if k in seen:
            continue
        seen.add(k)
        out.append(item)
    base["pilots"] = out
    return base

def merge_generic(ours, theirs):
    if isinstance(ours, dict) or isinstance(theirs, dict):
        base = {}
        for src in (theirs, ours):
            base.update(src or {})
        return base
    return ours if ours is not None else theirs

def resolve(path, ours, theirs):
    if path.endswith("golden-journeys.json"):
        return merge_golden(ours, theirs)
    if path.endswith("pilot-gates.json"):
        return merge_pilots(ours, theirs)
    return merge_generic(ours, theirs)

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--ours", help="git ref cote ours (defaut: index stage 2)")
    ap.add_argument("--theirs", help="git ref cote theirs (defaut: index stage 3)")
    ap.add_argument("--files", nargs="*", default=FILES)
    args = ap.parse_args()
    changed = 0
    for path in args.files:
        ours = show(args.ours, path) if args.ours else stage(2, path)
        theirs = show(args.theirs, path) if args.theirs else stage(3, path)
        if ours is None and theirs is None:
            continue
        merged = resolve(path, ours, theirs)
        Path(path).write_text(json.dumps(merged, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(f"resolved(union): {path}")
        changed += 1
    if changed == 0:
        print("aucun registre a resoudre", file=sys.stderr)
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
