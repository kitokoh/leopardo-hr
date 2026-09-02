#!/usr/bin/env bash
#
# check-ai-vendor-boundary.sh — Garde CI « frontière SDK fournisseurs IA »
# (QLT-003, issue #6777).
#
# Les moteurs biométriques (visage, empreinte) et d'OCR sont branchés derrière
# des contrats (`App\Core\AI`, BIO-001 #6762, AI-001 #6770), jamais importés
# en dur par les modules métier — règle R-5 de la cartographie du pointage
# (`docs/attendance/CARTOGRAPHIE_POINTAGE.md` §7).
#
# Règles :
#   1. Zéro import direct d'un SDK fournisseur (biométrie/visage/OCR) depuis
#      Attendance, Payroll ou FuelStation : on scanne les lignes `use ...;`
#      et on lève toute référence à un SDK connu (ZKTeco, Neurotechnology,
#      IDEMIA, Luxand, FaceSDK, Cognitec, Megvii, SenseTime, digitemp,
#      `sdk.*biometric`, ...). Approche whitelist : seules les lignes hors
#      namespace `App\` sont inspectées — les classes métier internes
#      (ZktecoDevice, KioskAttendanceService, ...) vivent sous `App\` et ne
#      sont jamais un SDK fournisseur.
#   2. Exception unique : les adaptateurs sous
#      `api/app/Core/AI/Infrastructure/Adapters/` sont le SEUL endroit légal
#      pour brancher un SDK — ils ne sont PAS scannés.
#   3. Le reste de `Core/AI` n'importe aucun SDK fournisseur non plus.
#   4. `Core/AI` n'importe jamais rien depuis `Modules/*` : le Core ne dépend
#      pas des modules métier — ce sont les modules qui consomment les
#      contrats `Core\AI` (injection par configuration, voir
#      `AttendanceServiceProvider` pour FaceVerificationPort).
#
# Ne PAS contourner en ajoutant une exception ici : si un module a besoin
# d'un nouveau moteur IA, il passe par un contrat `Core/AI` + un adaptateur
# sous `Core/AI/Infrastructure/Adapters/` résolu par `config/ai.php`.
#
# Usage : dev-hub/tools/check-ai-vendor-boundary.sh [api_dir]
# Exit 1 si une violation est détectée.

set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

violations=0

report() {
  echo "::error::check-ai-vendor-boundary: $1"
  violations=$((violations + 1))
}

# ── Prérequis : les modules protégés doivent exister -------------------------
for module in Attendance Payroll FuelStation; do
  if [[ ! -d "$API_DIR/app/Modules/$module" ]]; then
    echo "::error::check-ai-vendor-boundary: module '$API_DIR/app/Modules/$module' introuvable — le périmètre de la garde a changé (QLT-003 #6777)."
    exit 1
  fi
done
if [[ ! -d "$API_DIR/app/Core/AI" ]]; then
  echo "::error::check-ai-vendor-boundary: 'Core/AI' introuvable sous $API_DIR/app — contrats BIO-001 #6762 / AI-001 #6770 absents (QLT-003 #6777)."
  exit 1
fi

# ── Patterns SDK fournisseur (biométrie visage/empreinte + OCR) --------------
# Insensible à la casse : un SDK peut déclarer `ZKTeco\...`, `zkteco\...`,
# `FaceSDK\...`, `BiometricSdk\...`, etc. Les classes métier internes ne
# matchent pas : elles sont exclues plus bas par le filtre « hors namespace
# App\ ». Étendre cette liste à l'intégration d'un NOUVEAU fournisseur
# (whitelist — la liste n'est pas exhaustive par nature).
VENDOR_PATTERNS='ZKTeco|Neurotechnology|IDEMIA|Luxand|FaceSDK|Cognitec|Megvii|SenseTime|zkteco|digitemp|sdk.*biometric|biometric.*sdk'

# --- Règle 1 : modules métier → zéro SDK fournisseur -------------------------
# Seules les lignes `use ...;` hors `App\` sont inspectées (whitelist) : un
# SDK fournisseur ne peut pas vivre sous le namespace applicatif `App\`.
while IFS= read -r file; do
  while IFS= read -r line; do
    report "import direct de SDK fournisseur biométrique/visage/OCR ($file:$line) — brancher le moteur derrière un contrat Core\\AI + adaptateur (règle R-5, CARTOGRAPHIE_POINTAGE.md §7, QLT-003 #6777)."
  done < <(grep -nE '^[[:space:]]*use ' "$file" \
    | grep -vE '^[0-9]+:[[:space:]]*use App\\' \
    | grep -Ei "$VENDOR_PATTERNS" || true)
done < <(find "$API_DIR/app/Modules/Attendance" "$API_DIR/app/Modules/Payroll" "$API_DIR/app/Modules/FuelStation" -name '*.php' -type f)

# --- Règle 2 : Core/AI (hors adaptateurs) → zéro SDK fournisseur -------------
# Les adaptateurs `Infrastructure/Adapters/` sont l'endroit légal (non scanné).
while IFS= read -r file; do
  while IFS= read -r line; do
    report "import direct de SDK fournisseur hors du dossier Adapters ($file:$line) — les SDK ne s'importent que dans Core/AI/Infrastructure/Adapters (QLT-003 #6777)."
  done < <(grep -nE '^[[:space:]]*use ' "$file" \
    | grep -vE '^[0-9]+:[[:space:]]*use App\\' \
    | grep -Ei "$VENDOR_PATTERNS" || true)
done < <(find "$API_DIR/app/Core/AI" -name '*.php' -type f -not -path '*/Infrastructure/Adapters/*')

# --- Règle 3 : Core/AI ne dépend jamais des modules métier --------------------
while IFS= read -r file; do
  while IFS=: read -r line _; do
    report "Core/AI ne doit pas importer Modules/* ($file:$line) — le Core ne dépend pas des modules métier ; les modules consomment les contrats Core\\AI (QLT-003 #6777)."
  done < <(grep -n "use App\\\\Modules\\\\" "$file" || true)
done < <(find "$API_DIR/app/Core/AI" -name '*.php' -type f)

if [[ "$violations" -gt 0 ]]; then
  echo "::error::check-ai-vendor-boundary: $violations violation(s) de frontière SDK fournisseur (QLT-003 #6777)."
  exit 1
fi

echo "check-ai-vendor-boundary: OK — frontière SDK fournisseur respectée (modules → contrats Core\\AI, adaptateurs uniquement)."
