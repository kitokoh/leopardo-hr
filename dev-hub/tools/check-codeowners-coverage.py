#!/usr/bin/env python3
"""Garde CODEOWNERS — les surfaces sensibles sont-elles réellement couvertes ? (#9980)

## Le défaut que cette garde ferme

Le ruleset exige `require_code_owner_review: true`. Une entrée CODEOWNERS
n'est donc plus documentaire : elle est **opposable**. Mais GitHub applique la
règle par **absence** : un chemin qui n'est listé nulle part retombe sur le
`*` global, et le `*` global est satisfait par n'importe quel propriétaire —
donc par l'auteur lui-même quand il est le seul mainteneur.

Conséquence concrète : ajouter un fichier dans un dossier non listé le rend
éditable **sans revue**, alors que le ruleset affirme le contraire. C'est le
même mode de défaillance que l'audit reproche partout : une garantie annoncée,
non mesurée.

## Ce que la garde vérifie

  1. **Couverture** : chaque surface sensible déclarée ci-dessous a une entrée
     CODEOWNERS qui la couvre (préfixe le plus long, sémantique GitHub).
  2. **Syntaxe** : pas de ligne malformée (chemin sans propriétaire, propriétaire
     sans `@`, chemin relatif sans `/` initial là où GitHub l'exige).
  3. **Pas de propriétaire fantôme** : chaque `@handle` doit exister dans la
     liste des collaborateurs connus (fichier de référence), sinon la revue est
     impossible et la PR reste bloquée indéfiniment.

## Usage

    python3 dev-hub/tools/check-codeowners-coverage.py
    python3 dev-hub/tools/check-codeowners-coverage.py --self-test
"""
from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
CODEOWNERS = ROOT / "CODEOWNERS"

# Surfaces qui DOIVENT être couvertes par une entrée explicite (pas par `*`).
# Chaque entrée est une décision : « modifier ceci sans revue est inacceptable ».
REQUIRED_SURFACES = {
    "/api/database/migrations/": "le schéma EST l'isolation (RLS, index inter-tenant)",
    "/api/database/scripts/": "scripts de bascule RLS (#9974)",
    "/api/app/Core/Tenant/": "global scope tenant — premier filet d'isolation",
    "/api/app/Core/Auth/": "authentification",
    "/api/app/Shared/Traits/BelongsToCompany.php": "trait d'isolation tenant",
    "/api/app/Console/Commands/TenantRlsStatusCommand.php": "sonde RLS (gate de déploiement)",
    "/api/app/Modules/Payroll/": "paie — incident RGPD/financier",
    "/api/app/Modules/Billing/": "facturation",
    "/api/app/Modules/Paiements/": "socle de paiement",
    "/api/app/Modules/HealthManager/": "données de santé",
    "/api/app/Modules/Platform/Infrastructure/Services/HealthProbeService.php": "sondes — une sonde modifiée masque une panne",
    "/api/app/Modules/Platform/Interfaces/Api/V1/Controllers/HealthController.php": "exposition des sondes",
    "/api/config/": "configuration et secrets",
    "/api/app/Http/Middleware/": "middleware (tenant, auth, rate limit)",
    "/api/app/Policies/": "autorisation",
    "/api/docker-entrypoint.sh": "gates de démarrage (RLS, queue, scheduler)",
    "/render.yaml": "topologie de déploiement",
    "/render.prod.yaml": "topologie de production",
    "/.github/workflows/": "CI/CD — modifier un workflow, c'est modifier un contrôle",
    "/.github/BRANCH_PROTECTION_REQUIRED.md": "doc opposable de protection de branche",
    "/.github/CODEOWNERS": "cette liste elle-même",
    "/dev-hub/tools/": "gardes de gouvernance",
    "/dev-hub/governance/": "seuils et budgets des gardes",
    "/dev-hub/tools/branch-protection-canonical.json": "canonique machine de protection",
}

# Propriétaires connus. Un handle absent d'ici rend la revue impossible.
KNOWN_OWNERS = {"@kitokoh"}

LINE_RE = re.compile(r"^(?P<pattern>\S+)\s+(?P<owners>(?:@\S+\s*)+)$")


def parse(text: str) -> tuple[list[tuple[str, list[str]]], list[str]]:
    """Retourne (entrées, erreurs de syntaxe)."""
    entries: list[tuple[str, list[str]]] = []
    errors: list[str] = []

    for lineno, raw in enumerate(text.splitlines(), start=1):
        line = raw.split("#", 1)[0].strip()
        if not line:
            continue

        m = LINE_RE.match(line)
        if not m:
            errors.append(f"ligne {lineno} malformée : {raw.strip()!r}")
            continue

        pattern = m.group("pattern")
        owners = m.group("owners").split()

        # GitHub exige un `/` initial pour un motif ancré à la racine ; sans lui
        # le motif matche à n'importe quel niveau, ce qui est presque toujours
        # une erreur d'intention (ex. `api/config/` ne couvre pas `/api/config/`
        # de façon prévisible).
        if "/" in pattern and not pattern.startswith("/") and not pattern.startswith("*"):
            errors.append(
                f"ligne {lineno} : motif {pattern!r} sans `/` initial — "
                "GitHub le fait matcher à n'importe quel niveau."
            )

        entries.append((pattern, owners))

    return entries, errors


def covers(pattern: str, path: str) -> bool:
    """Sémantique GitHub simplifiée : préfixe de dossier ou fichier exact."""
    if pattern == "*":
        return True
    if pattern.endswith("/"):
        return path.startswith(pattern)
    return path == pattern or path.startswith(pattern.rstrip("/") + "/")


def segments(pattern: str) -> int:
    return len([s for s in pattern.strip("/").split("/") if s])


def effective_owner(entries: list[tuple[str, list[str]]], path: str) -> tuple[str, list[str]] | None:
    """GitHub : le DERNIER motif qui matche gagne.

    C'est la sémantique réelle, et elle change tout : une entrée précise placée
    AVANT un `*` global est écrasée par lui. La première version de cette garde
    testait « existe-t-il une entrée qui couvre ? » — donc `/api/` suffisait à
    « couvrir » `/api/database/migrations/`, et supprimer l'entrée migrations
    laissait la garde VERTE (vérifié par injection).
    """
    winner = None
    for pattern, owners in entries:
        if covers(pattern, path):
            winner = (pattern, owners)
    return winner


def is_specific_enough(pattern: str, surface: str) -> bool:
    """Le motif gagnant est-il aussi précis que la surface ?

    Sans ce contrôle, `/api/` « couvre » `/api/database/migrations/` : la
    surface serait protégée en apparence, éditable en pratique par quiconque
    peut toucher `/api/`.

    Règle : un dossier exige un motif d'au moins autant de segments ; un
    fichier exige une correspondance exacte (ou son dossier parent, qui a un
    segment de moins).
    """
    if pattern == "*":
        return False
    if surface.endswith("/"):
        return segments(pattern) >= segments(surface)
    return pattern == surface or segments(pattern) >= segments(surface) - 1


def check() -> int:
    if not CODEOWNERS.is_file():
        print("::error::CODEOWNERS introuvable à la racine.", file=sys.stderr)
        return 2

    entries, errors = parse(CODEOWNERS.read_text(encoding="utf-8"))
    problems = list(errors)

    # 1) Couverture des surfaces sensibles — par le motif GAGNANT, et assez précis.
    for surface, why in REQUIRED_SURFACES.items():
        winner = effective_owner(entries, surface)

        if winner is None:
            problems.append(
                f"surface sensible NON couverte : {surface} ({why}). "
                "Aucun motif ne la matche."
            )
            continue

        pattern, _owners = winner

        if pattern == "*":
            problems.append(
                f"surface sensible couverte seulement par `*` : {surface} ({why}). "
                "Le `*` global est satisfait par n'importe quel propriétaire — "
                "donc par l'auteur lui-même. Éditable sans revue malgré "
                "require_code_owner_review."
            )
            continue

        if not is_specific_enough(pattern, surface):
            problems.append(
                f"surface sensible couverte par un motif trop large : {surface} "
                f"({why}) → motif gagnant {pattern!r}. Un motif parent laisse "
                "éditer la surface sans revue dédiée ; ajouter une entrée "
                "explicite APRÈS ce motif (GitHub : le dernier match gagne)."
            )

    # 2) Propriétaires fantômes.
    for pattern, owners in entries:
        for owner in owners:
            if owner not in KNOWN_OWNERS:
                problems.append(
                    f"propriétaire inconnu {owner} sur {pattern!r} : la revue "
                    "sera impossible et la PR restera bloquée."
                )

    for p in problems:
        print(f"::error::{p}")

    if problems:
        print(f"\n❌ CODEOWNERS : {len(problems)} problème(s).")
        return 1

    print(
        f"✅ CODEOWNERS : {len(entries)} entrées, "
        f"{len(REQUIRED_SURFACES)} surfaces sensibles couvertes explicitement, "
        f"{len(KNOWN_OWNERS)} propriétaire(s) valide(s)."
    )
    return 0


def self_test() -> int:
    failures = 0

    def expect(name: str, cond: bool) -> None:
        nonlocal failures
        if not cond:
            failures += 1
            print(f"::error::self-test '{name}'")

    entries, errors = parse(
        "* @kitokoh\n"
        "/api/config/ @kitokoh\n"
        "/api/database/migrations/ @kitokoh @second\n"
        "# commentaire ignoré\n"
    )
    expect("3 entrées", len(entries) == 3)
    expect("aucune erreur de syntaxe", errors == [])
    expect("dossier couvre fichier", covers("/api/config/", "/api/config/app.php"))
    expect("fichier exact", covers("/render.yaml", "/render.yaml"))
    expect("fichier ne couvre pas voisin", not covers("/render.yaml", "/render.prod.yaml"))
    expect("glob couvre tout", covers("*", "/n-importe/quoi"))

    # Sémantique « dernier match gagne » + spécificité.
    ents = [("/api/", ["@kitokoh"]), ("*", ["@kitokoh"])]
    expect("dernier match gagne", effective_owner(ents, "/api/config/app.php")[0] == "*")
    expect("motif large insuffisant", not is_specific_enough("/api/", "/api/database/migrations/"))
    expect("motif exact suffisant", is_specific_enough("/api/database/migrations/", "/api/database/migrations/"))
    expect("fichier exact suffisant", is_specific_enough("/render.yaml", "/render.yaml"))
    expect("glob jamais suffisant", not is_specific_enough("*", "/render.yaml"))

    _, bad = parse("api/config/ @kitokoh\n")
    expect("motif sans / initial signalé", len(bad) == 1)

    _, bad2 = parse("/api/config/\n")
    expect("ligne sans propriétaire signalée", len(bad2) == 1)

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 13/13.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        return self_test()
    return check()


if __name__ == "__main__":
    sys.exit(main())
