#!/usr/bin/env bash
# Auto-test de check-notification-emitter-diff.sh (issue #7481).
# Même convention que check-governance-mojibake-test.ps1 : une garde non testée
# est une garde dont on ignore si elle garde quoi que ce soit.
set -uo pipefail
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
GUARD="$REPO_ROOT/dev-hub/tools/check-notification-emitter-diff.sh"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
fail=0

run_case() {
  local name="$1" expected_rc="$2" diff="$3"
  printf '%s\n' "$diff" > "$TMP/d.txt"
  rc=0; GUARD_DIFF_FILE="$TMP/d.txt" bash "$GUARD" >/dev/null 2>&1 || rc=1
  if [[ "$rc" == "$expected_rc" ]]; then echo "  ✓ $name"
  else echo "  ✗ $name (attendu rc=$expected_rc, obtenu rc=$rc)"; fail=1; fi
}

L='use App\Modules\Notification\Domain\Models\Notification;'

run_case "refuse un NOUVEAU fichier qui importe le modele historique" 1 \
"diff --git a/api/app/Modules/X/S.php b/api/app/Modules/X/S.php
--- /dev/null
+++ b/api/app/Modules/X/S.php
@@ -0,0 +1,3 @@
+<?php
+${L}"

run_case "autorise un nouveau fichier sans import historique" 0 \
"diff --git a/api/app/Modules/X/S.php b/api/app/Modules/X/S.php
--- /dev/null
+++ b/api/app/Modules/X/S.php
@@ -0,0 +1,2 @@
+<?php
+use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;"

run_case "autorise une migration qui importe le modele" 0 \
"diff --git a/api/database/migrations/tenant/m.php b/api/database/migrations/tenant/m.php
--- /dev/null
+++ b/api/database/migrations/tenant/m.php
@@ -0,0 +1,2 @@
+<?php
+${L}"

run_case "autorise un nouveau TEST" 0 \
"diff --git a/api/tests/Feature/Notification/T.php b/api/tests/Feature/Notification/T.php
--- /dev/null
+++ b/api/tests/Feature/Notification/T.php
@@ -0,0 +1,2 @@
+<?php
+${L}"

run_case "autorise le modele lui-meme" 0 \
"diff --git a/api/app/Modules/Notification/Domain/Models/Notification.php b/api/app/Modules/Notification/Domain/Models/Notification.php
--- a/api/app/Modules/Notification/Domain/Models/Notification.php
+++ b/api/app/Modules/Notification/Domain/Models/Notification.php
@@ -1,1 +1,2 @@
 <?php
+${L}"

# Le coeur du diff-scoping : un fichier EXISTANT qui importe deja le modele et
# dont on ne touche pas l'import ne doit PAS etre refuse.
run_case "n'accuse pas un fichier existant dont l'import n'est pas ajoute" 0 \
"diff --git a/api/app/Modules/Notification/Infrastructure/Services/CommunicationService.php b/api/app/Modules/Notification/Infrastructure/Services/CommunicationService.php
--- a/api/app/Modules/Notification/Infrastructure/Services/CommunicationService.php
+++ b/api/app/Modules/Notification/Infrastructure/Services/CommunicationService.php
@@ -10,2 +10,3 @@
-    // ancien commentaire
+    // nouveau commentaire (les lignes de contexte ne comptent pas)
+    \$x = 1;"

# ── La garde doit être INCONCLUANTE, pas rassurante ──
# Un faux négatif est le pire échec d'une garde : elle rassure à tort. Une
# révision introuvable doit donner rc=2, jamais rc=0.
check_rc2() {
  local name="$1" b="$2" h="$3"
  rc=0; bash "$GUARD" "$b" "$h" >/dev/null 2>&1; rc=$?
  if [[ "$rc" == "2" ]]; then echo "  ✓ $name"
  else echo "  ✗ $name (attendu rc=2, obtenu rc=$rc)"; fail=1; fi
}
check_rc2 "refuse de conclure si la révision de base est introuvable" "deadbeefdeadbeefdeadbeefdeadbeefdeadbeef" "HEAD"
check_rc2 "refuse de conclure si la révision de tête est introuvable" "HEAD" "deadbeefdeadbeefdeadbeefdeadbeefdeadbeef"
check_rc2 "refuse de conclure sans argument" "" ""

if [[ "$fail" == "0" ]]; then echo "Auto-test de la garde ADR-0013 : OK (9 cas)."; exit 0; fi
echo "Auto-test de la garde ADR-0013 : ÉCHEC." >&2; exit 1
