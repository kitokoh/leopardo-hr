#!/usr/bin/env bash
# stack-switch-front-guard-test.sh — garde-fou « cible sans front » (#9360).
#
# Incident #9360 (2026-11-10) : la bascule dev vers la stack B a été enregistrée
# alors que B n'avait AUCUN front (`web.url: null`). Le front Vercel de la
# stack A s'est retrouvé à proxifier vers l'API de B, tombée depuis → 502
# `backend_unavailable` sur toute route /api/v1/*, espace de travail du
# responsable inaccessible.
#
# Ce test verrouille le refus : une cible dev sans `web.url` ne doit PAS être
# basculée sans `--force`. Il tourne SANS réseau et SANS gh : le script est
# arrêté avant toute écriture de variable (le refus intervient en amont).
#
# Usage : bash scripts/tests/stack-switch-front-guard-test.sh
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCRIPT="$ROOT/scripts/stack-switch.sh"

pass=0
fail=0

ok()   { printf '  \033[1;32m✓\033[0m %s\n' "$1"; pass=$((pass + 1)); }
bad()  { printf '  \033[1;31m✗\033[0m %s\n' "$1"; fail=$((fail + 1)); }

# Registre synthétique : la cible « nofront » a une API saine mais AUCUN front.
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

cat > "$TMP/dev-stacks.json" <<'JSON'
{
  "version": 1,
  "tier": "dev",
  "active_stack": "a",
  "stacks": {
    "a": {
      "status": "active",
      "api": { "base_url": "https://a.example/api/v1" },
      "web": { "url": "https://a-web.example" },
      "admin": { "url": "https://a-admin.example" }
    },
    "nofront": {
      "status": "ready",
      "api": { "base_url": "https://nofront.example/api/v1" },
      "web": { "url": null },
      "admin": { "url": null }
    }
  }
}
JSON

# `gh` factice : si le script atteint l'écriture des variables, on le sait.
mkdir -p "$TMP/bin"
cat > "$TMP/bin/gh" <<'SH'
#!/usr/bin/env bash
echo "GH_CALLED $*" >> "${GH_CALL_LOG:?}"
exit 0
SH
chmod +x "$TMP/bin/gh"

# `curl` factice : API toujours saine (200) — on isole la garde « front ».
cat > "$TMP/bin/curl" <<'SH'
#!/usr/bin/env bash
# --write-out '%{http_code}' → 200
echo -n "200"
exit 0
SH
chmod +x "$TMP/bin/curl"

run_switch() {
  local log="$TMP/gh.log"
  : > "$log"
  PATH="$TMP/bin:$PATH" \
  GH_CALL_LOG="$log" \
  STACKS_REGISTRY="$TMP/dev-stacks.json" \
  GITHUB_REPOSITORY="kitokoh/leopardo-hr" \
    bash "$SCRIPT" "$@" 2>&1
  echo "EXIT=$?"
}

echo "── #9360 : cible dev sans front ──"

# 1. Sans --force : refus attendu, aucune variable écrite.
out="$(run_switch nofront --tier dev)"
if echo "$out" | grep -q "EXIT=1" && echo "$out" | grep -q "sans web.url"; then
  ok "refuse une cible dev sans web.url (exit 1, message explicite)"
else
  bad "aurait dû refuser la cible sans front — sortie : $out"
fi

if [[ ! -s "$TMP/gh.log" ]]; then
  ok "n'écrit AUCUNE variable du dépôt quand la cible est incomplète"
else
  bad "a écrit des variables malgré le refus : $(cat "$TMP/gh.log")"
fi

# 2. Avec --force : la soupape reste utilisable (avertissement, pas de refus).
out="$(run_switch nofront --tier dev --force)"
if echo "$out" | grep -q "sans web.url (front absent) — --force accepté"; then
  ok "--force reste la soupape assumée (avertissement, pas de refus)"
else
  bad "--force aurait dû accepter avec avertissement — sortie : $out"
fi

# 3. Cible AVEC front : la garde ne doit pas gêner le cas nominal.
out="$(run_switch a --tier dev)"
if echo "$out" | grep -q "déjà active"; then
  ok "cible avec front : la garde ne bloque pas le cas nominal"
else
  bad "la garde bloque à tort une cible complète — sortie : $out"
fi

echo
if [[ "$fail" -eq 0 ]]; then
  printf '\033[1;32mOK\033[0m — %d vérifications passées\n' "$pass"
  exit 0
fi
printf '\033[1;31mÉCHEC\033[0m — %d passées, %d échouées\n' "$pass" "$fail"
exit 1
