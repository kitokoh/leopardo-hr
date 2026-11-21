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
import threading
import time
import urllib.error
import urllib.request
from dataclasses import dataclass, field
from urllib.parse import parse_qs, urlparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
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
        # Surface grand public : chaque page de la vitrine est capturée en
        # desktop ET en mobile (390x844), c'est là que se joue la lisibilité
        # réelle des parcours d'acquisition.
        mobile_routes=[
            ("accueil-mobile", "/"),
            ("tarifs-mobile", "/business/pricing"),
            ("fonctionnalites-mobile", "/business/employes"),
            ("faq-mobile", "/business/faq"),
            ("blog-mobile", "/business/blog"),
            ("decouvrir-mobile", "/decouvrir"),
            ("emploi-mobile", "/emploi"),
            # Vitrines grand public par verticale (ADR-0034) en mobile :
            # c'est la que se joue la lisibilite reelle des annuaires.
            ("public-restaurants-mobile", "/restaurants"),
            ("public-retail-mobile", "/boutiques"),
            ("public-hospitality-mobile", "/stay"),
            ("public-trade-mobile", "/entreprises"),
            ("public-travel-mobile", "/billets"),
            ("public-pharmacy-mobile", "/pharmacies"),
            ("public-vtc-mobile", "/operateurs"),
            ("public-careers-mobile", "/emploi"),
            # Sites professionnels dedies par domaine (ADR-0034 : le front
            # commercial `modules/vitrine` + `/business/*` est hors du perimetre
            # grand public). Captures en mobile pour verifier la lisibilite des
            # parcours d'inscription metier sur telephone.
            ("business-restaurateur-mobile", "/business/restaurateur"),
            ("business-trade-mobile", "/business/trade"),
            ("business-station-service-mobile", "/business/packs/station-service"),
            ("business-ecole-mobile", "/business/packs/ecole"),
            ("business-agence-de-voyage-mobile", "/business/packs/agence-de-voyage"),
            ("business-cagnottes-mobile", "/business/packs/cagnottes"),
            ("business-vtc-mobile", "/business/packs/vtc"),
        ],
        # Sites professionnels dedies par domaine : pages marketing publiques,
        # aucune session requise. Capturees dans un contexte anonyme pour ne pas
        # dependre d'un cookie de session valide.
        anonymous_routes=[
            ("connexion", "/auth/login"),
            # --- Vitrines grand public par verticale (ADR-0034) -------------
            # `front/web/src/modules/public-surfaces/<verticale>` : huit
            # surfaces autonomes, chacune avec son identite propre (nom,
            # palette, domaine canonique) et sa route d'annuaire.
            ("public-restaurants", "/restaurants"),
            ("public-retail", "/boutiques"),
            ("public-hospitality", "/stay"),
            ("public-trade", "/entreprises"),
            ("public-travel", "/billets"),
            ("public-pharmacy", "/pharmacies"),
            ("public-vtc", "/operateurs"),
            ("public-careers", "/emploi"),
            # --- Sites professionnels dedies par domaine --------------------
            ("business-restaurateur", "/business/restaurateur"),
            ("business-trade", "/business/trade"),
            ("business-station-service", "/business/packs/station-service"),
            ("business-ecole", "/business/packs/ecole"),
            ("business-agence-de-voyage", "/business/packs/agence-de-voyage"),
            ("business-cagnottes", "/business/packs/cagnottes"),
            ("business-vtc", "/business/packs/vtc"),
        ],
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


# Types canoniques de l'annuaire public (`?type=`). `meta.types` est la SEULE
# source des catégories affichées par `/decouvrir` : sans lui, `knownTypes`
# vaut `[]` et la barre de filtres reste vide.
DISCOVERY_TYPES = ["restaurant", "pharmacy", "hotel", "travel_agency", "shop"]

DISCOVERY_ITEMS = [
    {
        "type": "restaurant",
        "slug": "le-cedre",
        "name": "Restaurant Le Cedre",
        "category": "Cuisine libanaise",
        "city": "Alger",
        "description": "Table familiale au coeur de la capitale, terrasse ombragee.",
        "url": "/restaurateur/le-cedre",
    },
    {
        "type": "pharmacy",
        "slug": "pharma-el-amel",
        "name": "Pharmacie El Amel",
        "category": "Parapharmacie",
        "city": "Oran",
        "description": "Officine de garde, conseil et orthopedie.",
        "url": "/pharmacie/pharma-el-amel",
    },
    {
        "type": "hotel",
        "slug": "hotel-es-salam",
        "name": "Hotel Es Salam",
        "category": "3 etoiles",
        "city": "Constantine",
        "description": "Vue sur les gorges, petit-dejeuner inclus.",
        "url": "/stay/hotel-es-salam",
    },
    {
        "type": "travel_agency",
        "slug": "voyages-sahara",
        "name": "Voyages Sahara",
        "category": "Circuits",
        "city": "Ghardaia",
        "description": "Sejours et circuits dans le grand Sud algerien.",
        "url": "/operateurs/voyages-sahara",
    },
    {
        "type": "shop",
        "slug": "boulangerie-el-baraka",
        "name": "Boulangerie El Baraka",
        "category": "Artisanat",
        "city": "Blida",
        "description": "Pain traditionnel cuit au four a bois.",
        "url": "/boutiques/boulangerie-el-baraka",
    },
]

DISCOVERY_META = {
    "total": len(DISCOVERY_ITEMS),
    "per_page": 20,
    "page": 1,
    "last_page": 1,
    "types": DISCOVERY_TYPES,
    "radius_km": None,
    "actions": {},
    "action_urls": {},
}

# --------------------------------------------------------------------------- #
# Vitrines grand public par verticale (ADR-0034)
# --------------------------------------------------------------------------- #
# `front/web/src/modules/public-surfaces/<verticale>` est la norme du front
# grand public : huit verticales, chacune avec son identite (nom, palette,
# domaine canonique) et sa route d'annuaire.
#
#   restaurants  -> /restaurants   (GET /public/restaurants)
#   retail       -> /boutiques     (GET /public/discover?type=retail_shop)
#   hospitality  -> /stay          (GET /public/discover?type=hospitality_property)
#   trade        -> /entreprises   (GET /public/discover?type=trade_company)
#   travel       -> /billets       (GET /public/travel/marketplace/trips)
#   pharmacy     -> /pharmacies    (GET /public/discover?type=pharmacy)
#   vtc          -> /operateurs    (GET /public/discover?type=vtc_operator)
#   careers      -> /emploi        (GET /public/careers)
#
# Le mock DOIT filtrer par `type` : servir tous les items ferait afficher des
# pharmacies dans l'annuaire des boutiques, ce qui rendrait la capture trompeuse
# sur la composition propre de chaque verticale.
PUBLIC_SURFACE_ITEMS: dict[str, list[dict]] = {
    "retail_shop": [
        {
            "type": "retail_shop",
            "slug": "boulangerie-el-baraka",
            "name": "Boulangerie El Baraka",
            "category": "Artisanat",
            "city": "Blida",
            "description": "Pain traditionnel cuit au four a bois, pâtisseries du jour.",
            "url": "/boutiques/boulangerie-el-baraka",
        },
        {
            "type": "retail_shop",
            "slug": "epicerie-du-coin",
            "name": "Épicerie du Coin",
            "category": "Alimentation",
            "city": "Alger",
            "description": "Produits frais, fruits et légumes de saison livrés le matin.",
            "url": "/boutiques/epicerie-du-coin",
        },
        {
            "type": "retail_shop",
            "slug": "atelier-tissus-oran",
            "name": "Atelier Tissus Oran",
            "category": "Textile",
            "city": "Oran",
            "description": "Tissus au mètre, coupes sur mesure et retouches rapides.",
            "url": "/boutiques/atelier-tissus-oran",
        },
        {
            "type": "retail_shop",
            "slug": "librairie-el-feth",
            "name": "Librairie El Feth",
            "category": "Culture",
            "city": "Constantine",
            "description": "Livres scolaires, romans et papeterie pour toute la famille.",
            "url": "/boutiques/librairie-el-feth",
        },
    ],
    "hospitality_property": [
        {
            "type": "hospitality_property",
            "slug": "hotel-es-salam",
            "name": "Hôtel Es Salam",
            "category": "Hôtel 3 étoiles",
            "city": "Constantine",
            "description": "Vue sur les gorges, petit-déjeuner inclus, parking privé.",
            "url": "/stay/hotel-es-salam",
        },
        {
            "type": "hospitality_property",
            "slug": "riad-des-oliviers",
            "name": "Riad des Oliviers",
            "category": "Maison d'hôtes",
            "city": "Tlemcen",
            "description": "Patio ombragé, cinq chambres, cuisine maison le soir.",
            "url": "/stay/riad-des-oliviers",
        },
        {
            "type": "hospitality_property",
            "slug": "residence-les-pins",
            "name": "Résidence Les Pins",
            "category": "Résidence",
            "city": "Annaba",
            "description": "Appartements équipés à deux pas de la corniche.",
            "url": "/stay/residence-les-pins",
        },
    ],
    "trade_company": [
        {
            "type": "trade_company",
            "slug": "sahel-import-export",
            "name": "Sahel Import Export",
            "category": "Agroalimentaire",
            "city": "Alger",
            "description": "Négoce de denrées sèches, import et distribution domestique.",
            "url": "/entreprises/sahel-import-export",
        },
        {
            "type": "trade_company",
            "slug": "metal-dz",
            "name": "Metal DZ",
            "category": "Métallurgie",
            "city": "Oran",
            "description": "Profilés acier et aluminium, livraison sur chantier.",
            "url": "/entreprises/metal-dz",
        },
        {
            "type": "trade_company",
            "slug": "textile-sud",
            "name": "Textile Sud",
            "category": "Textile",
            "city": "Ghardaïa",
            "description": "Grossiste en tissus et confection, export vers le Sahel.",
            "url": "/entreprises/textile-sud",
        },
    ],
    "pharmacy": [
        {
            "type": "pharmacy",
            "slug": "pharma-el-amel",
            "name": "Pharmacie El Amel",
            "category": "on_duty",
            "city": "Oran",
            "description": "Officine de garde, conseil et orthopédie.",
            "url": "/pharmacies/pharma-el-amel",
        },
        {
            "type": "pharmacy",
            "slug": "pharmacie-centrale-alger",
            "name": "Pharmacie Centrale",
            "category": None,
            "city": "Alger",
            "description": "Parapharmacie, homéopathie et matériel médical.",
            "url": "/pharmacies/pharmacie-centrale-alger",
        },
        {
            "type": "pharmacy",
            "slug": "pharmacie-ennour",
            "name": "Pharmacie Ennour",
            "category": "on_duty",
            "city": "Constantine",
            "description": "Garde de nuit, prise de tension et vaccinations.",
            "url": "/pharmacies/pharmacie-ennour",
        },
    ],
    "vtc_operator": [
        {
            "type": "vtc_operator",
            "slug": "alger-ride",
            "name": "Alger Ride",
            "category": "VTC",
            "city": "Alger",
            "description": "Flotte de berlines climatisées, réservation à la minute.",
            "url": "/operateurs/alger-ride",
        },
        {
            "type": "vtc_operator",
            "slug": "taxi-oran-express",
            "name": "Taxi Oran Express",
            "category": "Taxi",
            "city": "Oran",
            "description": "Courses urbaines et transferts aéroport 24h/24.",
            "url": "/operateurs/taxi-oran-express",
        },
        {
            "type": "vtc_operator",
            "slug": "constantine-mobilite",
            "name": "Constantine Mobilité",
            "category": "VTC",
            "city": "Constantine",
            "description": "Chauffeurs partenaires, tarif fixe annoncé avant la course.",
            "url": "/operateurs/constantine-mobilite",
        },
    ],
}

# Annuaire public des restaurants (`GET /public/restaurants`), contrat
# `RestaurantPublicBranchResource` — distinct de l'agrégateur `discover`.
PUBLIC_RESTAURANTS = [
    {
        "slug": "le-cedre",
        "name": "Restaurant Le Cèdre",
        "establishment_type": "restaurant",
        "cuisine_types": ["Libanaise", "Méditerranéenne"],
        "city": "Alger",
        "description": "Table familiale au cœur de la capitale, terrasse ombragée.",
        "cover_image_url": None,
        "latitude": 36.7538,
        "longitude": 3.0588,
        "rating_avg": 4.6,
        "reviews_count": 128,
    },
    {
        "slug": "la-toscane",
        "name": "La Toscane",
        "establishment_type": "pizzeria",
        "cuisine_types": ["Italienne"],
        "city": "Oran",
        "description": "Pizzas au feu de bois et pâtes fraîches faites maison.",
        "cover_image_url": None,
        "latitude": 35.6971,
        "longitude": -0.6308,
        "rating_avg": 4.3,
        "reviews_count": 74,
    },
    {
        "slug": "cafe-des-arcades",
        "name": "Café des Arcades",
        "establishment_type": "cafe",
        "cuisine_types": ["Café", "Pâtisserie"],
        "city": "Constantine",
        "description": "Café de spécialité et pâtisseries orientales, wifi et calme.",
        "cover_image_url": None,
        "latitude": 36.365,
        "longitude": 6.6147,
        "rating_avg": None,
        "reviews_count": 0,
    },
    {
        "slug": "brasserie-du-port",
        "name": "Brasserie du Port",
        "establishment_type": "brasserie",
        "cuisine_types": ["Française", "Poissons"],
        "city": "Annaba",
        "description": "Poisson du jour et vue sur le port, service continu.",
        "cover_image_url": None,
        "latitude": 36.9,
        "longitude": 7.7667,
        "rating_avg": 4.1,
        "reviews_count": 39,
    },
]

# Trajets publics (`GET /public/travel/marketplace/trips`).
PUBLIC_TRIPS = [
    {
        "reference": "TRP-2026-0041",
        "id": 41,
        "origin": "Alger",
        "destination": "Oran",
        "departure_at": "2026-11-22T07:30:00+01:00",
        "arrival_at": "2026-11-22T12:10:00+01:00",
        "price_minor": 320000,
        "currency": "DZD",
        "seats_available": 18,
    },
    {
        "reference": "TRP-2026-0042",
        "id": 42,
        "origin": "Alger",
        "destination": "Constantine",
        "departure_at": "2026-11-22T09:00:00+01:00",
        "arrival_at": "2026-11-22T14:45:00+01:00",
        "price_minor": 280000,
        "currency": "DZD",
        "seats_available": 4,
    },
    {
        "reference": "TRP-2026-0043",
        "id": 43,
        "origin": "Oran",
        "destination": "Tlemcen",
        "departure_at": "2026-11-23T06:15:00+01:00",
        "arrival_at": "2026-11-23T08:40:00+01:00",
        "price_minor": 150000,
        "currency": "DZD",
        "seats_available": 0,
    },
    {
        "reference": "TRP-2026-0044",
        "id": 44,
        "origin": "Constantine",
        "destination": "Annaba",
        "departure_at": "2026-11-23T15:20:00+01:00",
        "arrival_at": "2026-11-23T18:05:00+01:00",
        "price_minor": 190000,
        "currency": "DZD",
        "seats_available": 22,
    },
]

# Annuaire cross-tenant des entreprises qui recrutent (`GET /public/careers`).
# Consommé par `/emploi`, qui est rendu en SSR : ce payload doit donc être servi
# par un VRAI backend joignable (cf. `MockBackend`), pas par `page.route`.
HIRING_COMPANIES = [
    {
        "slug": "techcorp-algerie",
        "name": "TechCorp Algerie SARL",
        "country": "DZ",
        "last_published_at": "2026-11-18T10:00:00+01:00",
    },
    {
        "slug": "restaurant-le-cedre",
        "name": "Restaurant Le Cedre",
        "country": "DZ",
        "last_published_at": "2026-11-16T09:30:00+01:00",
    },
    {
        "slug": "pharma-el-amel",
        "name": "Pharmacie El Amel",
        "country": "DZ",
        "last_published_at": "2026-11-12T15:45:00+01:00",
    },
    {
        "slug": "voyages-sahara",
        "name": "Voyages Sahara",
        "country": "DZ",
        "last_published_at": "2026-11-08T11:20:00+01:00",
    },
]


def _discover_payload(url: str) -> dict:
    """Réponse de `GET /public/discover` filtrée par `?type=`.

    L'agrégateur public ne renvoie que les items du type demandé : servir la
    liste complète ferait apparaître des pharmacies dans l'annuaire des
    boutiques et rendrait la capture mensongère sur la composition de chaque
    verticale.
    """
    query = parse_qs(urlparse(url).query)
    requested = (query.get("type") or [""])[0]

    if requested and requested in PUBLIC_SURFACE_ITEMS:
        items = PUBLIC_SURFACE_ITEMS[requested]
    elif requested:
        items = []
    else:
        items = DISCOVERY_ITEMS

    meta = dict(DISCOVERY_META)
    meta["total"] = len(items)
    meta["types"] = DISCOVERY_TYPES + sorted(PUBLIC_SURFACE_ITEMS)

    return {"data": items, "meta": meta}


def _mock_api_web(page) -> None:
    """Mocks front web : `/auth/me` renvoie le profil tenant complet.

    ⚠️ Playwright évalue les routes dans l'ordre INVERSE de leur enregistrement :
    le catch-all est déclaré EN PREMIER, sinon il masque les mocks spécifiques.
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
        "**/api/v1/auth/me**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": WEB_USER}),
        ),
    )
    page.route(
        "**/api/v1/public/discover**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(_discover_payload(route.request.url)),
        ),
    )
    page.route(
        "**/api/v1/public/restaurants**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(
                {
                    "data": PUBLIC_RESTAURANTS,
                    "meta": {
                        "current_page": 1,
                        "last_page": 1,
                        "per_page": len(PUBLIC_RESTAURANTS),
                        "total": len(PUBLIC_RESTAURANTS),
                    },
                }
            ),
        ),
    )
    page.route(
        "**/api/v1/public/travel/marketplace/trips**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": PUBLIC_TRIPS}),
        ),
    )
    page.route(
        "**/api/v1/public/careers**",
        lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": HIRING_COMPANIES}),
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

        # Reveal au defilement : la vitrine anime ses blocs avec GSAP
        # (`useScrollReveal`, `.gsap-reveal` / `.gsap-stagger`) et framer-motion.
        # Sans défilement, tout ce qui est sous la ligne de flottaison reste à
        # `opacity: 0` et la capture pleine page montre des trous blancs (FAQ,
        # blog). On parcourt la page par paliers pour déclencher chaque trigger,
        # puis on remonte avant l'objet.
        with contextlib.suppress(Exception):
            page.evaluate(
                """async () => {
                    const step = Math.max(200, Math.floor(window.innerHeight * 0.8));
                    const height = document.body.scrollHeight;
                    for (let y = 0; y < height; y += step) {
                        window.scrollTo(0, y);
                        await new Promise((r) => setTimeout(r, 120));
                    }
                    window.scrollTo(0, document.body.scrollHeight);
                    await new Promise((r) => setTimeout(r, 400));
                    window.scrollTo(0, 0);
                    await new Promise((r) => setTimeout(r, 300));
                }"""
            )
        page.wait_for_timeout(1_200)

        # L'indicateur de developpement de Next.js (`nextjs-portal`, badge
        # « N Issue ») est un artefact de `next dev` : il n'existe pas en
        # production et n'a rien a faire sur une capture destinee a la revue.
        with contextlib.suppress(Exception):
            page.add_style_tag(
                content="nextjs-portal,[data-nextjs-toast],[data-nextjs-dev-tools-button]"
                "{display:none !important;}"
            )
        page.wait_for_timeout(300)

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
# --------------------------------------------------------------------------- #
# Backend mock (SSR)
# --------------------------------------------------------------------------- #
class MockBackend:
    """Backend JSON minimal pour les pages rendues côté serveur.

    `/emploi` appelle `GET /public/careers` depuis le serveur Next
    (`loadHiringDirectory` -> `resolveBackendBaseUrl()`), donc AVANT que le
    navigateur n'existe : `page.route` ne peut pas l'intercepter. Sans backend
    joignable, la page affiche son état « service momentanément indisponible ».

    Le serveur écoute sur le port du repli dev de `backend-url.ts`
    (`http://localhost:8000/api/v1`) et ne sert que les routes publiques
    nécessaires à la capture.
    """

    PORT = 8000

    def __init__(self) -> None:
        self._server: ThreadingHTTPServer | None = None
        self._thread: threading.Thread | None = None

    def start(self) -> None:
        if _port_open(self.PORT):
            print(f"  · backend mock: port {self.PORT} déjà occupé — réutilisation")
            return

        routes = {
            "/api/v1/public/careers": {"data": HIRING_COMPANIES},
            "/api/v1/public/discover": {"data": DISCOVERY_ITEMS, "meta": DISCOVERY_META},
            "/api/v1/public/restaurants": {
                "data": PUBLIC_RESTAURANTS,
                "meta": {
                    "current_page": 1,
                    "last_page": 1,
                    "per_page": len(PUBLIC_RESTAURANTS),
                    "total": len(PUBLIC_RESTAURANTS),
                },
            },
            "/api/v1/public/travel/marketplace/trips": {"data": PUBLIC_TRIPS},
        }

        class Handler(BaseHTTPRequestHandler):
            def do_GET(self) -> None:  # noqa: N802 — contrat de la stdlib
                path = self.path.split("?", 1)[0].rstrip("/") or "/"
                if path == "/api/v1/public/discover":
                    # `?type=` doit être respecté : c'est ce qui distingue les
                    # huit annuaires verticaux les uns des autres.
                    payload = _discover_payload(self.path)
                else:
                    payload = routes.get(path)
                if payload is None:
                    body = json.dumps({"data": {}, "meta": {"total": 0}}).encode()
                else:
                    body = json.dumps(payload).encode()
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)

            def log_message(self, *args) -> None:  # silence les logs d'accès
                return

        self._server = ThreadingHTTPServer(("127.0.0.1", self.PORT), Handler)
        self._thread = threading.Thread(target=self._server.serve_forever, daemon=True)
        self._thread.start()
        print(f"  · backend mock: http://127.0.0.1:{self.PORT}/api/v1")

    def stop(self) -> None:
        if self._server is not None:
            self._server.shutdown()
            self._server.server_close()
            self._server = None


def main() -> int:
    global OUTPUT_ROOT

    parser = argparse.ArgumentParser(
        description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter
    )
    parser.add_argument("--front", choices=sorted(FRONTS), help="Ne capturer qu'un front")
    parser.add_argument("--all", action="store_true", help="Capturer tous les fronts")
    parser.add_argument("--serve", action="store_true", help="Démarrer/arrêter les serveurs de dev")
    parser.add_argument("--out", type=Path, default=OUTPUT_ROOT, help="Répertoire de sortie")
    parser.add_argument(
        "--only",
        default=None,
        help="Ne capturer que les écrans dont le nom commence par ce préfixe (ex. public-)",
    )
    args = parser.parse_args()

    if not args.all and not args.front:
        parser.error("préciser --all ou --front <nom>")

    OUTPUT_ROOT = args.out

    selected = list(FRONTS.values()) if args.all else [FRONTS[args.front]]

    if args.only:
        # Filtre de commodité : recapturer une famille d'écrans sans relancer
        # les 52 captures du front web (chaque page coûte ~10 s de rendu).
        for front in selected:
            front.routes = [r for r in front.routes if r[0].startswith(args.only)]
            front.anonymous_routes = [
                r for r in front.anonymous_routes if r[0].startswith(args.only)
            ]
            front.mobile_routes = [r for r in front.mobile_routes if r[0].startswith(args.only)]

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
        backend: MockBackend | None = None
        try:
            # Le front web rend `/emploi` en SSR : il lui faut un backend
            # joignable AVANT le démarrage du serveur Next.
            if front.key == "web":
                backend = MockBackend()
                backend.start()

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
            if backend is not None:
                backend.stop()

    # `--out` peut pointer hors du dépôt : `relative_to` lèverait alors une
    # ValueError en fin de capture, après que tous les fichiers ont été écrits.
    with contextlib.suppress(ValueError):
        print(f"\n{len(produced)} capture(s) écrite(s) sous {OUTPUT_ROOT.relative_to(REPO_ROOT)}/")
    if not OUTPUT_ROOT.is_relative_to(REPO_ROOT):
        print(f"\n{len(produced)} capture(s) écrite(s) sous {OUTPUT_ROOT}/")
    return 0 if produced else 1


if __name__ == "__main__":
    sys.exit(main())
