#!/usr/bin/env bash
# stack-switch-sparse-checkout-test.sh — garde de non-régression #9360.
#
# Incident (2026-11-10) : `dev-stack-switch.yml` ne checkoutait que
# `scripts/dev-stack-switch.sh`, qui est un SHIM faisant
# `exec "$ROOT/scripts/stack-switch.sh" --tier dev "$@"`. Le fichier cible
# n'étant pas dans le sparse-checkout, le job sortait en 127 :
#
#   scripts/dev-stack-switch.sh: line 22: .../scripts/stack-switch.sh: No such file or directory
#   ##[error]Process completed with exit code 127.
#
# Conséquence : AUCUNE bascule n'était possible — ni manuelle, ni automatique
# (le watchdog de dev-keepalive.yml appelle ce même workflow). La stack dev
# restait donc bloquée sur une cible morte, et l'espace de travail du
# responsable d'entreprise demeurait inaccessible.
#
# Ce test vérifie, sans réseau ni runner, que tout script `exec`é par un shim
# présent dans un sparse-checkout y figure AUSSI. Il est volontairement
# générique : il couvre les deux workflows qui appellent la bascule.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

fail=0
ok()   { printf '  \u2713 %s\n' "$1"; }
bad()  { printf '  \u2717 %s\n' "$1" >&2; fail=1; }

echo "── #9360 : le sparse-checkout de la bascule contient ses dépendances ──"

WF=".github/workflows/dev-stack-switch.yml"
[[ -f "$WF" ]] || { bad "$WF introuvable"; exit 1; }

# 1. Le shim est bien checkouté.
if grep -qE '^\s+scripts/dev-stack-switch\.sh\s*$' "$WF"; then
  ok "scripts/dev-stack-switch.sh est dans le sparse-checkout"
else
  bad "scripts/dev-stack-switch.sh absent du sparse-checkout"
fi

# 2. Sa cible `exec` l'est aussi — c'est exactement ce qui manquait.
if grep -qE '^\s+scripts/stack-switch\.sh\s*$' "$WF"; then
  ok "scripts/stack-switch.sh (cible du shim) est dans le sparse-checkout"
else
  bad "scripts/stack-switch.sh absent du sparse-checkout — le shim sortirait en 127"
fi

# 3. Le registre lu par le script est présent.
if grep -qE '^\s+deploy/dev-stacks\.json\s*$' "$WF"; then
  ok "deploy/dev-stacks.json est dans le sparse-checkout"
else
  bad "deploy/dev-stacks.json absent du sparse-checkout"
fi

# 4. Cohérence réelle : tout `exec`/appel de script du shim doit être couvert.
SHIM="scripts/dev-stack-switch.sh"
if [[ -f "$SHIM" ]]; then
  # Cibles référencées par le shim (chemins relatifs au dépôt).
  while IFS= read -r target; do
    [[ -n "$target" ]] || continue
    if grep -qE "^\s+${target//./\\.}\s*$" "$WF"; then
      ok "dépendance du shim couverte : $target"
    else
      bad "dépendance du shim NON couverte par le sparse-checkout : $target"
    fi
  done < <(grep -oE 'scripts/[a-z0-9-]+\.sh' "$SHIM" | sort -u | grep -v '^scripts/dev-stack-switch\.sh$')
else
  bad "$SHIM introuvable"
fi

# 5. Le shim doit toujours être un shim (sinon ce test perd sa raison d'être).
if grep -qE 'exec .*stack-switch\.sh' "$SHIM" 2>/dev/null; then
  ok "le shim exec bien scripts/stack-switch.sh (hypothèse du test valide)"
else
  bad "le shim n'exec plus stack-switch.sh — revoir ce test"
fi

if [[ "$fail" -ne 0 ]]; then
  echo "ÉCHEC — le sparse-checkout de la bascule est incomplet (#9360)." >&2
  exit 1
fi

echo "OK — 5 vérifications passées"
