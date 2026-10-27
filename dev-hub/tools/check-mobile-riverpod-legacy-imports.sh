#!/usr/bin/env bash
#
# Garde CI — imports Riverpod 3 « legacy » (issue #8754).
#
# Contexte : Riverpod 3 a RETIRÉ `StateNotifier`, `StateNotifierProvider`,
# `AsyncStateNotifier` et `StateProvider` du point d'entrée principal
# `package:flutter_riverpod/flutter_riverpod.dart`. Ces symboles vivent
# désormais dans `package:flutter_riverpod/legacy.dart`.
#
# Conséquence observée en production CI : le smoke build mobile
# (`Mobile Smoke Build`) échouait sur TOUTES les PR — y compris docs-only —
# avec « Type 'StateNotifier' not found », parce que deux fichiers de la
# messagerie (#8754) utilisaient l'API legacy sans l'import. Le workflow
# n'étant pas requis au merge, le rouge est resté en place et a été ignoré :
# exactement le travers dénoncé par la décision D-8397.
#
# Pourquoi un garde statique et pas seulement le build ? Le build mobile prend
# ~10 min et ne tourne pas sur les PR backend (cf. en-tête de mobile-smoke.yml).
# Ce garde s'exécute en < 1 s, sur tous les chemins, et pointe la ligne exacte
# au lieu d'un « kernel_snapshot_program failed » noyé dans 48 erreurs en
# cascade.
#
# CAS COUVERT (faux négatif évité) : un `export` explicite est une ré-exportation
# légitime — `export 'legacy.dart' show StateNotifier;` — et ne doit pas être
# signalé. Seul l'USAGE du symbole sans import compte.
#
# Usage : bash dev-hub/tools/check-mobile-riverpod-legacy-imports.sh [racine]
# Tests : bash dev-hub/tools/tests/check-mobile-riverpod-legacy-imports.test.sh
# Exit 0 = conforme ; exit 1 = violation(s).
#
set -euo pipefail

ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
MOBILE="${ROOT}/front/mobile_apps"

if [[ ! -d "${MOBILE}" ]]; then
  echo "::error::Répertoire mobile introuvable : ${MOBILE}" >&2
  exit 1
fi

python3 - "${MOBILE}" << 'PYEOF'
import re
import sys
from pathlib import Path

mobile = Path(sys.argv[1])

# Symboles déplacés vers legacy.dart en Riverpod 3.
LEGACY_SYMBOLS = (
    "StateNotifier",
    "StateNotifierProvider",
    "AsyncStateNotifier",
    "AsyncStateNotifierProvider",
    "StateProvider",
)

# L'import qui les rend disponibles.
LEGACY_IMPORT = re.compile(
    r"""^\s*import\s+['"]package:(flutter_riverpod|riverpod)/legacy\.dart['"]""",
    re.MULTILINE,
)

# Usage du symbole. On exclut les commentaires (`//`) pour qu'une explication en
# prose (« StateNotifier a été retiré… ») ne déclenche pas le garde.
USAGE = re.compile(r"\b(" + "|".join(LEGACY_SYMBOLS) + r")\b")

# Un `export` de compatibilité est légitime : ce n'est pas un usage local.
EXPORT = re.compile(
    r"""^\s*export\s+['"]package:(flutter_riverpod|riverpod)/legacy\.dart['"]""",
    re.MULTILINE,
)


def strip_comments(source: str) -> str:
    """Retire commentaires de ligne et de bloc, en préservant les numéros de ligne."""
    out = []
    in_block = False
    for line in source.splitlines():
        stripped = line.lstrip()
        if in_block:
            if "*/" in stripped:
                in_block = False
                stripped = stripped.split("*/", 1)[1]
            else:
                out.append("")
                continue
        if stripped.startswith("//"):
            out.append("")
            continue
        if "/*" in stripped:
            before, _, after = stripped.partition("/*")
            if "*/" in after:
                out.append(before + after.split("*/", 1)[1])
                continue
            in_block = True
            out.append(before)
            continue
        out.append(line)
    return "\n".join(out)


violations = []
checked = 0

for dart_file in sorted(mobile.rglob("*.dart")):
    if ".dart_tool" in dart_file.parts or "build" in dart_file.parts:
        continue

    try:
        source = dart_file.read_text(encoding="utf-8")
    except (UnicodeDecodeError, OSError):
        continue

    code = strip_comments(source)

    # Fichier de ré-exportation : légitime, on passe.
    if EXPORT.search(code):
        continue

    has_import = bool(LEGACY_IMPORT.search(code))
    checked += 1

    for lineno, line in enumerate(code.splitlines(), start=1):
        if EXPORT.match(line):
            continue
        match = USAGE.search(line)
        if not match:
            continue
        # `StateProvider` est un cas particulier : c'est aussi un nom de classe
        # métier plausible ailleurs, mais dans `front/mobile_apps` la convention
        # est explicite — on signale quand même, le garde reste conservateur sur
        # les autres symboles et strict sur celui-ci.
        if not has_import:
            # `mobile` = <root>/front/mobile_apps → remonter de deux niveaux.
            rel = dart_file.relative_to(mobile.parent.parent)
            violations.append((str(rel), lineno, match.group(1), line.strip()))

if violations:
    print(
        f"::error::{len(violations)} usage(s) Riverpod legacy sans import "
        f"'package:flutter_riverpod/legacy.dart' (issue #8754).",
        file=sys.stderr,
    )
    print(
        "Riverpod 3 a déplacé StateNotifier/StateNotifierProvider/StateProvider "
        "hors du point d'entrée principal.\n",
        file=sys.stderr,
    )
    for rel, lineno, symbol, line in violations:
        print(f"  {rel}:{lineno}: '{symbol}' utilisé sans import legacy", file=sys.stderr)
        print(f"      {line}", file=sys.stderr)
        print(
            "      → ajouter : import 'package:flutter_riverpod/legacy.dart';",
            file=sys.stderr,
        )
    sys.exit(1)

print(
    f"✅ Imports Riverpod legacy cohérents — {checked} fichier(s) Dart vérifié(s), "
    "aucun usage de l'API legacy sans import."
)
PYEOF
