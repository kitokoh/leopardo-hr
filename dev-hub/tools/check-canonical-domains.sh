#!/usr/bin/env bash
# check-canonical-domains.sh — détecte les domaines first-party inconnus.
# Les domaines autorisés sont centralisés ici jusqu'à la canonicalisation infra.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT_DIR}"

allowed_hosts=(
  "gestionemployerbackend.onrender.com"
  "gestionemployer-backend.vercel.app"
  "api.leopardo-rh.com"
  "api-staging.leopardo-rh.com"
  "app.leopardo-rh.com"
  "admin.leopardo-rh.com"
  "client-a.leopardo-rh.com"
  "demo.leopardo-rh.com"
  "docs.leopardo-rh.com"
  "proxy.leopardo-rh.com"
  "www.leopardo-rh.com"
  "leopardo-rh.com"
  "demo.leopardo.app"
  "api.leopardo.app"
  "leopardo-api.onrender.com"
  "leopardo-hr.vercel.app"
)

allowlist="$(printf '%s\n' "${allowed_hosts[@]}" | sort -u)"
# Inspect tracked text files only; external SaaS domains are intentionally ignored.
files=$(git ls-files | grep -E '\.(md|mdx|json|ya?ml|ya?ml\.example|env|ts|tsx|js|jsx|dart|py|php|sh)$' || true)
unknown=""
while IFS= read -r host; do
  [[ -z "${host}" ]] && continue
  [[ "${host}" == "your-app.onrender.com" ]] && continue
  if ! grep -qxF "${host}" <<< "${allowlist}"; then
    unknown+="${host}\n"
  fi
done < <(grep -rhoE '([A-Za-z0-9-]+\.)+(leopardo-rh\.com|leopardo\.app|onrender\.com|vercel\.app)' ${files} 2>/dev/null | sort -u || true)

if [[ -n "${unknown}" ]]; then
  echo "::error::Domaine first-party hors registre canonique :"
  printf '::error::  - %s' "${unknown}"
  exit 1
fi

echo "✓ Domaines first-party présents dans le registre canonique."
