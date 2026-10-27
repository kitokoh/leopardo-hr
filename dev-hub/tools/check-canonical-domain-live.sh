#!/usr/bin/env bash
# check-canonical-domain-live.sh — garde de disponibilité du domaine canonique (#8062).
#
# Contexte : le domaine canonique déclaré par le front
# (`front/web/src/lib/site.ts` → `DEFAULT_SITE_URL`) ne résout pas (NXDOMAIN,
# constaté 2026-09-22 puis re-vérifié 2026-10-26). Conséquence : metadata Open
# Graph, sitemap, JSON-LD et liens partagés pointent vers un domaine mort, et le
# seul accès fonctionnel est un nom de projet Vercel
# (`gestionemployer-backend.vercel.app`) qui n'inspire aucune confiance en barre
# d'adresse.
#
# La correction est une configuration DNS (zone `leopardo-rh.com` → projet
# Vercel `web`) : c'est une action PROPRIÉTAIRE, aucun commit ne peut créer un
# enregistrement. Ce garde ne répare donc rien — il rend la panne VISIBLE au
# lieu de la laisser silencieuse, et il échoue tant que le domaine ne répond
# pas 200. C'est la seule partie de #8062 qui soit codable.
#
# Vérifie, dans l'ordre :
#   1. le domaine canonique déclaré par le front est lisible ;
#   2. il résout (DNS) ;
#   3. il répond HTTP 200 sur la home.
#
# Le domaine cible est lu DANS LE CODE (`DEFAULT_SITE_URL`) plutôt que codé en
# dur : surveiller un domaine que le site n'annonce pas laisserait la panne
# réelle invisible. Un domaine peut être forcé en 1er argument (pré-vol avant
# bascule DNS).
#
# Usage : dev-hub/tools/check-canonical-domain-live.sh [domaine] [repo_root]
# Tests : dev-hub/tools/tests/check-canonical-domain-live.test.sh
#
# Sortie : 0 si le domaine est opérationnel, 1 sinon (avec `::error::`).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="${2:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
cd "${REPO_ROOT}"

errors=0
note() { echo "  $*"; }
fail() {
  echo "::error::$*"
  errors=$((errors + 1))
}

# --- 1. Source de vérité : le domaine que le front ANNONCE -------------------
SITE_TS="front/web/src/lib/site.ts"
declared=""
if [[ -f "${SITE_TS}" ]]; then
  declared="$(grep -oE "DEFAULT_SITE_URL = 'https?://[^']+'" "${SITE_TS}" | head -1 | sed -E "s#.*'https?://([^']+)'.*#\1#")"
fi

if [[ -z "${declared}" ]]; then
  fail "Impossible de lire DEFAULT_SITE_URL dans ${SITE_TS} — le garde ne peut pas déterminer le domaine canonique (fail-closed : pas de vert par défaut)."
  echo "::error::check-canonical-domain-live.sh : ${errors} problème(s) — domaine canonique indéterminable (#8062)."
  exit 1
fi
note "Domaine canonique déclaré par le front : ${declared}"

# Le domaine testé est celui déclaré, sauf override explicite (pré-vol).
DOMAIN="${1:-${declared}}"
if [[ "${DOMAIN}" != "${declared}" ]]; then
  note "Override : test de ${DOMAIN} (le front déclare ${declared})."
fi

# --- 2. Résolution DNS ------------------------------------------------------
if getent hosts "${DOMAIN}" >/dev/null 2>&1; then
  note "DNS : ${DOMAIN} résout."
else
  fail "DNS : ${DOMAIN} ne résout pas (NXDOMAIN) — la zone DNS n'est pas configurée (#8062, action propriétaire)."
fi

# --- 3. Réponse HTTP --------------------------------------------------------
# `curl` sort non-zéro sur échec réseau : on capture le statut ET le code de
# sortie séparément, sinon un échec produirait un statut vide (ou doublé par un
# `|| echo`), et le message d'erreur serait illisible.
status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "https://${DOMAIN}/" 2>/dev/null)" || status=""
if [[ "${status}" == "200" ]]; then
  note "HTTP : https://${DOMAIN}/ → 200."
else
  fail "HTTP : https://${DOMAIN}/ → ${status:-injoignable} (attendu 200)."
fi

if [[ "${errors}" -gt 0 ]]; then
  echo "::error::check-canonical-domain-live.sh : ${errors} problème(s) — le domaine canonique n'est pas opérationnel (#8062)."
  exit 1
fi

echo "✅ Domaine canonique opérationnel : https://${DOMAIN}/ répond 200."
