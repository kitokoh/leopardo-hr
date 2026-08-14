#!/usr/bin/env bash
# Garde CI #2212 : les routes servies par MobileExperienceService (manifeste
# mobile) doivent exister dans le routeur GoRouter de l'app mobile cible.
#
# Sans ce garde, un module ou une quick action sert une route absente du
# routeur et `context.push(action.route)` crashe l'app (GoRouter throws).
# Le script parse le manifeste PHP (sections modules + quick actions, par
# role : base / principal / hr) et verifie que chaque route est declaree
# dans le app.dart de l'app concernee (employee / manager / hr).
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MANIFEST="${REPO_ROOT}/api/app/Modules/HR/Infrastructure/Services/MobileExperienceService.php"

failures=0

# Routes GoRoute declarees dans un app.dart (triees, dedupliquees).
app_routes() {
  grep -oE "path: '/[^']*'" "$1" | sed -E "s/path: '//; s/'$//" | sort -u
}

# Parse le manifeste : pour chaque `route: '/...'`, imprime `section|route`.
# Sections modules : base / principal / hr ; sections quick actions :
# base_actions / principal_actions / hr_actions. Les `route: null` sont ignores.
parse_manifest() {
  awk '
    /private function modulesFor/      { section = "base" }
    /private function quickActionsFor/ { section = "base_actions" }
    /if \(\$employee->isPrincipal\(\)\)/ {
      if (section == "base" || section == "principal" || section == "hr") section = "principal"
      else section = "principal_actions"
    }
    /elseif \(\$employee->isHr\(\)\)/ {
      if (section == "principal") section = "hr"
      else section = "hr_actions"
    }
    /key: .settings./ { section = "base_actions" }
    /route: .\/[^.]*./ { sub(/^[ \t]+/, ""); print section "|" $0 }
  ' "$1" \
  | grep -oE "^(base|principal|hr|base_actions|principal_actions|hr_actions)\|route: '/[^']*'" \
  | sed -E "s/^(base|principal|hr|base_actions|principal_actions|hr_actions)\|route: '([^']*)'$/\1|\2/"
}

# Section -> apps qui recoivent ces routes.
section_apps() {
  case "$1" in
    base | base_actions) echo "leopardo_employee leopardo_manager leopardo_hr" ;;
    principal | principal_actions) echo "leopardo_manager" ;;
    hr | hr_actions) echo "leopardo_hr" ;;
  esac
}

if [[ ! -f "$MANIFEST" ]]; then
  echo "ERROR: manifeste introuvable: $MANIFEST" >&2
  exit 1
fi

# Extraire les routes de chaque app une seule fois.
declare -A APP_ROUTES
for app in leopardo_employee leopardo_manager leopardo_hr; do
  app_dart="${REPO_ROOT}/front/mobile_apps/${app}/lib/app.dart"
  if [[ ! -f "$app_dart" ]]; then
    echo "ERROR: app.dart introuvable: $app_dart" >&2
    exit 1
  fi
  APP_ROUTES["$app"]="$(app_routes "$app_dart")"
done

while IFS='|' read -r section route; do
  [[ -z "$route" ]] && continue
  for app in $(section_apps "$section"); do
    if ! grep -qxF "$route" <<<"${APP_ROUTES[$app]}"; then
      echo "ERROR: le manifeste sert '$route' (section $section) mais $app ne declare pas cette route GoRoute dans lib/app.dart" >&2
      failures=1
    fi
  done
done < <(parse_manifest "$MANIFEST")

if [[ "$failures" -ne 0 ]]; then
  echo "check-mobile-manifest-routes: ECHEC — aligner les routes du manifeste sur les routeurs reels (voir #2212)." >&2
  exit 1
fi

echo "check-mobile-manifest-routes: OK — toutes les routes du manifeste existent dans les routeurs GoRouter."
