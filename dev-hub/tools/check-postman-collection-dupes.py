#!/usr/bin/env python3
"""check-postman-collection-dupes.py — Garde anti-doublon collection Postman (issue #3602).

La collection `postman/leopardo_hr.postman_collection.json` contenait les
requêtes `POST /auth/login`, `POST /platform/auth/login` et `GET /platform/plans`
en double (racine + dossiers), avec divergence garantie des payloads d'exemple.

La garde échoue si deux requêtes partagent la même paire (méthode, URL brute
normalisée) — la version canonique doit vivre dans un dossier thématique.

Usage : check-postman-collection-dupes.py [collection.json]
"""

from __future__ import annotations

import json
import sys
from collections import Counter
from pathlib import Path

DEFAULT_COLLECTION = (
    Path(__file__).resolve().parent.parent.parent
    / "postman"
    / "leopardo_hr.postman_collection.json"
)


def iter_requests(items: list[dict], folder: str = ""):
    for item in items:
        if "item" in item:  # dossier
            yield from iter_requests(item["item"], f"{folder}/{item.get('name', '?')}")
            continue
        request = item.get("request", {})
        if not isinstance(request, dict):
            continue
        url = request.get("url", {})
        raw = url.get("raw", "") if isinstance(url, dict) else str(url)
        raw = raw.replace("{{baseUrl}}", "").split("?")[0].rstrip("/")
        yield request.get("method", "?").upper(), raw, f"{folder}/{item.get('name', '?')}"


def main() -> int:
    collection = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_COLLECTION
    if not collection.exists():
        print(f"::error::Collection introuvable : {collection}")
        return 1

    data = json.loads(collection.read_text(encoding="utf-8"))
    entries = list(iter_requests(data.get("item", [])))
    counts = Counter((method, url) for method, url, _ in entries)
    dupes = {key: count for key, count in counts.items() if count > 1}

    if dupes:
        print(
            f"::error::Collection Postman — {len(dupes)} paire(s) méthode+URL "
            "dupliquée(s) (issue #3602) :"
        )
        for (method, url), count in sorted(dupes.items()):
            locations = [loc for m, u, loc in entries if (m, u) == (method, url)]
            print(f"::error::  {method} {url} ×{count} → {locations}")
        return 1

    print(f"✓ Collection Postman sans doublon ({len(entries)} requêtes, {collection.name}).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
