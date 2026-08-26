#!/usr/bin/env python3
"""Garde #5584 — isolation des modules (règle api/ARCHITECTURE.md:52-54).

« Un module n'importe jamais directement les classes d'un autre module. »
Cette garde ne bloque PAS l'existant (allowlist module-isolation-allowlist.txt,
dette à purger), mais fait échouer la CI sur TOUT NOUVEL import croisé
inter-modules, et sur tout import Modules/* depuis Core/ (inversion de
dépendance) — pattern archunit, même approche que la baseline PHPStan.

Usage : python3 dev-hub/tools/check-module-isolation.py
"""
from __future__ import annotations

import os
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent.parent
MODULES = REPO_ROOT / "api" / "app" / "Modules"
CORE = REPO_ROOT / "api" / "app" / "Core"
ALLOWLIST_FILE = REPO_ROOT / "dev-hub" / "tools" / "module-isolation-allowlist.txt"

# `use App\Modules\X\...` — X est capturé comme module cible.
USE_MODULE_RE = re.compile(r"use\s+App" + re.escape("\\") + r"Modules" + re.escape("\\") + r"([A-Za-z0-9_]+)" + re.escape("\\"))

allowlist = set()
for raw in ALLOWLIST_FILE.read_text(encoding="utf-8").splitlines():
    line = raw.strip()
    if line and not line.startswith("#"):
        allowlist.add(line)

violations: list[str] = []


def scan_module(module_dir: Path, module_name: str) -> None:
    for path in sorted(module_dir.rglob("*.php")):
        rel = path.relative_to(REPO_ROOT).as_posix()
        content = path.read_text(encoding="utf-8", errors="replace")
        for m in USE_MODULE_RE.finditer(content):
            target = m.group(1)
            if target == module_name:
                continue
            key = f"{module_name}:{target}:{rel}"
            if key not in allowlist:
                violations.append(
                    f"::error file={rel}::{module_name} importe le module {target} "
                    f"(règle d'isolation api/ARCHITECTURE.md:52-54, #5584) — ajouter à "
                    f"l'allowlist UNIQUEMENT si la dette est assumée, sinon refactorer."
                )


# 1) Modules → autres modules
for module_dir in sorted(MODULES.iterdir()):
    if module_dir.is_dir():
        scan_module(module_dir, module_dir.name)

# 2) Core → Modules (inversion de dépendance)
if CORE.is_dir():
    for path in sorted(CORE.rglob("*.php")):
        rel = path.relative_to(REPO_ROOT).as_posix()
        content = path.read_text(encoding="utf-8", errors="replace")
        for m in USE_MODULE_RE.finditer(content):
            target = m.group(1)
            key = f"Core:{target}:{rel}"
            if key not in allowlist:
                violations.append(
                    f"::error file={rel}::Core importe le module {target} "
                    f"(inversion de dépendance, #5584) — à extraire vers Shared/Contracts."
                )

def main() -> int:
    if violations:
        print("\n".join(violations))
        print(f"::error::Isolation des modules : {len(violations)} nouvel(le)(s) import(s) croisé(s) — #5584.")
        return 1

    print(f"::notice::Isolation des modules OK — {len(allowlist)} dépendances croisées baselignées (dette #5584 à purger).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
