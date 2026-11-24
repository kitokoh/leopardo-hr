#!/usr/bin/env python3
"""E2E TravelAgency v2 - payloads corriges + couverture elargie."""
import json, time, urllib.request, urllib.error, datetime, random
SUF = str(random.randint(1000,9999))

API = "http://localhost:8000/api/v1"
R = []

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
    R.append({"step": step, "method": method, "path": path, "status": st, "ms": ms,
              "snippet": raw[:600], "note": note})
    flag = "OK " if st in (200,201,204) else "!! "
    print(f"{flag}{st:>4} {method:<6} {path:<52} {step}")

def j(raw):
    try: return json.loads(raw)
    except: return {}

def did(raw):
    d = j(raw).get("data", {})
    return d.get("id") if isinstance(d, dict) else None

tok = login("ahmed.benali@techcorp-algerie.dz")
print("token:", "OK" if tok else "FAIL")
today = datetime.date.today()
dep = (today + datetime.timedelta(days=5)).isoformat()
dep2 = (today + datetime.timedelta(days=12)).isoformat()
frm = (today - datetime.timedelta(days=30)).isoformat(); to = (today + datetime.timedelta(days=30)).isoformat()

# referentiel
st, raw, ms = req("GET", "/travel/cities", tok)
cities = j(raw).get("data", [])
if isinstance(cities, dict): cities = cities.get("data", [])
import random as _r
allc=[c["id"] for c in cities]
_r.shuffle(allc)
cid = allc[:4]

st, raw, ms = req("POST", "/travel/carriers", tok, {"code": f"CAR-E2-{SUF}", "name": f"Transport E2 {SUF}", "type": "bus"}); rec("creer transporteur","POST","/travel/carriers",st,ms,raw); carrier=did(raw)
st, raw, ms = req("POST", "/travel/classes", tok, {"code": f"CLS-E2-{SUF}", "label": "Ordinaire", "priority": 1}); rec("creer classe","POST","/travel/classes",st,ms,raw); cls=did(raw)
st, raw, ms = req("POST", "/travel/vehicles", tok, {"code": f"VEH-E2-{SUF}", "seat_capacity": 50, "seat_layout": {"seats_per_row": 4, "aisle_after": 2}, "carrier_id": carrier}); rec("creer vehicule","POST","/travel/vehicles",st,ms,raw); veh=did(raw)
st, raw, ms = req("POST", "/travel/routes", tok, {"code": f"RTE-E2-{SUF}", "origin_city_id": cid[2], "destination_city_id": cid[3], "distance_km": 300, "duration_min": 240}); rec("creer ligne","POST","/travel/routes",st,ms,raw); rte=did(raw)
st, raw, ms = req("POST", f"/travel/routes/{rte}/stops", tok, {"city_id": cid[2], "rank": 1}); rec("ajouter arret","POST",f"/travel/routes/{rte}/stops",st,ms,raw)

# trips (2 pour aller-retour)
st, raw, ms = req("POST", "/travel/trips", tok, {"code": f"TRP-E2A-{SUF}", "route_id": rte, "carrier_id": carrier, "vehicle_id": veh, "departure_date": dep, "departure_time": "08:00", "arrival_date": dep, "arrival_time": "12:00", "means_of_transport": "bus", "total_seats": 50}); rec("creer voyage A","POST","/travel/trips",st,ms,raw); trip=did(raw)
st, raw, ms = req("POST", "/travel/trips", tok, {"code": f"TRP-E2B-{SUF}", "route_id": rte, "carrier_id": carrier, "vehicle_id": veh, "departure_date": dep2, "departure_time": "08:00", "arrival_date": dep2, "arrival_time": "12:00", "means_of_transport": "bus", "total_seats": 50}); rec("creer voyage B","POST","/travel/trips",st,ms,raw); trip2=did(raw)

# prices
st, raw, ms = req("POST", f"/travel/trips/{trip}/prices", tok, {"class_id": cls, "adult_price_minor": 250000, "child_price_minor": 150000, "currency": "DZD"}); rec("creer tarif A","POST",f"/travel/trips/{trip}/prices",st,ms,raw)
st, raw, ms = req("POST", f"/travel/trips/{trip2}/prices", tok, {"class_id": cls, "adult_price_minor": 250000, "currency": "DZD"}); rec("creer tarif B","POST",f"/travel/trips/{trip2}/prices",st,ms,raw)

# publish
st, raw, ms = req("POST", f"/travel/trips/{trip}/publish", tok); rec("publier voyage A","POST",f"/travel/trips/{trip}/publish",st,ms,raw)
st, raw, ms = req("POST", f"/travel/trips/{trip2}/publish", tok); rec("publier voyage B","POST",f"/travel/trips/{trip2}/publish",st,ms,raw)

# seats / search / connections
st, raw, ms = req("GET", f"/travel/trips/{trip}/seats", tok); rec("plan de sieges","GET",f"/travel/trips/{trip}/seats",st,ms,raw)
st, raw, ms = req("GET", f"/travel/trips/search?origin_city_id={cid[0]}&destination_city_id={cid[1]}&date={dep}", tok); rec("recherche voyage","GET","/travel/trips/search",st,ms,raw)
st, raw, ms = req("GET", f"/travel/trips/connections?origin_city_id={cid[0]}&destination_city_id={cid[1]}&date={dep}", tok); rec("correspondances","GET","/travel/trips/connections",st,ms,raw)

# booking
st, raw, ms = req("POST", "/travel/bookings", tok, {"trip_id": trip, "booking_source": "office", "idempotency_key": f"e2-bk-{SUF}", "passengers": [{"full_name": "Client E2", "age_category": "adult", "class_id": cls, "seat_number": 1}]}); rec("creer reservation","POST","/travel/bookings",st,ms,raw); bk=did(raw)
st, raw, ms = req("POST", f"/travel/bookings/{bk}/confirm", tok); rec("confirmer reservation","POST",f"/travel/bookings/{bk}/confirm",st,ms,raw)
st, raw, ms = req("POST", f"/travel/bookings/{bk}/issue-ticket", tok); rec("emettre billets","POST",f"/travel/bookings/{bk}/issue-ticket",st,ms,raw)
tickets = j(raw).get("data", [])
ticket = None
if isinstance(tickets, list) and tickets: ticket = tickets[0].get("id")
elif isinstance(tickets, dict): ticket = tickets.get("id")
st, raw, ms = req("GET", f"/travel/trips/{trip}/manifest", tok); rec("manifeste passagers","GET",f"/travel/trips/{trip}/manifest",st,ms,raw)
if ticket:
    st, raw, ms = req("POST", "/travel/tickets/verify", tok, {"ticket_id": ticket}); rec("verifier billet","POST","/travel/tickets/verify",st,ms,raw)
    st, raw, ms = req("POST", f"/travel/tickets/{ticket}/check-in", tok); rec("check-in billet","POST",f"/travel/tickets/{ticket}/check-in",st,ms,raw)
    st, raw, ms = req("GET", f"/travel/tickets/{ticket}/pdf", tok); rec("PDF billet","GET",f"/travel/tickets/{ticket}/pdf",st,ms,raw)
    st, raw, ms = req("GET", f"/travel/tickets/{ticket}/print-events", tok); rec("historique impression","GET",f"/travel/tickets/{ticket}/print-events",st,ms,raw)

# reports
for name, path in [("rapport ventes", f"/travel/reports/sales?from={frm}&to={to}"),("rapport occupation", f"/travel/reports/occupancy?from={frm}&to={to}"),("rapport revenus", f"/travel/reports/revenue?from={frm}&to={to}"),("rapport annulations", f"/travel/reports/cancellations?from={frm}&to={to}"),("rapport dashboard", f"/travel/reports/dashboard?from={frm}&to={to}"),("rapport agences", f"/travel/reports/offices?from={frm}&to={to}")]:
    st, raw, ms = req("GET", path, tok); rec(name,"GET",path.split("?")[0],st,ms,raw)
st, raw, ms = req("POST", "/travel/reports/export", tok, {"report_type": "sales", "from": frm, "to": to, "idempotency_key": f"e2-exp-{SUF}"}); rec("export rapport","POST","/travel/reports/export",st,ms,raw)

# quote (min 5 passagers)
pax = [{"full_name": f"Groupe {i}", "age_category": "adult", "class_id": cls} for i in range(5)]
st, raw, ms = req("POST", "/travel/quotes", tok, {"trip_id": trip, "idempotency_key": f"e2-q-{SUF}", "passengers": pax}); rec("creer devis groupe","POST","/travel/quotes",st,ms,raw); quote=did(raw)
if quote:
    st, raw, ms = req("POST", f"/travel/quotes/{quote}/book", tok, {"idempotency_key": f"e2-qb-{SUF}"}); rec("devis -> reservation","POST",f"/travel/quotes/{quote}/book",st,ms,raw)

# round-trip
st, raw, ms = req("POST", "/travel/round-trips", tok, {"outbound_trip_id": trip, "return_trip_id": trip2, "booking_source": "office", "idempotency_key": f"e2-rt-{SUF}", "passengers": [{"full_name": "AR Client", "age_category": "adult", "class_id": cls}]}); rec("aller-retour","POST","/travel/round-trips",st,ms,raw)

# hotel
st, raw, ms = req("POST", "/travel/hotels", tok, {"name": f"Hotel E2 {SUF}", "city_id": cid[0], "classification": 3}); rec("creer hotel","POST","/travel/hotels",st,ms,raw); hotel=did(raw)
if hotel:
    st, raw, ms = req("POST", f"/travel/hotels/{hotel}/rooms", tok, {"type_code": "double", "room_number": "101", "capacity": 2, "price_per_night_minor": 800000, "currency": "DZD"}); rec("creer chambre","POST",f"/travel/hotels/{hotel}/rooms",st,ms,raw)

# rental
st, raw, ms = req("POST", "/travel/rental-vehicles", tok, {"code": f"RV-E2-{SUF}", "title": "Toyota E2", "city_id": cid[0], "price_per_day_minor": 500000, "currency": "DZD"}); rec("creer vehicule location","POST","/travel/rental-vehicles",st,ms,raw); rv=did(raw)
if rv:
    st, raw, ms = req("POST", "/travel/rental-bookings", tok, {"vehicle_id": rv, "start_date": dep, "end_date": dep2, "idempotency_key": f"e2-rb-{SUF}"}); rec("reserver location","POST","/travel/rental-bookings",st,ms,raw)

# loyalty
st, raw, ms = req("POST", "/travel/loyalty/opt-in", tok, {"contact_identifier": f"client{SUF}@e2.dz", "full_name": "Client E2"}); rec("fidelite opt-in","POST","/travel/loyalty/opt-in",st,ms,raw)
st, raw, ms = req("GET", "/travel/loyalty/account?contact_identifier=client@e2.dz", tok); rec("compte fidelite","GET","/travel/loyalty/account",st,ms,raw)

# currency
st, raw, ms = req("POST", "/travel/currency-rates", tok, {"from_currency": "USD", "to_currency": "DZD", "rate_minor": 13500, "valid_from": today.isoformat()}); rec("creer taux devise","POST","/travel/currency-rates",st,ms,raw)
st, raw, ms = req("GET", "/travel/currency-rates/convert?from=EUR&to=DZD&amount_minor=10000", tok); rec("convertir devise","GET","/travel/currency-rates/convert",st,ms,raw)

# cancellation policy
st, raw, ms = req("POST", "/travel/cancellation-policies", tok, {"hours_before_departure": 24, "penalty_percent": 50, "refundable": True}); rec("creer politique annulation","POST","/travel/cancellation-policies",st,ms,raw)

# distributor key
st, raw, ms = req("POST", "/travel/distributor-keys", tok, {"name": f"Distributeur E2 {SUF}", "scopes": ["catalog.read"]}); rec("creer cle distributeur","POST","/travel/distributor-keys",st,ms,raw)

# webhook
st, raw, ms = req("POST", "/travel/webhook-subscriptions", tok, {"url": "https://example.com/hook", "events": ["travel.booking.confirmed.v1"]}); rec("creer webhook","POST","/travel/webhook-subscriptions",st,ms,raw)

# article + comment
st, raw, ms = req("POST", "/travel/articles", tok, {"title": f"Article E2 {SUF}", "body": "Contenu", "status": "draft"}); rec("creer article","POST","/travel/articles",st,ms,raw); art=did(raw)
if art:
    st, raw, ms = req("POST", f"/travel/articles/{art}/comments", tok, {"body": "Commentaire E2"}); rec("commenter article","POST",f"/travel/articles/{art}/comments",st,ms,raw)
    st, raw, ms = req("POST", f"/travel/articles/{art}/like", tok); rec("liker article","POST",f"/travel/articles/{art}/like",st,ms,raw)
    st, raw, ms = req("GET", f"/travel/articles/{art}/engagement", tok); rec("engagement article","GET",f"/travel/articles/{art}/engagement",st,ms,raw)

# tourist site
st, raw, ms = req("POST", "/travel/tourist-sites", tok, {"name": f"Site E2 {SUF}", "city_id": cid[0], "description": "Un site"}); rec("creer site touristique","POST","/travel/tourist-sites",st,ms,raw)

# quiz
st, raw, ms = req("POST", "/travel/quizzes", tok, {"title": f"Quiz E2 {SUF}", "status": "draft"}); rec("creer quiz","POST","/travel/quizzes",st,ms,raw); quiz=did(raw)
if quiz:
    st, raw, ms = req("POST", f"/travel/quizzes/{quiz}/questions", tok, {"question": "Capitale ?", "options": ["Alger", "Oran"], "correct_option_index": 0}); rec("ajouter question quiz","POST",f"/travel/quizzes/{quiz}/questions",st,ms,raw)

# advert type
st, raw, ms = req("POST", "/travel/advert-types", tok, {"code": f"adv_{SUF}", "label": "Banniere"}); rec("creer type annonce","POST","/travel/advert-types",st,ms,raw)

# contact
st, raw, ms = req("POST", "/travel/contact", tok, {"name": "Prospect E2", "email": f"prospect{SUF}@e2.dz", "message": "Bonjour, je souhaite un devis.", "consent": True}); rec("formulaire contact","POST","/travel/contact",st,ms,raw)

# staff assignment
st, raw, ms = req("POST", "/travel/staff-assignments", tok, {"trip_id": trip, "employee_id": 1, "role": "driver"}); rec("affecter equipage","POST","/travel/staff-assignments",st,ms,raw)

# PDV
st, raw, ms = req("POST", "/travel/pdv/session/open", tok, {"opening_float_minor": 0}); rec("ouvrir session PDV","POST","/travel/pdv/session/open",st,ms,raw)
st, raw, ms = req("GET", "/travel/pdv/session/current", tok); rec("session PDV courante","GET","/travel/pdv/session/current",st,ms,raw)

# public shop token
st, raw, ms = req("GET", "/travel/public-shop-token", tok); rec("jeton boutique publique","GET","/travel/public-shop-token",st,ms,raw)

# mobile sync
st, raw, ms = req("POST", "/travel/mobile/sync", tok, {"operations": [{"type": "ping", "idempotency_key": f"e2-sync-{SUF}", "payload": {"note": "ping"}}]}); rec("sync mobile","POST","/travel/mobile/sync",st,ms,raw)

# fleet
st, raw, ms = req("GET", "/travel/fleet/tracking", tok); rec("suivi flotte","GET","/travel/fleet/tracking",st,ms,raw)
if veh:
    st, raw, ms = req("POST", f"/travel/vehicles/{veh}/position", tok, {"latitude": 36.75, "longitude": 3.05, "source": "gps"}); rec("position vehicule","POST",f"/travel/vehicles/{veh}/position",st,ms,raw)
    st, raw, ms = req("GET", f"/travel/vehicles/{veh}/positions", tok); rec("historique positions","GET",f"/travel/vehicles/{veh}/positions",st,ms,raw)

# corporate
st, raw, ms = req("POST", "/travel/corporate-accounts", tok, {"name": f"Entreprise E2 {SUF}", "contact_email": f"corp{SUF}@e2.dz", "credit_limit_minor": 1000000}); rec("creer compte corporate","POST","/travel/corporate-accounts",st,ms,raw)

with open("/workspace/leopardo-hr/tests_eval/e2e2_results.json", "w") as f:
    json.dump(R, f, indent=2, ensure_ascii=False)
ok = sum(1 for x in R if x["status"] in (200,201,204))
print(f"\n{ok}/{len(R)} etapes OK")
