#!/usr/bin/env bash
# ============================================================
# check-i18n-diff-test.sh — Auto-test de la garde PA2-I18N-014
# (dev-hub/tools/check-i18n-diff.js), issue #7482.
#
# Pourquoi ce fichier existe : la garde produisait des faux positifs qui
# obligeaient à réécrire du code correct (constats de l'issue #7482, mesurés
# deux fois en session — #7302 « sortir les comparaisons == null du template »,
# lot admin #7431). Une garde qui fait réécrire du code correct pour elle est
# une garde qu'on contourne : chaque motif découvert doit devenir un cas de test
# ici, sinon la dérive recommence.
#
# Cas couverts :
#   1. diff sans aucune chaîne utilisateur (équivalent Vue/TSX des motifs
#      signalés par l'issue)                     → VERT, sans réécriture ;
#   2. `:label="'Supprimer'"` (littéral DANS une expression liée) → ROUGE ;
#   3. attribut statique porteur de texte (title/aria-label/placeholder) → ROUGE ;
#   4. littéral de script et littéral Next.js                       → ROUGE ;
#   5. les motifs techniques du cas 1 ne réapparaissent pas dans le rapport.
#
# Usage : bash dev-hub/tools/check-i18n-diff-test.sh
# ============================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-i18n-diff.js"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

fail() { echo "FAIL : $1" >&2; exit 1; }

# new_repo <nom> — dépôt git jetable avec un commit de base.
new_repo() {
  local repo="$TMP/$1"
  mkdir -p "$repo"
  git -C "$repo" init -q
  git -C "$repo" config user.email "guard-test@example.com"
  git -C "$repo" config user.name "guard-test"
  git -C "$repo" commit -q --allow-empty -m "base"
  echo "$repo"
}

# run_guard <repo> — base = premier commit, head = HEAD ; sortie sur stdout,
# code de sortie dans $GUARD_STATUS.
run_guard() {
  local repo="$1"
  local base head
  base="$(git -C "$repo" rev-list --max-parents=0 HEAD | head -n1)"
  head="$(git -C "$repo" rev-parse HEAD)"
  set +e
  OUT="$(I18N_DIFF_REPO_ROOT="$repo" node "$GUARD" "$base" "$head" 2>&1)"
  GUARD_STATUS=$?
  set -e
}

expect_clean() { # <libellé> — ne doit pas apparaître dans le rapport
  if printf '%s\n' "$OUT" | grep -qF -- "$1"; then
    printf '%s\n' "$OUT" >&2
    fail "faux positif : « $1 » signalé ($2)"
  fi
  echo "ok: non signalé — $2"
}

expect_flagged() { # <libellé> — doit apparaître dans le rapport
  if ! printf '%s\n' "$OUT" | grep -qF -- "$1"; then
    printf '%s\n' "$OUT" >&2
    fail "vrai positif manquant : « $1 » non détecté ($2)"
  fi
  echo "ok: détecté — $2"
}

# ── Cas 1 : que du code de template → VERT ───────────────────────────────────
REPO_TECH="$(new_repo technique)"
mkdir -p "$REPO_TECH/front/admin-dashboard/src/components"
# Cas 1bis — commentaires JSX/TSX : un commentaire français avec apostrophes
# n'est pas une chaîne utilisateur (constaté sur #7562 : « d'indicateur
# d'étapes » lu comme un littéral). Issue #7482 — motif ajouté comme fixture.
mkdir -p "$REPO_TECH/front/web/src/modules/vitrine/components"
cat > "$REPO_TECH/front/web/src/modules/vitrine/components/Commentaires.tsx" <<'TSX'
export function Commentaires() {
  return (
    <div>
      {/* #7489 — plus d'indicateur d'étapes : le tunnel tient en deux écrans. */}
      {/* NOTE : ceci est un commentaire, pas un libellé affiché. */}
      <span>{label}</span>
    </div>
  )
}
TSX
git -C "$REPO_TECH" add -A
git -C "$REPO_TECH" commit -q -m "commentaires JSX avec apostrophes (#7482)"

cat > "$REPO_TECH/front/admin-dashboard/src/components/PatternsTechniques.vue" <<'VUE'
<template>
  <div>
    <input v-model="form[key]" />
    <div :class="active ? 'bg-emerald-500' : 'bg-slate-100'">a</div>
    <div :class="{ 'opacity-50': row.disabled }">b</div>
    <span v-if="item.x == null">c</span>
    <span v-if="row.status === 'pending'">d</span>
    <span v-show="!isOpen">e</span>
    <span :style="{ width: '50%' }">f</span>
    <MyRow :key="`row-${row.id}`" :to="{ name: 'companies' }" />
    <Btn :disabled="row.disabled || loading" @click="openDialog(row)" />
    <template #default="{ row }"><span>{{ row.name }}</span></template>
    <!-- Dimensions d'image (Next.js) : jamais du texte utilisateur. -->
    <Image src="/blog/startup-rh.svg" alt={title} fill sizes="(min-width: 1024px) 33vw, 100vw" width={640} height={360} />
  </div>
</template>
<script setup lang="ts">
const key = 'options.0.label'
const otherKey = 'settings.billing.title'
const isEmpty = (v: unknown) => v == null
const classes = 'flex items-center'
</script>
VUE
git -C "$REPO_TECH" add -A
git -C "$REPO_TECH" commit -q -m "motifs techniques du constat #7482"

# Cas 1quater — catalogue i18n du kiosque (#7651) : i18n.js EST le mécanisme de
# localisation (catalogue inline ×4) — ses valeurs ne sont pas des chaînes en
# dur hors catalogue (même cas que vitrine-locale.ts).
mkdir -p "$REPO_TECH/front/zkteco-kiosk"
cat > "$REPO_TECH/front/zkteco-kiosk/i18n.js" <<'JS'
var CATALOG = {
  fr: {
    'admin.login.title': 'Acces administrateur',
    'admin.login.invalid': 'PIN invalide.',
  },
};
JS
git -C "$REPO_TECH" add -A
git -C "$REPO_TECH" commit -q -m "catalogue kiosk i18n.js (#7651)"
# Cas 1quinquies — données structurées JSON-LD (#7748) : les clés du
# vocabulaire schema.org ('@context', '@type'…) sont des constantes
# techniques, pas du texte utilisateur.
mkdir -p "$REPO_TECH/front/web/src/app/restaurants"
cat > "$REPO_TECH/front/web/src/app/restaurants/jsonld.tsx" <<'TSX'
export function restaurantJsonLd(name: string) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Restaurant',
    name,
    geo: { '@type': 'GeoCoordinates', latitude: 0, longitude: 0 },
  }
}
TSX
git -C "$REPO_TECH" add -A
git -C "$REPO_TECH" commit -q -m "clés JSON-LD schema.org (#7748)"
run_guard "$REPO_TECH"
if [[ "$GUARD_STATUS" -ne 0 ]]; then
  printf '%s\n' "$OUT" >&2
  fail "cas 1 : un diff sans chaîne utilisateur est refusé (code $GUARD_STATUS) — critère 1 de #7482"
fi
echo "ok: cas 1 vert (aucune réécriture de code technique exigée)"
for motif in 'form[key]' 'bg-emerald-500' 'item.x == null' 'options.0.label' 'settings.billing.title' '(min-width: 1024px) 33vw, 100vw' '@context' '@type'; do
  expect_clean "$motif" "motif technique « $motif »"
done
expect_clean "d'indicateur d'étapes" "commentaire JSX français (apostrophes) — cas #7562"
expect_clean "sizes=\"" "attribut de dimension d'image (Image sizes) — audit vitrine 2026-09-16"
expect_clean 'Acces administrateur' "valeur du catalogue i18n kiosk (#7651)"
expect_clean 'PIN invalide.' "valeur du catalogue i18n kiosk (#7651)"

# ── Cas 2 : code technique + vrais textes utilisateur → ROUGE ────────────────
REPO_TEXT="$(new_repo mixte)"
mkdir -p "$REPO_TEXT/front/admin-dashboard/src/components" "$REPO_TEXT/front/web/src/app/checkout"
cat > "$REPO_TEXT/front/admin-dashboard/src/components/TextesUtilisateur.vue" <<'VUE'
<template>
  <div>
    <input v-model="form[key]" />
    <span v-if="item.x == null">vide</span>
    <button title="Enregistrer la fiche">ok</button>
    <button aria-label="Supprimer le compte">x</button>
    <input placeholder="Nom de l'entreprise" />
    <MyDialog :label="'Champ obligatoire'" />
    <MyRow :key="`row-${row.id}`" :to="{ name: 'companies' }" />
  </div>
</template>
VUE
cat > "$REPO_TEXT/front/web/src/app/checkout/Checkout.client.tsx" <<'TSX'
export function Checkout() {
  const message = 'Votre espace est pret'
  return (
    <section className="flex items-center" aria-label="Recapitulatif du panier">
      <p>{message}</p>
    </section>
  )
}
TSX
git -C "$REPO_TEXT" add -A
git -C "$REPO_TEXT" commit -q -m "textes utilisateur + code technique"
run_guard "$REPO_TEXT"
if [[ "$GUARD_STATUS" -eq 0 ]]; then
  fail "cas 2 : des chaînes utilisateur en dur ne sont pas détectées — critère 2 de #7482"
fi
echo "ok: cas 2 rouge (violations détectées)"
expect_flagged 'Enregistrer la fiche' "attribut statique title"
expect_flagged 'Supprimer le compte' "attribut statique aria-label (texte multi-mots)"
expect_flagged "Nom de l'entreprise" "attribut statique placeholder (apostrophe)"
expect_flagged 'Champ obligatoire' "littéral DANS une expression liée :label"
expect_flagged 'Votre espace est pret' "littéral de script Next.js"
expect_flagged 'Recapitulatif du panier' "attribut statique aria-label (TSX)"
for motif in 'form[key]' 'item.x == null' 'flex items-center' 'companies'; do
  expect_clean "$motif" "motif technique « $motif » (cas 2)"
done

# ── Cas 3 : le message d'erreur dit quoi faire (critère 3) ───────────────────
if ! printf '%s\n' "$OUT" | grep -qF 'catalogue i18n'; then
  printf '%s\n' "$OUT" >&2
  fail "cas 3 : le message d'erreur ne dit pas quoi faire (critère 3 de #7482)"
fi
echo "ok: cas 3 — le message d'erreur est actionnable"

echo "PASS : check-i18n-diff-test.sh"
