#!/usr/bin/env python3
"""QA write-workflow smoke — Leopardo RH (LOCAL demo env only).

Exercises real business workflows end-to-end against a LOCAL demo database:
salary advance double validation, absence request -> approve, task create ->
close, branding PATCH, exports history, kiosk provisioning, marketing leads.
Usage: python3 scripts/qa_api_write_workflows.py [base_url]
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


tok_emp = login("karim.aouad@techcorp-algerie.dz", "password123")
tok_mgr = login("ahmed.benali@techcorp-algerie.dz", "password123")
tok_rh = login("fatima.meziane@techcorp-algerie.dz", "password123")

section("SALARY ADVANCE — double validation workflow")
advance_id = None
if tok_emp:
    st, d = call("POST", "/salary-advances", tok_emp, body={"amount": 15000, "reason": "QA workflow test"})
    check("POST /salary-advances (employee) 201", st == 201, f"{st} {str(d)[:250]}")
    if isinstance(d, dict):
        data = d.get("data", d)
        advance_id = data.get("id") if isinstance(data, dict) else None
if advance_id and tok_mgr:
    st, d = call("PUT", f"/salary-advances/{advance_id}/manager-approve", tok_mgr, body={})
    check(f"PUT /salary-advances/{advance_id}/manager-approve 200", st == 200, f"{st} {str(d)[:250]}")
    st, d = call("PUT", f"/salary-advances/{advance_id}/mark-paid", tok_rh, body={})
    check(f"PUT /salary-advances/{advance_id}/mark-paid (RH) 200", st == 200, f"{st} {str(d)[:250]}")
    st, d = call("PUT", f"/salary-advances/{advance_id}/confirm-received", tok_emp, body={})
    check(f"PUT /salary-advances/{advance_id}/confirm-received (employee) 200", st == 200, f"{st} {str(d)[:250]}")

section("ABSENCE — request then approve")
absence_id = None
if tok_emp:
    st, d = call("GET", "/me/leave-balances", tok_emp)
    types = None
    if isinstance(d, dict) and isinstance(d.get("data"), list) and d["data"]:
        types = d["data"][0].get("absence_type_id") or d["data"][0].get("type_id")
    st, d = call("POST", "/absences", tok_emp, body={
        "absence_type_id": types or 1,
        "start_date": "2026-09-01",
        "end_date": "2026-09-03",
        "reason": "QA workflow test",
    })
    check("POST /absences (employee) 201", st in (200, 201), f"{st} {str(d)[:250]}")
    if isinstance(d, dict):
        data = d.get("data", d)
        absence_id = data.get("id") if isinstance(data, dict) else None
if absence_id and tok_mgr:
    st, d = call("PUT", f"/absences/{absence_id}/approve", tok_mgr, body={})
    check(f"PUT /absences/{absence_id}/approve 200", st == 200, f"{st} {str(d)[:250]}")

section("TASK — create, assign, complete")
task_id = None
if tok_mgr:
    st, d = call("GET", "/employees?per_page=1", tok_mgr)
    emp_id = None
    if isinstance(d, dict) and isinstance(d.get("data"), list) and d["data"]:
        emp_id = d["data"][0].get("id")
    st, d = call("POST", "/tasks", tok_mgr, body={
        "title": "QA task workflow",
        "assigned_to": [emp_id] if emp_id else [],
        "due_date": "2026-08-20",
    })
    check("POST /tasks (manager) 201", st in (200, 201), f"{st} {str(d)[:250]}")
    if isinstance(d, dict):
        data = d.get("data", d)
        task_id = data.get("id") if isinstance(data, dict) else None
if task_id and tok_emp:
    st, d = call("PATCH", f"/tasks/{task_id}", tok_emp, body={"status": "done", "completed_minutes": 60, "completion_note": "QA done"})
    check(f"PATCH /tasks/{task_id} (employee close) 200", st == 200, f"{st} {str(d)[:250]}")

section("COMPANY BRANDING — PATCH")
if tok_mgr:
    st, d = call("PATCH", "/company/branding", tok_mgr, body={"primary_color": "#0f766e", "logo_url": "https://example.com/logo.png"})
    check("PATCH /company/branding 200", st == 200, f"{st} {str(d)[:250]}")
    st, d = call("GET", "/company/branding", tok_emp)
    check("GET /company/branding after PATCH 200", st == 200, f"{st} {str(d)[:250]}")

section("EXPORTS")
if tok_mgr:
    st, d = call("GET", "/export/history", tok_mgr)
    check("GET /export/history 200", st == 200, f"{st} {str(d)[:200]}")

section("KIOSK — provisioning via manager")
if tok_mgr:
    st, d = call("POST", "/kiosks", tok_mgr, body={"name": "QA Kiosk", "location": "Reception"})
    check("POST /kiosks (manager) 201", st in (200, 201), f"{st} {str(d)[:250]}")
    kiosk_code = None
    kiosk_token = None
    if isinstance(d, dict):
        data = d.get("data", d)
        if isinstance(data, dict):
            kiosk_code = data.get("device_code")
            kiosk_token = data.get("sync_token")
    if kiosk_code and kiosk_token:
        req = urllib.request.Request(BASE + f"/kiosks/{kiosk_code}/roster", method="GET")
        req.add_header("Accept", "application/json")
        req.add_header("X-Kiosk-Token", kiosk_token)
        try:
            with urllib.request.urlopen(req, timeout=40) as r:
                st2 = r.status
        except urllib.error.HTTPError as e:
            st2 = e.code
        except Exception as e:
            st2 = -1
        check(f"GET /kiosks/{{code}}/roster (X-Kiosk-Token) {st2}", st2 == 200, f"{st2}")

section("FEATURE MANIFEST + I18N CATALOG (public)")
for ep in ["/i18n/catalog/fr"]:
    st, d = call("GET", ep)
    check(f"GET {ep} {st}", st == 200, f"{st} {str(d)[:200]}")

section("NOTIFICATION PREFERENCES UPDATE (employee)")
if tok_emp:
    st, d = call("PATCH", "/notification-preferences", tok_emp, body={"email_notifications": True})
    check("PATCH /notification-preferences 200", st in (200, 201), f"{st} {str(d)[:250]}")

print(f"\n========== SUMMARY: {OUT['ok']} ok, {OUT['fail']} fail ==========")
for name, detail in OUT["failures"]:
    print(f"  FAIL: {name}\n        {detail}")
sys.exit(1 if OUT["fail"] else 0)
