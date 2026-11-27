#!/usr/bin/env python3
"""Exécute les shards Feature du plus PETIT au plus GROS et écrit un JUnit
par shard dans api/storage/qa/junit/.

Pourquoi cet ordre : sur ce sandbox (2 Go de cgroup, ~1,4 test/s) un shard de
1 121 fichiers ne finit jamais dans la fenêtre, alors qu'un shard de 1 à 10
fichiers finit en quelques secondes. En traitant les petits d'abord, le
rapport JUnit consolidé couvre le maximum de shards réellement exécutés.

Un shard déjà présent (XML non vide) est sauté : le script est reprenable.
"""
import re
import subprocess
import sys
import time
from pathlib import Path

API = Path("/workspace/leopardo-hr/api")
OUT = API / "storage/qa/junit"
OUT.mkdir(parents=True, exist_ok=True)
PER_SHARD_TIMEOUT = int(sys.argv[1]) if len(sys.argv) > 1 else 300

xml = (API / "phpunit.xml").read_text(encoding="utf-8")
blocks = re.findall(r'<testsuite name="([^"]+)">(.*?)</testsuite>', xml, re.S)
shards = []
for name, body in blocks:
    if name in ("Unit", "Arch", "Feature"):
        continue
    shards.append((len(re.findall(r"<file>", body)), name))
shards.sort()

print("shards: %d | ordre: plus petit d'abord" % len(shards), flush=True)
print("start", time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()), flush=True)

for nfiles, name in shards:
    target = OUT / ("%s.xml" % name)
    if target.exists() and target.stat().st_size > 0:
        print("SKIP %-22s (déjà présent)" % name, flush=True)
        continue
    t0 = time.time()
    try:
        subprocess.run(
            [str(API / "vendor/bin/phpunit"), "--no-coverage",
             "--testsuite", name, "--log-junit", str(target)],
            cwd=str(API), timeout=PER_SHARD_TIMEOUT,
            stdout=open(OUT / ("%s.log" % name), "w"),
            stderr=subprocess.STDOUT,
            env={**__import__("os").environ, "APP_ENV": "testing",
                 "DB_DATABASE": "leopardo_test_w1"},
        )
        rc = 0
    except subprocess.TimeoutExpired:
        rc = 124
    dur = int(time.time() - t0)
    if target.exists() and target.stat().st_size > 0:
        print("OK   %-22s %3d fichiers %4ds" % (name, nfiles, dur), flush=True)
    else:
        print("PART %-22s %3d fichiers %4ds (timeout)" % (name, nfiles, dur), flush=True)

print("done", time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()), flush=True)
