#!/usr/bin/env python3
"""Garde CI — le script de bascule du rôle applicatif RLS doit être EXÉCUTABLE.

## Le défaut que cette garde ferme (#9974)

`api/database/scripts/rls/create-app-role.sql` est la procédure de bascule de
l'ADR-9879 : c'est elle qui crée le rôle `leopardo_app` `NOSUPERUSER
NOBYPASSRLS` sans lequel les politiques RLS ne filtrent rien.

La version livrée par #9896 écrivait :

    DO $$
    BEGIN
        EXECUTE format('CREATE ROLE ... PASSWORD %L', :'app_password');
    END
    $$;

psql **n'interpole pas** ses variables (`:'app_password'`) à l'intérieur d'une
chaîne dollar-quotée (`$$ … $$`). Le script échouait donc en
`syntax error at or near ":"` — la procédure de bascule documentée ne pouvait
pas s'exécuter, et personne ne l'a su parce qu'aucune CI ne la lançait.

C'est la classe de défaut la plus coûteuse de ce dépôt : **une procédure de
sécurité documentée, jamais exécutée, donc jamais vérifiée**. Cette garde
vérifie ce qui est vérifiable statiquement ; `tests.yml` exécute le script
contre un vrai PostgreSQL et vérifie l'effet (`rolbypassrls = false`).

## Ce que la garde vérifie

  1. aucune variable psql (`:'x'` / `:"x"`) dans un bloc dollar-quoté ;
  2. le rôle créé porte bien `NOSUPERUSER` ET `NOBYPASSRLS` ;
  3. la création est idempotente (pas de `CREATE ROLE` nu sans garde
     `WHERE NOT EXISTS` / `IF NOT EXISTS`) ;
  4. le mot de passe n'est jamais écrit en clair (aucun littéral après
     `PASSWORD` autre qu'une variable psql ou `%L`).

## Usage

    python3 dev-hub/tools/check-rls-role-script.py
    python3 dev-hub/tools/check-rls-role-script.py --self-test
    python3 dev-hub/tools/check-rls-role-script.py --verbose

Exit 0 = propre, 1 = dérive, 2 = erreur technique.
"""
from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "api/database/scripts/rls/create-app-role.sql"

# `$$ … $$` ou `$tag$ … $tag$`
DOLLAR_BLOCK_RE = re.compile(r"\$([A-Za-z_]*)\$.*?\$\1\$", re.S)
PSQL_VAR_RE = re.compile(r":['\"][A-Za-z_][A-Za-z0-9_]*['\"]")
CREATE_ROLE_RE = re.compile(r"CREATE\s+ROLE\s+([a-z_][a-z0-9_]*)", re.I)
PASSWORD_LITERAL_RE = re.compile(r"PASSWORD\s+'([^']*)'", re.I)


def strip_sql_comments(text: str) -> str:
    """Retire les commentaires `--` en préservant les chaînes.

    Indispensable : le script est abondamment commenté et cite justement
    `:'app_password'` dans sa documentation du bug. Sans ce nettoyage, la
    garde signalerait le commentaire qui explique le défaut.
    """
    out: list[str] = []
    for line in text.splitlines():
        quote = False
        i = 0
        cut = len(line)
        while i < len(line):
            ch = line[i]
            if ch == "'":
                quote = not quote
            elif not quote and ch == "-" and i + 1 < len(line) and line[i + 1] == "-":
                cut = i
                break
            i += 1
        out.append(line[:cut])
    return "\n".join(out)


def check_text(text: str) -> list[str]:
    problems: list[str] = []
    sql = strip_sql_comments(text)

    # 1) variable psql dans un bloc dollar-quoté
    for m in DOLLAR_BLOCK_RE.finditer(sql):
        body = m.group(0)
        if PSQL_VAR_RE.search(body):
            var = PSQL_VAR_RE.search(body).group(0)  # type: ignore[union-attr]
            problems.append(
                f"variable psql {var} à l'intérieur d'un bloc dollar-quoté : "
                "psql ne l'interpole PAS, le script échouera en "
                "`syntax error at or near \":\"`. Utiliser `\\gexec` "
                "(la requête produit la commande) ou passer par une variable "
                "hors dollar-quotes."
            )

    # 2) attributs de sécurité du rôle
    if not re.search(r"NOSUPERUSER", sql, re.I):
        problems.append("le script ne pose pas NOSUPERUSER : RLS serait bypassé.")
    if not re.search(r"NOBYPASSRLS", sql, re.I):
        problems.append("le script ne pose pas NOBYPASSRLS : RLS serait bypassé.")

    # 3) idempotence
    for name in CREATE_ROLE_RE.findall(sql):
        guarded = re.search(
            r"WHERE\s+NOT\s+EXISTS\s*\(\s*SELECT\s+1\s+FROM\s+pg_roles", sql, re.I
        )
        if not guarded:
            problems.append(
                f"`CREATE ROLE {name}` sans garde `WHERE NOT EXISTS (SELECT 1 FROM pg_roles…)` : "
                "la réexécution échouerait en `role already exists`, alors que la "
                "procédure de bascule doit être rejouable (rotation de mot de passe)."
            )

    # 4) mot de passe en clair
    for literal in PASSWORD_LITERAL_RE.findall(sql):
        if literal.strip():
            problems.append(
                "mot de passe écrit en clair dans un fichier versionné "
                f"(PASSWORD '{literal[:3]}…'). Le secret doit venir de "
                "`-v app_password=…`."
            )

    return problems


def check(verbose: bool = False) -> int:
    if not SCRIPT.is_file():
        print(f"::error::Script introuvable : {SCRIPT}", file=sys.stderr)
        return 2

    text = SCRIPT.read_text(encoding="utf-8", errors="ignore")
    problems = check_text(text)

    if verbose:
        print(f"  script : {SCRIPT.relative_to(ROOT)} ({len(text.splitlines())} lignes)")

    for p in problems:
        print(f"::error::{p}")

    if problems:
        print(f"\n❌ {len(problems)} défaut(s) dans le script de bascule du rôle RLS.")
        return 1

    print("✅ Script de bascule RLS exécutable : pas de variable psql en dollar-quotes, "
          "NOSUPERUSER + NOBYPASSRLS posés, création idempotente, aucun secret en clair.")
    return 0


def self_test() -> int:
    failures = 0

    def expect(name: str, text: str, want: int) -> None:
        nonlocal failures
        got = len(check_text(text))
        if got != want:
            failures += 1
            print(f"::error::self-test '{name}' : attendu {want}, obtenu {got}")

    good = """
    SELECT format('CREATE ROLE leopardo_app LOGIN NOSUPERUSER NOBYPASSRLS PASSWORD %L', :'app_password')
    WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'leopardo_app')
    \\gexec
    ALTER ROLE leopardo_app NOSUPERUSER NOBYPASSRLS;
    """
    expect("script conforme", good, 0)

    # Le défaut historique exact de #9896.
    bad_dollar = """
    DO $$
    BEGIN
        EXECUTE format('CREATE ROLE leopardo_app LOGIN NOSUPERUSER NOBYPASSRLS PASSWORD %L', :'app_password')
        WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'leopardo_app');
    END
    $$;
    """
    expect("variable psql en dollar-quotes", bad_dollar, 1)

    expect("sans NOBYPASSRLS", "CREATE ROLE x LOGIN NOSUPERUSER WHERE NOT EXISTS (SELECT 1 FROM pg_roles);", 1)
    expect("sans NOSUPERUSER", "CREATE ROLE x LOGIN NOBYPASSRLS WHERE NOT EXISTS (SELECT 1 FROM pg_roles);", 1)
    expect("CREATE ROLE non idempotent", "CREATE ROLE x LOGIN NOSUPERUSER NOBYPASSRLS;", 1)
    expect("mot de passe en clair", "CREATE ROLE x LOGIN NOSUPERUSER NOBYPASSRLS PASSWORD 'hunter2' WHERE NOT EXISTS (SELECT 1 FROM pg_roles);", 1)

    # Un commentaire qui CITE le bug ne doit pas être signalé.
    commented = """
    -- Ancienne version : EXECUTE format('...', :'app_password') dans DO $$ ... $$;
    SELECT format('CREATE ROLE leopardo_app LOGIN NOSUPERUSER NOBYPASSRLS PASSWORD %L', :'app_password')
    WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'leopardo_app')
    \\gexec
    """
    expect("commentaire citant le bug", commented, 0)

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 7/7.")
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
