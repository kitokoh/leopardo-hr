#!/usr/bin/env bash
# ============================================================
# check-admin-action-labels-test.sh — Auto-test de la garde
# « actions de ligne & i18n » (issue #7434).
#
# Cas couverts :
#   1. libellé d'action en dur dans un template  → rouge ;
#   2. bouton icône seule sans aria-label        → rouge ;
#   3. RowActionButton sans :label               → rouge ;
#   4. RowActionButton avec :label               → vert ;
#   5. bouton texte interpolé (t('…'))           → vert ;
#   6. libellé en dur hors template (script)     → vert (pas de faux positif).
#
# Usage : bash dev-hub/tools/check-admin-action-labels-test.sh
# ============================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-admin-action-labels.py"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/front/admin-dashboard/src/components"

write_view() {
  cat > "$TMP/front/admin-dashboard/src/components/X.vue" <<<"$1"
}

expect_fail() {
  if python3 "$GUARD" "$TMP" >/dev/null 2>&1; then
    echo "FAIL : violation non détectée (cas $1)" >&2
    exit 1
  fi
  echo "ok: violation détectée ($1)"
}

expect_pass() {
  if ! python3 "$GUARD" "$TMP" >/dev/null 2>&1; then
    echo "FAIL : faux positif (cas $1)" >&2
    exit 1
  fi
  echo "ok: conforme accepté ($1)"
}

# --- Cas 1 : en-tête de table en dur → rouge
write_view '<template>
  <table>
    <thead><tr><th>Actions</th></tr></thead>
  </table>
</template>'
expect_fail "libellé « Actions » en dur"

# --- Cas 2 : bouton icône seule sans aria-label dans une cellule d'actions → rouge
write_view '<template>
  <DataTable>
    <template #row-actions="{ row }">
      <button @click="remove(row)"><TrashIcon class="h-4 w-4" /></button>
    </template>
  </DataTable>
</template>'
expect_fail "icône seule sans aria-label"

# --- Cas 3 : RowActionButton sans :label → rouge
write_view '<template>
  <DataTable>
    <template #row-actions="{ row }">
      <RowActionButton :icon="TrashIcon" @click="remove(row)" />
    </template>
  </DataTable>
</template>'
expect_fail "RowActionButton sans :label"

# --- Cas 4 : convention respectée → vert
write_view '<template>
  <DataTable>
    <template #row-actions="{ row }">
      <RowActionButton :icon="TrashIcon" tone="danger" :label="t(\"common.delete\", \"Supprimer\")" @click="remove(row)" />
    </template>
  </DataTable>
</template>
<script setup>
import RowActionButton from "@/components/common/RowActionButton.vue"
import { TrashIcon } from "@heroicons/vue/24/outline"
</script>'
expect_pass "RowActionButton avec :label"

# --- Cas 5 : bouton texte interpolé → vert
write_view '<template>
  <DataTable>
    <template #row-actions="{ row }">
      <button @click="remove(row)">{{ t("common.delete", "Supprimer") }}</button>
    </template>
  </DataTable>
</template>'
expect_pass "bouton texte i18n"

# --- Cas 6 : occurrence hors template → vert
write_view '<template><div>{{ t("common.actions", "Actions") }}</div></template>
<script setup>
const label = "Actions"
</script>'
expect_pass "hors template ignoré"

echo "PASS : check-admin-action-labels-test.sh"
