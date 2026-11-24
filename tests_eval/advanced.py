#!/usr/bin/env python3
"""Flux avances: annulation, remboursement, paiements, PDV, mobile, corporate, fidelite."""
import json, time, urllib.request, urllib.error, datetime, random

API = "http://localhost:8000/api/v1"
R = []
SUF = str(random.randint(100000,999999))

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
    print(f"{flag}{st:>4} {method:<6} {path:<52} {step}")

def j(raw):
    try: return json.loads(raw)
    except: return {}

def did(raw):
    d = j(raw).get("data", {})
    return d.get("id") if isinstance(d, dict) else None

tok = login("ahmed.benali@techcorp-algerie.dz")
today = datetime.date.today()
dep = (today + datetime.timedelta(days=20)).isoformat()
dep2 = (today + datetime.timedelta(days=25)).isoformat()
frm = (today - datetime.timedelta(days=30)).isoformat(); to = (today + datetime.timedelta(days=30)).isoformat()

# setup: carrier/class/vehicle/route/trip/price/publish
st, raw, ms = req("GET", "/travel/cities", tok); cities = j(raw).get("data", [])
if isinstance(cities, dict): cities = cities.get("data", [])
allc = [c["id"] for c in cities]; random.shuffle(allc); cid = allc[:4]
st, raw, ms = req("POST", "/travel/carriers", tok, {"code": f"CAR-ADV-{SUF}", "name": f"Transport ADV {SUF}", "type": "bus"}); carrier=did(raw)
st, raw, ms = req("POST", "/travel/classes", tok, {"code": f"CLS-ADV-{SUF}", "label": "VIP", "priority": 2}); cls=did(raw)
st, raw, ms = req("POST", "/travel/vehicles", tok, {"code": f"VEH-ADV-{SUF}", "seat_capacity": 40, "seat_layout": {"seats_per_row": 4, "aisle_after": 2}, "carrier_id": carrier}); veh=did(raw)
st, raw, ms = req("POST", "/travel/routes", tok, {"code": f"RTE-ADV-{SUF}", "origin_city_id": cid[0], "destination_city_id": cid[1], "distance_km": 200, "duration_min": 180}); rte=did(raw)
st, raw, ms = req("POST", "/travel/trips", tok, {"code": f"TRP-ADV-{SUF}", "route_id": rte, "carrier_id": carrier, "vehicle_id": veh, "departure_date": dep, "departure_time": "09:00", "arrival_date": dep, "arrival_time": "12:00", "means_of_transport": "bus", "total_seats": 40}); trip=did(raw)
st, raw, ms = req("POST", f"/travel/trips/{trip}/prices", tok, {"class_id": cls, "adult_price_minor": 300000, "currency": "DZD"}); rec("tarif","POST",f"/travel/trips/{trip}/prices",st,ms,raw)
st, raw, ms = req("POST", f"/travel/trips/{trip}/publish", tok); rec("publier","POST",f"/travel/trips/{trip}/publish",st,ms,raw)

# booking + confirm + issue
st, raw, ms = req("POST", "/travel/bookings", tok, {"trip_id": trip, "booking_source": "office", "idempotency_key": f"adv-bk-{SUF}", "passengers": [{"full_name": "Client ADV", "age_category": "adult", "class_id": cls, "seat_number": 1}]}); bk=did(raw)
st, raw, ms = req("POST", f"/travel/bookings/{bk}/confirm", tok); rec("confirmer","POST",f"/travel/bookings/{bk}/confirm",st,ms,raw)
st, raw, ms = req("POST", f"/travel/bookings/{bk}/issue-ticket", tok); rec("emettre billets","POST",f"/travel/bookings/{bk}/issue-ticket",st,ms,raw)
tickets = j(raw).get("data", [])
ticket = tickets[0].get("id") if isinstance(tickets, list) and tickets else (tickets.get("id") if isinstance(tickets, dict) else None)

# detail booking
st, raw, ms = req("GET", f"/travel/bookings/{bk}", tok); rec("detail reservation","GET",f"/travel/bookings/{bk}",st,ms,raw)

# paiement
st, raw, ms = req("POST", "/travel/payments/initiate", tok, {"booking_reference": j(req("GET", f"/travel/bookings/{bk}", tok)[1]).get("data", {}).get("reference"), "provider_code": "cash", "amount_minor": 300000, "idempotency_key": f"adv-pay-{SUF}"}); rec("initier paiement","POST","/travel/payments/initiate",st,ms,raw); pay=did(raw)
if pay:
    st, raw, ms = req("GET", f"/travel/payments/{pay}", tok); rec("statut paiement","GET",f"/travel/payments/{pay}",st,ms,raw)
    st, raw, ms = req("POST", f"/travel/payments/{pay}/verify", tok, {"idempotency_key": f"adv-pv-{SUF}"}); rec("verifier paiement","POST",f"/travel/payments/{pay}/verify",st,ms,raw)

# remboursement
st, raw, ms = req("POST", f"/travel/bookings/{bk}/refund", tok, {"reason": "Test eval", "idempotency_key": f"adv-rf-{SUF}"}); rec("rembourser reservation","POST",f"/travel/bookings/{bk}/refund",st,ms,raw)

# annulation booking (2e booking)
st, raw, ms = req("POST", "/travel/bookings", tok, {"trip_id": trip, "booking_source": "phone", "idempotency_key": f"adv-bk2-{SUF}", "passengers": [{"full_name": "Client ADV2", "age_category": "adult", "class_id": cls, "seat_number": 2}]}); bk2=did(raw)
st, raw, ms = req("POST", f"/travel/bookings/{bk2}/cancel", tok, {"reason": "Annulation test"}); rec("annuler reservation","POST",f"/travel/bookings/{bk2}/cancel",st,ms,raw)

# annulation trajet
st, raw, ms = req("POST", f"/travel/trips/{trip}/cancel", tok, {"reason": "Test eval"}); rec("annuler trajet","POST",f"/travel/trips/{trip}/cancel",st,ms,raw)

# check-in + manifeste
if ticket:
    st, raw, ms = req("POST", f"/travel/tickets/{ticket}/check-in", tok); rec("check-in","POST",f"/travel/tickets/{ticket}/check-in",st,ms,raw)
st, raw, ms = req("GET", f"/travel/trips/{trip}/manifest", tok); rec("manifeste","GET",f"/travel/trips/{trip}/manifest",st,ms,raw)

# rapports + export
for name, path in [("rapport ventes", f"/travel/reports/sales?from={frm}&to={to}"),("rapport occupation", f"/travel/reports/occupancy?from={frm}&to={to}"),("rapport revenus", f"/travel/reports/revenue?from={frm}&to={to}"),("rapport annulations", f"/travel/reports/cancellations?from={frm}&to={to}")]:
    st, raw, ms = req("GET", path, tok); rec(name,"GET",path.split("?")[0],st,ms,raw)
st, raw, ms = req("POST", "/travel/reports/export", tok, {"report_type": "sales", "from": frm, "to": to, "idempotency_key": f"adv-exp-{SUF}"}); rec("export rapport","POST","/travel/reports/export",st,ms,raw)

# PDV
st, raw, ms = req("POST", "/travel/pdv/session/open", tok, {"opening_float_minor": 0}); rec("ouvrir PDV","POST","/travel/pdv/session/open",st,ms,raw)
st, raw, ms = req("GET", "/travel/pdv/session/current", tok); rec("PDV courante","GET","/travel/pdv/session/current",st,ms,raw)
st, raw, ms = req("POST", "/travel/pdv/session/close", tok, {"actual_balance_minor": 0}); rec("fermer PDV","POST","/travel/pdv/session/close",st,ms,raw)

# mobile sync
st, raw, ms = req("POST", "/travel/mobile/sync", tok, {"operations": [{"type": "ping", "idempotency_key": f"adv-sync-{SUF}", "payload": {"note": "x"}}]}); rec("sync mobile","POST","/travel/mobile/sync",st,ms,raw)

# fidelite
st, raw, ms = req("POST", "/travel/loyalty/opt-in", tok, {"contact_identifier": f"loy{SUF}@e.dz", "full_name": "Loyal"}); rec("fidelite opt-in","POST","/travel/loyalty/opt-in",st,ms,raw)
st, raw, ms = req("GET", f"/travel/loyalty/account?contact_identifier=loy{SUF}@e.dz", tok); rec("compte fidelite","GET","/travel/loyalty/account",st,ms,raw)
st, raw, ms = req("GET", "/travel/loyalty/rewards", tok); rec("recompenses fidelite","GET","/travel/loyalty/rewards",st,ms,raw)

# corporate
st, raw, ms = req("POST", "/travel/corporate-accounts", tok, {"name": f"Corp {SUF}", "contact_email": f"corp{SUF}@e.dz", "credit_limit_minor": 5000000}); rec("compte corporate","POST","/travel/corporate-accounts",st,ms,raw); corp=did(raw)
if corp:
    st, raw, ms = req("POST", "/travel/corporate-quotes", tok, {"corporate_account_id": corp, "trip_id": trip, "class_id": cls, "passengers_count": 3}); rec("devis corporate","POST","/travel/corporate-quotes",st,ms,raw)

# staff assignment
st, raw, ms = req("POST", "/travel/staff-assignments", tok, {"trip_id": trip, "employee_id": 1, "role": "driver"}); rec("affecter equipage","POST","/travel/staff-assignments",st,ms,raw)

# fleet
st, raw, ms = req("GET", "/travel/fleet/tracking", tok); rec("suivi flotte","GET","/travel/fleet/tracking",st,ms,raw)
if veh:
    st, raw, ms = req("POST", f"/travel/vehicles/{veh}/position", tok, {"latitude": 36.75, "longitude": 3.05, "source": "gps"}); rec("position vehicule","POST",f"/travel/vehicles/{veh}/position",st,ms,raw)

# webhook + distributor + partner
st, raw, ms = req("POST", "/travel/webhook-subscriptions", tok, {"url": "https://example.com/hook", "events": ["travel.booking.confirmed.v1"]}); rec("webhook","POST","/travel/webhook-subscriptions",st,ms,raw)
st, raw, ms = req("POST", "/travel/distributor-keys", tok, {"name": f"Dist {SUF}", "scopes": ["catalog.read"]}); rec("cle distributeur","POST","/travel/distributor-keys",st,ms,raw)
st, raw, ms = req("POST", "/travel/partner-keys", tok, {"carrier_id": carrier, "name": f"Part {SUF}"}); rec("cle partenaire","POST","/travel/partner-keys",st,ms,raw)

# contenu
st, raw, ms = req("POST", "/travel/articles", tok, {"title": f"Art {SUF}", "body": "x", "status": "draft"}); rec("article","POST","/travel/articles",st,ms,raw)
st, raw, ms = req("POST", "/travel/tourist-sites", tok, {"name": f"Site {SUF}", "city_id": cid[0], "description": "x"}); rec("site touristique","POST","/travel/tourist-sites",st,ms,raw)
st, raw, ms = req("POST", "/travel/quizzes", tok, {"title": f"Quiz {SUF}", "status": "draft"}); rec("quiz","POST","/travel/quizzes",st,ms,raw)
st, raw, ms = req("POST", "/travel/advert-types", tok, {"code": f"adv_{SUF}", "label": "Banniere"}); rec("type annonce","POST","/travel/advert-types",st,ms,raw)
st, raw, ms = req("POST", "/travel/contact", tok, {"name": "Prospect", "email": f"p{SUF}@e.dz", "message": "Bonjour, je souhaite un devis pour un groupe.", "consent": True}); rec("contact","POST","/travel/contact",st,ms,raw)

# multi-devise
st, raw, ms = req("POST", "/travel/currency-rates", tok, {"from_currency": "GBP", "to_currency": "DZD", "rate_minor": 17000, "valid_from": today.isoformat()}); rec("taux devise","POST","/travel/currency-rates",st,ms,raw)
st, raw, ms = req("GET", "/travel/currency-rates/convert?from=GBP&to=DZD&amount_minor=10000", tok); rec("convertir devise","GET","/travel/currency-rates/convert",st,ms,raw)

# correspondances
st, raw, ms = req("GET", f"/travel/trips/connections?origin_city_id={cid[0]}&destination_city_id={cid[1]}&date={dep}", tok); rec("correspondances","GET","/travel/trips/connections",st,ms,raw)

# recherche flexible
st, raw, ms = req("GET", f"/travel/trips/search?origin_city_id={cid[0]}&destination_city_id={cid[1]}&date={dep}&flex_days=3", tok); rec("recherche flexible","GET","/travel/trips/search",st,ms,raw)

with open("/workspace/leopardo-hr/tests_eval/advanced_results.json", "w") as f:
    json.dump(R, f, indent=2, ensure_ascii=False)
ok = sum(1 for x in R if x["status"] in (200,201,202,204))
print(f"\n{ok}/{len(R)} OK")
