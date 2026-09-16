#!/usr/bin/env bash
# check-notification-emitter-diff-test.sh — auto-test de la garde #7481
# (dev-hub/tools/check-notification-emitter-diff.sh).
#
# La garde est diff-scoped : elle ne peut pas être vérifiée « en vrai » sur un
# dépôt sain (aucun diff fautif n'y existe). On la fait donc tourner sur des
# diffs FIXTURES via `GUARD_DIFF_FILE`, exactement comme le fait
# `check-i18n-diff-test.sh` pour la garde i18n.
#
# Cas couverts :
#   0. dépôt sain (diff vide)                        → VERT ;
#   1. nouvel import d'AppNotification dans api/app   → ROUGE (le défaut #7481) ;
#   2. utilisation directe (new/::/propriété)         → ROUGE ;
#   3. même import dans api/tests/**                  → VERT (la dépréciation se teste) ;
#   4. modification d'un fichier qui importait DÉJÀ le modèle, sans nouvelle ligne
#      d'import (correction de coquille)              → VERT (pas de régression par
#      fichier : c'est le point de #7482 que la garde doit respecter) ;
#   5. commentaire qui MENTIONNE AppNotification     → VERT.
#
# Usage : bash dev-hub/tools/check-notification-emitter-diff-test.sh
set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-notification-emitter-diff.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

fail() { echo "FAIL : $1" >&2; exit 1; }

run_guard_on() { # <fichier de diff>
  set +e
  OUT="$(GUARD_DIFF_FILE="$1" bash "$GUARD" 2>&1)"
  STATUS=$?
  set -e
}

expect_status() { # <statut attendu> <libellé>
  if [[ "$STATUS" -ne "$1" ]]; then
    printf '%s\n' "$OUT" >&2
    fail "$2 — statut attendu $1, obtenu $STATUS"
  fi
  echo "ok: $2 (statut $STATUS)"
}

# ── Cas 0 : diff vide ────────────────────────────────────────────────────────
: > "${TMP}/vide.diff"
run_guard_on "${TMP}/vide.diff"
expect_status 0 "diff vide = rien à examiner"

# ── Cas 1 : nouvel import en production ──────────────────────────────────────
cat > "${TMP}/app.diff" <<'DIFF'
diff --git a/api/app/Modules/Notification/Infrastructure/Services/NewSender.php b/api/app/Modules/Notification/Infrastructure/Services/NewSender.php
new file mode 100644
index 0000000..1111111
--- /dev/null
+++ b/api/app/Modules/Notification/Infrastructure/Services/NewSender.php
@@ -0,0 +1,8 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Modules\Notification\Infrastructure\Services;
+
+use App\Modules\Notification\Domain\Models\AppNotification;
DIFF
run_guard_on "${TMP}/app.diff"
expect_status 1 "nouvel import d'AppNotification dans api/app"
if ! printf '%s\n' "$OUT" | grep -q "AppNotification"; then
  fail "le message ne nomme pas le modèle déprécié"
fi

# ── Cas 2 : utilisation directe (sans import) ────────────────────────────────
cat > "${TMP}/usage.diff" <<'DIFF'
diff --git a/api/app/Modules/Notification/Application/Actions/SendNotification.php b/api/app/Modules/Notification/Application/Actions/SendNotification.php
--- a/api/app/Modules/Notification/Application/Actions/SendNotification.php
+++ b/api/app/Modules/Notification/Application/Actions/SendNotification.php
@@ -10,6 +10,7 @@
     public function handle(array $payload): void
     {
+        $row = new AppNotification($payload);
     }
DIFF
run_guard_on "${TMP}/usage.diff"
expect_status 1 "utilisation directe (new AppNotification) dans api/app"

# ── Cas 3 : le même import, mais dans les tests ──────────────────────────────
cat > "${TMP}/tests.diff" <<'DIFF'
diff --git a/api/tests/Feature/Notification/AppNotificationMigrationTest.php b/api/tests/Feature/Notification/AppNotificationMigrationTest.php
--- a/api/tests/Feature/Notification/AppNotificationMigrationTest.php
+++ b/api/tests/Feature/Notification/AppNotificationMigrationTest.php
@@ -5,6 +5,7 @@
 namespace Tests\Feature\Notification;
 
+use App\Modules\Notification\Domain\Models\AppNotification;
 
 class AppNotificationMigrationTest extends TestCase
DIFF
run_guard_on "${TMP}/tests.diff"
expect_status 0 "import d'AppNotification dans api/tests (la dépréciation se teste)"

# ── Cas 4 : correction dans un fichier qui importait déjà (aucune ligne ajoutée d'import)
cat > "${TMP}/correction.diff" <<'DIFF'
diff --git a/api/app/Listeners/NotifyTaxRateValidation.php b/api/app/Listeners/NotifyTaxRateValidation.php
--- a/api/app/Listeners/NotifyTaxRateValidation.php
+++ b/api/app/Listeners/NotifyTaxRateValidation.php
@@ -20,7 +20,7 @@
- * - Approbation/Rejet → notification in-app (AppNotification) + email
+ * - Approbation/Rejet → notification in-app (store canonique) + email
DIFF
run_guard_on "${TMP}/correction.diff"
expect_status 0 "aucune nouvelle ligne de production, même si le fichier mentionne le modèle"

# ── Cas 5 : commentaire qui mentionne le modèle ──────────────────────────────
cat > "${TMP}/commentaire.diff" <<'DIFF'
diff --git a/api/app/Modules/Notification/Infrastructure/Services/NotificationDispatcher.php b/api/app/Modules/Notification/Infrastructure/Services/NotificationDispatcher.php
--- a/api/app/Modules/Notification/Infrastructure/Services/NotificationDispatcher.php
+++ b/api/app/Modules/Notification/Infrastructure/Services/NotificationDispatcher.php
@@ -8,6 +8,7 @@
 class NotificationDispatcher
 {
+    // Historique : ce service écrivait dans AppNotification avant #7481.
     public function dispatch(): void {}
DIFF
run_guard_on "${TMP}/commentaire.diff"
expect_status 0 "un commentaire qui mentionne le modèle déprécié n'est pas une violation"

echo "PASS : check-notification-emitter-diff-test.sh"
