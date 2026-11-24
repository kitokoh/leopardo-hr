#!/usr/bin/env python3
"""Matrice RBAC: quelles operations travel chaque profil peut faire."""
import json, time, urllib.request, urllib.error, datetime, random

API = "http://localhost:8000/api/v1"
R = []
SUF = str(random.randint(100000,999999))

PERSONAS = {
    "principal": "ahmed.benali@techcorp-algerie.dz",
    "rh": "fatima.meziane@techcorp-algerie.dz",
    "dept": "samir.boukhalfa@techcorp-algerie.dz",
    "comptable": "lina.haddad@techcorp-algerie.dz",
    "superviseur": "nassim.cheriet@techcorp-algerie.dz",
    "employe": "karim.aouad@techcorp-algerie.dz",
}

def req(method, path, token=None, body=None):
    url = API + path
    data = json.dumps(body).encode() if body is not None else None
    h = {"Accept": "application/json"}
    if body is not None: h["Content-Type"] = "application/json"
    if token: h["Authorization"] = "Bearer " + token
    r = urllib.request.Request(url, data=data, headers=h, method=method)
    try:
        with urllib.request.urlopen(r, timeout=30) as resp:
            return resp.status, resp.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")
    except Exception as e:
        return 0, str(e)

def login(email):
    st, raw = req("POST", "/auth/login", body={"email": email, "password": "password123"})
    return json.loads(raw).get("token") if st == 200 else None

tokens = {p: login(e) for p, e in PERSONAS.items()}

# operations testees (methode, chemin, corps, permission attendue)
OPS = [
    ("lire trajets", "GET", "/travel/trips", None, "tout employe"),
    ("lire rapports", "GET", "/travel/reports/dashboard", None, "travel.reports"),
    ("creer gare", "POST", "/travel/stations", {"code": f"ST-{SUF}", "name": "Gare X", "city_id": 1}, "travel.manage"),
    ("creer transporteur", "POST", "/travel/carriers", {"code": f"CR-{SUF}", "name": "C X", "type": "bus"}, "travel.manage"),
    ("creer voyage", "POST", "/travel/trips", {"code": f"TP-{SUF}", "route_id": 1, "carrier_id": 1, "vehicle_id": 1, "departure_date": "2026-12-01", "departure_time": "08:00", "arrival_date": "2026-12-01", "arrival_time": "12:00", "means_of_transport": "bus", "total_seats": 40}, "travel.manage"),
    ("creer reservation", "POST", "/travel/bookings", {"trip_id": 1, "booking_source": "office", "idempotency_key": f"rb-{SUF}", "passengers": [{"full_name": "X", "age_category": "adult", "class_id": 1}]}, "travel.agent"),
    ("cle distributeur", "GET", "/travel/distributor-keys", None, "travel.manage"),
    ("webhooks", "GET", "/travel/webhook-subscriptions", None, "travel.manage"),
    ("export rapport", "POST", "/travel/reports/export", {"report_type": "sales", "from": "2026-01-01", "to": "2026-12-31", "idempotency_key": f"rbx-{SUF}"}, "travel.reports"),
    ("ouvrir PDV", "POST", "/travel/pdv/session/open", {"opening_float_minor": 0}, "travel.agent"),
    ("affecter equipage", "POST", "/travel/staff-assignments", {"trip_id": 1, "employee_id": 1, "role": "driver"}, "travel.manage"),
    ("creer article", "POST", "/travel/articles", {"title": f"A {SUF}", "body": "x", "status": "draft"}, "travel.manage"),
    ("creer quiz", "POST", "/travel/quizzes", {"title": f"Q {SUF}", "status": "draft"}, "travel.manage"),
    ("creer annonce type", "POST", "/travel/advert-types", {"code": f"at_{SUF}", "label": "B"}, "travel.manage"),
    ("creer hotel", "POST", "/travel/hotels", {"name": f"H {SUF}", "city_id": 1, "classification": 3}, "travel.manage"),
    ("creer vehicule location", "POST", "/travel/rental-vehicles", {"code": f"RV-{SUF}", "title": "T", "city_id": 1, "price_per_day_minor": 1000, "currency": "DZD"}, "travel.manage"),
    ("politique annulation", "POST", "/travel/cancellation-policies", {"hours_before_departure": 48, "penalty_percent": 30, "refundable": True}, "travel.manage"),
    ("taux devise", "POST", "/travel/currency-rates", {"from_currency": "CHF", "to_currency": "DZD", "rate_minor": 15000, "valid_from": "2026-11-01"}, "travel.manage"),
    ("compte corporate", "POST", "/travel/corporate-accounts", {"name": f"C {SUF}", "contact_email": f"c{SUF}@e.dz", "credit_limit_minor": 1000}, "travel.manage"),
    ("site touristique", "POST", "/travel/tourist-sites", {"name": f"S {SUF}", "city_id": 1, "description": "x"}, "travel.manage"),
]

matrix = {}
for p, tok in tokens.items():
    if not tok:
        matrix[p] = {"_login": "FAIL"}
        continue
    row = {}
    for label, method, path, body, perm in OPS:
        st, raw = req(method, path, tok, body)
        row[label] = st
    matrix[p] = row
    print(f"{p:<12}", " ".join(f"{v}" for v in row.values()))

with open("/workspace/leopardo-hr/tests_eval/rbac_matrix.json", "w") as f:
    json.dump({"ops": [{"label": o[0], "method": o[1], "path": o[2], "expected": o[4]} for o in OPS], "matrix": matrix}, f, indent=2, ensure_ascii=False)

print("\n--- detail des refus (403/401) ---")
for p, row in matrix.items():
    for label, st in row.items():
        if st in (401, 403):
            print(f"{p:<12} {label:<24} {st}")
