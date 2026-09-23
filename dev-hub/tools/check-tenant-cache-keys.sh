#!/usr/bin/env bash
#
# check-tenant-cache-keys.sh — Garde CI « clés de cache tenant » (issue #8058).
#
# Politique : toute clé de cache portant des données d'un tenant DOIT être
# préfixée par son company_id via le helper central
# `App\Shared\Support\TenantCache` (key() = contexte courant fail-closed,
# keyFor() = company_id explicite). Une clé construite à la main qui oublie
# company_id = fuite inter-tenant potentielle — le cache est le maillon
# manquant de la même politique que `BelongsToCompany` (DB) et
# `QueueTenantContextArchitectureTest` (jobs).
#
# Règle scannée (périmètre : api/app/Modules/) :
#   tout appel BRUT à la facade Cache
#   (Cache::get|put|remember|rememberForever|add|forever|has|pull|forget|
#    missing|flexible|increment|decrement)
#   doit porter, sur la ligne même ou dans les 2 lignes qui précèdent, le
#   marqueur « tenant-cache: » suivi d'une justification :
#     - tenant-cache:via-helper — clé construite par TenantCache (cas nominal) ;
#     - tenant-cache:shared    — cache LÉGITIMEMENT partagé (sitemap global,
#       crédential plateforme, back-office cross-tenant, throttle email/IP…).
#
# Le marqueur n'est pas un contournement : il DOCUMENTE la décision et la
# rend relisible en revue. Un cache partagé sans raison valide doit être
# refusé en review (même esprit que check-module-isolation.sh).
#
# Usage : dev-hub/tools/check-tenant-cache-keys.sh [api_dir]
# Exit 1 si une violation est détectée.

set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

SCAN_DIR="$API_DIR/app/Modules"
if [[ ! -d "$SCAN_DIR" ]]; then
  echo "::error::check-tenant-cache-keys: '$SCAN_DIR' introuvable — le périmètre de la garde a changé (#8058)."
  exit 1
fi

if [[ ! -f "$API_DIR/app/Shared/Support/TenantCache.php" ]]; then
  echo "::error::check-tenant-cache-keys: helper 'app/Shared/Support/TenantCache.php' introuvable — la garde exige le point d'entrée unique (#8058)."
  exit 1
fi

# Appels bruts de la facade Cache — le lookbehind évite les faux positifs
# des méthodes de classes *Cache métier (CatalogPublicCache::forget…).
PATTERN='(?<![A-Za-z0-9_])Cache::(get|put|remember|rememberForever|add|forever|has|pull|forget|missing|flexible|increment|decrement)\s*\('

violations=0

while IFS=: read -r file line text; do
  # Ignore les lignes de commentaire/docblock mentionnant Cache:: en prose.
  stripped="$(printf '%s' "$text" | sed 's/^[[:space:]]*//')"
  case "$stripped" in
    \** | //* ) continue ;;
  esac

  # Fenêtre de conformité : ligne même + 2 lignes au-dessus (un appel
  # multi-lignes voit son marqueur posé juste au-dessus de la 1re ligne).
  start=$((line - 2))
  [[ "$start" -lt 1 ]] && start=1
  context="$(sed -n "${start},${line}p" "$file")"

  if ! grep -q 'tenant-cache:' <<<"$context"; then
    echo "::error file=$file,line=$line::check-tenant-cache-keys: appel Cache:: brut sans marqueur « tenant-cache:via-helper » ni « tenant-cache:shared » (#8058) — passer par TenantCache::key()/keyFor() ou justifier le partage."
    violations=$((violations + 1))
  fi
done < <(grep -rnP "$PATTERN" "$SCAN_DIR" --include='*.php' || true)

if [[ "$violations" -gt 0 ]]; then
  echo "::error::check-tenant-cache-keys: $violations appel(s) Cache:: non conforme(s) dans $SCAN_DIR (#8058)."
  exit 1
fi

echo "check-tenant-cache-keys: OK — toutes les clés de cache de $SCAN_DIR passent par TenantCache ou portent une exemption justifiée (#8058)."
