#!/usr/bin/env python3
"""check-mobile-core-duplication.py — Garde anti-NOUVELLE duplication core <-> apps (issue #7652).

Contexte (audit 2026-09-19, issue #7652) : `front/mobile_apps/leopardo_core/lib/features/`
contient des features complètes qui existent AUSSI en copies modifiées dans les apps
(attendance, absences, auth, settings, ...). Trois forks du même écran = corrections de
bugs et fixes sécu non propagés, en contradiction avec la règle du
`front/mobile_apps/README.md` (« toute modification partagée va dans leopardo_core »).

Ce garde interdit toute NOUVELLE duplication, sans rougir sur l'existant :

  * Un fichier `front/mobile_apps/<app>/lib/features/X` est un DOUBLON si le même
    chemin relatif existe dans `leopardo_core/lib/features/X`.
  * EXCEPTION : un pur shim de ré-export (`export 'package:leopardo_core/...';`,
    pattern #5279 utilisé par leopardo_manager) n'est PAS un doublon — c'est
    précisément l'état cible de la dé-duplication.
  * La dette EXISTANTE est figée dans `mobile-core-duplication-baseline.txt`
    (même dossier). Le garde échoue (exit 1) UNIQUEMENT sur un doublon absent de
    la baseline. Une entrée de baseline résolue est signalée en avertissement
    (à retirer via --update-baseline) mais ne bloque pas.

Usage :
  python3 dev-hub/tools/check-mobile-core-duplication.py              # garde (CI)
  python3 dev-hub/tools/check-mobile-core-duplication.py --report     # inventaire des diffs
  python3 dev-hub/tools/check-mobile-core-duplication.py --update-baseline

Exit 0 = OK ; exit 1 = nouvelle duplication core->app (ou baseline/arborescence invalide).
"""

from __future__ import annotations

import argparse
import difflib
import hashlib
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
MOBILE_ROOT = REPO_ROOT / "front" / "mobile_apps"
CORE_FEATURES = MOBILE_ROOT / "leopardo_core" / "lib" / "features"
BASELINE_PATH = Path(__file__).resolve().parent / "mobile-core-duplication-baseline.txt"

# Un shim de ré-export ne contient (hors commentaires/blancs) que des directives
# `export 'package:leopardo_core/...'` éventuellement suivies de show/hide.
_LINE_COMMENT = re.compile(r"//[^\n]*")
_BLOCK_COMMENT = re.compile(r"/\*.*?\*/", re.S)
_SHIM = re.compile(
    r"^(?:export\s+'package:leopardo_core/[^']+'\s*(?:show\s[^;]+|hide\s[^;]+)?;\s*)+$"
)


def is_reexport_shim(path: Path) -> bool:
    try:
        text = path.read_text(encoding="utf-8")
    except (OSError, UnicodeDecodeError):
        return False
    stripped = _BLOCK_COMMENT.sub("", _LINE_COMMENT.sub("", text)).strip()
    return bool(stripped) and bool(_SHIM.match(stripped))


def iter_apps():
    for entry in sorted(MOBILE_ROOT.iterdir()):
        if entry.is_dir() and entry.name != "leopardo_core" and (entry / "lib").is_dir():
            yield entry


def collect_duplicates():
    """[(clé baseline, chemin app, chemin core, est_shim)] pour tout chemin relatif partagé."""
    if not CORE_FEATURES.is_dir():
        print(f"ERREUR: introuvable: {CORE_FEATURES}", file=sys.stderr)
        sys.exit(1)
    core_files = {
        p.relative_to(CORE_FEATURES).as_posix(): p
        for p in CORE_FEATURES.rglob("*.dart")
    }
    result = []
    for app in iter_apps():
        features = app / "lib" / "features"
        if not features.is_dir():
            continue
        for p in sorted(features.rglob("*.dart")):
            rel = p.relative_to(features).as_posix()
            core_path = core_files.get(rel)
            if core_path is None:
                continue
            key = p.relative_to(MOBILE_ROOT).as_posix()
            result.append((key, p, core_path, is_reexport_shim(p)))
    return result


def load_baseline() -> set[str]:
    if not BASELINE_PATH.is_file():
        return set()
    lines = BASELINE_PATH.read_text(encoding="utf-8").splitlines()
    return {ln.strip() for ln in lines if ln.strip() and not ln.startswith("#")}


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def cmd_report(dups) -> int:
    identical = divergent = 0
    print(f"{'ÉTAT':<10} {'DIFF':>6}  {'CORE':>6} {'APP':>6}  CHEMIN (relatif à front/mobile_apps)")
    for key, app_path, core_path, shim in dups:
        if shim:
            print(f"{'SHIM':<10} {'-':>6}  {'-':>6} {'-':>6}  {key}")
            continue
        a = core_path.read_text(encoding="utf-8", errors="replace").splitlines()
        b = app_path.read_text(encoding="utf-8", errors="replace").splitlines()
        if sha256(app_path) == sha256(core_path):
            identical += 1
            print(f"{'IDENTIQUE':<10} {0:>6}  {len(a):>6} {len(b):>6}  {key}")
        else:
            divergent += 1
            diff = sum(
                1
                for ln in difflib.unified_diff(a, b, lineterm="", n=0)
                if ln[:1] in "+-" and ln[:3] not in ("+++", "---")
            )
            print(f"{'DIVERGENT':<10} {diff:>6}  {len(a):>6} {len(b):>6}  {key}")
    real = [d for d in dups if not d[3]]
    print(
        f"\nTotal: {len(real)} doublon(s) réel(s) "
        f"({identical} identique(s), {divergent} divergent(s)), "
        f"{len(dups) - len(real)} shim(s) de ré-export (état cible)."
    )
    return 0


def cmd_update_baseline(dups) -> int:
    keys = sorted(key for key, _, _, shim in dups if not shim)
    header = (
        "# Baseline de la duplication core<->apps Flutter (issue #7652).\n"
        "# Chaque ligne = un fichier d'app dont le chemin relatif existe aussi dans\n"
        "# leopardo_core/lib/features (dette FIGÉE — toute NOUVELLE entrée est refusée\n"
        "# par dev-hub/tools/check-mobile-core-duplication.py).\n"
        "# La dé-duplication (issue #7652) doit faire MAIGRIR cette liste, jamais grossir.\n"
        "# Régénérer : python3 dev-hub/tools/check-mobile-core-duplication.py --update-baseline\n"
    )
    BASELINE_PATH.write_text(header + "\n".join(keys) + "\n", encoding="utf-8")
    print(f"Baseline régénérée: {BASELINE_PATH.relative_to(REPO_ROOT)} ({len(keys)} entrées)")
    return 0


def cmd_guard(dups) -> int:
    baseline = load_baseline()
    current = {key for key, _, _, shim in dups if not shim}
    new = sorted(current - baseline)
    resolved = sorted(baseline - current)
    for key in resolved:
        print(
            f"::warning::Entrée de baseline résolue (à retirer via --update-baseline) : {key}"
        )
    if new:
        print(
            "ERREUR — nouvelle duplication leopardo_core -> app détectée (issue #7652).\n"
            "Règle (front/mobile_apps/README.md) : toute modification partagée va dans\n"
            "leopardo_core ; l'app importe le package core (ou un shim de ré-export\n"
            "`export 'package:leopardo_core/...';`, pattern #5279). Ne copiez pas le fichier.\n"
        )
        for key in new:
            print(f"  NOUVEAU DOUBLON: front/mobile_apps/{key}")
        return 1
    real = len(current)
    print(
        f"OK — aucune nouvelle duplication core->app. Dette figée: {real} fichier(s) "
        f"en baseline, {len(dups) - real} shim(s) de ré-export."
    )
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--report", action="store_true", help="inventaire des diffs core<->apps")
    parser.add_argument(
        "--update-baseline", action="store_true", help="régénère la baseline depuis l'état courant"
    )
    args = parser.parse_args()
    dups = collect_duplicates()
    if args.report:
        return cmd_report(dups)
    if args.update_baseline:
        return cmd_update_baseline(dups)
    return cmd_guard(dups)


if __name__ == "__main__":
    sys.exit(main())
