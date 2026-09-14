#!/usr/bin/env bash
# ============================================================
# check-admin-destructive-actions-test.sh — Auto-test de la garde
# « actions destructives admin » (issue #7433).
#
# Cas couverts :
#   1. `window.confirm(` dans une vue         → rouge ;
#   2. `catch {}` vide                         → rouge ;
#   3. `catch { /* commentaire */ }` seul      → rouge ;
#   4. fichier allowlisté (stockage local)     → vert ;
#   5. code conforme (ConfirmDialog + catch)   → vert ;
#   6. mention de window.confirm en commentaire→ vert (pas de faux positif).
#
# Usage : bash dev-hub/tools/check-admin-destructive-actions-test.sh
# ============================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-admin-destructive-actions.py"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/front/admin-dashboard/src/views" "$TMP/dev-hub/tools"

write_view() { # $1 = nom de fichier, $2 = contenu
  cat > "$TMP/front/admin-dashboard/src/views/$1" <<<"$2"
}

expect_fail() { # $1 = cas
  if python3 "$GUARD" "$TMP" >/dev/null 2>&1; then
    echo "FAIL : violation non détectée (cas $1)" >&2
    exit 1
  fi
  echo "ok: violation détectée ($1)"
}

expect_pass() { # $1 = cas
  if ! python3 "$GUARD" "$TMP" >/dev/null 2>&1; then
    echo "FAIL : faux positif (cas $1)" >&2
    exit 1
  fi
  echo "ok: conforme accepté ($1)"
}

# --- Cas 1 : window.confirm → rouge
write_view A.vue '<script setup>
function onDelete(row) {
  if (!window.confirm("Supprimer ?")) return
  doDelete(row)
}
</script>'
expect_fail "window.confirm"

# --- Cas 2 : catch {} vide → rouge
write_view A.vue '<script setup>
async function onDelete(row) {
  try {
    await doDelete(row)
  } catch {}
}
</script>'
expect_fail "catch vide"

# --- Cas 3 : catch avec commentaire seul → rouge
write_view A.vue '<script setup>
async function onDelete(row) {
  try {
    await doDelete(row)
  } catch {
    // best-effort
  }
}
</script>'
expect_fail "catch commentaire seul"

# --- Cas 4 : catch muet allowlisté → vert
cat > "$TMP/dev-hub/tools/admin-silent-catch-allowlist.txt" <<'EOF'
front/admin-dashboard/src/views/A.vue  # stockage indisponible
EOF
expect_pass "catch allowlisté"

# --- Cas 5 : code conforme → vert
rm -f "$TMP/dev-hub/tools/admin-silent-catch-allowlist.txt"
write_view A.vue '<script setup>
async function onDelete(row) {
  const ok = await confirmDialog.ask({ title: t("confirm.title") })
  if (!ok) return
  try {
    await doDelete(row)
  } catch (e) {
    actionError.value = errorMessage(e)
  }
}
</script>'
expect_pass "code conforme"

# --- Cas 6 : mention en commentaire → vert
write_view A.vue '<script setup>
// QA : remplace window.confirm() (non i18n).
/* window.alert interdit aussi */
const label = "Confirmer"
</script>'
expect_pass "commentaires ignorés"

echo "PASS : check-admin-destructive-actions-test.sh"
