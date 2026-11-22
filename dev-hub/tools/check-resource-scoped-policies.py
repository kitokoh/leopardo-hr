#!/usr/bin/env python3
"""Issue #9812 — garde CI : une policy d'un module « ressource-scope » doit
utiliser le concern d'acces ressource.

## Pourquoi cette garde existe

`docs/security/RBAC_SYSTEM.md` (section « Couche acces aux ressources », epic
#7597) documente deja le modele : un role ouvre la porte, une assignation borne
le perimetre (ABAC sur attributs sujet / ressource / action). C'est de l'ABAC
au sens strict, mais le depot ne l'appelle jamais ainsi.

Ce qui manquait n'est donc pas la documentation : c'est l'**application**. Une
policy qui decide d'un acces a une ressource nommee en comparant `company_id`
a la main, au lieu d'appeler `hasResourceAccess(type, id, niveau)`, a l'air
correcte, passe les tests de non-regression, et pourtant **ignore le niveau**
(`view` / `operate` / `manage`). Le collaborateur obtient alors `manage` alors
qu'il n'a que `view`. C'est le type de faille qu'aucune garde existante ne voit :
`check-vertical-controller-policies.sh` verifie qu'un controleur appelle UNE
policy, pas que la policy applique le bon modele.

## Ce que la garde refuse

Une policy situee dans un module qui declare au moins un type de ressource dans
`api/config/resource_types.php`, dont le code ne contient pas l'un des signaux
d'usage du concern :

    ChecksResourceScopedAccess
    ChecksRestaurantBranchAccess
    hasResourceAccess
    accessibleResourceIds
    isResourceTypeScoped

## Ce qu'elle fait pour les cas legitimes

Certaines policies d'un module scope ne concernent PAS une ressource nommee
(facturation, consentements, campagnes) : elles n'ont aucune raison d'appeler
le concern. Elles sont listees dans `EXEMPTIONS` avec leur motif — une
exemption ecrite est relue en revue ; un `skip` silencieux ne l'est jamais.

## Usage

    python3 dev-hub/tools/check-resource-scoped-policies.py
    python3 dev-hub/tools/check-resource-scoped-policies.py --self-test
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
API_DIR = REPO_ROOT / "api"
CONFIG = API_DIR / "config" / "resource_types.php"

# Signaux d'usage du modele ressource-scope.
#
# `EmployeeResourceAssignment` est inclus : une policy peut lire l'assignation
# directement (c'est le cas de `CameraPolicy::assignedLevelSatisfies`, qui
# combine l'assignation avec son systeme `CameraPermission` existant, de facon
# additive). Exiger le nom du trait produirait un faux positif sur ce cas
# legitime.
SIGNALS = (
    "ChecksResourceScopedAccess",
    "ChecksRestaurantBranchAccess",
    "hasResourceAccess",
    "accessibleResourceIds",
    "isResourceTypeScoped",
    "EmployeeResourceAssignment",
)

# Policies d'un module scope qui ne portent deliberement PAS sur une ressource
# nommee. Le motif est obligatoire et doit rester vrai.
EXEMPTIONS: dict[str, str] = {
    # La boite mail est PERSONNELLE (R1 #7686) : la policy borne deja l'acces au
    # proprietaire (`employee_id`) ou a principal/rh. Le partage fin par
    # assignation est explicitement reporte aux R2-R4 de l'epique #7597 — le
    # type `communication_mailbox` est declare des R1 mais aucun acces n'est
    # ouvert par defaut (fail-closed). Exiger le concern ici forcerait un
    # partage qui n'est pas encore concu.
    "api/app/Modules/Communication/Domain/Policies/CommunicationIntegrationPolicy.php":
        "boite mail personnelle (R1 #7686) — partage fin reporte aux R2-R4 de #7597",
}

# `'camera' => [ 'model' => App\Modules\Cameras\...` -> prefixe de namespace
ENTRY = re.compile(r"^\s{4}'([a-z_]+)'\s*=>\s*\[", re.MULTILINE)
MODEL = re.compile(r"'model'\s*=>\s*([A-Za-z0-9_\\]+)::class")


def module_namespaces() -> dict[str, set[str]]:
    """Retourne {namespace du module: {types de ressource}}."""
    if not CONFIG.is_file():
        return {}

    text = CONFIG.read_text(encoding="utf-8", errors="ignore")
    result: dict[str, set[str]] = {}

    entries = list(ENTRY.finditer(text))
    for index, match in enumerate(entries):
        kind = match.group(1)
        end = entries[index + 1].start() if index + 1 < len(entries) else len(text)
        block = text[match.end() : end]
        model = MODEL.search(block)
        if not model:
            continue
        class_name = model.group(1)
        module_ns = class_name.rsplit("\\Domain\\", 1)[0] if "\\Domain\\" in class_name else class_name.rsplit("\\", 2)[0]
        result.setdefault(module_ns, set()).add(kind)

    return result


def resource_models() -> dict[str, str]:
    """Retourne {type de ressource: nom court du modele}.

    Ex. `travel_office` -> `TravelOffice`. C'est le nom qui determine le nom de
    la policy censee garder cette ressource (`TravelOfficePolicy`).
    """
    if not CONFIG.is_file():
        return {}

    text = CONFIG.read_text(encoding="utf-8", errors="ignore")
    result: dict[str, str] = {}

    entries = list(ENTRY.finditer(text))
    for index, match in enumerate(entries):
        kind = match.group(1)
        end = entries[index + 1].start() if index + 1 < len(entries) else len(text)
        block = text[match.end() : end]
        model = MODEL.search(block)
        if not model:
            continue
        result[kind] = model.group(1).rsplit("\\", 1)[-1]

    return result


def policies_under(namespace: str) -> list[Path]:
    """Fichiers *Policy.php sous le dossier du module."""
    relative = namespace.replace("App\\", "")
    base = API_DIR / "app" / relative.replace("\\", "/")
    if not base.is_dir():
        return []
    return sorted(base.rglob("*Policy.php"))


def scan(policies: list[Path]) -> list[Path]:
    bad: list[Path] = []
    for path in policies:
        try:
            rel = str(path.relative_to(REPO_ROOT))
        except ValueError:
            # Autotest : fichier temporaire hors du depot.
            rel = str(path)
        if rel in EXEMPTIONS:
            continue
        text = path.read_text(encoding="utf-8", errors="ignore")
        if not any(signal in text for signal in SIGNALS):
            bad.append(path)
    return bad


def run(verbose: bool = False) -> int:
    models = resource_models()
    if not models:
        print(f"ERREUR : aucun type de ressource lu dans {CONFIG}.", file=sys.stderr)
        return 2

    # Politique de perimetre VOLONTAIREMENT etroite.
    #
    # Mesure sur `main` : appliquer la regle « toute policy d'un module qui
    # declare un type de ressource » produit 40+ faux positifs (TravelArticle,
    # TravelQuiz, EduStudent, HospitalityLease...). Aucune de ces policies ne
    # porte sur la ressource nommee : elles seraient toutes exemptees, ce qui
    # viderait la garde de son sens.
    #
    # On ne controle donc que la policy qui garde LE MODELE de la ressource :
    # `travel_office` -> `TravelOffice` -> `TravelOfficePolicy`. C'est la seule
    # qui, par construction, doit appliquer le niveau view/operate/manage.
    all_policies = sorted((API_DIR / "app").rglob("*Policy.php"))

    checked = 0
    missing: list[str] = []
    failures: list[Path] = []

    for kind, model in sorted(models.items()):
        expected = f"{model}Policy.php"
        matches = [p for p in all_policies if p.name == expected]

        if not matches:
            # Aucune policy dediee : la ressource est gardee par un trait de
            # module ou directement dans le controleur. On le signale sans
            # echouer (ce n'est pas une policy qui ignore le niveau).
            missing.append(f"{kind} ({model})")
            continue

        bad = scan(matches)
        checked += len(matches)
        if verbose:
            state = "OK" if not bad else "SANS CONCERN"
            print(f"  {kind:24} -> {expected:34} {state}")
        failures.extend(bad)

    if verbose and missing:
        print(f"  (types sans policy dediee : {', '.join(missing)})")

    if failures:
        print(f"ECHEC : {len(failures)} policy/policies de ressource n'appliquent pas le modele :", file=sys.stderr)
        for path in failures:
            print(f"  {path.relative_to(REPO_ROOT)}", file=sys.stderr)
        print(
            "\nAppeler App\\Core\\Auth\\Domain\\Policies\\Concerns\\ChecksResourceScopedAccess "
            "(ou hasResourceAccess) pour que le niveau view/operate/manage soit respecte, "
            "ou declarer une exemption motivee dans EXEMPTIONS (#9812).",
            file=sys.stderr,
        )
        return 1

    print(f"OK : {checked} policy/policies de ressource appliquent le niveau view/operate/manage.")
    return 0


def self_test() -> int:
    import tempfile

    cases = [
        # (contenu, doit_echouer, description)
        ("<?php class XPolicy { public function view($u, $r) { return $u->company_id === $r->company_id; } }", True, "comparaison company_id a la main -> doit echouer"),
        ("<?php use App\\Core\\Auth\\Domain\\Policies\\Concerns\\ChecksResourceScopedAccess; class XPolicy { use ChecksResourceScopedAccess; }", False, "concern importe -> doit passer"),
        ("<?php class XPolicy { return $actor->hasResourceAccess('vehicle', 1, 'view'); }", False, "hasResourceAccess -> doit passer"),
        ("<?php class XPolicy { public function view($u) { return true; } }", True, "aucun signal -> doit echouer"),
        ("<?php class XPolicy { return $actor->isResourceTypeScoped('site'); }", False, "isResourceTypeScoped -> doit passer"),
    ]

    failed = 0
    with tempfile.TemporaryDirectory() as tmp:
        for index, (content, should_fail, description) in enumerate(cases):
            path = Path(tmp) / f"Case{index}Policy.php"
            path.write_text(content, encoding="utf-8")
            bad = scan([path])
            ok = bool(bad) == should_fail
            status = "OK  " if ok else "ECHEC"
            print(f"  [{status}] {description}")
            if not ok:
                failed += 1

    if failed:
        print(f"\nAUTOTEST ECHOUE : {failed} cas.", file=sys.stderr)
        return 1

    print(f"\nAUTOTEST OK : {len(cases)} cas.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--verbose", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    return run(verbose=args.verbose)


if __name__ == "__main__":
    sys.exit(main())
