#!/usr/bin/env python3
"""Leopardo HR — harnais d'evaluation par profil (tenant TechCorp Algerie SARL).

Teste un ensemble representatif d'endpoints API pour chaque profil utilisateur
et enregistre le code HTTP + un extrait de la reponse.

Le rate-limit 'api-plan' est de 100 req/min pour le plan Pilot, partage par
toute l'entreprise : on purge le cache entre chaque profil pour eviter les 429.
"""
import json
import subprocess
import sys

BASE = "http://127.0.0.1:8000/api/v1"
API_DIR = "/workspace/leopardo-hr/api"

PROFILES = {
    "principal":  ("ahmed.benali@techcorp-algerie.dz", "password123"),
    "rh":         ("fatima.meziane@techcorp-algerie.dz", "password123"),
    "dept_mgr":   ("samir.boukhalfa@techcorp-algerie.dz", "password123"),
    "comptable":  ("lina.haddad@techcorp-algerie.dz", "password123"),
    "superviseur":("nassim.cheriet@techcorp-algerie.dz", "password123"),
    "employe":    ("karim.aouad@techcorp-algerie.dz", "password123"),
}

# (label, method, path, body)
ENDPOINTS = [
    # --- Auth / profil ---
    ("auth.me",               "GET",  "/auth/me", None),
    ("auth.contexts",         "GET",  "/auth/contexts", None),
    ("auth.2fa.status",       "GET",  "/auth/2fa/status", None),
    ("auth.profile.patch",    "PATCH","/auth/profile", {"preferred_name": "Eval"}),
    # --- RH coeur ---
    ("employees.list",        "GET",  "/employees", None),
    ("employees.create",      "POST", "/employees", {"first_name": "Test", "last_name": "Eval", "email": "test.eval@techcorp-algerie.dz", "role": "employee", "salary_type": "fixed", "salary_base": 50000}),
    ("departments.list",      "GET",  "/departments", None),
    ("departments.create",    "POST", "/departments", {"name": "Eval Dept"}),
    ("positions.list",        "GET",  "/positions", None),
    ("contracts.list",        "GET",  "/contracts", None),
    ("contracts.expiring",    "GET",  "/contracts/expiring", None),
    ("contracts.templates",   "GET",  "/contracts/templates", None),
    ("absences.list",         "GET",  "/absences", None),
    ("absences.stats",        "GET",  "/absences/stats", None),
    ("absences.create",       "POST", "/absences", {"type": "annual", "start_date": "2026-12-01", "end_date": "2026-12-03", "reason": "Eval"}),
    ("salary-advances.list",  "GET",  "/salary-advances", None),
    ("expense-claims.list",   "GET",  "/expense-claims", None),
    ("expense-claims.create", "POST", "/expense-claims", {"amount": 1000, "currency": "DZD", "category": "transport", "description": "Eval"}),
    # --- Attendance / planning ---
    ("attendance.list",       "GET",  "/attendance", None),
    ("attendance.today",      "GET",  "/attendance/today", None),
    ("attendance.dashboard",  "GET",  "/attendance/dashboard", None),
    ("attendance.my-sessions","GET",  "/attendance/my-sessions", None),
    ("attendance.anomalies",  "GET",  "/attendance/anomalies", None),
    ("attendance.regularity", "GET",  "/attendance/regularity", None),
    ("attendance.config",     "GET",  "/attendance/config", None),
    ("attendance.checkin",    "POST", "/attendance/check-in", {"latitude": 36.7529, "longitude": 3.0420}),
    ("schedules.list",        "GET",  "/schedules", None),
    ("sites.list",            "GET",  "/sites", None),
    # --- Paie ---
    ("payroll-runs.list",     "GET",  "/payroll-runs", None),
    ("payroll-runs.create",   "POST", "/payroll-runs", {"period": "2026-11"}),
    # --- Comptabilite ---
    ("accounting.dashboard",  "GET",  "/accounting/dashboard", None),
    ("accounting.documents",  "GET",  "/accounting/documents", None),
    ("accounting.journal",    "GET",  "/accounting/journal", None),
    ("accounting.chart",      "GET",  "/accounting/chart", None),
    ("accounting.contacts",   "GET",  "/accounting/contacts", None),
    ("accounting.tax-rates",  "GET",  "/accounting/tax-rates", None),
    ("accounting.settings",   "GET",  "/accounting/settings", None),
    # --- CRM ---
    ("crm.accounts",          "GET",  "/crm/accounts", None),
    ("crm.leads",             "GET",  "/crm/leads", None),
    ("crm.opportunities",     "GET",  "/crm/opportunities", None),
    ("crm.activities",        "GET",  "/crm/activities", None),
    # --- Facturation / abonnement ---
    ("billing.subscription",  "GET",  "/billing/subscription", None),
    ("billing.invoices",      "GET",  "/billing/invoices", None),
    # --- Notifications / messagerie ---
    ("notifications.list",    "GET",  "/notifications", None),
    ("conversations.list",    "GET",  "/conversations", None),
    # --- Recrutement ---
    ("recruitment.jobs",      "GET",  "/recruitment/jobs", None),
    # --- Entreprise / config ---
    ("company.profile",       "GET",  "/company/profile", None),
    ("company.roles",         "GET",  "/company/roles", None),
    ("company.branding",      "GET",  "/company/branding", None),
    ("company.banking",       "GET",  "/company/banking", None),
    # --- Audit / rapports ---
    ("audit-logs.list",       "GET",  "/audit-logs", None),
    # --- Verticales (doivent etre refusees si non activees) ---
    ("travel.trips",          "GET",  "/travel/trips", None),
    ("restaurant.branches",   "GET",  "/restaurant/branches", None),
    ("pharmacy.products",     "GET",  "/pharmacy/products", None),
    ("fuel-station.stations", "GET",  "/fuel-station/stations", None),
    # --- Plateforme (doit etre refuse pour un employe tenant) ---
    ("platform.companies",    "GET",  "/platform/companies", None),
    ("admin.dashboard.stats", "GET",  "/admin/dashboard/stats", None),
]


def curl(method, path, token=None, body=None):
    cmd = ["curl", "-s", "-o", "/tmp/_body.json", "-w", "%{http_code}",
           "-X", method, BASE + path, "-H", "Accept: application/json"]
    if token:
        cmd += ["-H", f"Authorization: Bearer {token}"]
    if body is not None:
        cmd += ["-H", "Content-Type: application/json", "-d", json.dumps(body)]
    code = subprocess.run(cmd, capture_output=True, text=True).stdout.strip()
    try:
        raw = open("/tmp/_body.json").read()
        data = json.loads(raw) if raw.strip() else {}
    except Exception:
        data = {"_raw": open("/tmp/_body.json").read()[:200]}
    return code, data


def clear_throttle():
    subprocess.run(["php", "artisan", "cache:clear"], cwd=API_DIR,
                   capture_output=True, text=True)


def login(email, password):
    code, data = curl("POST", "/auth/login", body={"email": email, "password": password, "device_name": "eval"})
    return code, data.get("token"), data


def summarize(data):
    if isinstance(data, dict):
        if "data" in data and isinstance(data["data"], list):
            return f"list[{len(data['data'])}]"
        if "data" in data and isinstance(data["data"], dict):
            return "obj:" + ",".join(list(data["data"].keys())[:6])
        if "error" in data:
            return f"error={data.get('error')}"
        if "message" in data:
            return f"msg={str(data.get('message'))[:70]}"
        return "keys:" + ",".join(list(data.keys())[:6])
    return str(data)[:80]


def main():
    only = sys.argv[1] if len(sys.argv) > 1 else None
    out_path = "/workspace/leopardo-hr/eval/results.json"
    try:
        results = json.load(open(out_path))
    except Exception:
        results = {}

    for prof, (email, pwd) in PROFILES.items():
        if only and prof != only:
            continue
        clear_throttle()
        code, tok, data = login(email, pwd)
        results.setdefault(prof, {})
        results[prof]["_login"] = {"http": code, "token": bool(tok),
                                   "role": data.get("role"), "manager_role": data.get("manager_role")}
        print(f"[login] {prof:12s} HTTP={code} token={'yes' if tok else 'NO'} role={data.get('role')}/{data.get('manager_role')}")
        if not tok:
            continue
        for label, method, path, body in ENDPOINTS:
            code, data = curl(method, path, token=tok, body=body)
            results[prof][label] = {"http": code, "summary": summarize(data)}
        print(f"[done] {prof}")

    json.dump(results, open(out_path, "w"), indent=2, ensure_ascii=False)
    print("WROTE results.json")


if __name__ == "__main__":
    main()
