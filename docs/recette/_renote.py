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

# Cas dont TOUS les critères sont mesurés en exécution -> 5
FULL_PASS = {
    "UC-13": "health/live/ready mesurés en HTTP (200/200/200) et suite de tests plateforme verte",
}

# Écarts constatés en exécution, qui plafonnent la note
CAPS = {
    "UC-17": (2, "`GET /api/v1/role-assignments` renvoie **404** en exécution : la route documentée n'existe pas. "
                 "L'attribution de rôle passe par `POST /employees/{employee}/assign-role` et `GET /company/team-roles` "
                 "(vérifiés 200)."),
    "UC-29": (2, "`GET /api/v1/expenses` renvoie **404** en exécution : la ressource réelle est `/expense-claims`."),
    "UC-40": (2, "`GET /api/v1/search` renvoie **404** en exécution : la recherche est exposée sous `/crm/search` et "
                 "`/trade/search`, pas à la racine."),
    "UC-18": (3, "`GET /api/v1/overtime` renvoie **404** en exécution ; les heures supplémentaires sont sous "
                 "`/hr/overtime`. `GET /api/v1/planning` répond 200."),
    "UC-70": (2, "Aucun test desktop exécutable (0 fichier) et `docs/desktop/README.md` indique « chaîne de "
                 "build/distribution : aucune » pour 5 apps sur 7."),
    "UC-14": (3, "`GET /api/v1/public/travel/shop/trips` renvoie **401** en exécution : la boutique publique exige "
                 "une authentification, contrairement à ce que le cas suppose."),
    "UC-88": (3, "Les 3 endpoints trade renvoient **403** avec le persona principal : la verticale Trade n'est pas "
                 "activée sur le tenant de démo (feature gate), donc le parcours n'a pas pu être rejoué."),
    "UC-15": (3, "`GET /dashboard` (page web) renvoie **401** : le rendu du tableau de bord n'a pas été observé ; "
                 "seules les routes API `/dashboard/*` sont vérifiées."),
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
