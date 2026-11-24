#!/usr/bin/env python3
"""Test end-to-end complet de la verticale TravelAgency (metier: responsable d'agence).
Cree la chaine metier complete via l'API et verifie chaque etape.
"""
import json, time, urllib.request, urllib.error, datetime

API = "http://localhost:8000/api/v1"
R = []

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
              "snippet": raw[:500], "note": note})
    flag = "OK " if st in (200, 201, 204) else "!! "
    print(f"{flag}{st:>4} {method:<6} {path:<50} {step}")

def j(raw):
    try: return json.loads(raw)
    except: return {}

tok = login("ahmed.benali@techcorp-algerie.dz")
print("token principal:", "OK" if tok else "FAIL")

# 0. ping
st, raw, ms = req("GET", "/travel/ping", tok); rec("ping", "GET", "/travel/ping", st, ms, raw)

# 1. cities (pour recuperer des ids)
st, raw, ms = req("GET", "/travel/cities", tok); rec("liste villes", "GET", "/travel/cities", st, ms, raw)
cities = j(raw).get("data", [])
if isinstance(cities, dict): cities = cities.get("data", [])
city_ids = [c["id"] for c in cities][:4] if cities else []
print("villes dispo:", len(cities), city_ids)

# 2. carrier
st, raw, ms = req("POST", "/travel/carriers", tok, {"code": "CAR-EVAL-1", "name": "Transport Eval", "type": "bus"})
rec("creer transporteur", "POST", "/travel/carriers", st, ms, raw)
carrier_id = j(raw).get("data", {}).get("id")

# 3. class
st, raw, ms = req("POST", "/travel/classes", tok, {"code": "CLS-EVAL-1", "label": "Ordinaire", "priority": 1})
rec("creer classe", "POST", "/travel/classes", st, ms, raw)
class_id = j(raw).get("data", {}).get("id")

# 4. vehicle
st, raw, ms = req("POST", "/travel/vehicles", tok, {"code": "VEH-EVAL-1", "seat_capacity": 50,
    "seat_layout": {"seats_per_row": 4, "aisle_after": 2}, "carrier_id": carrier_id})
rec("creer vehicule", "POST", "/travel/vehicles", st, ms, raw)
vehicle_id = j(raw).get("data", {}).get("id")

# 5. route
st, raw, ms = req("POST", "/travel/routes", tok, {"code": "RTE-EVAL-1", "origin_city_id": city_ids[0],
    "destination_city_id": city_ids[1], "distance_km": 300, "duration_min": 240})
rec("creer ligne", "POST", "/travel/routes", st, ms, raw)
route_id = j(raw).get("data", {}).get("id")

# 6. route stop
st, raw, ms = req("POST", f"/travel/routes/{route_id}/stops", tok, {"city_id": city_ids[2], "rank": 1})
rec("ajouter arret", "POST", f"/travel/routes/{route_id}/stops", st, ms, raw)

# 7. trip
today = datetime.date.today()
dep = (today + datetime.timedelta(days=3)).isoformat()
arr = (today + datetime.timedelta(days=3)).isoformat()
st, raw, ms = req("POST", "/travel/trips", tok, {"code": "TRP-EVAL-1", "route_id": route_id,
    "carrier_id": carrier_id, "vehicle_id": vehicle_id, "departure_date": dep, "departure_time": "08:00",
    "arrival_date": arr, "arrival_time": "12:00", "means_of_transport": "bus", "total_seats": 50})
rec("creer voyage", "POST", "/travel/trips", st, ms, raw)
trip_id = j(raw).get("data", {}).get("id")

# 8. trip price
st, raw, ms = req("POST", f"/travel/trips/{trip_id}/prices", tok, {"class_id": class_id, "price": 2500, "currency": "DZD"})
rec("creer tarif", "POST", f"/travel/trips/{trip_id}/prices", st, ms, raw)

# 9. publish trip
st, raw, ms = req("POST", f"/travel/trips/{trip_id}/publish", tok)
rec("publier voyage", "POST", f"/travel/trips/{trip_id}/publish", st, ms, raw)

# 10. seats
st, raw, ms = req("GET", f"/travel/trips/{trip_id}/seats", tok); rec("plan de sieges", "GET", f"/travel/trips/{trip_id}/seats", st, ms, raw)

# 11. search
st, raw, ms = req("GET", f"/travel/trips/search?origin_city_id={city_ids[0]}&destination_city_id={city_ids[1]}&date={dep}", tok)
rec("recherche voyage", "GET", "/travel/trips/search", st, ms, raw)

# 12. booking
st, raw, ms = req("POST", "/travel/bookings", tok, {"trip_id": trip_id, "booking_source": "office",
    "idempotency_key": "eval-bk-1", "passengers": [{"full_name": "Client Eval", "age_category": "adult",
    "class_id": class_id, "seat_number": 1}]})
rec("creer reservation", "POST", "/travel/bookings", st, ms, raw)
booking_id = j(raw).get("data", {}).get("id")

# 13. confirm booking
st, raw, ms = req("POST", f"/travel/bookings/{booking_id}/confirm", tok); rec("confirmer reservation", "POST", f"/travel/bookings/{booking_id}/confirm", st, ms, raw)

# 14. issue tickets
st, raw, ms = req("POST", f"/travel/bookings/{booking_id}/issue-ticket", tok); rec("emettre billets", "POST", f"/travel/bookings/{booking_id}/issue-ticket", st, ms, raw)
tickets = j(raw).get("data", [])
ticket_id = None
if isinstance(tickets, list) and tickets: ticket_id = tickets[0].get("id")
elif isinstance(tickets, dict): ticket_id = tickets.get("id")

# 15. manifest
st, raw, ms = req("GET", f"/travel/trips/{trip_id}/manifest", tok); rec("manifeste passagers", "GET", f"/travel/trips/{trip_id}/manifest", st, ms, raw)

# 16. ticket verify / lookup / check-in
if ticket_id:
    st, raw, ms = req("POST", "/travel/tickets/verify", tok, {"ticket_id": ticket_id}); rec("verifier billet", "POST", "/travel/tickets/verify", st, ms, raw)
    st, raw, ms = req("POST", f"/travel/tickets/{ticket_id}/check-in", tok); rec("check-in billet", "POST", f"/travel/tickets/{ticket_id}/check-in", st, ms, raw)
    st, raw, ms = req("GET", f"/travel/tickets/{ticket_id}/pdf", tok); rec("PDF billet", "GET", f"/travel/tickets/{ticket_id}/pdf", st, ms, raw)

# 17. reports avec from/to
frm = (today - datetime.timedelta(days=30)).isoformat(); to = (today + datetime.timedelta(days=30)).isoformat()
for name, path in [("rapport ventes", f"/travel/reports/sales?from={frm}&to={to}"),
                   ("rapport occupation", f"/travel/reports/occupancy?from={frm}&to={to}"),
                   ("rapport revenus", f"/travel/reports/revenue?from={frm}&to={to}"),
                   ("rapport annulations", f"/travel/reports/cancellations?from={frm}&to={to}"),
                   ("rapport dashboard", f"/travel/reports/dashboard?from={frm}&to={to}"),
                   ("rapport agences", f"/travel/reports/offices?from={frm}&to={to}")]:
    st, raw, ms = req("GET", path, tok); rec(name, "GET", path.split("?")[0], st, ms, raw)

# 18. export
st, raw, ms = req("POST", "/travel/reports/export", tok, {"from": frm, "to": to, "format": "csv"})
rec("export rapport", "POST", "/travel/reports/export", st, ms, raw)

# 19. quote
st, raw, ms = req("POST", "/travel/quotes", tok, {"trip_id": trip_id, "passengers": 5, "contact_name": "Groupe Eval"})
rec("creer devis", "POST", "/travel/quotes", st, ms, raw)

# 20. round-trip
st, raw, ms = req("POST", "/travel/round-trips", tok, {"outbound_trip_id": trip_id, "return_trip_id": trip_id})
rec("aller-retour", "POST", "/travel/round-trips", st, ms, raw)

# 21. hotel + room
st, raw, ms = req("POST", "/travel/hotels", tok, {"name": "Hotel Eval", "city_id": city_ids[0], "stars": 3})
rec("creer hotel", "POST", "/travel/hotels", st, ms, raw)
hotel_id = j(raw).get("data", {}).get("id")
if hotel_id:
    st, raw, ms = req("POST", f"/travel/hotels/{hotel_id}/rooms", tok, {"name": "Chambre 1", "room_type": "double", "price_per_night": 8000})
    rec("creer chambre", "POST", f"/travel/hotels/{hotel_id}/rooms", st, ms, raw)

# 22. rental vehicle + booking
st, raw, ms = req("POST", "/travel/rental-vehicles", tok, {"name": "Toyota Eval", "daily_price": 5000, "city_id": city_ids[0]})
rec("creer vehicule location", "POST", "/travel/rental-vehicles", st, ms, raw)
rv_id = j(raw).get("data", {}).get("id")
if rv_id:
    st, raw, ms = req("POST", "/travel/rental-bookings", tok, {"rental_vehicle_id": rv_id, "start_date": dep, "end_date": arr})
    rec("reserver location", "POST", "/travel/rental-bookings", st, ms, raw)

# 23. loyalty
st, raw, ms = req("POST", "/travel/loyalty/opt-in", tok, {"contact_identifier": "client@eval.dz", "full_name": "Client Eval"})
rec("fidelite opt-in", "POST", "/travel/loyalty/opt-in", st, ms, raw)
st, raw, ms = req("GET", "/travel/loyalty/account?contact_identifier=client@eval.dz", tok); rec("compte fidelite", "GET", "/travel/loyalty/account", st, ms, raw)

# 24. currency rate
st, raw, ms = req("POST", "/travel/currency-rates", tok, {"base_currency": "EUR", "quote_currency": "DZD", "rate": 145.5})
rec("creer taux devise", "POST", "/travel/currency-rates", st, ms, raw)

# 25. cancellation policy
st, raw, ms = req("POST", "/travel/cancellation-policies", tok, {"name": "Standard", "hours_before": 24, "refund_percent": 50})
rec("creer politique annulation", "POST", "/travel/cancellation-policies", st, ms, raw)

# 26. distributor key
st, raw, ms = req("POST", "/travel/distributor-keys", tok, {"name": "Distributeur Eval", "scopes": ["catalog.read"]})
rec("creer cle distributeur", "POST", "/travel/distributor-keys", st, ms, raw)

# 27. webhook subscription
st, raw, ms = req("POST", "/travel/webhook-subscriptions", tok, {"url": "https://example.com/hook", "events": ["travel.booking.confirmed.v1"]})
rec("creer webhook", "POST", "/travel/webhook-subscriptions", st, ms, raw)

# 28. article + comment
st, raw, ms = req("POST", "/travel/articles", tok, {"title": "Article Eval", "body": "Contenu de test", "status": "draft"})
rec("creer article", "POST", "/travel/articles", st, ms, raw)
art_id = j(raw).get("data", {}).get("id")
if art_id:
    st, raw, ms = req("POST", f"/travel/articles/{art_id}/comments", tok, {"body": "Commentaire Eval"})
    rec("commenter article", "POST", f"/travel/articles/{art_id}/comments", st, ms, raw)

# 29. tourist site
st, raw, ms = req("POST", "/travel/tourist-sites", tok, {"name": "Site Eval", "city_id": city_ids[0], "description": "Un site"})
rec("creer site touristique", "POST", "/travel/tourist-sites", st, ms, raw)

# 30. quiz
st, raw, ms = req("POST", "/travel/quizzes", tok, {"title": "Quiz Eval", "status": "draft"})
rec("creer quiz", "POST", "/travel/quizzes", st, ms, raw)

# 31. advert type + advert
st, raw, ms = req("POST", "/travel/advert-types", tok, {"code": "ADV-EVAL", "label": "Banniere"})
rec("creer type annonce", "POST", "/travel/advert-types", st, ms, raw)

# 32. contact
st, raw, ms = req("POST", "/travel/contact", tok, {"name": "Prospect Eval", "email": "prospect@eval.dz", "message": "Bonjour"})
rec("formulaire contact", "POST", "/travel/contact", st, ms, raw)

# 33. staff assignment
st, raw, ms = req("POST", "/travel/staff-assignments", tok, {"trip_id": trip_id, "employee_id": 1, "role": "driver"})
rec("affecter equipage", "POST", "/travel/staff-assignments", st, ms, raw)

# 34. PDV session
st, raw, ms = req("POST", "/travel/pdv/session/open", tok, {"office_id": None, "opening_float": 0})
rec("ouvrir session PDV", "POST", "/travel/pdv/session/open", st, ms, raw)

# 35. public shop token
st, raw, ms = req("GET", "/travel/public-shop-token", tok); rec("jeton boutique publique", "GET", "/travel/public-shop-token", st, ms, raw)

# 36. mobile sync
st, raw, ms = req("POST", "/travel/mobile/sync", tok, {"since": "2026-01-01T00:00:00Z"}); rec("sync mobile", "POST", "/travel/mobile/sync", st, ms, raw)

# 37. fleet tracking
st, raw, ms = req("GET", "/travel/fleet/tracking", tok); rec("suivi flotte", "GET", "/travel/fleet/tracking", st, ms, raw)

# 38. vehicle position
if vehicle_id:
    st, raw, ms = req("POST", f"/travel/vehicles/{vehicle_id}/position", tok, {"latitude": 36.75, "longitude": 3.05})
    rec("position vehicule", "POST", f"/travel/vehicles/{vehicle_id}/position", st, ms, raw)

with open("/workspace/leopardo-hr/tests_eval/e2e_results.json", "w") as f:
    json.dump(R, f, indent=2, ensure_ascii=False)
ok = sum(1 for x in R if x["status"] in (200,201,204))
print(f"\n{ok}/{len(R)} etapes OK -> e2e_results.json")
