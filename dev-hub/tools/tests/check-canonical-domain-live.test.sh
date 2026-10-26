#!/usr/bin/env bash
#
# check-canonical-domain-live.test.sh — tests du garde (#8062).
#
# Le garde dépend du réseau (DNS + HTTP) : les tests injectent de FAUX binaires
# `getent` et `curl` en tête de PATH, ce qui exerce le VRAI code du garde sans
# dépendre d'un domaine réel — et permet de simuler une panne DNS ou un 500,
# qu'aucun test en ligne ne pourrait provoquer à volonté.
#
# Scénarios :
#   1. domaine résolu + 200 → vert ;
#   2. NXDOMAIN → rouge (nomme la cause DNS) ;
#   3. HTTP 500 → rouge (nomme le statut) ;
#   4. curl en échec réseau (statut vide) → rouge, message lisible ;
#   5. source de vérité absente → rouge (fail-closed, pas de vert par défaut) ;
#   6. override explicite du domaine → testé, et le domaine déclaré est rappelé.
#
# Usage : bash dev-hub/tools/tests/check-canonical-domain-live.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-canonical-domain-live.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0
ok() {
  echo "✅ $1"
  pass=$((pass + 1))
}
ko() {
  echo "❌ $1"
  fail=$((fail + 1))
}

# ── Faux dépôt : la source de vérité du front ────────────────────────────────
FAKE_REPO="${TMP}/repo"
mkdir -p "${FAKE_REPO}/front/web/src/lib"
cat > "${FAKE_REPO}/front/web/src/lib/site.ts" <<'EOF'
export const DEFAULT_SITE_URL = 'https://www.leopardo-rh.com';
EOF

# ── Faux binaires, pilotés par variables d'environnement ─────────────────────
BIN="${TMP}/bin"
mkdir -p "${BIN}"
cat > "${BIN}/getent" <<'EOF'
#!/usr/bin/env bash
[[ "${FAKE_DNS:-ok}" == "ok" ]] || exit 2
echo "1.2.3.4  $2"
EOF
cat > "${BIN}/curl" <<'EOF'
#!/usr/bin/env bash
# Échec réseau simulé : sortie non-zéro SANS statut (comme un vrai curl).
if [[ "${FAKE_HTTP:-200}" == "netfail" ]]; then
  exit 6
fi
printf '%s' "${FAKE_HTTP:-200}"
EOF
chmod +x "${BIN}/getent" "${BIN}/curl"
export PATH="${BIN}:${PATH}"

# ── 1. Résolu + 200 → vert ───────────────────────────────────────────────────
out="$(FAKE_DNS=ok FAKE_HTTP=200 bash "${GUARD}" "" "${FAKE_REPO}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  ok "domaine résolu + 200 → vert"
else
  ko "domaine résolu + 200 → vert attendu"
  echo "${out}" | tail -3
fi

# ── 2. NXDOMAIN → rouge ──────────────────────────────────────────────────────
out="$(FAKE_DNS=nx FAKE_HTTP=200 bash "${GUARD}" "" "${FAKE_REPO}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"ne résout pas"* ]]; then
  ok "NXDOMAIN → rouge (cause DNS nommée)"
else
  ko "NXDOMAIN → rouge attendu"
  echo "${out}" | tail -3
fi

# ── 3. HTTP 500 → rouge ──────────────────────────────────────────────────────
out="$(FAKE_DNS=ok FAKE_HTTP=500 bash "${GUARD}" "" "${FAKE_REPO}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"500"* ]]; then
  ok "HTTP 500 → rouge (statut nommé)"
else
  ko "HTTP 500 → rouge attendu"
  echo "${out}" | tail -3
fi

# ── 4. Échec réseau (statut vide) → rouge, message lisible ───────────────────
out="$(FAKE_DNS=ok FAKE_HTTP=netfail bash "${GUARD}" "" "${FAKE_REPO}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"injoignable"* ]]; then
  ok "échec réseau → rouge (message lisible, pas de statut vide)"
else
  ko "échec réseau → rouge attendu"
  echo "${out}" | tail -3
fi

# ── 5. Source de vérité absente → rouge (pas de vert par défaut) ─────────────
EMPTY_REPO="${TMP}/empty"
mkdir -p "${EMPTY_REPO}"
out="$(FAKE_DNS=ok FAKE_HTTP=200 bash "${GUARD}" "" "${EMPTY_REPO}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"Impossible de lire"* ]]; then
  ok "source de vérité absente → rouge (fail-closed)"
else
  ko "source de vérité absente → rouge attendu"
  echo "${out}" | tail -3
fi

# ── 6. Override explicite → testé, domaine déclaré rappelé ───────────────────
out="$(FAKE_DNS=ok FAKE_HTTP=200 bash "${GUARD}" autre-domaine.example "${FAKE_REPO}" 2>&1 || true)"
if [[ "${out}" == *"Override"* ]] && [[ "${out}" == *"www.leopardo-rh.com"* ]] && [[ "${out}" == *"✅"* ]]; then
  ok "override explicite → testé, domaine déclaré rappelé"
else
  ko "override explicite → comportement attendu"
  echo "${out}" | tail -3
fi

echo ""
echo "── check-canonical-domain-live.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
