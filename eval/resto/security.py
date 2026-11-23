#!/usr/bin/env python3
"""Securite & cloisonnement du tenant restaurateur.

- R9 : la succursale privee (BR-NORD) ne doit pas etre visible/operable par
  un profil non assigne.
- Isolation inter-tenant : un jeton d'un autre tenant ne doit pas lire les
  donnees du restaurant.
- Commande en ligne publique (route signee).
"""
from __future__ import annotations

import json
import time

from harness import Session, call

OUT = "eval/resto/security.json"


def main():
    sec = {}
    principal = Session("principal")
    serveur = Session("serveur")
    livreur = Session("livreur")

    # Succursales visibles par chaque profil
    for name, s in (("principal", principal), ("serveur", serveur), ("livreur", livreur)):
        code, payload = s.get("/restaurant/branches")
        ids = []
        d = payload.get("data") if isinstance(payload, dict) else None
        if isinstance(d, dict):
            d = d.get("data") or d.get("items")
        if isinstance(d, list):
            ids = [b.get("id") for b in d]
        sec[f"branches_visible_{name}"] = {"code": code, "ids": ids}

    # La succursale privee : lecture directe
    code, payload = principal.get("/restaurant/branches")
    all_ids = []
    d = payload.get("data") if isinstance(payload, dict) else None
    if isinstance(d, dict):
        d = d.get("data") or d.get("items")
    if isinstance(d, list):
        all_ids = [b.get("id") for b in d]
    sec["all_branch_ids"] = all_ids

    # Tentative d'ouverture de caisse sur une succursale non assignee
    if len(all_ids) > 1:
        other = all_ids[-1]
        code, payload = serveur.post("/restaurant/pos-sessions", {
            "branch_id": int(other), "opening_cash_minor": 100000,
        })
        sec["pos_open_unassigned_branch"] = {"code": code, "body": str(payload)[:250]}

    # Isolation inter-tenant : jeton d'un autre tenant (techcorp) sur les routes resto
    code, payload, _ = call("POST", "/auth/login", body={
        "email": "principal@techcorp.example.com", "password": "recette123",
    })
    if code == 200 and isinstance(payload, dict) and payload.get("token"):
        other_token = payload["token"]
        for path in ("/restaurant/branches", "/restaurant/orders", "/restaurant/products"):
            c, p, _ = call("GET", path, other_token)
            sec[f"cross_tenant{path}"] = {"code": c, "body": str(p)[:200]}
    else:
        sec["cross_tenant_login"] = {"code": code, "body": str(payload)[:200]}

    # Commande en ligne publique
    code, payload = principal.post(f"/restaurant/branches/{all_ids[0]}/public-menu-link", {})
    menu_url = ""
    if isinstance(payload, dict):
        d = payload.get("data") or payload
        if isinstance(d, dict):
            menu_url = d.get("menu_url") or ""
    sec["public_menu_url_ok"] = bool(menu_url)

    if menu_url:
        path = menu_url.split("/api/v1", 1)[-1]
        code, payload, _ = call("GET", path)
        sec["public_menu"] = {"code": code}
        # Commande publique : tenter une commande en ligne
        products = []
        if isinstance(payload, dict):
            d = payload.get("data") or payload
            for b in (d.get("branches") or []) if isinstance(d, dict) else []:
                for m in (b.get("menus") or []):
                    for it in (m.get("items") or []):
                        products.append(it)
        sec["public_menu_items"] = len(products)
        if products:
            first = products[0]
            code, payload, _ = call("POST", "/restaurant/public/orders", body={
                "branch_id": int(all_ids[0]),
                "order_type": "takeaway",
                "customer_name": "Client Web",
                "customer_phone": "+237600000123",
                "items": [{"product_id": int(first.get("product_id") or first.get("id")), "quantity": 1}],
            })
            sec["public_order"] = {"code": code, "body": str(payload)[:300]}

    with open(OUT, "w", encoding="utf-8") as handle:
        json.dump(sec, handle, indent=2, ensure_ascii=False)
    for k, v in sec.items():
        print(f"{k:38s} {v if not isinstance(v, dict) else v.get('code')}")
        if isinstance(v, dict) and v.get("body"):
            print("   ", v["body"][:200])
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
