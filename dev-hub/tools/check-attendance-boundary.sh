#!/usr/bin/env bash
#
# check-attendance-boundary.sh — Garde CI « frontière Attendance ↔ Payroll »
# (ATT-001, issue #6760).
#
# Attendance est le bounded context propriétaire des événements de présence.
# Payroll CONSOMME la projection validée `AttendanceLog` — il ne doit pas
# dépendre des adaptateurs biométriques (KioskAttendanceService, Zkteco*,
# BiometricEnrollment*, FaceVerification*, AttendanceKiosk, ...).
#
# Règles :
#   1. Zéro import `App\Modules\Payroll\*` depuis le module Attendance.
#   2. Depuis Payroll, le SEUL import autorisé depuis Attendance est
#      `App\Modules\Attendance\Domain\Models\AttendanceLog` (projection de
#      paie). Tout autre import (service, adaptateur, contrôleur, modèle
#      kiosque, enrôlement biométrique) est une violation.
#
# Note : le garde module-isolation (#5584) tolère la paire
# `Modules/Payroll -> Modules/Attendance` (héritage) ; cette garde affine la
# frontière au niveau de la classe pour empêcher la fuite vers l'intérieur
# biométrique du module. Ne PAS contourner en ajoutant une exception : si
# Payroll a besoin d'un nouveau contrat, il passe par un événement versionné
# (ATT-003, #6768) ou un contrat du domaine.
#
# Usage : dev-hub/tools/check-attendance-boundary.sh [api_dir]
# Exit 1 si une violation est détectée.

set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

violations=0

report() {
  echo "::error::check-attendance-boundary: $1"
  violations=$((violations + 1))
}

# --- Règle 1 : Attendance ne dépend jamais de Payroll -------------------------
while IFS= read -r file; do
  while IFS=: read -r _ line _; do
    report "Attendance ne doit pas importer Payroll ($file:$line)"
  done < <(grep -n "use App\\\\Modules\\\\Payroll\\\\" "$file" || true)
done < <(find "$API_DIR/app/Modules/Attendance" -name '*.php' -type f)

# --- Règle 2 : Payroll ne lit que la projection AttendanceLog ----------------
ALLOWED_PROJECTION='use App\Modules\Attendance\Domain\Models\AttendanceLog;'
while IFS= read -r file; do
  while IFS= read -r line; do
    report "Payroll importe un élément interne de Attendance non autorisé ($file) — seule la projection AttendanceLog est consommable ; passer par un événement versionné (ATT-003) ou un contrat du domaine."
  done < <(grep -nF "use App\Modules\Attendance\\" "$file" | grep -vF "$ALLOWED_PROJECTION" || true)
done < <(find "$API_DIR/app/Modules/Payroll" -name '*.php' -type f)

if [[ "$violations" -gt 0 ]]; then
  echo "::error::check-attendance-boundary: $violations violation(s) de frontière Attendance/Payroll."
  exit 1
fi

echo "check-attendance-boundary: OK — frontière Attendance ↔ Payroll respectée."
