#!/usr/bin/env python3
"""Harnais d'evaluation TravelAgency - Leopardo.
Teste les endpoints /api/v1/travel/* par profil utilisateur et enregistre
les resultats (statut HTTP, latence, extrait de reponse) dans un JSON.
"""
import json, time, urllib.request, urllib.error, sys, os

API = "http://localhost:8000/api/v1"
RESULTS = []

PERSONAS = {
    "principal": "ahmed.benali@techcorp-algerie.dz",
    "rh": "fatima.meziane@techcorp-algerie.dz",
    "dept": "samir.boukhalfa@techcorp-algerie.dz",
    "comptable": "lina.haddad@techcorp-algerie.dz",
    "superviseur": "nassim.cheriet@techcorp-algerie.dz",
    "employe": "karim.aouad@techcorp-algerie.dz",
}

def req(method, path, token=None, body=None, headers=None):
    url = API + path
    data = json.dumps(body).encode() if body is not None else None
    h = {"Accept": "application/json"}
    if body is not None:
        h["Content-Type"] = "application/json"
    if token:
        h["Authorization"] = "Bearer " + token
    if headers:
        h.update(headers)
    r = urllib.request.Request(url, data=data, headers=h, method=method)
    t0 = time.time()
    try:
        with urllib.request.urlopen(r, timeout=30) as resp:
            raw = resp.read().decode("utf-8", "replace")
            return resp.status, raw, round((time.time()-t0)*1000)
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", "replace")
        return e.code, raw, round((time.time()-t0)*1000)
    except Exception as e:
        return 0, str(e), round((time.time()-t0)*1000)

def login(email):
    st, raw, ms = req("POST", "/auth/login", body={"email": email, "password": "password123"})
    if st == 200:
        d = json.loads(raw)
        return d.get("token"), d.get("data", {})
    return None, {"_error": raw[:300], "_status": st}

def record(persona, feature, method, path, status, ms, snippet, note=""):
    RESULTS.append({
        "persona": persona, "feature": feature, "method": method, "path": path,
        "status": status, "ms": ms, "snippet": snippet[:400], "note": note,
    })

def main():
    tokens = {}
    profiles = {}
    for p, email in PERSONAS.items():
        tok, data = login(email)
        tokens[p] = tok
        profiles[p] = {
            "role": data.get("role"), "manager_role": data.get("manager_role"),
            "features": data.get("features"), "capabilities": data.get("capabilities"),
            "module_grants": data.get("module_grants"),
        }
        print(f"[login] {p}: token={'OK' if tok else 'FAIL'} role={data.get('role')} mr={data.get('manager_role')}")

    with open("/workspace/leopardo-hr/tests_eval/profiles.json", "w") as f:
        json.dump(profiles, f, indent=2, ensure_ascii=False)

    # --- Tests de base par profil ---
    for p, tok in tokens.items():
        if not tok:
            continue
        st, raw, ms = req("GET", "/travel/ping", tok)
        record(p, "ping (smoke)", "GET", "/travel/ping", st, ms, raw)

    # --- Referentiel (principal) ---
    tok = tokens["principal"]
    for feat, path in [
        ("countries", "/travel/countries"),
        ("cities", "/travel/cities"),
        ("stations", "/travel/stations"),
        ("offices", "/travel/offices"),
        ("carriers", "/travel/carriers"),
        ("classes", "/travel/classes"),
        ("vehicles", "/travel/vehicles"),
        ("routes", "/travel/routes"),
        ("trips", "/travel/trips"),
        ("bookings", "/travel/bookings"),
        ("rental-vehicles", "/travel/rental-vehicles"),
        ("rental-bookings", "/travel/rental-bookings"),
        ("hotels", "/travel/hotels"),
        ("quotes", "/travel/quotes"),
        ("cancellation-policies", "/travel/cancellation-policies"),
        ("currency-rates", "/travel/currency-rates"),
        ("distributor-keys", "/travel/distributor-keys"),
        ("webhook-subscriptions", "/travel/webhook-subscriptions"),
        ("tourist-sites", "/travel/tourist-sites"),
        ("articles", "/travel/articles"),
        ("comments", "/travel/comments"),
        ("quizzes", "/travel/quizzes"),
        ("adverts", "/travel/adverts"),
        ("adverts/manage", "/travel/adverts/manage"),
        ("advert-types", "/travel/advert-types"),
        ("advert-positions", "/travel/advert-positions"),
        ("advert-prices", "/travel/advert-prices"),
        ("staff-assignments", "/travel/staff-assignments"),
        ("contacts", "/travel/contacts"),
        ("corporate-accounts", "/travel/corporate-accounts"),
        ("corporate-quotes", "/travel/corporate-quotes"),
        ("reports/dashboard", "/travel/reports/dashboard"),
        ("reports/sales", "/travel/reports/sales"),
        ("reports/occupancy", "/travel/reports/occupancy"),
        ("reports/revenue", "/travel/reports/revenue"),
        ("reports/cancellations", "/travel/reports/cancellations"),
        ("reports/offices", "/travel/reports/offices"),
        ("fleet/tracking", "/travel/fleet/tracking"),
        ("shop/trips", "/travel/shop/trips"),
        ("shop/connections", "/travel/shop/connections"),
        ("pdv/session/current", "/travel/pdv/session/current"),
        ("public-shop-token", "/travel/public-shop-token"),
        ("loyalty/account", "/travel/loyalty/account"),
        ("loyalty/rewards", "/travel/loyalty/rewards"),
        ("loyalty/entries", "/travel/loyalty/entries"),
        ("article-categories", "/travel/article-categories"),
    ]:
        st, raw, ms = req("GET", path, tok)
        record("principal", feat, "GET", path, st, ms, raw)

    # --- Controle d'acces: employe (non-manager) sur endpoints sensibles ---
    etok = tokens["employe"]
    for feat, method, path in [
        ("reports/dashboard (doit refuser)", "GET", "/travel/reports/dashboard"),
        ("stations create (doit refuser)", "POST", "/travel/stations"),
        ("bookings create (doit refuser)", "POST", "/travel/bookings"),
        ("distributor-keys (doit refuser)", "GET", "/travel/distributor-keys"),
    ]:
        st, raw, ms = req(method, path, etok, body={} if method == "POST" else None)
        record("employe", feat, method, path, st, ms, raw)

    # --- Controle d'acces: rh sur endpoints sensibles ---
    rtok = tokens["rh"]
    for feat, method, path in [
        ("reports/dashboard (rh)", "GET", "/travel/reports/dashboard"),
        ("distributor-keys (rh)", "GET", "/travel/distributor-keys"),
    ]:
        st, raw, ms = req(method, path, rtok)
        record("rh", feat, method, path, st, ms, raw)

    # --- Controle d'acces: comptable / dept / superviseur ---
    for p in ["comptable", "dept", "superviseur"]:
        t = tokens[p]
        st, raw, ms = req("GET", "/travel/reports/dashboard", t)
        record(p, "reports/dashboard", "GET", "/travel/reports/dashboard", st, ms, raw)
        st, raw, ms = req("GET", "/travel/trips", t)
        record(p, "trips (lecture)", "GET", "/travel/trips", st, ms, raw)

    with open("/workspace/leopardo-hr/tests_eval/results.json", "w") as f:
        json.dump(RESULTS, f, indent=2, ensure_ascii=False)
    print(f"\n{len(RESULTS)} tests enregistres -> results.json")

if __name__ == "__main__":
    main()
