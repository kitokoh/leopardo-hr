#!/usr/bin/env bash
# check-duplicate-routes.sh — garde CI issue #5577
#
# Détecte les routes Laravel dupliquées (méthode HTTP + URI complète) dans les
# fichiers de routes du projet API. Un doublon silencieux rend un contrôleur
# inatteignable et génère des bugs difficiles à diagnostiquer (audit 2026-08-26).
#
# Usage : bash dev-hub/tools/check-duplicate-routes.sh [api_dir]
#         api_dir par défaut = "api"
#
# Sortie :
#   - 0 si aucun doublon détecté
#   - 1 si des doublons sont trouvés (liste dans stderr)
#
# Prérequis : php, composer (artisan route:list)

set -euo pipefail

API_DIR="${1:-api}"

if [[ ! -f "${API_DIR}/artisan" ]]; then
  echo "❌  Artisan introuvable dans '${API_DIR}'. Passer le chemin correct en argument." >&2
  exit 1
fi

# Générer la liste des routes au format JSON, extraire méthode+URI, trier, compter.
ROUTE_LIST=$(
  cd "${API_DIR}" && \
  php artisan route:list --json 2>/dev/null \
  | php -r '
      $routes = json_decode(file_get_contents("php://stdin"), true) ?? [];
      $seen   = [];
      $dups   = [];
      foreach ($routes as $r) {
          // Normaliser les méthodes multiples (HEAD|GET → GET, etc.)
          $methods = array_filter(
              explode("|", strtoupper($r["method"] ?? "")),
              static fn(string $m): bool => $m !== "HEAD"
          );
          $uri = trim($r["uri"] ?? "");
          foreach ($methods as $method) {
              $key = $method . " " . $uri;
              if (isset($seen[$key])) {
                  $dups[] = $key;
              }
              $seen[$key] = true;
          }
      }
      $dups = array_unique($dups);
      if (count($dups) > 0) {
          echo implode("\n", $dups) . "\n";
          exit(1);
      }
      exit(0);
  '
)
EXIT_CODE=$?

if [[ ${EXIT_CODE} -ne 0 ]]; then
  echo "❌  Routes dupliquées détectées (issue #5577) :" >&2
  echo "${ROUTE_LIST}" | sed "s/^/    /" >&2
  echo "" >&2
  echo "Chaque paire (méthode, URI) doit être unique. Supprimer ou fusionner les doublons." >&2
  exit 1
fi

echo "✅  Aucune route dupliquée détectée."
