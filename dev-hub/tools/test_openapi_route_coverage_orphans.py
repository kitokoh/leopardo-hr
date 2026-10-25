#!/usr/bin/env python3
"""Tests du garde de couverture OpenAPI — entrées ORPHELINES (#8719).

Ces tests ne reformulent pas l'implémentation : ils construisent de vraies
entrées d'allowlist et vérifient que le garde les classe correctement, en
repartant du script réel (import du fichier, pas d'une copie).

Exécution : python3 dev-hub/tools/test_openapi_route_coverage_orphans.py
"""
from __future__ import annotations

import importlib.util
import sys
from pathlib import Path

TOOLS = Path(__file__).resolve().parent
SCRIPT = TOOLS / "check-openapi-route-coverage.py"


def load_module():
    spec = importlib.util.spec_from_file_location("cov_guard", SCRIPT)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


cov = load_module()

failures: list[str] = []
checks = 0


def check(label: str, condition: bool, detail: str = "") -> None:
    global checks
    checks += 1
    if condition:
        print(f"  PASS  {label}")
    else:
        print(f"  FAIL  {label}{(' — ' + detail) if detail else ''}")
        failures.append(label)


def build_route_ops() -> set[tuple[str, str]]:
    """Reproduit l'ensemble des formes routées — comme le fait main()."""
    ops: set[tuple[str, str]] = set()
    for r in cov.parse_routes():
        ops.add((r["method"], r["path"]))
        canonical = cov.canonical_spec_path(r["path"])
        if canonical is not None:
            ops.add((r["method"], canonical))
        spec = cov.spec_form(r["path"])
        if spec is not None:
            ops.add((r["method"], spec))
    ops |= cov.parse_routes_accurate()
    return ops


def is_orphan(entry: str, route_ops: set[tuple[str, str]]) -> bool:
    """Même règle que le garde : orpheline si aucune forme ne matche."""
    parts = entry.split(" ", 1)
    if len(parts) != 2:
        return False
    method, path = parts[0].lower(), parts[1]
    forms = {path}
    canonical = cov.canonical_spec_path(path)
    if canonical is not None:
        forms.add(canonical)
    spec = cov.spec_form(path)
    if spec is not None:
        forms.add(spec)
    return not any((method, form) in route_ops for form in forms)


print("Test 1 — une route réelle n'est PAS orpheline")
route_ops = build_route_ops()
real_route = next(
    r for r in cov.parse_routes() if not any(ch.isupper() for ch in r["path"])
)
real_entry = f"{real_route['method'].upper()} {real_route['path']}"
check(
    f"route déclarée {real_entry} reconnue",
    not is_orphan(real_entry, route_ops),
)

print("\nTest 2 — un chemin inexistant EST orphelin")
ghost = "DELETE /v1/ce/chemin/n-a-jamais-existe/{param}"
check(f"{ghost} détectée orpheline", is_orphan(ghost, route_ops))

print("\nTest 3 — préfixe doublé : distinguer récupérable et vraiment orphelin")
# 107 entrées `/v1/v1/...` se canonicalisent vers une route réelle (le préfixe
# doublé est neutralisé par canonical_spec_path) : elles NE SONT PAS orphelines.
recoverable = "DELETE /v1/v1/travel/carriers/{param}"
check(
    "/v1/v1/travel/carriers/{param} n'est PAS orpheline (route réelle après canonicalisation)",
    not is_orphan(recoverable, route_ops),
)
# Les autres pointent vers une surface supprimée : celles-là sont orphelines.
gone = "DELETE /v1/v1/travel/partner/advert-positions/{param}"
check(
    "/v1/v1/travel/partner/advert-positions/{param} détectée orpheline (surface supprimée)",
    is_orphan(gone, route_ops),
)

print("\nTest 4 — le gel contient exactement les orphelines constatées")
frozen = cov.load_orphan_allowlist()
check("fichier de gel non vide", len(frozen) > 0, f"{len(frozen)} entrées")
actual = [e for e in sorted(cov.load_allowlist()) if is_orphan(e, route_ops)]
check(
    "aucune orpheline hors gel",
    not [e for e in actual if e not in frozen],
    str([e for e in actual if e not in frozen][:3]),
)
check(
    "aucune entrée gelée devenue valide",
    not [e for e in sorted(frozen) if e not in actual],
    str([e for e in sorted(frozen) if e not in actual][:3]),
)

print("\nTest 5 — une entrée gelée qui redevient valide est signalée stale")
# On simule : on prend une entrée gelée et on la retire de l'ensemble des
# orphelines réelles → elle devient « stale » et doit être signalée.
sample = sorted(frozen)[0]
remaining = [e for e in actual if e != sample]
stale = [e for e in sorted(frozen) if e not in remaining]
check(
    f"{sample} signalée stale quand elle redevient valide",
    sample in stale,
)

print("\nTest 6 — le rapport JSON expose les compteurs d'orphelines")
src = SCRIPT.read_text(encoding="utf-8")
for key in (
    '"allowlist_orphan"',
    '"new_allowlist_orphan"',
    '"stale_orphan_allowlist"',
):
    check(f"rapport JSON porte {key}", key in src)

print("\nTest 7 — le garde bloque sur une NOUVELLE orpheline")
check(
    "blocage code retour 1 sur new_orphans",
    "if new_orphans:" in src and "return 1" in src.split("if new_orphans:", 1)[1][:600],
)
check(
    "blocage code retour 1 sur gel devenu stale",
    "if stale_orphan_allowlist:" in src
    and "return 1" in src.split("if stale_orphan_allowlist:", 1)[1][:600],
)

print(f"\n{'=' * 60}")
if failures:
    print(f"ÉCHEC — {len(failures)}/{checks} vérifications en échec :")
    for f in failures:
        print(f"  - {f}")
    sys.exit(1)

print(f"OK — {checks}/{checks} vérifications passées")
sys.exit(0)
