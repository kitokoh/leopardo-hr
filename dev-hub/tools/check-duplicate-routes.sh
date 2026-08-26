#!/usr/bin/env bash
# check-duplicate-routes.sh — Garde CI « pas de routes dupliquées » (issue #5577)
#
# Pourquoi : Laravel sert la PREMIÈRE route enregistrée pour une paire
# (méthode, URI) identique — toute route dupliquée rend silencieusement
# inatteignable l'autre implémentation. Constats : #5577 (POST
# /accounting/documents/{document}/payments déclaré deux fois), rh.php
# (PUT reject dupliqué), geo.php (bloc day-closures copié-collé).
#
# Règle : `php artisan route:list --json`, normalisation (méthodes groupées
# GET|HEAD, paramètres `{param}`), puis détection des doublons exacts de
# (méthode, URI). Échoue (exit 1) dès qu'un doublon existe.
#
# Usage : dev-hub/tools/check-duplicate-routes.sh [repo_root]
#   repo_root : racine du dépôt (défaut : .)
#   Exige PHP + Composer installés dans api/ (même environnement que le job
#   backend-quality de tests.yml).
set -uo pipefail

ROOT="${1:-.}"
ROOT="$(cd "$ROOT" && pwd)"

echo "::group::Duplicate routes guard (issue #5577)"

# route:list a besoin d'une APP_KEY pour booter ; on force un environnement
# déterministe (la liste des routes ne dépend pas de la base de données).
export APP_ENV="${APP_ENV:-testing}"
export APP_KEY="${APP_KEY:-base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=}"
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"

ROUTES_JSON="$(mktemp)"
trap 'rm -f "$ROUTES_JSON"' EXIT

if ! (cd "$ROOT/api" && php artisan route:list --json --except-vendor > "$ROUTES_JSON" 2> /dev/null); then
  echo "::error::route:list a échoué — impossible de vérifier les doublons de routes."
  exit 1
fi

# (méthode, URI) normalisés : méthodes groupées en un token, paramètres
# d'URI normalisés ({document} → {param}).
DUPES="$(jq -r '.[] | "\(.method | if type == "array" then join("|") else . end)\t\(.uri)"' "$ROUTES_JSON" \
  | sed -E 's#\{[^}]+\}#{param}#g' \
  | sort \
  | uniq -d || true)"

if [[ -n "$DUPES" ]]; then
  echo "::error::Routes dupliquées détectées (méthode + URI identiques — la première enregistrée masque les autres) :"
  while IFS=$'\t' read -r method uri; do
    echo "::error::  ${method} ${uri}"
  done <<< "$DUPES"
  echo "::error::→ Supprimer ou fusionner les doublons (convention : une seule route par opération, cf. issue #5577)."
  echo "::endgroup::"
  exit 1
fi

echo "✓ Aucune route dupliquée (route:list normalisé — méthodes + URIs uniques)."
echo "::endgroup::"
exit 0
