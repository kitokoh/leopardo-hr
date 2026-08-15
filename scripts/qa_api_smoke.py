#!/usr/bin/env python3
"""QA smoke — Leopardo RH API functional workflows (read-only + own-data).

Tests the main API workflows against a base URL (local dev or staging):
auth login -> me -> dashboard -> employees -> attendance -> absences ->
payroll -> tasks -> schedules -> notifications -> platform admin.
Usage: python3 scripts/qa_api_smoke.py [base_url]
"""
import json
import sys
import urllib.request
import urllib.error

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000/api/v1"
OUT = {"ok": 0, "fail": 0, "failures": []}


def call(method, path, token=None, body=None):
    url = BASE + path
    req = urllib.request.Request(url, method=method)
    req.add_header("Accept", "application/json")
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    if body is not None:
        req.add_header("Content-Type", "application/json")
        req.data = json.dumps(body).encode()
    try:
        with urllib.request.urlopen(req, timeout=40) as r:
            raw = r.read().decode()
            try:
                return r.status, json.loads(raw)
            except Exception:
                return r.status, raw[:200]
    except urllib.error.HTTPError as e:
        raw = e.read().decode()[:500]
        try:
            return e.code, json.loads(raw)
        except Exception:
            return e.code, raw
    except Exception as e:
        return -1, str(e)


def check(name, cond, detail=""):
    if cond:
        OUT["ok"] += 1
        print(f"  ✅ {name}")
    else:
        OUT["fail"] += 1
        OUT["failures"].append((name, detail))
        print(f"  ❌ {name} :: {detail}")


def login(email, pwd):
    st, d = call("POST", "/auth/login", body={"email": email, "password": pwd})
    tok = None
    if isinstance(d, dict):
        data = d.get("data", d)
        tok = d.get("token") or (data.get("token") if isinstance(data, dict) else None)
    if not tok:
        OUT["fail"] += 1
        OUT["failures"].append((f"login {email}", str(d)[:200]))
        print(f"  ❌ LOGIN {email} -> {st} {str(d)[:150]}")
    return tok


def section(title):
    print(f"\n=== {title} ===")


tok_manager = login("ahmed.benali@techcorp-algerie.dz", "password123")
tok_rh = login("fatima.meziane@techcorp-algerie.dz", "password123")
tok_employee = login("karim.aouad@techcorp-algerie.dz", "password123")

section("AUTH / ME")
if tok_manager:
    st, d = call("GET", "/auth/me", tok_manager)
    me = d.get("data", d) if isinstance(d, dict) else {}
    check("GET /auth/me 200", st == 200, f"{st} {str(d)[:200]}")
    check("me has company + currency", isinstance(me, dict) and ((me.get("company") or {}).get("currency") or me.get("currency")), f"{str(me)[:200]}")
    check("me has manager_role principal", (me.get("manager_role") or "") == "principal", f"{me.get('manager_role')}")
if tok_employee:
    st, d = call("GET", "/auth/me", tok_employee)
    check("employee /auth/me 200", st == 200, f"{st}")

section("DASHBOARD (manager)")
if tok_manager:
    for ep in ["/dashboard/summary", "/dashboard/kpi", "/dashboard/manager-digest", "/dashboard/recent-activity"]:
        st, d = call("GET", ep, tok_manager)
        check(f"GET {ep} {st}", st == 200, f"{str(d)[:200]}")
    st, d = call("GET", "/launch-readiness", tok_manager)
    score = None
    if isinstance(d, dict):
        score = d.get("data", d).get("score") if isinstance(d.get("data"), dict) else d.get("score")
    check("GET /launch-readiness 200", st == 200 and score is not None, f"{st} score={score}")

section("EMPLOYEES (manager)")
if tok_manager:
    st, d = call("GET", "/employees?per_page=5", tok_manager)
    items = d.get("data", []) if isinstance(d, dict) else []
    check("GET /employees 200 + list", st == 200 and isinstance(items, list), f"{st} {str(d)[:200]}")
    emp_id = items[0]["id"] if items else None
    if emp_id:
        st, d = call("GET", f"/employees/{emp_id}", tok_manager)
        check(f"GET /employees/{{id}} {st}", st == 200, f"{st} {str(d)[:200]}")
        st, d = call("GET", f"/employees/{emp_id}/balance", tok_manager)
        check(f"GET /employees/{{id}}/balance {st}", st == 200, f"{st} {str(d)[:200]}")

section("ATTENDANCE")
if tok_employee:
    for ep in ["/attendance/today", "/attendance?date_from=2026-07-01&date_to=2026-07-31", "/me/monthly-summary?year=2026&month=7"]:
        st, d = call("GET", ep, tok_employee)
        check(f"GET {ep} {st}", st == 200, f"{st} {str(d)[:200]}")
if tok_manager:
    st, d = call("GET", "/attendance?per_page=5", tok_manager)
    check("GET /attendance (manager list) 200", st == 200, f"{st} {str(d)[:200]}")
    st, d = call("GET", "/attendance/corrections", tok_manager)
    check("GET /attendance/corrections 200", st == 200, f"{st} {str(d)[:200]}")

section("ABSENCES / LEAVES")
if tok_employee:
    st, d = call("GET", "/me/leave-balances", tok_employee)
    check("GET /me/leave-balances 200", st == 200, f"{st} {str(d)[:200]}")
    st, d = call("GET", "/absences?per_page=5", tok_employee)
    check("GET /absences (employee) 200", st == 200, f"{st} {str(d)[:200]}")
if tok_manager:
    st, d = call("GET", "/leave-balances", tok_manager)
    check("GET /leave-balances (manager) 200", st == 200, f"{st} {str(d)[:200]}")

section("PAYROLL")
if tok_employee:
    st, d = call("GET", "/me/balance", tok_employee)
    check("GET /me/balance 200", st == 200, f"{st} {str(d)[:200]}")
if tok_manager:
    st, d = call("GET", "/payroll/mobile-summary", tok_manager)
    check("GET /payroll/mobile-summary (manager) 200", st == 200, f"{st} {str(d)[:200]}")
    for ep in ["/payroll/simulate", "/cotisation-simulation"]:
        st, d = call("POST", ep, tok_manager, body={"gross_salary": 50000, "country": "DZ", "acknowledge_placeholder": True})
        check(f"POST {ep} {st}", st in (200, 201, 422), f"{st} {str(d)[:250]}")

section("TASKS (manager + employee)")
if tok_manager:
    st, d = call("GET", "/tasks?per_page=5", tok_manager)
    check("GET /tasks (manager) 200", st == 200, f"{st} {str(d)[:200]}")
if tok_employee:
    st, d = call("GET", "/tasks/today", tok_employee)
    check("GET /tasks/today 200", st == 200, f"{st} {str(d)[:200]}")

section("SCHEDULES")
if tok_manager:
    st, d = call("GET", "/schedules", tok_manager)
    check("GET /schedules 200", st == 200, f"{st} {str(d)[:200]}")
    sched = None
    if isinstance(d, dict):
        items = d.get("data", d)
        if isinstance(items, list) and items and isinstance(items[0], dict):
            sched = items[0].get("id")
    if sched:
        st, d = call("POST", f"/schedules/{sched}/assign-employees", tok_manager, body={"employee_ids": []})
        check(f"POST /schedules/{{id}}/assign-employees {st}", st in (200, 422), f"{st} {str(d)[:200]}")

section("NOTIFICATIONS")
if tok_manager:
    st, d = call("GET", "/notifications?unread=true", tok_manager)
    check("GET /notifications?unread=true 200", st == 200, f"{st} {str(d)[:200]}")
    st, d = call("GET", "/notification-preferences", tok_manager)
    check("GET /notification-preferences 200", st == 200, f"{st} {str(d)[:200]}")
    st, d = call("GET", "/communication/analytics", tok_manager)
    check("GET /communication/analytics 200", st in (200, 403), f"{st} {str(d)[:150]}")

section("PROFILE / CAREER (employee)")
if tok_employee:
    for ep in ["/me/career", "/me/qr-profile", "/me/payment-documents", "/company/branding"]:
        st, d = call("GET", ep, tok_employee)
        check(f"GET {ep} {st}", st == 200, f"{st} {str(d)[:200]}")

section("PLATFORM ADMIN (super-admin)")
st, d = call("POST", "/platform/auth/login", body={"email": "admin@leopardo-rh.com", "password": "password123"})
tok_sa = None
if isinstance(d, dict):
    data = d.get("data", d)
    tok_sa = d.get("token") or (data.get("token") if isinstance(data, dict) else None)
check("POST /platform/auth/login 200", st == 200 and tok_sa is not None, f"{st} {str(d)[:200]}")
if tok_sa:
    for ep in ["/platform/auth/me", "/platform/companies?per_page=5", "/platform/country-defaults", "/platform/plans"]:
        st, d = call("GET", ep, tok_sa)
        check(f"GET {ep} {st}", st == 200, f"{st} {str(d)[:200]}")

section("PRIVACY / MISC")
if tok_employee:
    st, d = call("GET", "/privacy/export", tok_employee)
    check("GET /privacy/export 200", st == 200, f"{st} {str(d)[:200]}")
if tok_manager:
    st, d = call("GET", "/company/qr-onboarding", tok_manager)
    check("GET /company/qr-onboarding (manager) 200", st == 200, f"{st} {str(d)[:200]}")

print(f"\n========== SUMMARY: {OUT['ok']} ok, {OUT['fail']} fail ==========")
for name, detail in OUT["failures"]:
    print(f"  FAIL: {name}\n        {detail}")
sys.exit(1 if OUT["fail"] else 0)
