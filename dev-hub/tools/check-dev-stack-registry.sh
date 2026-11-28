#!/usr/bin/env bash
#
# #10043 — Garde de cohérence du registre des stacks DEV.
#
# Contexte : le 2026-11-28 le watchdog dev-keepalive a alerté « stack active
# malade, aucune bascule possible ». Le registre `deploy/dev-stacks.json`
# déclarait pourtant la stack `b` au statut `active`, avec un
# `last_verified.checked_at` du 2026-10-18 — soit 41 jours plus tôt. Le
# service Render était en réalité suspendu (HTTP 503 « Service Suspended »)
# depuis des semaines, et AUCUN garde ne l'a vu : le registre mentait, et rien
# ne comparait ce mensonge à une date.
#
# Ce garde est DÉTERMINISTE (aucun appel réseau, donc utilisable en CI sans
# dépendre de la disponibilité d'un fournisseur) et vérifie la cohérence
# INTERNE du registre, plus la FRAÎCHEUR des vérifications :
#
#   R1  `active_stack` référence une stack existante.
#   R2  Les statuts appartiennent à `status_enum`.
#   R3  Une stack `ready` ou `active` porte un `api.health_url`.
#   R4  Une stack `ready` ou `active` a un `last_verified.checked_at` de moins
#       de MAX_AGE_DAYS jours. C'est la règle qui aurait attrapé #10043 : une
#       stack déclarée saine sans vérification récente est une affirmation non
#       étayée, pas un état.
#   R5  Si la stack active n'est ni `active` ni `ready` (panne documentée), son
#       `status_note` doit être non vide — une panne se documente, elle ne se
#       tait pas.
#   R6  Au plus une stack au statut `active`.
#
# Usage : dev-hub/tools/check-dev-stack-registry.sh [registre] [max_age_days]
#   registre     : défaut deploy/dev-stacks.json
#   max_age_days : défaut 14
#
# Codes de sortie : 0 = cohérent ; 1 = incohérence ; 2 = usage/fichier absent.

set -euo pipefail

REGISTRY="${1:-deploy/dev-stacks.json}"
MAX_AGE_DAYS="${2:-14}"

if [[ ! -f "$REGISTRY" ]]; then
  echo "❌ #10043 : registre introuvable ($REGISTRY)." >&2
  exit 2
fi

python3 - "$REGISTRY" "$MAX_AGE_DAYS" <<'PYEOF'
import datetime
import json
import sys

registry_path, max_age_days = sys.argv[1], int(sys.argv[2])

try:
    with open(registry_path, encoding="utf-8") as fh:
        reg = json.load(fh)
except Exception as exc:  # noqa: BLE001 - message d'erreur explicite
    print(f"❌ #10043 : {registry_path} n'est pas un JSON valide ({exc}).", file=sys.stderr)
    sys.exit(2)

stacks = reg.get("stacks") or {}
status_enum = reg.get("status_enum") or {}
active = reg.get("active_stack")
errors = []
warnings = []

# R1 — active_stack existe.
if not active:
    errors.append("R1 : `active_stack` est absent.")
elif active not in stacks:
    errors.append(f"R1 : `active_stack` = « {active} » ne référence aucune stack du registre.")

# R2 — statuts dans l'enum.
for name, stack in stacks.items():
    status = stack.get("status")
    if status not in status_enum:
        errors.append(
            f"R2 : stack « {name} » a le statut « {status} », hors de `status_enum` "
            f"({', '.join(sorted(status_enum))})."
        )

# R6 — au plus une stack `active`.
actives = [n for n, s in stacks.items() if s.get("status") == "active"]
if len(actives) > 1:
    errors.append(f"R6 : {len(actives)} stacks au statut `active` ({', '.join(actives)}) — une seule possible.")

today = datetime.date.today()

for name, stack in stacks.items():
    status = stack.get("status")
    if status not in ("ready", "active"):
        continue

    # R3 — health_url présente.
    health_url = (stack.get("api") or {}).get("health_url")
    if not health_url:
        errors.append(f"R3 : stack « {name} » est `{status}` sans `api.health_url` — invérifiable.")

    # R4 — fraîcheur de la vérification.
    checked_at = ((stack.get("last_verified") or {}).get("checked_at") or "").strip()
    if not checked_at:
        errors.append(
            f"R4 : stack « {name} » est `{status}` sans `last_verified.checked_at` — "
            "un état sain non daté n'est pas un état."
        )
        continue
    try:
        # Accepte « 2026-10-18T06:50Z » et « 2026-10-18 ».
        day = datetime.date.fromisoformat(checked_at[:10])
    except ValueError:
        errors.append(f"R4 : stack « {name} » a un `checked_at` illisible (« {checked_at} »).")
        continue
    age = (today - day).days
    if age > max_age_days:
        errors.append(
            f"R4 : stack « {name} » est déclarée `{status}` mais sa dernière vérification "
            f"date de {age} jours ({checked_at}) > {max_age_days} j — re-mesurer "
            "(`scripts/dev-stack-health.sh`) et mettre à jour `last_verified`, ou "
            "corriger le statut. C'est exactement la dérive de #10043."
        )
    elif age > max_age_days - 3:
        warnings.append(f"R4 : stack « {name} » vérifiée il y a {age} j — bientôt périmée.")

# R5 — panne de la stack active documentée.
if active in stacks:
    a_status = stacks[active].get("status")
    if a_status not in ("active", "ready"):
        note = (stacks[active].get("status_note") or "").strip()
        if not note:
            errors.append(
                f"R5 : la stack active « {active} » est `{a_status}` sans `status_note` — "
                "une panne se documente (cause, date, action), elle ne se tait pas."
            )

for w in warnings:
    print(f"⚠️  {w}")

if errors:
    print(f"❌ #10043 : registre des stacks DEV incohérent ({len(errors)} erreur(s)) :")
    for e in errors:
        print(f"   - {e}")
    print("   Fichier : deploy/dev-stacks.json — doc : docs/ops/DEV_STACK_ROTATION.md")
    sys.exit(1)

print(
    f"✅ #10043 : registre des stacks DEV cohérent "
    f"({len(stacks)} stack(s), active = « {active} », fraîcheur ≤ {max_age_days} j)."
)
PYEOF
