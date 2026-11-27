#!/usr/bin/env python3
"""Sonde RÉELLE v2 des endpoints, avec le VERBE déclaré par le routeur.

Améliorations vs v1 :
  - le verbe est lu dans `php artisan route:list --json` (plus de faux 405) ;
  - un 405/404 est distingué d'un 401/403 (route absente vs accès refusé).

Sortie : _runtime.json
"""
import json
import re
import urllib.request
import urllib.error
from pathlib import Path

HERE = Path(__file__).resolve().parent
BASE = "http://127.0.0.1:8000"
ROUTES = Path("/tmp/routes.json")

PERSONAS = {
    "principal": "ahmed.benali@techcorp-algerie.dz",
    "rh": "fatima.meziane@techcorp-algerie.dz",
    "dept": "samir.boukhalfa@techcorp-algerie.dz",
    "comptable": "lina.haddad@techcorp-algerie.dz",
    "superviseur": "nassim.cheriet@techcorp-algerie.dz",
    "employe": "karim.aouad@techcorp-algerie.dz",
}
PASSWORD = "password123"


def http(method, path, token=None, body=None, timeout=20):
    url = BASE + path
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Accept", "application/json")
    if data is not None:
        req.add_header("Content-Type", "application/json")
    if token:
        req.add_header("Authorization", "Bearer " + token)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")
    except Exception as e:  # noqa: BLE001
        return 0, repr(e)


def login(email):
    st, body = http("POST", "/api/v1/auth/login", body={"email": email, "password": PASSWORD})
    if st != 200:
        return None, st
    try:
        return json.loads(body).get("token"), st
    except Exception:  # noqa: BLE001
        return None, st


def load_routes():
    """uri -> set(methodes)."""
    raw = json.loads(ROUTES.read_text(encoding="utf-8"))
    table = {}
    for r in raw:
        uri = r.get("uri") or ""
        methods = r.get("method") or ""
        if isinstance(methods, str):
            methods = [m.strip() for m in methods.split("|")]
        table.setdefault(uri, set()).update(methods)
    return table


def main():
    tokens, login_status = {}, {}
    for name, email in PERSONAS.items():
        tok, st = login(email)
        tokens[name] = tok
        login_status[name] = st
    print("logins:", login_status)

    ids = {}
    tok = tokens.get("principal")
    st, body = http("GET", "/api/v1/employees?per_page=1", tok)
    if st == 200:
        try:
            rows = json.loads(body).get("data") or []
            if rows:
                ids["employee"] = rows[0].get("id")
        except Exception:  # noqa: BLE001
            pass
    print("ids:", ids)

    table = load_routes()
    eps = json.loads((HERE / "_endpoints.json").read_text(encoding="utf-8"))
    out = []
    for uc in eps:
        uc_id = uc["id"]
        results = []
        for ep in uc.get("endpoints") or []:
            path = ep.get("path") or ""
            if not path.startswith("/api/"):
                st, _ = http("GET", path)
                results.append({"path": path, "method": "GET", "status": st, "kind": "web"})
                continue
            p = re.sub(r"\{employee\}", str(ids.get("employee", 1)), path)
            p = re.sub(r"\{[a-zA-Z_]+\}", "1", p)
            uri = p.lstrip("/")
            methods = table.get(uri) or set()
            verb = "GET" if "GET" in methods else ("POST" if "POST" in methods else "GET")
            st, _ = http(verb, p, tok)
            results.append({"path": p, "method": verb, "status": st, "kind": "api"})
        ok = [r for r in results if 200 <= r["status"] < 300]
        out.append({
            "id": uc_id,
            "title": uc.get("title"),
            "probed": len(results),
            "ok": len(ok),
            "results": results,
        })
        print(uc_id, "probed=%d ok=%d" % (len(results), len(ok)))

    (HERE / "_runtime.json").write_text(
        json.dumps({"logins": login_status, "ids": ids, "cases": out}, indent=1, ensure_ascii=False),
        encoding="utf-8",
    )
    print("écrit _runtime.json")


if __name__ == "__main__":
    main()
