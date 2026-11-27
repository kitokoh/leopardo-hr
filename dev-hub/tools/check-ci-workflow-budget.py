#!/usr/bin/env python3
"""Inventaire CI + garde anti-saturation (#9979).

## Le défaut que cette garde ferme

Le dépôt a **89 workflows**. Le regroupement en `guards-fast.yml` a déjà réduit
41 runs → 12 pour dix gardes (-71 %), mais 62 workflows restent mono-job et 35
d'entre eux se déclenchent sur `pull_request`. Chaque PR crée donc des dizaines
de runs, et le dépôt documente l'incident réel : la file `queued` est passée de
99 à 140 en 30 min, et **les 3 checks requis portés par `architecture-check.yml`
sont sortis `ABSENT`** (pas `queued`, pas rouge — absents) parce que leur job
amont `detect-changes` n'obtenait pas de runner. Avec `enforce_admins: true`,
**aucune PR n'était mergeable**.

Un check requis ABSENT est indétectable par la garde de protection de branche :
elle ne voit que la liste des contexts attendus, pas leur existence. C'est
exactement le trou que cette garde comble.

## Ce qu'elle vérifie

  1. **Budget de workflows** : le nombre de fichiers ne peut pas croître sans
     justification (gelé sur la mesure du 2026-11-27).
  2. **Checks requis jamais orphelins** : pour chaque context requis du
     canonique, le job qui le porte ne doit pas dépendre d'un `needs:` dont le
     job amont peut être SKIPPÉ par un filtre `paths:`/`if:` — sinon le check
     n'existe pas comme check-run.
  3. **Inventaire** : déclencheurs, nombre de jobs, mono-job, redondance.

## Usage

    python3 dev-hub/tools/check-ci-workflow-budget.py
    python3 dev-hub/tools/check-ci-workflow-budget.py --inventory
    python3 dev-hub/tools/check-ci-workflow-budget.py --self-test
"""
from __future__ import annotations

import argparse
import json
import pathlib
import sys

try:
    import yaml
except ImportError:  # pragma: no cover
    print("::error::PyYAML requis (pip install pyyaml)", file=sys.stderr)
    sys.exit(2)

ROOT = pathlib.Path(__file__).resolve().parents[2]
WORKFLOWS = ROOT / ".github/workflows"
CANONICAL = ROOT / "dev-hub/tools/branch-protection-canonical.json"
BUDGET = ROOT / "dev-hub/governance/ci-workflow-budget.json"


def load_workflows() -> dict[str, dict]:
    out: dict[str, dict] = {}
    for p in sorted(WORKFLOWS.glob("*.y*ml")):
        try:
            doc = yaml.safe_load(p.read_text(encoding="utf-8"))
        except yaml.YAMLError as exc:
            out[p.name] = {"_error": str(exc)}
            continue
        if isinstance(doc, dict):
            out[p.name] = doc
    return out


def triggers(doc: dict) -> set[str]:
    # YAML 1.1 parse `on:` comme le booléen True — piège classique.
    on = doc.get(True, doc.get("on"))
    if isinstance(on, str):
        return {on}
    if isinstance(on, list):
        return {str(x) for x in on}
    if isinstance(on, dict):
        return {str(k) for k in on}
    return set()


def jobs_of(doc: dict) -> dict:
    jobs = doc.get("jobs")
    return jobs if isinstance(jobs, dict) else {}


def job_can_be_skipped(job: dict) -> bool:
    """Un job peut-il ne jamais produire de check-run ?

    Deux causes mesurées dans ce dépôt :
      - un `if:` au niveau du job (ex. `if: needs.detect-changes.outputs.x`);
      - un `needs:` vers un job lui-même conditionnel.

    Un job `if:` qui évalue faux produit un check-run `skipped`, ce qui est un
    SUCCÈS pour un check requis — donc acceptable. Le vrai danger est le job
    AMONT qui ne démarre pas du tout (pas de runner) : le check-run n'existe
    alors pas (`ABSENT`). On ne peut pas le détecter statiquement avec
    certitude ; on détecte la CONDITION qui le rend possible : un `needs:` vers
    un job dont le workflow a un filtre `paths:` au niveau du trigger.
    """
    return bool(job.get("if"))


def workflow_has_path_filter(doc: dict) -> bool:
    on = doc.get(True, doc.get("on"))
    if not isinstance(on, dict):
        return False
    for cfg in on.values():
        if isinstance(cfg, dict) and ("paths" in cfg or "paths-ignore" in cfg):
            return True
    return False


def find_required_context_owners(workflows: dict[str, dict], contexts: list[str]) -> dict[str, list[tuple[str, str, dict]]]:
    """context → [(workflow, job_id, job)] pour chaque job qui porte ce nom."""
    found: dict[str, list[tuple[str, str, dict]]] = {c: [] for c in contexts}
    for wf, doc in workflows.items():
        if "_error" in doc:
            continue
        for job_id, job in jobs_of(doc).items():
            if not isinstance(job, dict):
                continue
            name = job.get("name")
            if isinstance(name, str) and name in found:
                found[name].append((wf, job_id, job))
    return found


def check() -> int:
    if not BUDGET.is_file():
        print(f"::error::Budget CI introuvable : {BUDGET}", file=sys.stderr)
        return 2

    budget = json.loads(BUDGET.read_text(encoding="utf-8"))
    workflows = load_workflows()
    problems: list[str] = []

    # 1) Budget de fichiers
    n = len(workflows)
    if n > int(budget["max_workflows"]):
        problems.append(
            f"{n} workflows > budget {budget['max_workflows']}. "
            "Regrouper par surface (modèle guards-fast.yml : 41 runs → 12 pour "
            "dix gardes), ou justifier et relever le budget dans la PR."
        )

    # 2) Checks requis jamais orphelins
    canonical = json.loads(CANONICAL.read_text(encoding="utf-8"))
    contexts = (canonical.get("required_status_checks") or {}).get("contexts") or []
    owners = find_required_context_owners(workflows, contexts)

    for ctx in contexts:
        if not owners[ctx]:
            problems.append(
                f"check requis « {ctx} » : AUCUN job ne porte ce nom. "
                "Le check sortira ABSENT et, avec enforce_admins, aucune PR ne "
                "sera mergeable (incident #9979)."
            )
            continue

        for wf, job_id, job in owners[ctx]:
            doc = workflows[wf]
            needs = job.get("needs")
            needs_list = [needs] if isinstance(needs, str) else list(needs or [])

            if needs_list and workflow_has_path_filter(doc):
                problems.append(
                    f"check requis « {ctx} » ({wf}::{job_id}) dépend de "
                    f"needs={needs_list} dans un workflow à filtre `paths:`. "
                    "Si le job amont ne démarre pas, le check n'existe pas "
                    "comme check-run (ABSENT, pas skipped) — incident #9979."
                )

            # Un job requis qui porte lui-même un `if:` peut sortir `skipped`
            # (acceptable) mais ne doit pas dépendre d'un amont conditionnel
            # non plus.
            for dep in needs_list:
                dep_job = jobs_of(doc).get(dep)
                if isinstance(dep_job, dict) and job_can_be_skipped(dep_job):
                    # Acceptable SI le job requis a lui aussi un `if:` qui
                    # reproduit la condition (il sortira skipped, pas absent).
                    if not job_can_be_skipped(job):
                        problems.append(
                            f"check requis « {ctx} » ({wf}::{job_id}) dépend de "
                            f"« {dep} » qui porte un `if:`, sans `if:` propre : "
                            "si l'amont est skipped, le check peut ne jamais "
                            "être créé."
                        )

    for p in problems:
        print(f"::error::{p}")

    if problems:
        print(f"\n❌ CI : {len(problems)} problème(s).")
        return 1

    print(
        f"✅ CI dans le budget : {n} workflows (max {budget['max_workflows']}), "
        f"{len(contexts)} checks requis tous portés par un job atteignable."
    )
    return 0


def inventory() -> int:
    workflows = load_workflows()
    rows = []
    for wf, doc in workflows.items():
        if "_error" in doc:
            rows.append({"file": wf, "error": doc["_error"]})
            continue
        trig = triggers(doc)
        jobs = jobs_of(doc)
        rows.append({
            "file": wf,
            "jobs": len(jobs),
            "triggers": sorted(trig),
            "mono_job": len(jobs) <= 1,
            "on_pull_request": "pull_request" in trig,
            "path_filtered": workflow_has_path_filter(doc),
        })

    mono_pr = [r for r in rows if r.get("mono_job") and r.get("on_pull_request")]
    print("# Inventaire CI")
    print()
    print(f"- Workflows : **{len(rows)}**")
    print(f"- Mono-job : **{sum(1 for r in rows if r.get('mono_job'))}**")
    print(f"- Déclenchés sur `pull_request` : **{sum(1 for r in rows if r.get('on_pull_request'))}**")
    print(f"- Mono-job ET sur `pull_request` (candidats au regroupement) : **{len(mono_pr)}**")
    print()
    print("## Mono-job sur pull_request")
    print()
    print("| Workflow | Déclencheurs |")
    print("|---|---|")
    for r in mono_pr:
        print(f"| `{r['file']}` | {', '.join(r['triggers'])} |")
    print()
    print("## Tous les workflows")
    print()
    print("| Workflow | Jobs | Déclencheurs | Filtre paths |")
    print("|---|---:|---|---|")
    for r in rows:
        if "error" in r:
            print(f"| `{r['file']}` | — | **ERREUR YAML** | — |")
            continue
        print(f"| `{r['file']}` | {r['jobs']} | {', '.join(r['triggers'])} | {'oui' if r['path_filtered'] else 'non'} |")
    return 0


def self_test() -> int:
    failures = 0

    def expect(name: str, cond: bool) -> None:
        nonlocal failures
        if not cond:
            failures += 1
            print(f"::error::self-test '{name}'")

    # `on:` parsé comme booléen True par YAML 1.1.
    doc = yaml.safe_load("on:\n  pull_request:\n    paths: ['api/**']\njobs:\n  a:\n    name: X\n")
    expect("trigger pull_request", "pull_request" in triggers(doc))
    expect("filtre paths détecté", workflow_has_path_filter(doc))

    doc2 = yaml.safe_load("on: [push, workflow_dispatch]\njobs:\n  a:\n    name: Y\n")
    expect("trigger liste", triggers(doc2) == {"push", "workflow_dispatch"})
    expect("pas de filtre paths", not workflow_has_path_filter(doc2))

    # Un job requis qui dépend d'un amont conditionnel.
    doc3 = yaml.safe_load(
        "on:\n  pull_request:\njobs:\n"
        "  detect:\n    name: D\n    if: github.event_name == 'push'\n"
        "  req:\n    name: R\n    needs: detect\n"
    )
    jobs = jobs_of(doc3)
    expect("amont conditionnel détecté", job_can_be_skipped(jobs["detect"]))
    expect("job requis sans if", not job_can_be_skipped(jobs["req"]))

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 6/6.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--inventory", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if args.self_test:
        return self_test()
    if args.inventory:
        return inventory()
    return check()


if __name__ == "__main__":
    sys.exit(main())
