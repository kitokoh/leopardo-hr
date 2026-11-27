#!/usr/bin/env python3
"""Exécuteur de shards Feature, reprenable, un shard = une testsuite phpunit.

Chaque shard tourne sur sa PROPRE base PostgreSQL (leopardo_shard_<n>) pour
éviter les collisions de `migrate:fresh` entre shards et le deadlock observé
sur le shard Demo (base `leopardo_test` partagée et non migrée).

Un shard déjà terminé (XML présent et non vide) est sauté : le script reprend
là où il s'est arrêté.

Usage:
    python3 docs/recette/_run_shards.py [--budget-secondes N] [--only NAME]
"""
from __future__ import annotations

import argparse
import os
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path

API = Path(__file__).resolve().parents[2] / "api"
OUT = Path(__file__).resolve().parent / "rapport-junit"
LOG = OUT / "execution-shards.log"

# Testsunités à NE PAS exécuter ici (hors périmètre Feature).
SKIP = {"Unit", "Arch", "Feature"}

# Une erreur d'INFRASTRUCTURE n'est pas un échec du code testé : un shard qui
# en contient n'a produit aucune preuve et doit être rejoué (voir
# `already_done`). Même expression que `_aggregate_shards.py`.
INFRA_ERROR = re.compile(
    r"No space left on device|Disk full|SQLSTATE\[53100\]|out of memory|"
    r"OOM killer|Connection refused|could not connect|server closed the connection|"
    r"SQLSTATE\[25P02\]|current transaction is aborted",
    re.I,
)


def shard_names() -> list[str]:
    xml = (API / "phpunit.xml").read_text(encoding="utf-8")
    names = re.findall(r'<testsuite name="([^"]+)"', xml)
    return [n for n in names if n not in SKIP]


def shard_file_count(name: str) -> int:
    """Nombre de fichiers <file> listés dans la testsuite (0 = répertoire)."""
    xml = (API / "phpunit.xml").read_text(encoding="utf-8")
    m = re.search(
        rf'<testsuite name="{re.escape(name)}">(.*?)</testsuite>', xml, re.S
    )
    if not m:
        return 0
    return len(re.findall(r"<file>", m.group(1)))


def already_done(name: str) -> bool:
    p = OUT / f"{name}.xml"
    if not p.exists() or p.stat().st_size < 200:
        return False
    try:
        root = ET.parse(p).getroot()
    except ET.ParseError:
        # XML corrompu (écriture interrompue par la saturation disque) : le
        # shard n'a produit aucune preuve exploitable, on le rejoue.
        return False
    suites = root.findall(".//testsuite")
    if not suites:
        return False
    # Un XML valide avec au moins un testcase compte comme terminé.
    if root.find(".//testcase") is None:
        return False
    # Un shard dont des tests ont échoué pour cause d'INFRASTRUCTURE (disque
    # plein, OOM, base injoignable) n'est pas « terminé » : il doit être rejoué.
    for tc in root.iter("testcase"):
        for child in tc:
            if child.tag == "error":
                blob = (child.text or "") + (child.get("message") or "")
                if INFRA_ERROR.search(blob):
                    return False
    return True


def _psql(sql: str, db: str = "postgres") -> str:
    return subprocess.run(
        ["su", "postgres", "-c", f"psql -d {db} -tAc \"{sql}\""],
        capture_output=True,
        text=True,
    ).stdout.strip()


def ensure_db(db: str) -> None:
    if _psql(f"SELECT 1 FROM pg_database WHERE datname='{db}'") != "1":
        subprocess.run(
            ["su", "postgres", "-c", f"createdb -O leopardo {db}"],
            capture_output=True,
            text=True,
        )


def drop_db(db: str) -> None:
    """Supprime la base du shard une fois son XML écrit.

    Le run précédent a saturé les 4 Go du disque en accumulant une base par
    shard (83 x ~200 Mo). On ne garde donc AUCUNE base après exploitation :
    le XML est la seule sortie qui compte.
    """
    subprocess.run(
        ["su", "postgres", "-c", f"psql -q -c 'DROP DATABASE IF EXISTS {db} WITH (FORCE)'"],
        capture_output=True,
        text=True,
    )


def bootstrap_schemas(db: str) -> None:
    """Reproduit `.github/actions/setup-backend-db` : dépôts de migration
    `public.migrations` + `shared_tenants.migrations` et schéma tenant.

    Sans ce bootstrap, les classes qui n'utilisent PAS `RefreshTenantDatabase`
    (ex. `QueuedListenersTest`) trouvent une base vide : `relation "invoices"
    does not exist`. En CI la base est migrée une fois par l'action composite
    avant la suite ; ici chaque shard a sa base, donc on la prépare pareil.
    """
    _psql(
        "CREATE SCHEMA IF NOT EXISTS shared_tenants; "
        "CREATE TABLE IF NOT EXISTS public.migrations "
        "(id serial PRIMARY KEY, migration varchar(255) NOT NULL, batch integer NOT NULL); "
        "CREATE TABLE IF NOT EXISTS shared_tenants.migrations "
        "(id serial PRIMARY KEY, migration varchar(255) NOT NULL, batch integer NOT NULL);",
        db,
    )
    env = dict(os.environ)
    env.update({"DB_DATABASE": db, "APP_ENV": "testing", "API_DOCS_PUBLIC": "false"})
    for search_path, path in (
        ("public", "database/migrations/public"),
        ("shared_tenants", "database/migrations/tenant"),
    ):
        subprocess.run(
            ["php", "artisan", "migrate", f"--path={path}", "--force"],
            cwd=API,
            env={**env, "DB_SEARCH_PATH": search_path},
            capture_output=True,
            text=True,
        )


def run_shard(name: str, idx: int, timeout: int) -> tuple[str, int, float]:
    db = f"leopardo_shard_{idx}"
    ensure_db(db)
    bootstrap_schemas(db)
    xml_out = OUT / f"{name}.xml"
    log_out = OUT / f"{name}.log"
    env = dict(os.environ)
    env.update(
        {
            "DB_DATABASE": db,
            "APP_ENV": "testing",
            # Le flag public de la doc API est une config de DÉPLOIEMENT : en
            # test il doit être false, sinon OpenApiDocsTest::test_docs_require_
            # authentication_in_production échoue (200 au lieu de 403).
            "API_DOCS_PUBLIC": "false",
        }
    )
    cmd = [
        "php",
        "artisan",
        "test",
        f"--testsuite={name}",
        "--log-junit",
        str(xml_out),
    ]
    t0 = time.time()
    try:
        proc = subprocess.run(
            cmd,
            cwd=API,
            env=env,
            capture_output=True,
            text=True,
            timeout=timeout,
        )
        out = (proc.stdout or "") + (proc.stderr or "")
        rc = proc.returncode
        status = "OK" if rc == 0 else "FAIL"
    except subprocess.TimeoutExpired as exc:
        out = (exc.stdout or "") if isinstance(exc.stdout, str) else ""
        rc = 124
        status = "PART"
    dur = time.time() - t0
    log_out.write_text(out, encoding="utf-8")
    # Le XML est écrit : la base n'a plus d'utilité. On la détruit AVANT de
    # rendre la main pour ne jamais en accumuler (saturation disque du run
    # précédent : 83 bases x ~200 Mo sur un disque de 4 Go).
    drop_db(db)
    return status, rc, dur


def free_mb() -> int:
    st = os.statvfs("/")
    return int(st.f_bavail * st.f_frsize / (1024 * 1024))


def mem_available_mb() -> int:
    """Mémoire disponible en Mo, lue sur le CGROUP quand il existe.

    `/proc/meminfo` donne la mémoire de l'HÔTE (319 Go annoncés par le noyau),
    ce qui ne dit rien de la limite réelle du sandbox (2 Go). Le run précédent
    a vu le checkpointer PostgreSQL tué par l'OOM killer : c'est la limite
    cgroup qui compte, pas l'hôte.
    """
    for base in (Path("/sys/fs/cgroup"), Path("/sys/fs/cgroup/memory")):
        cur, mx = base / "memory.current", base / "memory.max"
        try:
            if cur.exists() and mx.exists():
                limit = mx.read_text().strip()
                if limit == "max":
                    break
                used = int(cur.read_text().strip())
                # `memory.current` inclut le CACHE DE PAGES (inactive_file), qui
                # est récupérable sous pression. Le compter comme « utilisé »
                # faisait croire à 1 Mo libre alors que 1,6 Go d'anon seulement
                # étaient réellement occupés — et bloquait tout lancement.
                stat = base / "memory.stat"
                if stat.exists():
                    for line in stat.read_text().splitlines():
                        if line.startswith("inactive_file "):
                            used -= int(line.split()[1])
                            break
                limit_mb = int(limit) // (1024 * 1024)
                return max(0, limit_mb - used // (1024 * 1024))
        except (OSError, ValueError):
            pass
    try:
        for line in Path("/proc/meminfo").read_text().splitlines():
            if line.startswith("MemAvailable:"):
                return int(line.split()[1]) // 1024
    except OSError:
        pass
    return 10**6


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--budget-secondes", type=int, default=3600)
    ap.add_argument("--only", default=None)
    ap.add_argument("--timeout-shard", type=int, default=900)
    ap.add_argument("--min-free-mb", type=int, default=400)
    ap.add_argument("--min-mem-mb", type=int, default=250)
    args = ap.parse_args()

    OUT.mkdir(parents=True, exist_ok=True)
    names = shard_names()
    if args.only:
        names = [n for n in names if n == args.only]

    # Plus petit d'abord : maximise le nombre de shards terminés.
    names.sort(key=lambda n: (shard_file_count(n), n))

    start = time.time()
    stopped = None
    with LOG.open("a", encoding="utf-8") as log:
        log.write(f"\n=== reprise {time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())} ===\n")
        log.write(f"shards: {len(names)} | budget: {args.budget_secondes}s\n")
        log.flush()
        for idx, name in enumerate(names):
            if already_done(name):
                log.write(f"SKIP {name:<24} (déjà terminé)\n")
                log.flush()
                continue
            elapsed = time.time() - start
            if elapsed > args.budget_secondes:
                stopped = f"budget épuisé après {elapsed:.0f}s"
                log.write(f"STOP {stopped}\n")
                log.flush()
                break
            fm, mm = free_mb(), mem_available_mb()
            if fm < args.min_free_mb or mm < args.min_mem_mb:
                stopped = f"ressources insuffisantes (disque {fm} Mo, mémoire {mm} Mo)"
                log.write(f"STOP {stopped}\n")
                log.flush()
                break
            remaining = args.budget_secondes - elapsed
            timeout = int(min(args.timeout_shard, max(60, remaining)))
            status, rc, dur = run_shard(name, idx, timeout)
            log.write(
                f"{status:<4} {name:<24} {shard_file_count(name):>3} fichiers "
                f"{dur:>6.0f}s rc={rc} | disque {fm} Mo libres\n"
            )
            log.flush()
        log.write(
            f"FIN reprise — arrêt: {stopped or 'tous les shards traités'} | "
            f"disque {free_mb()} Mo, mémoire {mem_available_mb()} Mo\n"
        )
    return 0


if __name__ == "__main__":
    sys.exit(main())