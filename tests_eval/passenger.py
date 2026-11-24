#!/usr/bin/env python3
"""Parcours passager public + compte client marketplace."""
import json, time, urllib.request, urllib.error, datetime, random

API = "http://localhost:8000/api/v1"
R = []
SUF = str(random.randint(10000,99999))

def req(method, path, token=None, body=None, headers=None):
    url = API + path
    data = json.dumps(body).encode() if body is not None else None
    h = {"Accept": "application/json"}
    if body is not None: h["Content-Type"] = "application/json"
    if token: h["Authorization"] = "Bearer " + token
    if headers: h.update(headers)
    r = urllib.request.Request(url, data=data, headers=h, method=method)
    t0 = time.time()
    try:
        with urllib.request.urlopen(r, timeout=30) as resp:
            return resp.status, resp.read().decode("utf-8", "replace"), round((time.time()-t0)*1000)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), round((time.time()-t0)*1000)
    except Exception as e:
        return 0, str(e), round((time.time()-t0)*1000)

def login(email):
    st, raw, _ = req("POST", "/auth/login", body={"email": email, "password": "password123"})
    return json.loads(raw).get("token") if st == 200 else None

def rec(step, method, path, st, ms, raw, note=""):
    R.append({"step": step, "method": method, "path": path, "status": st, "ms": ms, "snippet": raw[:600], "note": note})
    flag = "OK " if st in (200,201,202,204) else "!! "
    print(f"{flag}{st:>4} {method:<6} {path:<50} {step}")

def j(raw):
    try: return json.loads(raw)
    except: return {}

tok = login("ahmed.benali@techcorp-algerie.dz")
st, raw, ms = req("POST", "/travel/public-shop-token/rotate", tok)
SHOP = j(raw).get("data", {}).get("token")
H = {"X-Travel-Shop-Token": SHOP}
print("shop token:", (SHOP or "")[:16])

# 1. recherche publique
st, raw, ms = req("GET", "/public/travel/shop/trips", headers=H); rec("boutique: recherche", "GET", "/public/travel/shop/trips", st, ms, raw)
trips = j(raw).get("data", [])
sel = next((t for t in trips if t.get("prices")), trips[0] if trips else None)
trip = sel["id"] if sel else None
sel_class = sel["prices"][0]["class_id"] if sel and sel.get("prices") else 1
print("trips:", len(trips), "trip:", trip)

# 2. detail trajet
if trip:
    st, raw, ms = req("GET", f"/public/travel/shop/trips/{trip}", headers=H); rec("boutique: detail trajet", "GET", f"/public/travel/shop/trips/{trip}", st, ms, raw)

# 3. reservation en ligne (passager)
booking_ref = None
if trip:
    st, raw, ms = req("POST", "/public/travel/shop/bookings", headers=H, body={
        "trip_id": trip, "idempotency_key": f"pub-{SUF}",
        "contact_email": f"passager{SUF}@mail.dz", "contact_phone": "+213555000111",
        "notify_consent": True,
        "passengers": [{"full_name": "Passager Public", "age_category": "adult", "class_id": sel_class, "seat_number": random.randint(21, 45)}]
    })
    rec("boutique: reserver en ligne", "POST", "/public/travel/shop/bookings", st, ms, raw)
    booking_ref = j(raw).get("data", {}).get("reference")
    booking_code = j(raw).get("data", {}).get("code") or j(raw).get("data", {}).get("ticket_code")

# 4. suivi par reference
if booking_ref:
    st, raw, ms = req("GET", f"/public/travel/shop/bookings/{booking_ref}?code={booking_code}", headers=H); rec("boutique: suivi reservation", "GET", f"/public/travel/shop/bookings/{booking_ref}", st, ms, raw)

# 5. paiement en ligne
if booking_ref:
    st, raw, ms = req("POST", "/public/travel/payments/initiate", headers=H, body={"booking_reference": booking_ref, "provider_code": "cash", "idempotency_key": f"pay-{SUF}"})
    rec("boutique: initier paiement", "POST", "/public/travel/payments/initiate", st, ms, raw)

# 6. compte client marketplace
st, raw, ms = req("POST", "/public/travel/marketplace/account/register", body={
    "email": f"client{SUF}@mail.dz", "password": "Password123!Secure", "name": "Client Market"})
rec("marketplace: inscription client", "POST", "/public/travel/marketplace/account/register", st, ms, raw)
ctok = j(raw).get("token") or j(raw).get("data", {}).get("token")

st, raw, ms = req("POST", "/public/travel/marketplace/account/login", body={"email": f"client{SUF}@mail.dz", "password": "Password123!Secure"})
rec("marketplace: connexion client", "POST", "/public/travel/marketplace/account/login", st, ms, raw)
ctok = ctok or j(raw).get("token") or j(raw).get("data", {}).get("token")

if ctok:
    st, raw, ms = req("GET", "/public/travel/marketplace/account/me", ctok); rec("marketplace: profil client", "GET", "/public/travel/marketplace/account/me", st, ms, raw)
    st, raw, ms = req("GET", "/public/travel/marketplace/account/bookings", ctok); rec("marketplace: mes reservations", "GET", "/public/travel/marketplace/account/bookings", st, ms, raw)

# 7. marketplace: detail + reservation
st, raw, ms = req("GET", "/public/travel/marketplace/trips", headers={}); rec("marketplace: recherche", "GET", "/public/travel/marketplace/trips", st, ms, raw)
mtrips = j(raw).get("data", [])
if mtrips:
    mt = mtrips[0]["id"]
    st, raw, ms = req("GET", f"/public/travel/marketplace/trips/{mt}"); rec("marketplace: detail trajet", "GET", f"/public/travel/marketplace/trips/{mt}", st, ms, raw)
    st, raw, ms = req("POST", "/public/travel/marketplace/bookings", body={
        "trip_id": next((t["id"] for t in mtrips if t.get("prices")), mt), "idempotency_key": f"mkt-{SUF}", "contact_email": f"mkt{SUF}@mail.dz",
        "passengers": [{"full_name": "Client Market", "age_category": "adult", "class_id": (next((t["prices"][0]["class_id"] for t in mtrips if t.get("prices")), 1))}]})
    rec("marketplace: reserver", "POST", "/public/travel/marketplace/bookings", st, ms, raw)

with open("/workspace/leopardo-hr/tests_eval/passenger_results.json", "w") as f:
    json.dump(R, f, indent=2, ensure_ascii=False)
ok = sum(1 for x in R if x["status"] in (200,201,202,204))
print(f"\n{ok}/{len(R)} OK")
