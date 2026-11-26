#!/usr/bin/env python3
"""Collecte de preuves statiques par cas d'usage (UC-xx) pour le portail de recette.

Ne modifie rien : lit routes/, app/Modules/, tests/ et produit un JSON de preuves.
"""
import importlib.util
import json
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
API = HERE.parent.parent / "api"

spec = importlib.util.spec_from_file_location("bp", HERE / "build_portal.py")
bp = importlib.util.module_from_spec(spec)
spec.loader.exec_module(bp)

# slug module (portail) -> (fichiers de routes, dossiers de modules, motifs de tests)
MODULE_MAP = {
    "iam": (["user.php", "sso.php", "integrations.php"], ["Core", "Shared"], ["Auth", "SSO", "PlatformAuth", "WebEmployeeAuth", "WebManagerPages", "RoleAssignment", "EmployeeInvitation"]),
    "hr": (["rh.php", "hr_app.php", "hr_extended.php"], ["HR"], ["HR", "HrApp", "Employees", "Employee", "Department", "OrgChart", "HrReport", "Evaluations", "Documents"]),
    "attendance": (["tracking.php", "dashboard.php"], ["Attendance"], ["Attendance", "Kiosk", "Zkteco", "Schedule"]),
    "absence": (["absence.php"], ["Absence"], ["Leave", "Absences", "Approval"]),
    "payroll": (["payroll_engine.php"], ["Payroll"], ["Payroll", "PaySlip", "Payslip", "Salary", "Cotisation", "SocialDeclaration"]),
    "crm": (["crm.php"], ["CRM"], ["CRM", "Crm"]),
    "marketing": (["marketing.php", "market_public.php"], ["Marketing"], ["Marketing", "Growth", "SocialDeclaration"]),
    "platform": (["solutions.php", "pages_platform.php"], ["Platform"], ["Platform", "Health", "LaunchReadiness", "FeatureFlag", "FeatureKillSwitch", "QueueObservability"]),
    "kiosque": (["tracking.php"], ["Attendance", "EdgeSync"], ["Kiosk", "Zkteco", "EdgeSync", "WebKiosk"]),
    "travel": (["travelagency.php"], ["TravelAgency"], ["Travel"]),
    "tenant": (["user.php"], ["Core", "Shared"], ["Tenant", "MultiTenant", "CrossTenant", "TenantCompanyIdSpoofing"]),
    "i18n": (["user.php"], ["Core", "Shared"], ["Translation", "LangCatalog", "Localized", "Locale", "I18n"]),
    "connectors": (["connectors.php", "integrations.php"], ["Connectors"], ["Connectors", "Webhook", "OutboundWebhook"]),
    "accounting": (["accounting.php"], ["Accounting"], ["Accounting", "Ledger", "BankExport", "Export"]),
    "expense": (["expense.php"], ["Expense"], ["Expense", "ExpenseClaim"]),
    "planning": (["planning.php"], ["Planning"], ["Planning", "Task", "Schedule"]),
    "recruitment": (["recruitment_platform.php"], ["Recruitment"], ["Recruitment", "JobPosting", "PublicCareer"]),
    "communication": (["communication.php"], ["Communication"], ["Communication", "Announcement", "Conversation"]),
    "notification": (["user.php"], ["Notification"], ["Notification", "DeviceToken"]),
    "onboarding": (["user.php", "solutions.php"], ["Onboarding"], ["Onboarding", "Trial", "SelfServiceTrial"]),
    "catalog": (["catalog.php", "catalog_public.php"], ["Catalog"], ["Catalog"]),
    "search": (["search_intelligence.php"], ["SearchIntelligence"], ["Search"]),
    "pages": (["pages.php", "pages_public.php"], ["Pages"], ["Pages", "PublicCareer"]),
    "showcase": (["showcase.php"], ["Showcase"], ["Showcase"]),
    "library": (["library.php", "library_public.php"], ["Library"], ["Library"]),
    "cameras": (["cameras.php"], ["Cameras"], ["Cameras"]),
    "fleet": (["tracking.php"], ["Fleet"], ["Fleet", "Vehicle"]),
    "fuel": (["fuel_station.php"], ["FuelStation"], ["Fuel"]),
    "vtc": (["vtc.php"], ["Vtc"], ["Vtc"]),
    "delivery": (["delivery.php"], ["Delivery"], ["Delivery"]),
    "restaurant": (["restaurantmanager.php"], ["Restaurant", "RestaurantManager"], ["Restaurant"]),
    "retail": (["retail.php"], ["Retail"], ["Retail"]),
    "hospitality": (["hospitality_manager.php"], ["HospitalityManager"], ["Hospitality"]),
    "pharmacy": (["pharmacy.php"], ["Pharmacy"], ["Pharmacy"]),
    "health": (["health_manager.php"], ["HealthManager"], ["HealthManager"]),
    "edu": (["edu_manager.php"], ["EduManager"], ["EduManager", "Training"]),
    "farm": (["farm_os.php"], ["FarmOs"], ["Farm"]),
    "fundraising": (["fundraising.php"], ["Fundraising"], ["Fundraising"]),
    "cabinet": (["cabinet.php"], ["Cabinet"], ["Cabinet"]),
    "trade": (["trade.php", "trade_public.php"], ["Trade"], ["Trade"]),
    "branding": (["branding.php", "branding_public.php"], ["Branding"], ["Branding", "PdfBranding"]),
    "billing": (["billing.php"], ["Billing"], ["Billing", "Plan", "Subscription"]),
    "paiements": (["paiements.php"], ["Paiements"], ["Payment", "BulkPayment", "PaymentWebhook"]),
    "edge": (["tracking.php"], ["EdgeSync"], ["EdgeSync", "Edge"]),
    "geo": (["tracking.php"], ["Geo"], ["Geo", "Geofence"]),
    "api": (["api.php"], ["Core"], ["OpenApi", "ApiErrorContract", "FrontendApiContract", "RouteControllerMethodContract"]),
    "mobile": ([], [], ["Mobile"]),
    "desktop": ([], [], ["Desktop"]),
    "public": (["pages_public.php", "catalog_public.php", "market_public.php", "trade_public.php", "library_public.php", "branding_public.php"], ["Pages", "Showcase"], ["LeopardoPublic", "PublicCareer"]),
}


def route_stats(files):
    total = 0
    found = []
    for f in files:
        p = API / "routes" / "modules" / f
        if p.exists():
            txt = p.read_text(encoding="utf-8", errors="ignore")
            n = len(re.findall(r"Route::(?:get|post|put|patch|delete)\(", txt))
            total += n
            found.append(f"{f}:{n}")
    return total, found


def controller_stats(module_dirs):
    files = 0
    for d in module_dirs:
        base = API / "app" / "Modules" / d
        if base.exists():
            files += len(list(base.rglob("*Controller.php")))
    return files


def test_stats(patterns):
    hits = []
    for root in (API / "tests" / "Feature", API / "tests" / "Unit"):
        if not root.exists():
            continue
        for p in root.rglob("*Test.php"):
            name = p.name
            if any(pat.lower() in name.lower() for pat in patterns):
                hits.append(str(p.relative_to(API)))
    return sorted(set(hits))


def main():
    out = []
    for uc in bp.USE_CASES:
        routes = 0
        route_files = []
        ctrls = 0
        tests = []
        for slug in uc["modules"]:
            rf, md, tp = MODULE_MAP.get(slug, ([], [], []))
            n, found = route_stats(rf)
            routes += n
            route_files += found
            ctrls += controller_stats(md)
            tests += test_stats(tp)
        tests = sorted(set(tests))
        out.append({
            "id": uc["id"],
            "title": uc["title"],
            "modules": uc["modules"],
            "type": uc["type"],
            "status": uc["status"],
            "note_ia": uc.get("note_ia", ""),
            "routes": routes,
            "route_files": sorted(set(route_files)),
            "controllers": ctrls,
            "tests": tests,
            "test_count": len(tests),
        })
    (HERE / "_evidence.json").write_text(json.dumps(out, ensure_ascii=False, indent=1), encoding="utf-8")
    for r in out:
        print(f"{r['id']} routes={r['routes']:4d} ctrl={r['controllers']:3d} tests={r['test_count']:3d} note={r['note_ia'] or '-':2s} {r['title'][:50]}")


if __name__ == "__main__":
    main()
