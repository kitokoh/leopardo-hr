#!/usr/bin/env python3
"""Garde CI — `.env.example` et `api/config/` doivent décrire le même contrat.

## Le défaut que cette garde ferme

`.env.example` est le contrat d'exploitation : ce qu'un opérateur peut régler.
`api/config/*.php` est la réalité : ce que l'application lit. Rien ne les
obligeait à rester d'accord.

Constat mesuré sur `main` (audit architecture 2026-11-26) : **44 variables**
documentées dans `.env.example` n'étaient lues par aucun fichier `config/`.
Parmi elles, des réglages qu'un opérateur croit pouvoir piloter :

  - `RATE_LIMIT_PLAN_STARTER/PROFESSIONAL/BUSINESS/TRIAL/PRO_PER_MINUTE` —
    `PlanCode` ne connaît que `free`, `pilot`, `operations`, `enterprise`.
    Cinq variables documentaient des plans **qui n'existent pas** ;
  - `CORS_ALLOWED_ORIGINS` — le code lit `CORS_EXTRA_ORIGIN` ;
  - `RESTAURANT_STOCK_INSUFFICIENT_POLICY` — la config lit
    `block_on_insufficient` ;
  - `CATALOG_CURRENCIES` / `CATALOG_UNITS` — `CatalogPricePolicy` lit
    `config('catalog.currencies')`, clé qui n'existait dans aucun fichier.

Ce n'est pas une faille, c'est une **promesse non tenue** : régler la variable
ne change rien, et personne ne le sait. Le même mécanisme appliqué à
`DISABLE_DEMO_SEEDING` était un défaut critique (traité dans le commit
`fix(security)` de la branche d'audit).

## Ce que la garde vérifie

  1. toute variable lue par `env()` dans `api/config/` est documentée dans
     `.env.example` (sinon un opérateur ne peut pas la régler) ;
  2. toute variable documentée dans `.env.example` est lue par `api/config/`,
     OU figure dans `READ_OUTSIDE_CONFIG` avec sa raison (variables lues par
     `docker-entrypoint.sh`, un paquet tiers, ou un script de déploiement).

## Usage

    python3 dev-hub/tools/check-env-example-parity.py
    python3 dev-hub/tools/check-env-example-parity.py --self-test
    python3 dev-hub/tools/check-env-example-parity.py --verbose

Exit 0 = propre, 1 = dérive, 2 = erreur technique.
"""
from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
API = ROOT / "api"
ENV_EXAMPLE = API / ".env.example"
CONFIG_DIR = API / "config"

ENV_CALL_RE = re.compile(r"env\(\s*'([A-Z0-9_]+)'")
ENV_LINE_RE = re.compile(r"^([A-Z0-9_]+)\s*=")

# Variables légitimement documentées mais lues HORS de api/config/.
# Chaque entrée DOIT porter sa raison : une allow-list sans raison devient
# une dette invisible, et c'est exactement ce que cette garde combat.
READ_OUTSIDE_CONFIG: dict[str, str] = {
    # Lu par le paquet laravel/telescope lui-même (config/telescope.php absent :
    # le paquet utilise ses défauts). Documenté pour que l'opérateur sache
    # qu'il peut le régler, mais ce n'est pas notre code qui le lit.
    "TELESCOPE_ENABLED": "paquet laravel/telescope (pas de config/telescope.php publiée)",
    "TELESCOPE_PATH": "paquet laravel/telescope",
    "TELESCOPE_DRIVER": "paquet laravel/telescope",
    "TELESCOPE_DOMAIN": "paquet laravel/telescope",
    "TELESCOPE_PRUNE_HOURS": "paquet laravel/telescope",
    "TELESCOPE_RESPONSE_SIZE_LIMIT": "paquet laravel/telescope",
    "TELESCOPE_BATCH_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_CACHE_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_COMMAND_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_DUMP_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_EVENT_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_EXCEPTION_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_GATE_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_JOB_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_LOG_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_MAIL_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_MODEL_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_NOTIFICATION_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_QUERY_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_REDIS_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_REQUEST_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_SCHEDULE_WATCHER": "paquet laravel/telescope",
    "TELESCOPE_VIEW_WATCHER": "paquet laravel/telescope",
    # Lu par config/cors.php via cors_env_origin('ADMIN_DASHBOARD_URL') — la
    # valeur passe par une fonction, donc le motif `env('X')` ne la voit pas.
    "ADMIN_DASHBOARD_URL": "lu par cors_env_origin() dans config/cors.php",
    "CORS_EXTRA_ORIGIN": "lu par cors_env_origin() dans config/cors.php",
    # Lus par docker-entrypoint.sh (bascule queue/scheduler dans le conteneur web).
    "WEB_QUEUE_DRAIN": "lu par docker-entrypoint.sh",
    "WEB_SCHEDULER_LOOP": "lu par docker-entrypoint.sh",
    # Lus par les scripts de sauvegarde (hors dépôt applicatif).
    "BACKUP_PATH": "script de sauvegarde externe",
    "BACKUP_KEEP_LAST": "script de sauvegarde externe",
    # Lu par config/tenancy.php via une constante de classe, pas un env() littéral.
    "TENANCY_DEFAULT_TYPE": "lu par config/tenancy.php (indirection)",
}


def strip_php_comments(text: str) -> str:
    out: list[str] = []
    i, n = 0, len(text)
    quote: str | None = None
    while i < n:
        ch = text[i]
        if quote is not None:
            out.append(ch)
            if ch == "\\" and i + 1 < n:
                out.append(text[i + 1])
                i += 2
                continue
            if ch == quote:
                quote = None
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
            out.append(ch)
            i += 1
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "/":
            while i < n and text[i] != "\n":
                i += 1
            continue
        if ch == "#":
            while i < n and text[i] != "\n":
                i += 1
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "*":
            i += 2
            while i + 1 < n and not (text[i] == "*" and text[i + 1] == "/"):
                i += 1
            i += 2
            continue
        out.append(ch)
        i += 1
    return "".join(out)


def read_in_config() -> set[str]:
    used: set[str] = set()
    for path in sorted(CONFIG_DIR.glob("*.php")):
        text = strip_php_comments(path.read_text(encoding="utf-8", errors="ignore"))
        used.update(ENV_CALL_RE.findall(text))
    return used


def documented() -> set[str]:
    doc: set[str] = set()
    for line in ENV_EXAMPLE.read_text(encoding="utf-8", errors="ignore").split("\n"):
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        m = ENV_LINE_RE.match(line)
        if m:
            doc.add(m.group(1))
    return doc


def compare(used: set[str], doc: set[str]) -> list[str]:
    problems: list[str] = []
    for var in sorted(used - doc):
        problems.append(
            f"`{var}` est lue par api/config/ mais ABSENTE de .env.example — "
            f"un opérateur ne peut pas la régler."
        )
    for var in sorted(doc - used - set(READ_OUTSIDE_CONFIG)):
        problems.append(
            f"`{var}` est documentée dans .env.example mais AUCUN fichier "
            f"api/config/ ne la lit — la régler ne change rien. Supprimer, "
            f"brancher, ou déclarer dans READ_OUTSIDE_CONFIG avec sa raison."
        )
    return problems


def check(verbose: bool = False) -> int:
    if not ENV_EXAMPLE.is_file():
        print(f"::error::{ENV_EXAMPLE} introuvable.", file=sys.stderr)
        return 2
    used, doc = read_in_config(), documented()
    problems = compare(used, doc)

    if verbose:
        print(f"  lues par config/ : {len(used)}")
        print(f"  documentées      : {len(doc)}")
        print(f"  hors config/     : {len(READ_OUTSIDE_CONFIG)} (allow-list justifiée)")

    for p in problems:
        print(f"::error::{p}")

    if problems:
        print(f"\n❌ {len(problems)} dérive(s) entre .env.example et api/config/.")
        return 1

    print(f"✅ .env.example et api/config/ d'accord ({len(used)} variables).")
    return 0


def self_test() -> int:
    failures = 0

    def expect(name: str, used: set[str], doc: set[str], want: int) -> None:
        nonlocal failures
        got = len(compare(used, doc))
        if got != want:
            failures += 1
            print(f"::error::self-test '{name}' : attendu {want}, obtenu {got}")

    expect("accord parfait", {"A", "B"}, {"A", "B"}, 0)
    expect("lue non documentée", {"A", "B"}, {"A"}, 1)
    expect("documentée non lue", {"A"}, {"A", "B"}, 1)
    expect("les deux", {"A", "X"}, {"A", "Y"}, 2)
    expect("ensembles vides", set(), set(), 0)

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 5/5.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--verbose", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        return self_test()
    return check(verbose=args.verbose)


if __name__ == "__main__":
    sys.exit(main())
