#!/usr/bin/env bash
# check-front-shared-parity.sh — issue #7964 (précédent mobile : #7652).
#
# Plusieurs fichiers dupliqués VOLONTAIREMENT entre les fronts Next.js du
# monorepo (front/web, front/travel-web, front/marketplace) doivent rester
# BYTE-IDENTIQUES : ce sont des fichiers de sécurité/infrastructure partagés
# (résolution d'URL backend, proxy CSP à nonce) — un correctif appliqué à
# une seule copie ne se propage pas aux autres (dérive déjà constatée sur
# les proxies auth dans l'audit 2026-09-20).
#
# Pourquoi pas un package npm partagé (demande initiale de l'issue) :
# chaque front est volontairement AUTONOME (lockfile propre, `turbopack.root`
# épinglé, build Vercel par sous-dossier — décision #7305, rappelée dans
# l'en-tête de `backend-url.ts` : « pas de package npm partagé dans ce
# dépôt »). Tant que cette contrainte tient, la règle est la
# SYNCHRONISATION MANUELLE — et cette garde la rend OPPOSABLE : elle rougit
# dès qu'une paire canonique diverge, au lieu de laisser la dérive
# silencieuse (constat #7964). Le passage à un workspace npm est une
# décision propriétaire (renverse #7305) — l'issue reste ouverte pour elle.
#
# Règle : tout fichier listé ici porte dans son en-tête la mention
# « SYNCHRONISATION MANUELLE » avec la liste des copies. Une paire dont un
# membre est absent (front pas encore doté du fichier) est SIGNALÉE mais
# non bloquante — la parité s'applique dès que les deux existent.
#
# Usage : bash dev-hub/tools/check-front-shared-parity.sh
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

# Paires canoniques : source de vérité déclarée en première position (le
# message d'erreur la désigne comme référence de resynchronisation).
PAIRS=(
  "front/web/src/lib/backend-url.ts|front/travel-web/src/lib/backend-url.ts"
  "front/travel-web/src/middleware.ts|front/marketplace/src/middleware.ts"
)

status=0
for pair in "${PAIRS[@]}"; do
  canonical="${pair%%|*}"
  copy="${pair##*|}"

  if [[ ! -f "$canonical" ]]; then
    if [[ ! -f "$copy" ]]; then
      echo "ℹ️  paire dormante (aucun membre présent — non bloquant) : $canonical ↔ $copy"
      continue
    fi
    echo "❌ SOURCE CANONIQUE ABSENTE : $canonical (paire déclarée pour $copy)"
    status=1
    continue
  fi
  if [[ ! -f "$copy" ]]; then
    echo "ℹ️  copie absente (front non doté — non bloquant) : $copy"
    continue
  fi

  if ! cmp -s "$canonical" "$copy"; then
    echo "❌ DÉRIVE de copie partagée (#7964) :"
    echo "   canonique : $canonical"
    echo "   divergée  : $copy"
    echo "   → reporter la modification à l'identique (SYNCHRONISATION MANUELLE,"
    echo "     cf. en-tête du fichier) ou trancher la fusion dans UN sens puis"
    echo "     recopier. Un fix sécu sur UNE copie ne se propage pas tout seul."
    status=1
  else
    echo "ok: $canonical ≡ $copy"
  fi
done

if [[ "$status" -ne 0 ]]; then
  echo
  echo "Garde #7964 — copies partagées front divergentes. Voir l'en-tête de"
  echo "dev-hub/tools/check-front-shared-parity.sh pour la règle."
  exit 1
fi
echo "PASS : toutes les copies partagées front sont synchronisées."
