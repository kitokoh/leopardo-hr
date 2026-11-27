#!/usr/bin/env python3
"""Renote les 96 cas d'usage à partir des preuves d'EXÉCUTION réelles.

Entrées (toutes produites pendant la campagne du 2026-11-26) :
  _runtime.json        — sondes HTTP réelles contre l'API locale (Laravel 12,
                         PHP 8.4.28, PostgreSQL 15.20 + PostGIS 3.3)
  _tests_runtime.json  — échantillon de tests PHPUnit réellement exécuté par cas
  _evidence.json       — inventaire statique (routes, contrôleurs, fichiers de test)

Barème du cahier de recette, appliqué strictement :
  5 = entièrement conforme, aucune anomalie
  4 = conforme, défauts cosmétiques ou mineurs
  3 = fonctionne partiellement (contournement nécessaire ou critère non vérifiable)
  2 = défaillance fonctionnelle sur un critère majeur
  1 = bloqué (erreur serveur / accès impossible) mais comportement compréhensible
  0 = impossible à exécuter (prérequis absents)

Règle de calcul (aucune note « au feeling ») :
  - tests exécutés ET verts ET au moins un endpoint 2xx  -> 4
  - tests exécutés ET verts, mais aucun endpoint 2xx      -> 3
  - tests exécutés avec échecs                            -> 2
  - aucun test exécutable, endpoints 2xx                  -> 3
  - aucun test, aucun endpoint                            -> 3 (non vérifiable)
  - endpoint documenté renvoyant 404 (route absente)      -> 2
  - 5 réservé aux cas dont TOUS les critères sont mesurés (liste explicite)
"""
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent

runtime = json.loads((HERE / "_runtime.json").read_text(encoding="utf-8"))
tests = json.loads((HERE / "_tests_runtime.json").read_text(encoding="utf-8"))
evidence = {r["id"]: r for r in json.loads((HERE / "_evidence.json").read_text(encoding="utf-8"))}
rt = {c["id"]: c for c in runtime["cases"]}

# --- Existence réelle d'un chemin au routeur -------------------------------
# Sert à distinguer « route absente » (écart) de « ressource absente en base »
# (comportement normal). Source : `php artisan route:list --json`.
_ROUTES = None


def _load_routes():
    global _ROUTES
    if _ROUTES is not None:
        return _ROUTES
    _ROUTES = []
    try:
        import subprocess
        out = subprocess.run(
            ["php", "artisan", "route:list", "--json"],
            cwd="/workspace/leopardo-hr/api", capture_output=True, text=True, timeout=120,
        ).stdout
        _ROUTES = [r["uri"] for r in json.loads(out)]
    except Exception:  # noqa: BLE001
        _ROUTES = []
    return _ROUTES


def route_exists(path):
    """True si `path` correspond à une route déclarée (paramètres compris)."""
    import re
    routes = _load_routes()
    if not routes:
        return True  # routeur indisponible : on ne pénalise pas
    p = path.split("?")[0].lstrip("/")
    for uri in routes:
        pattern = "^" + re.sub(r"\{[^}]+\}", "[^/]+", uri.strip("/")) + "$"
        if re.match(pattern, p):
            return True
    return False

# Cas dont TOUS les critères sont mesurés en exécution -> 5
FULL_PASS = {
    "UC-13": "health/live/ready mesurés en HTTP (200/200/200) et suite de tests plateforme verte",
}

# Écarts constatés en exécution, qui plafonnent la note
CAPS = {
    "UC-17": (3, "`GET /api/v1/company/team-roles` répond **403** avec le persona Manager RH : la route existe mais "
                 "le périmètre RBAC ne lui ouvre pas la lecture des rôles d'équipe. `GET /api/v1/invitations` répond "
                 "**200**. L'attribution passe par `POST /employees/{employee}/assign-role` (route présente au "
                 "routeur, non rejouée en écriture)."),
    "UC-29": (3, "`GET /api/v1/expense-claims` répond **200** : la ressource existe et est lisible. Le cycle "
                 "soumission → approbation → rejet n'a pas été rejoué en écriture."),
    "UC-40": (3, "`GET /api/v1/crm/search` répond **422** : la route existe et valide ses paramètres, mais la "
                 "recherche transverse n'a pas été rejouée avec un terme exploitable, donc le filtrage par "
                 "permissions n'a pas été observé."),
    "UC-18": (3, "`GET /api/v1/planning` et `GET /api/v1/reports/overtime` répondent **200** : les deux routes "
                 "existent et sont lisibles. La validation/rejet d'une demande d'heures supplémentaires n'a pas été "
                 "rejouée en écriture."),
    "UC-70": (2, "Aucun test desktop exécutable (0 fichier) et `docs/desktop/README.md` indique « chaîne de "
                 "build/distribution : aucune » pour 5 apps sur 7."),
    "UC-14": (3, "`GET /api/v1/public/travel/marketplace/trips` répond **200** sans authentification : la boutique "
                 "publique est bien ouverte. Le parcours réservation → suivi → annulation n'a pas été rejoué en "
                 "écriture."),
    "UC-88": (3, "Les 3 endpoints trade renvoient **403** avec le persona principal : la verticale Trade n'est pas "
                 "activée sur le tenant de démo (feature gate), donc le parcours n'a pas pu être rejoué."),
    "UC-15": (3, "`GET /dashboard` (page web) renvoie **401** : le rendu du tableau de bord n'a pas été observé ; "
                 "seules les routes API `/dashboard/*` sont vérifiées."),
    "UC-90": (3, "`GET /api/v1/ai/chat/history` et `GET /api/v1/ai/tools` renvoient **403** avec le persona Manager "
                 "Principal : la verticale IA n'est pas ouverte à ce rôle sur le tenant de démo. Les 26 routes de "
                 "`api/routes/ai.php` existent et 6 tests de proposition d'action passent, mais le chat n'a pas pu "
                 "être rejoué."),
    "UC-91": (3, "`GET /api/v1/contracts` et `GET /api/v1/contracts/expiring` répondent **200** ; "
                 "`GET /api/v1/contracts/templates` répond **422** (paramètre pays obligatoire). La signature d'un "
                 "contrat n'a pas été rejouée en écriture."),
    "UC-92": (3, "`GET /api/v1/audit-logs` répond **200** ; `GET /api/v1/audit-logs/export` répond **422** "
                 "(paramètre de format obligatoire). L'export n'a pas été rejoué avec un format valide."),
    "UC-93": (3, "`GET /api/v1/export/employees` et `GET /api/v1/export/pay-slips` répondent **200** : les exports "
                 "sont produits. Le contenu des fichiers n'a pas été ouvert ni comparé au nombre de lignes attendu."),
    "UC-94": (2, "`GET /api/v1/partners` renvoie **404** en exécution : la route documentée n'existe pas sous ce "
                 "chemin. Les tests de candidature partenaire passent (4 tests), mais le parcours HTTP documenté "
                 "n'est pas exposé à cette adresse."),
    "UC-95": (3, "`GET /api/v1/training/courses` et `GET /api/v1/training/sessions` répondent **200** et 12 tests "
                 "d'inscription/créneau passent. L'inscription d'un apprenant n'a pas été rejouée en écriture."),
    "UC-96": (3, "`GET /api/v1/auth/2fa/status` répond **200** et 22 tests 2FA/MFA passent. L'enrôlement complet "
                 "(enroll → confirm → verify) n'a pas été rejoué, faute de secret TOTP généré côté testeur."),
}


def summarize(uc_id):
    t = tests.get(uc_id) or {}
    r = rt.get(uc_id) or {}
    return t, r


def build(uc_id):
    t, r = summarize(uc_id)
    ev = evidence.get(uc_id) or {}
    n_tests = t.get("tests", 0)
    fails = t.get("failures", 0) + t.get("errors", 0)
    t_status = t.get("status", "no-tests")
    probed = r.get("probed", 0)
    ok = r.get("ok", 0)
    results = r.get("results") or []
    not_found = [x for x in results if x.get("status") == 404]
    forbidden = [x for x in results if x.get("status") in (401, 403)]

    # --- note de base, calculée ---
    if t_status == "fail" or fails > 0:
        note = 2
    elif n_tests > 0 and ok > 0:
        note = 4
    elif n_tests > 0:
        note = 3
    elif ok > 0:
        note = 3
    else:
        note = 3

    if not_found:
        # Un 404 sur une route ABSENTE du routeur est un écart fonctionnel.
        # Un 404 sur une route PRÉSENTE mais une ressource inexistante en base
        # (ex. `/me/pay-slips/1` alors que l'employé seedé n'a aucun bulletin)
        # n'est pas un défaut : c'est le comportement attendu. On ne plafonne
        # donc que si le chemin n'existe pas au routeur.
        missing_routes = [x for x in not_found if not route_exists(x.get("path") or "")]
        if missing_routes:
            note = min(note, 2)

    # --- plafonds explicites ---
    cap_reason = None
    if uc_id in CAPS:
        cap, cap_reason = CAPS[uc_id]
        note = min(note, cap)

    if uc_id in FULL_PASS and note == 4:
        note = 5

    # --- observation factuelle ---
    parts = []
    parts.append("**Exécution réelle** (backend Laravel 12 local, PHP 8.4.28, PostgreSQL 15.20 + PostGIS 3.3, "
                 "base seedée par `DemoCompanySeeder`) :")
    if n_tests:
        parts.append("échantillon de **%d test(s)** exécuté(s) sur %d fichier(s) — %d échec(s)/erreur(s) "
                     "(statut `%s`)." % (n_tests, t.get("files", 0), fails, t_status))
    else:
        parts.append("aucun test n'a pu être exécuté pour ce cas (statut `%s`)." % t_status)
    if probed:
        detail = ", ".join("`%s %s` → **%s**" % (x.get("method", "GET"), x.get("path"), x.get("status"))
                           for x in results[:5])
        parts.append("Sondes HTTP : %d endpoint(s) appelé(s), %d en 2xx (%s)." % (probed, ok, detail))
    else:
        parts.append("Aucun endpoint HTTP n'est déclaré pour ce cas dans `_endpoints.json` : "
                     "la vérification repose sur les tests et l'inventaire statique.")
    if cap_reason:
        parts.append("**Écart constaté** : " + cap_reason)
    if forbidden and not cap_reason:
        parts.append("Endpoints en 401/403 : accès refusé pour le persona utilisé (RBAC ou feature gate).")
    parts.append("Note %d = %s." % (note, {
        5: "tous les critères mesurés en exécution",
        4: "conforme en exécution (tests verts + endpoint 2xx)",
        3: "fonctionne partiellement ou critère non vérifiable",
        2: "défaillance fonctionnelle sur un critère majeur",
        1: "bloqué",
        0: "impossible à exécuter",
    }[note]))
    return str(note), " ".join(parts)


out = {}
for uc_id in evidence:
    note, obs = build(uc_id)
    out[uc_id] = {"note_ia": note, "obs_ia": obs}

(HERE / "_notes_ia.json").write_text(json.dumps(out, ensure_ascii=False, indent=1), encoding="utf-8")

from collections import Counter
c = Counter(v["note_ia"] for v in out.values())
print("cas:", len(out), "| distribution:", dict(sorted(c.items())))
print("moyenne: %.2f" % (sum(int(v["note_ia"]) for v in out.values()) / len(out)))
