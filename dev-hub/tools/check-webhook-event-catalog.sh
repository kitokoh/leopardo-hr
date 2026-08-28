#!/usr/bin/env bash
# check-webhook-event-catalog.sh
# Issue #5744 (CRM PRE) : garde CI du catalogue d'événements webhook sortants.
#
# Le contrat partenaire (docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md §
# Webhooks) expose un catalogue d'événements. Ce garde empêche la DÉRIVE entre
# ce qui est ANNONCÉ et ce qui est réellement ÉMIS :
#
#   1. Tout événement annoncé (WebhookController::AVAILABLE_EVENTS) doit être
#      émis (WebhookListener::EVENT_NAMES) OU listé dans
#      webhook-event-catalog-allowlist.txt avec une justification
#      (événement planifié, test ping).
#   2. Tout événement émis doit être annoncé (un événement non documenté est
#      un contrat implicite — le partenaire ne peut pas s'y abonner).
#   3. Les noms d'événements suivent `^[a-z]+\.[a-z_]+$` (stable, versionné
#      via event_version dans l'enveloppe — docs/api/VERSIONING.md § 5).
#
# Un changement incompatible du catalogue (renommage, suppression, ajout non
# documenté) fait échouer la CI → le contrat ne peut pas dériver silencieusement.
#
# Usage :
#   check-webhook-event-catalog.sh [api_dir]            # scan (CI)
#   check-webhook-event-catalog.sh [api_dir] --self-test # auto-test
# Exit : 0 = OK, 1 = violation(s), 2 = usage/erreur
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ALLOWLIST="${SCRIPT_DIR}/webhook-event-catalog-allowlist.txt"
API_DIR="${1:-api}"
SELF_TEST=0
[[ "${2:-}" == "--self-test" ]] && SELF_TEST=1

CONTROLLER="${API_DIR}/app/Modules/Billing/Interfaces/Api/V1/WebhookController.php"
LISTENER="${API_DIR}/app/Listeners/WebhookListener.php"

# ── Mode auto-test ------------------------------------------------------------
if [[ "$SELF_TEST" -eq 1 ]]; then
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT

  mkdir -p "$TMP/app/Modules/Billing/Interfaces/Api/V1" "$TMP/app/Listeners"

  # Fixture : événement annoncé jamais émis + nom invalide → doit échouer
  cat > "$TMP/app/Modules/Billing/Interfaces/Api/V1/WebhookController.php" << 'PHP'
<?php
public const AVAILABLE_EVENTS = [
    'employee.created',
    'phantom.event',
    'Bad Event Name',
];
PHP
  cat > "$TMP/app/Listeners/WebhookListener.php" << 'PHP'
<?php
private const EVENT_NAMES = [
    EmployeeCreated::class => 'employee.created',
];
PHP

  set +e
  bash "$0" "$TMP" > /dev/null 2>&1
  rc=$?
  set -e
  if [[ "$rc" -ne 1 ]]; then
    echo "❌ self-test : le catalogue dérivant aurait dû échouer (exit 1), obtenu ${rc}."
    exit 1
  fi
  echo "✅ self-test 1/2 : phantom event + nom invalide détectés (exit 1)."

  # Fixture propre : annoncé == émis → doit passer
  cat > "$TMP/app/Modules/Billing/Interfaces/Api/V1/WebhookController.php" << 'PHP'
<?php
public const AVAILABLE_EVENTS = [
    'employee.created',
    'employee.departed',
];
PHP
  cat > "$TMP/app/Listeners/WebhookListener.php" << 'PHP'
<?php
private const EVENT_NAMES = [
    EmployeeCreated::class => 'employee.created',
    EmployeeDeparted::class => 'employee.departed',
];
PHP

  set +e
  bash "$0" "$TMP" > /dev/null 2>&1
  rc=$?
  set -e
  if [[ "$rc" -ne 0 ]]; then
    echo "❌ self-test : le catalogue cohérent aurait dû passer (exit 0), obtenu ${rc}."
    exit 1
  fi
  echo "✅ self-test 2/2 : catalogue cohérent accepté (exit 0)."
  exit 0
fi

# ── Mode scan -----------------------------------------------------------------
if [[ ! -f "$CONTROLLER" || ! -f "$LISTENER" ]]; then
  echo "❌ Fichiers webhook introuvables ($CONTROLLER / $LISTENER)." >&2
  exit 2
fi

# Extraire les noms d'événements (chaînes 'x.y' dans les consts)
advertised=$(
  sed -n '/AVAILABLE_EVENTS = \[/,/^\];/p' "$CONTROLLER" \
    | grep -oE "'[a-z]+\.[a-z_]+'" | tr -d "'" | sort -u
)
emitted=$(
  sed -n '/EVENT_NAMES = \[/,/^\];/p' "$LISTENER" \
    | grep -oE "'[a-z]+\.[a-z_]+'" | tr -d "'" | sort -u
)

# Allowlist : événements annoncés non encore émis, avec justification
declare -A ALLOWED
while IFS= read -r line; do
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
  name="${line%%|*}"
  name="${name//[[:space:]]/}"
  [[ -n "$name" ]] && ALLOWED["$name"]=1
done < "$ALLOWLIST"

ERRORS=0
REPORT=""

# Règle 1 : annoncé mais ni émis ni exempté (contrat fantôme)
while IFS= read -r ev; do
  [[ -z "$ev" ]] && continue
  if ! grep -qxF "$ev" <<< "$emitted" && [[ -z "${ALLOWED[$ev]:-}" ]]; then
    ERRORS=$((ERRORS + 1))
    REPORT="${REPORT}  ❌  annoncé mais jamais émis ni exempté : ${ev}
"
  fi
done <<< "$advertised"

# Règle 2 : émis mais non annoncé (contrat implicite)
while IFS= read -r ev; do
  [[ -z "$ev" ]] && continue
  if ! grep -qxF "$ev" <<< "$advertised" && [[ -z "${ALLOWED[$ev]:-}" ]]; then
    ERRORS=$((ERRORS + 1))
    REPORT="${REPORT}  ❌  émis mais non annoncé dans AVAILABLE_EVENTS : ${ev}
"
  fi
done <<< "$emitted"

# Règle 3 : format du nom
while IFS= read -r ev; do
  [[ -z "$ev" ]] && continue
  if ! grep -qE '^[a-z]+\.[a-z_]+$' <<< "$ev"; then
    ERRORS=$((ERRORS + 1))
    REPORT="${REPORT}  ❌  nom d'événement invalide (attendu ^[a-z]+\\.[a-z_]+$) : ${ev}
"
  fi
done <<< "$(printf '%s\n%s' "$advertised" "$emitted" | sort -u)"

if [[ "$ERRORS" -eq 0 ]]; then
  echo "✅ Webhook event catalog OK ($(wc -l <<< "$advertised") annoncés, $(wc -l <<< "$emitted") émis)."
  exit 0
fi

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  WEBHOOK EVENT CATALOG GUARD — dérive de contrat (issue #5744)"
echo "══════════════════════════════════════════════════════════════"
echo ""
printf "%b" "$REPORT"
echo ""
echo "  Fix : aligner AVAILABLE_EVENTS ↔ WebhookListener::EVENT_NAMES, ou"
echo "  documenter l'événement planifié dans"
echo "  dev-hub/tools/webhook-event-catalog-allowlist.txt (avec justification)."
echo "  Les noms suivent ^[a-z]+\\.[a-z_]+$ ; la version vit dans"
echo "  l'enveloppe (event_version) — docs/api/VERSIONING.md § 5."
echo ""
exit 1
