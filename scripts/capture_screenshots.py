#!/usr/bin/env python3
"""Captures d'écran reproductibles des fronts web de Leopardo RH.

Remplace la version d'origine (#9650), qui ne pouvait plus rien capturer :

- ports faux (3001/3002/8080 au lieu de 3000/5173/3001) ;
- routes mortes (`/about`, `/pricing`, `/careers`… déplacées sous `/business/`) ;
- authentification cassée : le script semait `localStorage.admin_token` alors
  que le token super-admin vit en mémoire volatile (`src/services/token-storage.js`,
  #7695) et que la zone dashboard du front web est protégée par le cookie
  httpOnly `leopardo_token` posé par le proxy Next (#2746) ;
- cibles mobiles (`mobile_employee`, `mobile_manager`, `mobile_platform_admin`)
  qui supposaient un build Flutter web servi sur `8080` — inexistant ici.

Les patterns d'authentification réutilisés sont ceux des suites e2e du dépôt :

- admin-dashboard : `add_init_script` + `sessionStorage.admin_token`, consommé
  au boot du module de stockage (cf. `front/admin-dashboard/e2e/*.spec.js`) ;
- front web (dashboard) : cookie `leopardo_token` + `localStorage.auth_user`
  (cf. `front/web/e2e/session-helpers.ts` et `src/lib/auth-session.ts`).

Usage
-----
    # Les serveurs de dev tournent déjà (ports 3000 / 5173 / 3001) :
    python3 scripts/capture_screenshots.py --all

    # Le script démarre et arrête lui-même les serveurs :
    python3 scripts/capture_screenshots.py --all --serve

    # Un seul front :
    python3 scripts/capture_screenshots.py --front admin

Sortie : `front/web/public/screenshots/<front>/<ecran>.png` — seul chemin PNG
exempté de Git LFS (cf. `.gitattributes`), donc les captures restent des
binaires réels dans le dépôt.

Limite connue : `front/mobile_apps` (Flutter natif) n'est pas capturable ici —
aucun émulateur Android/iOS n'est disponible dans l'environnement de capture.
"""

from __future__ import annotations

import argparse
import contextlib
import json
import os
import shutil
import signal
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
from dataclasses import dataclass, field
from pathlib import Path

from playwright.sync_api import sync_playwright

REPO_ROOT = Path(__file__).resolve().parent.parent
FRONT_DIR = REPO_ROOT / "front"
OUTPUT_ROOT = FRONT_DIR / "web" / "public" / "screenshots"

DESKTOP = {"width": 1440, "height": 900}
MOBILE = {"width": 390, "height": 844}

# Jeton factice : il ne sert qu'à franchir les gardes *cosmétiques* côté client
# (proxy Next + store Pinia). Aucune requête n'atteint un vrai backend — toutes
# les routes `/api/**` sont mockées par `_mock_api`.
FAKE_TOKEN = "screenshot-session-token"

# Profil tenant utilisé pour rendre le shell du dashboard web (EmployeeResource
# `/auth/me`, cf. `src/lib/auth-session.ts`).
WEB_USER = {
    "id": 101,
    "first_name": "Fatima",
    "last_name": "Meziane",
    "name": "Fatima Meziane",
    "email": "fatima.meziane@techcorp-algerie.dz",
    "role": "manager",
    "manager_role": "principal",
    "language": "fr",
    "is_rtl": False,
    "capabilities": {
        "can_view_dashboard": True,
        "employees": True,
        "attendance": True,
        "absences": True,
        "payroll": True,
        "reports": True,
    },
    "features": {"rh": True, "finance": True, "cameras": True, "leo_ai": True},
    "company": {
        "id": 1,
        "name": "TechCorp Algérie SARL",
        "language": "fr",
        "timezone": "Africa/Algiers",
        "currency": "DZD",
        "type": "company",
        "sector": "services",
        "status": "active",
        # `welcome_seen_at` + `setup_interview.completed` : sans eux, le shell
        # du dashboard superpose l'ecran de bienvenue « definir mon mot de
        # passe » et l'entretien de preparation (#7490/#7493), qui masquent la
        # page capturee. `demo_data` : l'invite d'import (#7866) est deja passee.
        "metadata": {
            "onboarding_completed": True,
            "welcome_seen_at": "2026-09-01T08:00:00+00:00",
            "setup_interview": {"status": "completed"},
            "demo_data": {
                "restaurant": {"status": "dismissed"},
                "travelagency": {"status": "dismissed"},
                "fuel_station": {"status": "dismissed"},
                "edumanager": {"status": "dismissed"},
                "pharmacy": {"status": "dismissed"},
            },
        },
        "features": {
            "employees": True,
            "attendance": True,
            "absences": True,
            "payroll": True,
            "reports": True,
        },
        "modules": {
            "employees": True,
            "attendance": True,
            "absences": True,
            "payroll": True,
            "reports": True,
            "accounting": True,
        },
    },
    "plan": {"name": "Business", "features": {}},
}

# Compte plateforme complet : sans la matrice de permissions, la sidebar de
# l'admin masque (à juste titre) la plupart des entrées (#7553/#7554).
ADMIN_USER = {
    "id": 1,
    "name": "Agent Capture",
    "email": "capture@leopardo.test",
    "role": "super_admin",
    "platform_role": "super_admin",
    "permissions": [
        "companies.view",
        "companies.manage",
        "companies.provision",
        "billing.view",
        "billing.manage",
        "plans.view",
        "users.view",
        "users.manage",
        "impersonate",
        "killswitch.manage",
        "observability.view",
        "metrics.view",
        "support.manage",
        "announcements.manage",
        "crm.view",
        "showcase.manage",
        "edge.manage",
        "team.manage",
    ],
    "language": "fr",
}


@dataclass
class Front:
    """Un front capturable : serveur, contexte navigateur et écrans."""

    key: str
    label: str
    cwd: Path
    port: int
    serve_cmd: list[str] | None
    routes: list[tuple[str, str]]
    viewport: dict = field(default_factory=lambda: dict(DESKTOP))
    mobile_routes: list[tuple[str, str]] = field(default_factory=list)
    # Ecrans a capturer SANS session : une route comme `/auth/login` redirige
    # vers le dashboard quand un cookie de session valide est present (garde du
    # proxy Next), et la capture ne montrerait jamais l'ecran de connexion.
    anonymous_routes: list[tuple[str, str]] = field(default_factory=list)
    auth: str = "none"  # none | web | admin
    full_page: bool = True


FRONTS: dict[str, Front] = {
    "web": Front(
        key="web",
        label="Vitrine + dashboard (Next.js)",
        cwd=FRONT_DIR / "web",
        port=3000,
        serve_cmd=["npm", "run", "dev", "--", "--port", "3000"],
        auth="web",
        routes=[
            ("accueil", "/"),
            ("tarifs", "/business/pricing"),
            ("fonctionnalites", "/business/employes"),
            ("faq", "/business/faq"),
            ("blog", "/business/blog"),
            ("decouvrir", "/decouvrir"),
            ("emploi", "/emploi"),
            ("dashboard", "/dashboard"),
            ("employes", "/employees"),
            ("absences", "/absences"),
            ("pointage", "/attendance"),
            ("paie", "/payroll"),
            ("comptabilite", "/accounting"),
            ("modules", "/modules"),
        ],
        mobile_routes=[
            ("accueil-mobile", "/"),
            ("tarifs-mobile", "/business/pricing"),
        ],
        anonymous_routes=[("connexion", "/auth/login")],
    ),
    "admin": Front(
        key="admin",
        label="Console plateforme (Vue 3 + Vite)",
        cwd=FRONT_DIR / "admin-dashboard",
        port=5173,
        serve_cmd=["npm", "run", "dev", "--", "--host", "127.0.0.1", "--port", "5173"],
        auth="admin",
        routes=[
            ("dashboard", "/"),
            ("analytique", "/analytics"),
            ("entreprises", "/companies"),
            ("utilisateurs", "/users"),
            ("abonnements", "/subscriptions"),
            ("comptabilite", "/accounting/dashboard"),
            ("recrutement", "/recruitment/offers"),
            ("voyage", "/travel"),
            ("vitrine", "/showcase"),
            ("parametres", "/settings"),
        ],
        anonymous_routes=[("connexion", "/login")],
    ),
    "web-offline": Front(
        key="web-offline",
        label="PWA Edge offline-first (Next.js)",
        cwd=FRONT_DIR / "web-offline",
        port=3001,
        # `output: 'export'` (next.config.js) : ce front se deploie en statique.
        # En `next dev`, l'hydratation ne s'execute pas dans cet environnement
        # (le `useEffect` qui lit `?branch_id` ne tourne jamais et l'ecran reste
        # sur son message « ouvrez avec ?branch_id »). On capture donc l'export
        # statique, c'est-a-dire exactement ce qui est servi en production.
        serve_cmd=[
            "sh",
            "-c",
            "npm run build >/dev/null 2>&1 && cd out && exec python3 -m http.server 3001 --bind 127.0.0.1",
        ],
        routes=[
            ("accueil", "/"),
            # `branch_id` est obligatoire : sans lui l'ecran affiche son message
            # « ouvrez cet ecran avec ?branch_id=<id> » au lieu du KDS.
            ("cuisine", "/kitchen/?branch_id=1"),
        ],
    ),
    "kiosk": Front(
        key="kiosk",
        label="Borne de pointage ZKTeco (statique)",
        cwd=FRONT_DIR / "zkteco-kiosk",
        port=8099,
        serve_cmd=[sys.executable, "-m", "http.server", "8099", "--bind", "127.0.0.1"],
        routes=[
            ("pointage", "/index.html"),
            ("administration", "/admin.html"),
        ],
    ),
}


# --------------------------------------------------------------------------- #
# Serveurs
# --------------------------------------------------------------------------- #
def _port_open(port: int, host: str = "127.0.0.1") -> bool:
    with contextlib.closing(socket.socket(socket.AF_INET, socket.SOCK_STREAM)) as sock:
        sock.settimeout(0.5)
        return sock.connect_ex((host, port)) == 0


def _http_ok(url: str, timeout: float = 3.0) -> bool:
    try:
        with urllib.request.urlopen(url, timeout=timeout) as response:
            return response.status < 500
    except urllib.error.HTTPError as exc:
        # 401/404 restent des réponses du serveur : il est debout.
        return exc.code < 500
    except Exception:
        return False


def wait_for_server(port: int, timeout: float = 180.0) -> bool:
    deadline = time.time() + timeout
    while time.time() < deadline:
        if _port_open(port) and _http_ok(f"http://127.0.0.1:{port}/"):
            return True
        time.sleep(1.0)
    return False


def start_server(front: Front) -> subprocess.Popen | None:
    """Démarre le serveur de dev du front si le port est libre."""
    if _port_open(front.port):
        print(f"  · {front.key}: port {front.port} déjà occupé — réutilisation")
        return None

    if front.serve_cmd is None:
        return None

    log_path = REPO_ROOT / f".capture-{front.key}.log"
    log = log_path.open("w")
    print(f"  · {front.key}: démarrage ({' '.join(front.serve_cmd)}) → {log_path.name}")
    process = subprocess.Popen(
        front.serve_cmd,
        cwd=str(front.cwd),
        stdout=log,
        stderr=subprocess.STDOUT,
        start_new_session=True,
    )
    return process


def stop_server(process: subprocess.Popen | None) -> None:
    if process is None or process.poll() is not None:
        return
    with contextlib.suppress(ProcessLookupError, PermissionError):
        os.killpg(os.getpgid(process.pid), signal.SIGTERM)
    with contextlib.suppress(subprocess.TimeoutExpired):
        process.wait(timeout=15)
    if process.poll() is None:
        with contextlib.suppress(ProcessLookupError, PermissionError):
            os.killpg(os.getpgid(process.pid), signal.SIGKILL)


# --------------------------------------------------------------------------- #
# Contexte navigateur
# --------------------------------------------------------------------------- #
def _mock_api(page) -> None:
    """Répond à toutes les routes API par un payload vide mais valide.

    Sans cela, chaque écran déclenche un vrai appel réseau (backend absent) et
    reste sur son état de chargement ou son écran d'erreur.
    """
    page.route(
        "**/api/**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": {}, "meta": {"total": 0}}),
        ),
    )


# Portefeuille + metriques du cockpit plateforme (`DashboardView.vue`).
# Sans ces deux payloads, le tableau de bord admin s'affiche vide : la vue
# remplace ses valeurs par `data.summary` / `data` et un `{}` les remet a zero.
ADMIN_PORTFOLIO = {
    "summary": {
        "companies": 42,
        "active_companies": 36,
        "mrr": 18450,
        "risk": {"high": 3, "medium": 7, "low": 32},
    },
    "items": [
        {
            "id": 1,
            "name": "TechCorp Algerie SARL",
            "plan": "Business",
            "status": "active",
            "health_score": 92,
            "risk": "low",
            "employees_active": 32,
            "attendance_logs_30d": 640,
            "mrr": 890,
            "currency": "EUR",
        },
        {
            "id": 2,
            "name": "Restaurant Le Cedre",
            "plan": "Starter",
            "status": "active",
            "health_score": 74,
            "risk": "medium",
            "employees_active": 12,
            "attendance_logs_30d": 210,
            "mrr": 190,
            "currency": "EUR",
        },
        {
            "id": 3,
            "name": "Pharma El Amel",
            "plan": "Business",
            "status": "trial",
            "health_score": 38,
            "risk": "high",
            "employees_active": 5,
            "attendance_logs_30d": 24,
            "mrr": 0,
            "currency": "EUR",
        },
    ],
}

ADMIN_METRICS = {
    "revenue": {
        "currency": "EUR",
        "mrr": 18450,
        "arr": 221400,
        "collected_30d": 17980,
        "overdue_total": 1240,
    },
    "companies": {"total": 42, "active": 36, "trial": 4, "suspended": 2},
    "subscriptions": {"active": 36, "churn_30d": 1, "trial_conversion_30d": 3},
}

# Demandes d'essai en attente (`GET /platform/company-requests?status=pending`).
ADMIN_REQUESTS = [
    {
        "id": 11,
        "company_name": "Boulangerie El Baraka",
        "contact_name": "Yacine Haddad",
        "email": "yacine@elbaraka.dz",
        "status": "pending",
        "created_at": "2026-11-17T09:20:00+01:00",
    },
    {
        "id": 12,
        "company_name": "Cabinet Dentaire Sourire",
        "contact_name": "Nadia Belkacem",
        "email": "nadia@sourire.dz",
        "status": "pending",
        "created_at": "2026-11-18T14:05:00+01:00",
    },
]


def _mock_api_admin(page) -> None:
    """Mocks admin : identite complete + cockpit plateforme alimente.

    ⚠️ Playwright evalue les routes dans l'ordre INVERSE de leur enregistrement
    (la derniere declaree gagne). Le catch-all est donc enregistre EN PREMIER,
    sinon il masque les mocks specifiques et le cockpit reste vide.
    """
    page.route(
        "**/api/**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": {}, "meta": {"total": 0}}),
        ),
    )
    page.route(
        "**/api/v1/platform/auth/me**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": ADMIN_USER}),
        ),
    )
    page.route(
        "**/api/v1/platform/companies/health**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": ADMIN_PORTFOLIO}),
        ),
    )
    page.route(
        "**/api/v1/platform/metrics/overview**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": ADMIN_METRICS}),
        ),
    )
    # Route de LISTE : `data` doit etre un tableau (le catch-all renvoie `{}`,
    # ce qui suffisait a faire planter le cockpit avant le durcissement #9651).
    page.route(
        "**/api/v1/platform/company-requests**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": ADMIN_REQUESTS, "meta": {"total": len(ADMIN_REQUESTS)}}),
        ),
    )


def _mock_api_web(page) -> None:
    """Mocks front web : `/auth/me` renvoie le profil tenant complet."""
    page.route(
        "**/api/**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": {}, "meta": {"total": 0}}),
        ),
    )
    page.route(
        "**/api/v1/auth/me**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": WEB_USER}),
        ),
    )


# Etat du pont local de la borne (`/local/status`) : sans lui, l'ecran
# affiche « Borne non configuree » et desactive tous les boutons de pointage.
KIOSK_STATUS = {
    "company_name": "TechCorp Algerie SARL",
    "location_label": "Siege — Alger",
    "device_code": "KIOSK-ALG-01",
    "queue_count": 0,
    "online": True,
    "last_sync_at": "2026-11-19T09:12:00+01:00",
    "punch_methods": ["fingerprint", "badge", "pin", "face", "manager"],
}


def _mock_kiosk(page) -> None:
    """Mocks du pont local Python (`/local/*`) pour la borne ZKTeco.

    Meme regle d'ordre que `_mock_api_admin` : catch-all d'abord.
    """
    page.route(
        "**/local/**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": {}}),
        ),
    )
    page.route(
        "**/local/status**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": KIOSK_STATUS}),
        ),
    )
    page.route(
        "**/local/cloud/config**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": {"punch_methods": KIOSK_STATUS["punch_methods"]}}),
        ),
    )


def prepare_context(browser, front: Front, viewport: dict):
    context = browser.new_context(
        viewport=viewport,
        locale="fr-FR",
        timezone_id="Africa/Algiers",
        device_scale_factor=1,
        service_workers="block",
    )

    if front.auth == "admin":
        # Le module de stockage consomme `sessionStorage.admin_token` au boot
        # (#7695) : le semer AVANT le chargement de l'app est le seul moyen.
        context.add_init_script(
            f"sessionStorage.setItem('admin_token', {json.dumps(FAKE_TOKEN)});"
            "localStorage.setItem('admin_locale', 'fr');"
        )
    elif front.key == "kiosk":
        # `app.js` lit la configuration sur `window` AVANT `init()` : sans ces
        # deux globales, `hasKioskConfiguration()` est faux et la borne
        # s'arrete sur son ecran « non configuree » (#2911).
        context.add_init_script(
            "window.__KIOSK_API_BASE = 'http://127.0.0.1:8000/api/v1';"
            "window.__KIOSK_DEVICE_CODE = 'KIOSK-ALG-01';"
            "window.__KIOSK_DEFAULT_ACTION = 'check_in';"
        )
    elif front.auth == "web":
        context.add_cookies(
            [
                {
                    "name": "leopardo_token",
                    "value": FAKE_TOKEN,
                    "url": f"http://127.0.0.1:{front.port}",
                    "httpOnly": True,
                    "sameSite": "Lax",
                },
                {
                    # Visiteur ayant déjà consenti : pas de bannière qui
                    # recouvre le bas de page (#7618).
                    "name": "leopardo_consent",
                    "value": json.dumps(
                        {
                            "version": 1,
                            "necessary": True,
                            "analytics": False,
                            "marketing": False,
                            "decidedAt": "2026-09-01T08:00:00+00:00",
                        },
                        separators=(",", ":"),
                    ),
                    "url": f"http://127.0.0.1:{front.port}",
                    "sameSite": "Lax",
                },
            ]
        )
        context.add_init_script(
            f"localStorage.setItem('auth_user', {json.dumps(json.dumps(WEB_USER))});"
            "localStorage.setItem('leopardo_locale', 'fr');"
        )

    return context


def capture_route(page, front: Front, name: str, route: str, out_dir: Path) -> Path | None:
    url = f"http://127.0.0.1:{front.port}{route}"
    target = out_dir / f"{name}.png"
    try:
        page.goto(url, wait_until="domcontentloaded", timeout=60_000)
        # Les vues qui chargent leurs données derrière un spinner (`isLoading`)
        # laissent sinon une capture sur l'écran de chargement. Attention :
        # au `domcontentloaded` le spinner n'est PAS encore monté, donc tester
        # sa seule absence sortirait immédiatement. On attend d'abord son
        # apparition (budget court : certaines pages n'en ont pas), puis sa
        # disparition (budget large : l'optimisation Vite à froid est lente).
        appeared = False
        appear_deadline = time.time() + 10
        while time.time() < appear_deadline:
            if page.locator(".animate-spin").count() > 0:
                appeared = True
                break
            page.wait_for_timeout(250)

        if appeared:
            gone_deadline = time.time() + 60
            while time.time() < gone_deadline:
                if page.locator(".animate-spin").count() == 0:
                    break
                page.wait_for_timeout(500)

        # Filet de sécurité : certaines vues montent un second indicateur
        # (composant chargé à la demande) qui ne se démonte pas dans le même
        # tick. On laisse le rendu se stabiliser avant l'objet.
        page.wait_for_timeout(4_000)

        # Réseau au repos (les mocks répondent instantanément) + polices/animations.
        with contextlib.suppress(Exception):
            page.wait_for_load_state("networkidle", timeout=20_000)
        page.wait_for_timeout(1_500)
        page.screenshot(path=str(target), full_page=front.full_page)
        size = target.stat().st_size
        if size < 5_000:
            print(f"    ! {name}: capture suspecte ({size} o)")
        print(f"    ✓ {name} ({size // 1024} Ko)")
        return target
    except Exception as exc:  # noqa: BLE001 — une route cassée ne doit pas tout arrêter
        print(f"    ✗ {name} ({route}): {type(exc).__name__}: {str(exc)[:160]}")
        return None


def capture_front(browser, front: Front) -> list[Path]:
    out_dir = OUTPUT_ROOT / front.key
    out_dir.mkdir(parents=True, exist_ok=True)

    produced: list[Path] = []

    context = prepare_context(browser, front, front.viewport)
    page = context.new_page()
    if front.auth == "admin":
        _mock_api_admin(page)
    elif front.auth == "web":
        _mock_api_web(page)
    elif front.key == "kiosk":
        _mock_kiosk(page)
    else:
        _mock_api(page)

    print(f"  {front.label} — {len(front.routes)} écrans")
    for name, route in front.routes:
        shot = capture_route(page, front, name, route, out_dir)
        if shot:
            produced.append(shot)
    context.close()

    if front.anonymous_routes:
        # Contexte neuf, sans cookie ni jeton : c'est le seul moyen de rendre
        # l'ecran de connexion plutot que le dashboard deja authentifie.
        anon_front = Front(
            key=front.key,
            label=front.label,
            cwd=front.cwd,
            port=front.port,
            serve_cmd=front.serve_cmd,
            routes=front.anonymous_routes,
            viewport=front.viewport,
            auth="none",
            full_page=front.full_page,
        )
        anon_context = prepare_context(browser, anon_front, front.viewport)
        anon_page = anon_context.new_page()
        if front.auth == "admin":
            _mock_api_admin(anon_page)
        elif front.auth == "web":
            _mock_api_web(anon_page)
        else:
            _mock_api(anon_page)
        print(f"  {front.label} — {len(front.anonymous_routes)} écran(s) anonyme(s)")
        for name, route in front.anonymous_routes:
            shot = capture_route(anon_page, front, name, route, out_dir)
            if shot:
                produced.append(shot)
        anon_context.close()

    if front.mobile_routes:
        mobile_context = prepare_context(browser, front, MOBILE)
        mobile_page = mobile_context.new_page()
        if front.auth == "web":
            _mock_api_web(mobile_page)
        else:
            _mock_api(mobile_page)
        print(f"  {front.label} — {len(front.mobile_routes)} écrans mobiles")
        for name, route in front.mobile_routes:
            shot = capture_route(mobile_page, front, name, route, out_dir)
            if shot:
                produced.append(shot)
        mobile_context.close()

    return produced


# --------------------------------------------------------------------------- #
# Entrée
# --------------------------------------------------------------------------- #
def main() -> int:
    global OUTPUT_ROOT

    parser = argparse.ArgumentParser(
        description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter
    )
    parser.add_argument("--front", choices=sorted(FRONTS), help="Ne capturer qu'un front")
    parser.add_argument("--all", action="store_true", help="Capturer tous les fronts")
    parser.add_argument("--serve", action="store_true", help="Démarrer/arrêter les serveurs de dev")
    parser.add_argument("--out", type=Path, default=OUTPUT_ROOT, help="Répertoire de sortie")
    args = parser.parse_args()

    if not args.all and not args.front:
        parser.error("préciser --all ou --front <nom>")

    OUTPUT_ROOT = args.out

    selected = list(FRONTS.values()) if args.all else [FRONTS[args.front]]

    if shutil.which("npm") is None and any(
        f.serve_cmd and f.serve_cmd[0] == "npm" for f in selected
    ):
        print("npm introuvable : lancer les serveurs manuellement puis relancer sans --serve.")
        return 2

    # Un front à la fois : un serveur Next + un Chromium tiennent dans les 2 Go
    # du conteneur, trois serveurs + un navigateur non (crash du renderer
    # « Page crashed » constaté avec /dev/shm à 64 Mo).
    produced: list[Path] = []
    for front in selected:
        process: subprocess.Popen | None = None
        try:
            if args.serve:
                print(f"\n{front.key} — démarrage…")
                process = start_server(front)
                if not wait_for_server(front.port):
                    print(f"  ✗ {front.key}: port {front.port} injoignable après 180 s")
                    continue
                print(f"  ✓ {front.key}: http://127.0.0.1:{front.port}")
            elif not _port_open(front.port):
                print(f"  ✗ {front.key}: rien n'écoute sur {front.port} (utiliser --serve)")
                continue

            with sync_playwright() as playwright:
                browser = playwright.chromium.launch(
                    args=[
                        "--no-sandbox",
                        "--disable-dev-shm-usage",
                        "--disable-gpu",
                        "--disable-extensions",
                        "--js-flags=--max-old-space-size=512",
                    ]
                )
                try:
                    produced.extend(capture_front(browser, front))
                finally:
                    browser.close()
        finally:
            if args.serve:
                stop_server(process)
                # Laisse le port se libérer avant le front suivant.
                time.sleep(2)

    print(f"\n{len(produced)} capture(s) écrite(s) sous {OUTPUT_ROOT.relative_to(REPO_ROOT)}/")
    return 0 if produced else 1


if __name__ == "__main__":
    sys.exit(main())
