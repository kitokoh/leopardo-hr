#!/usr/bin/env python3
"""Parcours metier RestaurantManager (tenant resto-pilot-001).

Contrats d'API verifies dans les FormRequests du module :
- POST /restaurant/pos-sessions {branch_id, opening_cash_minor}
- POST /restaurant/orders {branch_id, order_type, table_id (si dine_in), covers}
- POST /restaurant/orders/{id}/submit|confirm|serve
- POST /restaurant/kitchen/orders/{id}/start|ready
- POST /restaurant/orders/{id}/pay {provider_code, amount_minor}
- POST /restaurant/orders/{id}/refund {amount_minor, reason_code}
- POST /restaurant/reservations {branch_id, contact_name, contact_phone, reserved_at, covers}
- POST /restaurant/inventory-movements {branch_id, ingredient_id, quantity_delta, reason_code}
- GET  /restaurant/public/menu (route signee)
"""
from __future__ import annotations

import json
import time
import urllib.parse

from harness import Session, call

OUT = "eval/resto/workflows.json"


def first_id(payload, *keys):
    d = payload.get("data") if isinstance(payload, dict) else None
    if isinstance(d, dict):
        d = d.get("data") or d.get("items") or d
    if isinstance(d, list) and d:
        d = d[0]
    if isinstance(d, dict):
        for k in keys or ("id",):
            if d.get(k) is not None:
                return str(d[k])
    return ""


def main():
    wf = {}
    principal = Session("principal")
    serveur = Session("serveur")
    caisse = Session("caisse")
    chef = Session("chef")
    gerant = Session("gerant")

    code, payload = principal.get("/restaurant/branches")
    branch_id = first_id(payload)
    wf["branch_id"] = branch_id

    code, payload = principal.get("/restaurant/tables")
    table_id = first_id(payload)
    wf["table_id"] = table_id

    code, payload = principal.get("/restaurant/ingredients")
    ingredient_id = first_id(payload)
    wf["ingredient_id"] = ingredient_id

    # 1. Ouverture de caisse (caissier)
    code, payload = caisse.post("/restaurant/pos-sessions", {
        "branch_id": int(branch_id), "opening_cash_minor": 5000000,
    })
    wf["pos_open"] = {"code": code, "body": str(payload)[:300]}
    pos_id = first_id(payload)

    # 2. Commande dine_in sur table (serveur)
    order_id = ""
    code, payload = serveur.post("/restaurant/orders", {
        "branch_id": int(branch_id), "order_type": "dine_in",
        "table_id": int(table_id) if table_id else None, "covers": 2,
        "pos_session_id": int(pos_id) if pos_id else None,
    })
    wf["order_create"] = {"code": code, "body": str(payload)[:400]}
    order_id = first_id(payload)

    # 3. Ajout d'articles
    code, payload = serveur.get("/restaurant/products")
    products = payload.get("data") if isinstance(payload, dict) else None
    if isinstance(products, dict):
        products = products.get("data") or products.get("items")
    products = products or []
    wf["products_count"] = len(products)
    if order_id and products:
        p = products[0]
        code, payload = serveur.post(f"/restaurant/orders/{order_id}/items", {
            "product_id": int(p["id"]), "quantity": 2,
        })
        wf["order_add_item"] = {"code": code, "body": str(payload)[:300]}

    # 4. Cycle de vie : submit -> confirm -> cuisine (start/ready) -> serve
    if order_id:
        code, payload = serveur.post(f"/restaurant/orders/{order_id}/submit", {})
        wf["order_submit"] = {"code": code, "body": str(payload)[:250]}
        code, payload = gerant.post(f"/restaurant/orders/{order_id}/confirm", {})
        wf["order_confirm"] = {"code": code, "body": str(payload)[:250]}
        code, payload = chef.post(f"/restaurant/kitchen/orders/{order_id}/start", {})
        wf["kitchen_start"] = {"code": code, "body": str(payload)[:250]}
        code, payload = chef.post(f"/restaurant/kitchen/orders/{order_id}/ready", {})
        wf["kitchen_ready"] = {"code": code, "body": str(payload)[:250]}
        code, payload = serveur.post(f"/restaurant/orders/{order_id}/serve", {})
        wf["order_serve"] = {"code": code, "body": str(payload)[:250]}

    # 5. Paiement du montant exact attendu
    if order_id:
        code, payload = caisse.get(f"/restaurant/orders/{order_id}")
        due = 0
        if isinstance(payload, dict):
            d = payload.get("data") or payload
            if isinstance(d, dict):
                due = d.get("total_minor") or d.get("balance_due_minor") or 0
        wf["order_due_minor"] = due
        code, payload = caisse.post(f"/restaurant/orders/{order_id}/pay", {
            "provider_code": "cash", "amount_minor": int(due),
        })
        wf["order_pay"] = {"code": code, "body": str(payload)[:300]}

    # 6. Remboursement partiel
    if order_id:
        code, payload = gerant.post(f"/restaurant/orders/{order_id}/refund", {
            "amount_minor": 100000, "reason_code": "customer_request",
            "reason_text": "Geste commercial (recette)",
        })
        wf["order_refund"] = {"code": code, "body": str(payload)[:300]}

    # 7. Reservation
    code, payload = serveur.post("/restaurant/reservations", {
        "branch_id": int(branch_id), "contact_name": "Client Recette",
        "contact_phone": "+237600000099",
        "reserved_at": time.strftime("%Y-%m-%dT19:30:00+01:00"), "covers": 4,
    })
    wf["reservation_create"] = {"code": code, "body": str(payload)[:300]}

    # 8. Mouvement de stock (perte) — geste de gestion
    if ingredient_id:
        code, payload = gerant.post("/restaurant/inventory-movements", {
            "branch_id": int(branch_id), "ingredient_id": int(ingredient_id),
            "quantity_delta": -1, "reason_code": "waste",
        })
        wf["stock_waste"] = {"code": code, "body": str(payload)[:300]}

    # 9. Cloture de caisse
    if pos_id:
        code, payload = caisse.post(f"/restaurant/pos-sessions/{pos_id}/close", {
            "closing_cash_minor": 5000000,
        })
        wf["pos_close"] = {"code": code, "body": str(payload)[:300]}

    # 10. Menu public : route signee -> on genere l'URL signee via le lien public
    code, payload = principal.post(f"/restaurant/branches/{branch_id}/public-menu-link", {})
    wf["public_menu_link"] = {"code": code, "body": str(payload)[:400]}
    signed_url = ""
    if isinstance(payload, dict):
        d = payload.get("data") or payload
        if isinstance(d, dict):
            signed_url = d.get("menu_url") or d.get("url") or d.get("signed_url") or ""
    if signed_url:
        path = signed_url.split("/api/v1", 1)[-1]
        code, payload, _ = call("GET", path)
        wf["public_menu_fetch"] = {"code": code, "body": str(payload)[:300]}

    with open(OUT, "w", encoding="utf-8") as handle:
        json.dump(wf, handle, indent=2, ensure_ascii=False)
    for k, v in wf.items():
        print(f"{k:22s} {v if not isinstance(v, dict) else v.get('code')}")
        if isinstance(v, dict) and v.get("body"):
            print("   ", v["body"][:200])
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
