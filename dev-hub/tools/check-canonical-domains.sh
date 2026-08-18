#!/usr/bin/env bash
# check-canonical-domains.sh — garde anti-drift des domaines projet (issue #3706).
#
# Source de vérité : docs/ops/DOMAINS.md. Cette garde vérifie que toute
# référence à un domaine propre au projet correspond à une entrée du registre
# et qu'aucun domaine inconnu (ex. un nouveau sous-domaine improvisé) n'apparaît.
#
# Usage : dev-hub/tools/check-canonical-domains.sh [repo_root]
# Exit 1 si un domaine inconnu est référencé ou si le registre diverge du doc.

set -euo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "${ROOT}"

# --- Registre (miroir de docs/ops/DOMAINS.md) -------------------------------
# Format : domaine<TAB>statut   (status: live|target|deprecated)
REGISTRY=(
  $'gestionemployerbackend.onrender.com\tlive'
  $'gestionemployer-backend.vercel.app\tlive'
  $'leo-admin.pages.dev\tlive'
  $'api.leopardo-rh.com\ttarget'
  $'app.leopardo-rh.com\ttarget'
  $'leopardo-rh.com\ttarget'
  $'www.leopardo-rh.com\ttarget'
  $'api.leopardo.app\ttarget'
  $'proxy.leopardo-rh.com\ttarget'
  $'admin.leopardo-rh.com\ttarget'
  $'docs.leopardo-rh.com\ttarget'
  $'demo.leopardo-rh.com\ttarget'
  $'api-staging.leopardo-rh.com\ttarget'
  $'demo.leopardo.app\ttarget'
  $'client-a.leopardo-rh.com\ttarget'
)

errors=0

# --- Scan des fichiers du dépôt ---------------------------------------------
mapfile -t FILES < <(
  find . -type f \
    -not -path './.git/*' \
    -not -path '*/node_modules/*' \
    -not -path '*/vendor/*' \
    -not -path '*/.specify/*' \
    -not -path '*/assets/*' \
    -not -path '*/storage/*' \
    -not -path '*/.dart_tool/*' \
    -not -path '*/build/*' \
    -not -path '*/.next/*' \
    -not -name 'package-lock.json' \
    -not -name 'composer.lock' \
    -not -name 'CHANGELOG_ARCHIVE.md' \
    -not -name '.secrets.baseline' \
    -not -name '*.png' -not -name '*.jpg' -not -name '*.jpeg' \
    -not -name '*.gif' -not -name '*.webp' -not -name '*.ico' \
    -not -name '*.woff' -not -name '*.woff2' -not -name '*.ttf' \
    2>/dev/null || true
)

declare -A SEEN
for f in "${FILES[@]}"; do
  [[ -f "${f}" ]] || continue
  while IFS= read -r domain; do
    [[ -z "${domain}" ]] && continue
    domain="${domain,,}"
    key="${f}|${domain}"
    [[ -n "${SEEN[${key}]:-}" ]] && continue
    SEEN["${key}"]=1

    known=""
    for entry in "${REGISTRY[@]}"; do
      reg_domain="${entry%%$'\t'*}"
      if [[ "${domain}" == "${reg_domain}" ]]; then
        known="${entry##*$'\t'}"
        break
      fi
    done

    if [[ -z "${known}" ]]; then
      echo "::error::Domaine projet inconnu référencé dans ${f} : ${domain} (ajoutez-le à docs/ops/DOMAINS.md + registre de la garde)"
      errors=$((errors + 1))
    elif [[ "${known}" == "deprecated" ]]; then
      echo "::error::Domaine 'deprecated' référencé dans ${f} : ${domain}"
      errors=$((errors + 1))
    fi
  done < <(grep -oE '([a-z0-9-]+\.)+(leopardo-rh\.com|leopardo\.app)|gestionemployerbackend\.onrender\.com|leo-admin\.pages\.dev' "${f}" 2>/dev/null || true)
done

# --- Cohérence registre garde <-> docs/ops/DOMAINS.md ------------------------
doc_domains=""
if [[ -f docs/ops/DOMAINS.md ]]; then
  # Ligne du tableau : "| `domaine.tld` | ..." → ne garder que la 1re cellule backtickée
  doc_domains="$(grep -oE '^\| `[a-z0-9.-]+\.[a-z0-9-]+`' docs/ops/DOMAINS.md | sed 's/^| `//; s/`$//' | sort)"
fi
guard_domains="$(printf '%s\n' "${REGISTRY[@]}" | cut -f1 | sort)"

if [[ "${doc_domains}" != "${guard_domains}" ]]; then
  echo "::error::Registre docs/ops/DOMAINS.md et registre de la garde divergents :"
  echo "  docs  : ${doc_domains}"
  echo "  garde : ${guard_domains}"
  errors=$((errors + 1))
fi

if [[ "${errors}" -gt 0 ]]; then
  echo "::error::check-canonical-domains.sh : ${errors} problème(s) — voir ci-dessus."
  exit 1
fi

echo "✅ Domaines canoniques OK (docs/ops/DOMAINS.md synchronisé, aucune référence hors registre)."
