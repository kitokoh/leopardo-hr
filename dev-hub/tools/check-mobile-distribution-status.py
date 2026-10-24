#!/usr/bin/env python3
"""BOS-A-05 / #8628 — garde de cohérence du statut de distribution mobile.

Le catalogue « Mon Logiciel » ne peut pas annoncer une application comme
publiée/pilote si son canal de distribution est en échec. Cette garde compare
la table versionnée `front/web/src/lib/mobile-distribution-status.ts` au
dernier run de `mobile-distribute-main.yml` sur `main` et échoue si :

  - un canal déclaré `ok` (ou absent) est en réalité en échec ;
  - un canal déclaré `failed` est en réalité vert (déclaration périmée).

Sans cette garde, la table pourrait pourrir en silence et redevenir un
mensonge — exactement le défaut que #8628 corrige.

Usage : python3 dev-hub/tools/check-mobile-distribution-status.py
Variables : GITHUB_TOKEN (ou GH_TOKEN), GITHUB_REPOSITORY (owner/repo).
"""

from __future__ import annotations

import json
import os
import re
import sys
import urllib.error
import urllib.request

REPO = os.environ.get("GITHUB_REPOSITORY", "kitokoh/leopardo-hr")
TOKEN = os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN") or ""
STATUS_FILE = "front/web/src/lib/mobile-distribution-status.ts"
WORKFLOW = "mobile-distribute-main.yml"

# Nom du job CI -> slug du catalogue.
JOB_TO_SLUG = {
    "employee": "employee",
    "manager": "manager",
    "platform_admin": "platform-admin",
    "hr": "hr-mobile",
}


def api(path: str):
    req = urllib.request.Request("https://api.github.com" + path)
    req.add_header("Accept", "application/vnd.github+json")
    req.add_header("User-Agent", "mobile-distribution-status-guard")
    if TOKEN:
        req.add_header("Authorization", "Bearer " + TOKEN)
    with urllib.request.urlopen(req) as response:
        return json.loads(response.read().decode())


def declared_states() -> dict[str, str]:
    with open(STATUS_FILE, encoding="utf-8") as handle:
        source = handle.read()

    block = re.search(
        r"MOBILE_DISTRIBUTION_STATUS[^=]*=\s*\{(.*?)\}", source, re.DOTALL
    )
    if block is None:
        print(f"::error::{STATUS_FILE} : table MOBILE_DISTRIBUTION_STATUS introuvable.")
        sys.exit(1)

    states: dict[str, str] = {}
    for key, value in re.findall(r"['\"]?([\w-]+)['\"]?\s*:\s*['\"](\w+)['\"]", block.group(1)):
        states[key] = value
    return states


def observed_states() -> dict[str, str]:
    runs = api(f"/repos/{REPO}/actions/workflows/{WORKFLOW}/runs?branch=main&per_page=1")
    workflow_runs = runs.get("workflow_runs", [])
    if not workflow_runs:
        print(f"::warning::Aucun run de {WORKFLOW} sur main — garde ignorée.")
        return {}

    run = workflow_runs[0]
    jobs = api(f"/repos/{REPO}/actions/runs/{run['id']}/jobs?per_page=50")

    observed: dict[str, str] = {}
    for job in jobs.get("jobs", []):
        match = re.match(r"Distribute (\w+) to Firebase", job.get("name", ""))
        if match is None:
            continue
        slug = JOB_TO_SLUG.get(match.group(1))
        if slug is None:
            continue
        observed[slug] = "ok" if job.get("conclusion") == "success" else "failed"

    print(f"Run de référence : {run['id']} ({run['created_at'][:16]})")
    return observed


def main() -> int:
    declared = declared_states()
    observed = observed_states()

    if not observed:
        return 0

    errors: list[str] = []

    for slug, state in observed.items():
        declared_state = declared.get(slug, "ok")
        if state == "failed" and declared_state != "failed":
            errors.append(
                f"{slug} : canal EN ÉCHEC en CI mais déclaré « {declared_state} » "
                f"dans {STATUS_FILE} — la fiche annoncerait une app non distribuable."
            )
        if state == "ok" and declared_state == "failed":
            errors.append(
                f"{slug} : canal VERT en CI mais déclaré « failed » dans {STATUS_FILE} "
                f"— déclaration périmée, à rafraîchir."
            )

    if errors:
        for error in errors:
            print(f"::error::{error}")
        return 1

    print(f"Statut de distribution cohérent ({len(observed)} canaux vérifiés).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
