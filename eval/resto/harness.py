#!/usr/bin/env python3
"""Harnais de recette RestaurantManager (tenant resto-pilot-001).

- matrice RBAC : chaque profil x chaque endpoint GET
- parcours metier : commande POS -> cuisine -> paiement, reservation,
  avis, stock, cloture de caisse
- surfaces publiques : menu public par slug

Suit la rotation de jeton (`_auth.new_token`) et retente sur 429.
"""
from __future__ import annotations

import json
import sys
import time
import urllib.error
import urllib.request

API = "http://127.0.0.1:8000/api/v1"
PASSWORD = "recette123"
PROFILES = ["principal", "gerant", "comptable", "superviseur", "chef", "serveur", "caisse", "livreur"]


def call(method, path, token=None, body=None, retries=5):
    url = API + path
    data = json.dumps(body).encode() if body is not None else None
    raw, code = "", 0
    for attempt in range(retries + 1):
        req = urllib.request.Request(url, data=data, method=method)
        req.add_header("Accept", "application/json")
        if data is not None:
            req.add_header("Content-Type", "application/json")
        if token:
            req.add_header("Authorization", "Bearer " + token)
        try:
            with urllib.request.urlopen(req, timeout=40) as resp:
                raw, code = resp.read().decode("utf-8", "replace"), resp.status
        except urllib.error.HTTPError as exc:
            raw, code = exc.read().decode("utf-8", "replace"), exc.code
        except Exception as exc:  # noqa: BLE001
            return 0, {"error": str(exc)}, None
        if code == 429 and attempt < retries:
            time.sleep(15)
            continue
        break
    try:
        payload = json.loads(raw)
    except json.JSONDecodeError:
        payload = {"_raw": raw[:400]}
    new_token = None
    if isinstance(payload, dict) and isinstance(payload.get("_auth"), dict):
        new_token = payload["_auth"].get("new_token") or payload["_auth"].get("token")
    return code, payload, new_token


class Session:
    def __init__(self, profile):
        self.profile = profile
        code, payload, _ = call("POST", "/auth/login", body={"email": f"{profile}@example.com", "password": PASSWORD})
        if code != 200:
            raise RuntimeError(f"login {profile} -> {code} {str(payload)[:200]}")
        self.token = payload["token"]

    def _do(self, method, path, body=None):
        code, payload, new = call(method, path, self.token, body)
        if new:
            self.token = new
        return code, payload

    def get(self, p):
        return self._do("GET", p)

    def post(self, p, b=None):
        return self._do("POST", p, b)

    def put(self, p, b=None):
        return self._do("PUT", p, b)

    def patch(self, p, b=None):
        return self._do("PATCH", p, b)


RESTAURANT_GET = [
    "/restaurant/ping", "/restaurant/branches", "/restaurant/zones", "/restaurant/tables",
    "/restaurant/products", "/restaurant/ingredients", "/restaurant/menus", "/restaurant/orders",
    "/restaurant/kitchen/orders", "/restaurant/pos-sessions/current", "/restaurant/stock-levels",
    "/restaurant/stock/alerts", "/restaurant/purchase-orders", "/restaurant/reservations",
    "/restaurant/reviews", "/restaurant/delivery-riders", "/restaurant/deliveries",
    "/restaurant/loyalty-programs", "/restaurant/loyalty-customers", "/restaurant/promotions",
    "/restaurant/reports/sales", "/restaurant/reports/kpis", "/restaurant/reports/occupancy",
    "/restaurant/reports/products", "/restaurant/reports/cogs", "/restaurant/reports/pos",
    "/restaurant/reports/group", "/restaurant/dashboard/kpis", "/restaurant/health/metrics",
    "/restaurant/suppliers", "/restaurant/units", "/restaurant/tax-rates", "/restaurant/categories",
    "/restaurant/hours", "/restaurant/delivery-zones", "/restaurant/inventory-movements",
    "/restaurant/inventory-counts", "/restaurant/receivings", "/restaurant/print-jobs",
    "/restaurant/printing/bridge/health", "/restaurant/printing/bridge/jobs",
    "/restaurant/sync/status", "/restaurant/sync/errors", "/restaurant/sync/conflicts",
    "/restaurant/mobile/server/orders", "/restaurant/mobile/server/tables",
    "/restaurant/mobile/rider/deliveries", "/restaurant/mobile/manager/kpis",
    "/restaurant/mobile/manager/stock-alerts", "/restaurant/mobile/manager/pos-sessions/current",
    "/restaurant/shop/token", "/restaurant/payments/configuration",
]

HR_GET = [
    "/employees", "/departments", "/positions", "/schedules", "/attendance/today",
    "/attendance/dashboard", "/attendance/anomalies", "/attendance/regularity",
    "/attendance/monthly-report", "/attendance/my-sessions", "/attendance/config",
    "/attendance/corrections", "/attendance/day-closures", "/absences", "/absences/stats",
    "/leave-balances", "/leave-policies", "/leave-accruals", "/payrolls", "/contracts",
    "/contracts/expiring", "/contracts/templates", "/employee-documents", "/notifications",
    "/expense-claims", "/expense/stats", "/loans", "/evaluations", "/hr/dashboard",
    "/dashboard/rh", "/dashboard/kpi", "/dashboard/summary", "/dashboard/recent-activity",
    "/me/leave-balances", "/me/contracts", "/me/documents", "/me/loans",
    "/me/attendance-anomalies", "/me/training-enrollments",
]

CRM_GET = [
    "/crm/accounts", "/crm/contacts", "/crm/leads", "/crm/opportunities", "/crm/tasks",
    "/crm/dashboard/pipeline", "/crm/dashboard/quality", "/crm/segments", "/crm/campaigns",
    "/crm/automations", "/crm/consents", "/crm/exports", "/crm/read-models",
    "/crm/dedup/suggestions", "/crm/search?q=hotel",
]


def matrix(sessions, paths, label, results, key):
    for path in paths:
        row = {}
        for profile, session in sessions.items():
            code, _ = session.get(path)
            row[profile] = code
            time.sleep(0.1)
        results[key][path] = row
        print(f"{label} {path:48s} " + " ".join(f"{p[:4]}={c}" for p, c in row.items()), flush=True)


def main():
    out_path = "eval/resto/results.json"
    if "--out" in sys.argv:
        out_path = sys.argv[sys.argv.index("--out") + 1]

    results = {"profiles": {}, "restaurant": {}, "hr": {}, "crm": {}, "workflows": {}, "public": {}}
    sessions = {}
    for profile in PROFILES:
        try:
            sessions[profile] = Session(profile)
            results["profiles"][profile] = {"login": 200}
        except Exception as exc:  # noqa: BLE001
            results["profiles"][profile] = {"login": "FAIL", "error": str(exc)}
        time.sleep(0.3)

    branch_id = ""
    code, payload = sessions["principal"].get("/restaurant/branches")
    items = payload.get("data") if isinstance(payload, dict) else None
    if isinstance(items, dict):
        items = items.get("data") or items.get("items")
    if isinstance(items, list) and items:
        branch_id = str(items[0].get("id", ""))
    results["branch_id"] = branch_id
    print("branch_id =", branch_id, flush=True)

    def q(path):
        if not branch_id:
            return path
        if path in ("/restaurant/kitchen/orders", "/restaurant/pos-sessions/current",
                    "/restaurant/mobile/manager/pos-sessions/current"):
            return f"{path}?branch_id={branch_id}"
        if path == "/restaurant/reservations/availability":
            return f"{path}?branch_id={branch_id}&date={time.strftime('%Y-%m-%d')}&covers=2"
        return path

    matrix(sessions, [q(p) for p in RESTAURANT_GET], "RST", results, "restaurant")
    matrix(sessions, HR_GET, "HR ", results, "hr")
    matrix(sessions, CRM_GET, "CRM", results, "crm")

    wf = results["workflows"]
    principal = sessions["principal"]
    serveur = sessions["serveur"]
    caisse = sessions["caisse"]
    chef = sessions["chef"]

    code, payload = caisse.post("/restaurant/pos-sessions/open", {"branch_id": branch_id, "opening_float_minor": 5000000})
    wf["pos_open"] = {"code": code, "body": str(payload)[:300]}
    pos_id = ""
    if isinstance(payload, dict):
        d = payload.get("data") or payload
        pos_id = str(d.get("id", "")) if isinstance(d, dict) else ""

    code, payload = serveur.get("/restaurant/products")
    products = []
    if isinstance(payload, dict):
        d = payload.get("data")
        if isinstance(d, dict):
            d = d.get("data") or d.get("items")
        if isinstance(d, list):
            products = d
    wf["products_count"] = len(products)
    product_id = str(products[0].get("id")) if products else ""
    price = (products[0].get("price_minor") or products[0].get("price") or 0) if products else 0

    order_id = ""
    if product_id:
        code, payload = serveur.post("/restaurant/orders", {
            "branch_id": branch_id, "type": "dine_in", "source": "pos", "covers": 2,
            "items": [{"product_id": product_id, "quantity": 2, "unit_price_minor": price}],
        })
        wf["order_create"] = {"code": code, "body": str(payload)[:400]}
        if isinstance(payload, dict):
            d = payload.get("data") or payload
            order_id = str(d.get("id", "")) if isinstance(d, dict) else ""

    code, payload = chef.get(f"/restaurant/kitchen/orders?branch_id={branch_id}")
    wf["kitchen_queue"] = {"code": code, "body": str(payload)[:300]}
    if order_id:
        for action in ("confirm", "start_preparation", "mark_ready", "serve"):
            code, payload = chef.post(f"/restaurant/orders/{order_id}/{action}", {})
            wf[f"order_{action}"] = {"code": code, "body": str(payload)[:200]}
            time.sleep(0.2)

    if order_id:
        code, payload = caisse.post(f"/restaurant/orders/{order_id}/payments", {
            "method": "cash", "amount_minor": int(price) * 2,
        })
        wf["order_payment"] = {"code": code, "body": str(payload)[:300]}

    code, payload = serveur.post("/restaurant/reservations", {
        "branch_id": branch_id, "customer_name": "Client Recette",
        "customer_phone": "+237600000099", "date": time.strftime("%Y-%m-%d"),
        "time": "19:30", "covers": 4,
    })
    wf["reservation_create"] = {"code": code, "body": str(payload)[:300]}

    code, payload = principal.post("/restaurant/reviews", {
        "branch_id": branch_id, "rating": 5, "comment": "Service impeccable (recette).",
        "customer_name": "Client Recette",
    })
    wf["review_create"] = {"code": code, "body": str(payload)[:300]}

    code, payload = chef.post("/restaurant/inventory-movements", {
        "branch_id": branch_id, "type": "waste", "quantity": 1,
        "reason": "Perte service (recette)",
    })
    wf["stock_movement"] = {"code": code, "body": str(payload)[:300]}

    if pos_id:
        code, payload = caisse.post(f"/restaurant/pos-sessions/{pos_id}/close", {"closing_float_minor": 5000000})
        wf["pos_close"] = {"code": code, "body": str(payload)[:300]}

    code, payload, _ = call("GET", "/restaurant/public/menu?slug=chez-leo-recette")
    results["public"]["menu"] = {"code": code, "body": str(payload)[:300]}

    with open(out_path, "w", encoding="utf-8") as handle:
        json.dump(results, handle, indent=2, ensure_ascii=False)
    print("written", out_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
