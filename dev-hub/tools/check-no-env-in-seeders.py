#!/usr/bin/env python3
"""Garde CI — aucun `env()` hors de `api/config/`.

## Le défaut que cette garde ferme

`docker-entrypoint.sh` exécute `php artisan config:cache` (ligne 35) **avant**
`php artisan db:seed` (ligne 430). Après `config:cache`, Laravel ne charge plus
`.env` : `env()` ne renvoie plus que ce que le SAPI a exposé dans `$_SERVER`,
c'est-à-dire ce que `variables_order` veut bien inclure. Le dépôt documente
lui-même ce piège à deux endroits :

  - `app/Modules/EdgeSync/Console/Commands/EdgeSyncDaemonCommand.php:46`
  - `app/Modules/Platform/Infrastructure/Services/HealthProbeService.php:94`

Or les gardes qui empêchent le seeding de démonstration en production lisaient
`env()` **directement**, et `DISABLE_DEMO_SEEDING` / `DEPLOY_TIER` n'existaient
dans aucun fichier `config/` :

  - `DemoCompanyOnceSeeder`  : `env('DISABLE_DEMO_SEEDING')`, `env('DEPLOY_TIER')`
  - `DemoCompanySeeder`      : `env('ALLOW_DEMO_SEEDING')`, `env('DEMO_SEED_ONCE')`
  - `CrmPilotSeeder`         : `env('DISABLE_DEMO_SEEDING')`
  - `GuardsPilotSeeding`     : `env('DEPLOY_TIER')`, `env('ALLOW_PILOT_SEEDING')`
  - `SuperAdminSeeder`       : `env('SUPER_ADMIN_PASSWORD')`, `env('FORCE_SUPER_ADMIN_PASSWORD_RESET')`

Une garde de sécurité dont la valeur dépend de `variables_order` n'est pas une
garde : elle est vraie sur un poste de dev et fausse dans le conteneur, sans
que personne ne le sache. C'est exactement le mode de défaillance qu'un ratchet
CI doit rendre impossible.

## La règle

`env()` n'a le droit d'exister que dans `api/config/*.php`. Partout ailleurs
(`app/`, `database/`, `routes/`, `bootstrap/`), on lit `config()`.

## Exceptions légitimes (allow-list explicite, justifiée)

  - `getenv()` n'est PAS `env()` : il lit l'environnement du processus et
    fonctionne après `config:cache`. Il reste autorisé (ex. `getenv('PATH')`).
  - `env()` dans un commentaire ou une chaîne n'est pas un appel.

## Usage

    python3 dev-hub/tools/check-no-env-in-seeders.py
    python3 dev-hub/tools/check-no-env-in-seeders.py --self-test
    python3 dev-hub/tools/check-no-env-in-seeders.py --verbose

Exit 0 = propre, 1 = au moins un `env()` hors config, 2 = erreur technique.
"""
from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
API = ROOT / "api"

# Répertoires où `env()` est interdit. `config/` est volontairement absent :
# c'est le seul endroit où Laravel garantit que `env()` est encore lisible.
SCANNED_DIRS = ["app", "database", "routes", "bootstrap"]

# `env(` non précédé d'un identifiant (pour ne pas matcher `getenv(`,
# `putenv(`, `$this->env(`) et non suivi d'un autre identifiant.
ENV_CALL_RE = re.compile(r"(?<![A-Za-z0-9_$>:])env\s*\(")

# Fichiers autorisés, avec la raison. Toute entrée doit être justifiée :
# une allow-list sans raison devient une dette invisible.
ALLOWLIST: dict[str, str] = {}


def strip_php_comments_and_strings(text: str) -> str:
    """Remplace commentaires et littéraux de chaîne par des espaces.

    On conserve les retours à la ligne pour que les numéros de ligne restent
    exacts. Sans cela, `// env('X')` dans un commentaire — et le dépôt en est
    plein, précisément pour expliquer le piège — produirait un faux positif.
    """
    out: list[str] = []
    i = 0
    n = len(text)
    quote: str | None = None
    while i < n:
        ch = text[i]
        if quote is not None:
            if ch == "\\" and i + 1 < n:
                out.append("  ")
                i += 2
                continue
            if ch == quote:
                quote = None
                out.append(ch)
                i += 1
                continue
            out.append("\n" if ch == "\n" else " ")
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
            out.append(ch)
            i += 1
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "/":
            while i < n and text[i] != "\n":
                out.append(" ")
                i += 1
            continue
        if ch == "#":
            while i < n and text[i] != "\n":
                out.append(" ")
                i += 1
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "*":
            out.append("  ")
            i += 2
            while i + 1 < n and not (text[i] == "*" and text[i + 1] == "/"):
                out.append("\n" if text[i] == "\n" else " ")
                i += 1
            out.append("  ")
            i += 2
            continue
        out.append(ch)
        i += 1
    return "".join(out)


def find_env_calls(text: str) -> list[tuple[int, str]]:
    """Retourne [(ligne, extrait)] pour chaque appel `env()` réel."""
    clean = strip_php_comments_and_strings(text)
    hits: list[tuple[int, str]] = []
    for m in ENV_CALL_RE.finditer(clean):
        line = clean.count("\n", 0, m.start()) + 1
        # Extrait lisible : la ligne d'origine, pas la version masquée.
        original_line = text.split("\n")[line - 1].strip()
        hits.append((line, original_line))
    return hits


def check(verbose: bool = False) -> int:
    if not API.is_dir():
        print(f"::error::Répertoire api/ introuvable sous {ROOT}.", file=sys.stderr)
        return 2

    total = 0
    scanned = 0
    for sub in SCANNED_DIRS:
        base = API / sub
        if not base.is_dir():
            continue
        for path in sorted(base.rglob("*.php")):
            rel = path.relative_to(ROOT).as_posix()
            if rel in ALLOWLIST:
                continue
            scanned += 1
            hits = find_env_calls(path.read_text(encoding="utf-8", errors="ignore"))
            for line, snippet in hits:
                total += 1
                print(
                    f"::error file={rel},line={line}::"
                    f"`env()` hors de api/config/ : {snippet} — "
                    f"lire `config()` à la place. Après `config:cache` "
                    f"(docker-entrypoint.sh:35, avant db:seed:430) `env()` ne "
                    f"renvoie plus que ce que le SAPI expose : une garde de "
                    f"sécurité lue par `env()` n'est pas fiable."
                )

    if total:
        print(f"\n❌ {total} appel(s) `env()` hors de api/config/ ({scanned} fichiers).")
        return 1

    print(f"✅ Aucun `env()` hors de api/config/ ({scanned} fichiers analysés).")
    return 0


def self_test() -> int:
    failures = 0

    def expect(name: str, text: str, want: int) -> None:
        nonlocal failures
        got = len(find_env_calls(text))
        if got != want:
            failures += 1
            print(f"::error::self-test '{name}' : attendu {want}, obtenu {got}")

    expect("appel simple", "<?php $x = env('FOO');", 1)
    expect("appel avec défaut", "<?php $x = env('FOO', false);", 1)
    expect("getenv n'est pas env", "<?php $x = getenv('PATH');", 0)
    expect("putenv n'est pas env", "<?php putenv('A=1');", 0)
    expect("commentaire //", "<?php // env('FOO')\n$x = 1;", 0)
    expect("commentaire #", "<?php # env('FOO')\n$x = 1;", 0)
    expect("bloc /* */", "<?php /* env('FOO') */\n$x = 1;", 0)
    expect("chaîne littérale", "<?php $x = 'env(FOO)';", 0)
    expect("méthode ->env(", "<?php $x = $this->env('FOO');", 0)
    expect("deux appels", "<?php env('A'); env('B');", 2)

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 10/10.")
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
