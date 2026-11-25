#!/usr/bin/env python3
"""Tests surfaces externes + isolation multi-tenant + gating module."""
import json, time, urllib.request, urllib.error, datetime

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
    R.append({"step": step, "method": method, "path": path, "status": st, "ms": ms, "snippet": raw[:500], "note": note})
    flag = "OK " if st in (200,201,202,204) else "!! "
    print(f"{flag}{st:>4} {method:<6} {path:<48} {step}")

def j(raw):
    try: return json.loads(raw)
    except: return {}

tok = login("ahmed.benali@techcorp-algerie.dz")

# --- 1. Isolation multi-tenant: PharmaPlus (sans travelagency) ---
ptok = login("amina.tahiri@pharmaplus.ma")
print("pharmaplus token:", "OK" if ptok else "FAIL")
st, raw, ms = req("GET", "/travel/ping", ptok); rec("gating module (tenant sans travelagency)", "GET", "/travel/ping", st, ms, raw)
st, raw, ms = req("GET", "/travel/trips", ptok); rec("gating module trips", "GET", "/travel/trips", st, ms, raw)

# --- 2. Isolation: techcorp ne voit pas les donnees d'un autre tenant ---
# creer une cle distributeur pour techcorp
st, raw, ms = req("POST", "/travel/distributor-keys", tok, {"name": "Dist Eval", "scopes": ["catalog.read", "bookings.read"]})
rec("creer cle distributeur (scopes)", "POST", "/travel/distributor-keys", st, ms, raw)
dkey = j(raw).get("data", {})
dkey_token = dkey.get("api_key")
print("distributor token:", (dkey_token or "")[:20], "...")

# --- 3. API distributeur (X-Distributor-Key) ---
if dkey_token:
    st, raw, ms = req("GET", "/travel/distributor/catalog", headers={"X-Distributor-Key": dkey_token})
    rec("catalogue distributeur", "GET", "/travel/distributor/catalog", st, ms, raw)
    st, raw, ms = req("GET", "/travel/distributor/catalog", headers={"X-Distributor-Key": "invalid-key"})
    rec("catalogue distributeur (cle invalide)", "GET", "/travel/distributor/catalog", st, ms, raw)

# --- 4. API partenaire transporteur (X-Partner-Key) ---
st, raw, ms = req("POST", "/travel/partner-keys", tok, {"carrier_id": 1, "name": "Partner Eval"})
rec("creer cle partenaire", "POST", "/travel/partner-keys", st, ms, raw)
pkey = j(raw).get("data", {})
pkey_token = pkey.get("api_key")
if pkey_token:
    st, raw, ms = req("POST", "/travel/partner/sync", headers={"X-Partner-Key": pkey_token}, body={"trips": []})
    rec("sync partenaire", "POST", "/travel/partner/sync", st, ms, raw)
st, raw, ms = req("POST", "/travel/partner/sync", headers={"X-Partner-Key": "bad"}, body={"trips": []})
rec("sync partenaire (cle invalide)", "POST", "/travel/partner/sync", st, ms, raw)

# --- 5. Formulaire contact public (URL signee) ---
st, raw, ms = req("POST", "/travel/public-contact-link", tok, {"expires_in_hours": 24})
rec("generer lien contact public", "POST", "/travel/public-contact-link", st, ms, raw)
link = j(raw).get("data", {})
signed_url = link.get("contact_url")
print("signed url:", (signed_url or "")[:80])
if signed_url:
    # extraire le chemin signe
    from urllib.parse import urlparse
    pu = urlparse(signed_url)
    path = pu.path.replace("/api/v1", "") + ("?" + pu.query if pu.query else "")
    st, raw, ms = req("POST", path, body={"first_name": "Pub", "last_name": "Lic", "email": "pub@e2.dz", "message": "Bonjour depuis le public", "consent_email": True})
    rec("contact public signe", "POST", "/travel/public/contact", st, ms, raw)
    st, raw, ms = req("POST", "/travel/public/contact", body={"first_name": "Pub", "last_name": "Lic", "email": "pub@e2.dz", "message": "Sans signature", "consent_email": True})
    rec("contact public sans signature (doit refuser)", "POST", "/travel/public/contact", st, ms, raw)

# --- 6. Boutique publique (token) ---
st, raw, ms = req("POST", "/travel/public-shop-token/rotate", tok); rec("rotation jeton boutique", "POST", "/travel/public-shop-token/rotate", st, ms, raw)
shop = j(raw).get("data", {})
st, raw, ms = req("GET", "/travel/public-shop-token", tok); rec("jeton boutique", "GET", "/travel/public-shop-token", st, ms, raw)
shop_token = (shop or {}).get("token") if isinstance(shop, dict) else None
if shop_token:
    st, raw, ms = req("GET", f"/travel/shop/trips?token={shop_token}"); rec("boutique: recherche", "GET", "/travel/shop/trips", st, ms, raw)

# --- 7. Endpoints sans auth (doivent refuser) ---
st, raw, ms = req("GET", "/travel/trips"); rec("trips sans auth (doit refuser)", "GET", "/travel/trips", st, ms, raw)
st, raw, ms = req("GET", "/travel/reports/dashboard"); rec("dashboard sans auth (doit refuser)", "GET", "/travel/reports/dashboard", st, ms, raw)

with open("/workspace/leopardo-hr/tests_eval/external_results.json", "w") as f:
    json.dump(R, f, indent=2, ensure_ascii=False)
ok = sum(1 for x in R if x["status"] in (200,201,202,204))
print(f"\n{ok}/{len(R)} OK")
