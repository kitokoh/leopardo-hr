#!/usr/bin/env bash
# Garde des IDENTITES d'applications mobiles — « rien ne doit etre oublie ».
#
# ## Le defaut qu'elle attrape
#
# `leopardo_marketing` declarait `applicationId "com.leopardo.accounting"` :
# deux apps du depot portaient donc le MEME identifiant Android. Rien ne
# l'empechait — ni la garde de couche (`check-mobile-layer-guard.sh`, qui ne
# regarde que `lib/features/**/domain/`), ni `mobile-apps-ci.yml` (qui analyse
# et teste chaque app ISOLEMENT, donc ne voit jamais les identifiants des
# autres). Consequence concrete : les deux APK ne peuvent pas etre installes
# cote a cote sur un appareil, et le second publie ecrase le premier — un
# utilisateur qui installe « Marketing » se retrouve avec un paquet nomme
# `com.leopardo.accounting`.
#
# ## Ce que la garde verifie
#
#   1. chaque app de `front/mobile_apps` (hors `leopardo_core`) declare un
#      `applicationId` Android et un `PRODUCT_BUNDLE_IDENTIFIER` iOS ;
#   2. aucun `applicationId` Android n'est partage par deux apps ;
#   3. aucun bundle id iOS n'est partage par deux apps ;
#   4. l'Android et l'iOS d'une MEME app designent la meme identite ;
#   5. chaque app figure dans `dev-hub/tools/mobile-layer-registry.txt`
#      (le registre de couche reste la source de verite de l'existence) ;
#   6. chaque app figure dans la matrice `flutter analyze` de
#      `.github/workflows/mobile-apps-ci.yml` — une app non analysee est une
#      app dont personne ne voit la casse ;
#   7. chaque app figure dans une matrice de distribution Android
#      (`mobile-distribute-main.yml` ou `mobile-distribute.yml`) — une app
#      qui n'est dans aucune matrice n'a jamais d'APK installe ;
#   8. chaque app de ces matrices a sa BRANCHE dans chaque ternaire d'app id,
#      et cette branche lit le secret que la matrice declare
#      (`firebase_secret`), et le ternaire ne se termine PAS par le repli
#      generique `secrets.FIREBASE_APP_ID`.
#
# Le point 8 est le plus vicieux : une app ajoutee a la matrice mais oubliee
# dans le ternaire resolvait `FIREBASE_APP_ID` vers le secret generique, donc
# vers l'identite Firebase d'une AUTRE app — et l'etape `mobilesdk_app_id`
# validait alors un identifiant different de celui reellement utilise pour
# l'upload. Depuis #9883 le ternaire se termine par une chaine vide (l'oubli
# saute la distribution au lieu de viser la mauvaise app), mais un saut non
# signale reste un canal mort : la garde exige donc la branche, pas le silence.
#
# Exception DOCUMENTEE et bornee : `hr`. Son secret dedie `FIREBASE_APP_ID_HR`
# n'existe pas dans les secrets du depot (verifie le 2026-11-24 par
# `gh secret list`) et son identite Firebase EST le secret historique
# `FIREBASE_APP_ID` (`com.leopardo.rh`). Sa branche lit donc
# `secrets.FIREBASE_APP_ID_HR || secrets.FIREBASE_APP_ID` : supprimer ce repli
# arreterait un canal qui fonctionne aujourd'hui. La garde accepte une branche
# qui contient le secret declare par la matrice, et interdit le repli
# generique en FIN de chaine (qui, lui, s'appliquerait a toutes les apps).
#
# ## Auto-test
#
# Comme les autres gardes du depot, elle se prouve : `--self-test` soumet une
# identite dupliquee a son propre analyseur, puis un ternaire incomplet, et
# echoue si l'un des deux n'est pas signale. Une regex cassee passerait sinon
# au vert pour toujours.
#
# Usage :
#   dev-hub/tools/check-mobile-app-identities.sh [--self-test]
#
# Sortie : exit 0 conforme, exit 1 violation (details sur stdout).

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APPS_DIR="$ROOT/front/mobile_apps"
LAYER_REGISTRY="$ROOT/dev-hub/tools/mobile-layer-registry.txt"
PLATFORM_REGISTRY="$ROOT/dev-hub/tools/mobile-platform-registry.txt"
CI_ANALYZE="$ROOT/.github/workflows/mobile-apps-ci.yml"
CI_DISTRIBUTE_MAIN="$ROOT/.github/workflows/mobile-distribute-main.yml"
CI_DISTRIBUTE_TAGS="$ROOT/.github/workflows/mobile-distribute.yml"

fail=0
error() { echo "::error::$*"; fail=1; }
info() { echo "  $*"; }

# Lit l'`applicationId` Android d'une app (kotlin ou groovy DSL).
android_id() {
  local dir="$1" file
  for file in "$dir"/android/app/build.gradle.kts "$dir"/android/app/build.gradle; do
    [[ -f "$file" ]] || continue
    sed -n 's/^[[:space:]]*applicationId[[:space:]]*[= ][[:space:]]*["'"'"']\([^"'"'"']*\)["'"'"'].*/\1/p' "$file" | head -1
    return 0
  done
}

# Lit le bundle id iOS de l'app (premiere occurrence du project.pbxproj).
ios_id() {
  local dir="$1" file="$dir/ios/Runner.xcodeproj/project.pbxproj"
  [[ -f "$file" ]] || return 0
  sed -n 's/.*PRODUCT_BUNDLE_IDENTIFIER[[:space:]]*=[[:space:]]*\([^;]*\);.*/\1/p' "$file" | head -1 | tr -d ' '
}

apps_in_disk() {
  local d
  for d in "$APPS_DIR"/leopardo_*; do
    [[ -d "$d" ]] || continue
    basename "$d"
  done
}

# Paires "nom secret" de la matrice de distribution d'un workflow.
# Indentation obligatoire : une entree de matrice est `          - name:` et son
# secret `            firebase_secret:` — les steps ont une autre indentation,
# sans quoi `- name: Guard ...` serait lu comme une app.
matrix_pairs() {
  # tr : un worktree CRLF ne doit pas produire "secrets.X", faux rouge
  awk '/^ {10}- name: /{name=$3} /^ {12}firebase_secret: /{if(name!=""){print name" "$2; name=""}}' "$1" | tr -d ''
}

# Verifie UNE ligne de ternaire d'app id contre les paires de la matrice.
check_ternary_line() {
  local line="$1" pairs="$2" name secret
  if [[ "$line" == *"|| secrets.FIREBASE_APP_ID }}"* ]]; then
    error "ternaire d'app id termine par le repli generique \`secrets.FIREBASE_APP_ID\` — une app sans branche heriterait de l'identifiant Firebase d'une AUTRE app"
  fi
  while read -r name secret; do
    [[ -n "$name" ]] || continue
    if [[ "$line" != *"matrix.app.name == '$name'"* ]]; then
      error "l'app '$name' est dans la matrice mais n'a AUCUNE branche dans un ternaire d'app id — sa distribution serait sautee sans que rien ne le dise"
    elif [[ "$line" != *"secrets.$secret"* ]]; then
      error "la branche de '$name' ne lit pas le secret declare par sa matrice ($secret) — l'app viserait une autre identite Firebase"
    fi
  done <<< "$pairs"
}

# --- Auto-test : une duplication injectee DOIT etre signalee -------------------
if [[ "${1:-}" == "--self-test" ]]; then
  # 1. identifiant partage
  declare -A probe=([app_a]="com.leopardo.probe" [app_b]="com.leopardo.probe")
  seen=" "
  detected=0
  for name in "${!probe[@]}"; do
    id="${probe[$name]}"
    if [[ "$seen" == *" $id "* ]]; then
      detected=1
    fi
    seen="$seen$id "
  done
  if [[ "$detected" -ne 1 ]]; then
    echo "::error::auto-test en echec : une duplication injectee n'a pas ete detectee"
    exit 1
  fi

  # 2. ternaire qui oublie une app de la matrice
  probe_pairs=$'employee FIREBASE_EMPLOYEE_ANDROID_APP_ID\nmanager FIREBASE_MANAGER_ANDROID_APP_ID'
  fail=0
  check_ternary_line "\${{ matrix.app.name == 'employee' && secrets.FIREBASE_EMPLOYEE_ANDROID_APP_ID || '' }}" "$probe_pairs" >/dev/null
  if [[ "$fail" -ne 1 ]]; then
    echo "::error::auto-test en echec : un ternaire qui oublie l'app 'manager' n'a pas ete signale"
    exit 1
  fi

  # 3. ternaire complet : ne doit RIEN signaler
  fail=0
  check_ternary_line "\${{ matrix.app.name == 'employee' && secrets.FIREBASE_EMPLOYEE_ANDROID_APP_ID || matrix.app.name == 'manager' && secrets.FIREBASE_MANAGER_ANDROID_APP_ID || '' }}" "$probe_pairs" >/dev/null
  if [[ "$fail" -ne 0 ]]; then
    echo "::error::auto-test en echec : un ternaire complet a ete signale a tort"
    exit 1
  fi

  # 4. repli generique en fin de chaine
  fail=0
  check_ternary_line "\${{ matrix.app.name == 'employee' && secrets.FIREBASE_EMPLOYEE_ANDROID_APP_ID || matrix.app.name == 'manager' && secrets.FIREBASE_MANAGER_ANDROID_APP_ID || '' || secrets.FIREBASE_APP_ID }}" "$probe_pairs" >/dev/null
  if [[ "$fail" -ne 1 ]]; then
    echo "::error::auto-test en echec : un repli generique en fin de chaine n'a pas ete signale"
    exit 1
  fi

  echo "auto-test OK : duplication, ternaire incomplet et repli generique signales"
  exit 0
fi

[[ -d "$APPS_DIR" ]] || { echo "::error::dossier introuvable : $APPS_DIR"; exit 1; }

declare -A android_owner=()
declare -A ios_owner=()
declare -A registry=()
declare -A platform_of=()

if [[ -f "$LAYER_REGISTRY" ]]; then
  while read -r name _rest; do
    [[ -z "${name:-}" || "${name:0:1}" == "#" ]] && continue
    registry["$name"]=1
  done < "$LAYER_REGISTRY"
else
  error "registre de couche introuvable : dev-hub/tools/mobile-layer-registry.txt"
fi

if [[ -f "$PLATFORM_REGISTRY" ]]; then
  while read -r name plats _rest; do
    [[ -z "${name:-}" || "${name:0:1}" == "#" ]] && continue
    platform_of["$name"]="$plats"
  done < "$PLATFORM_REGISTRY"
else
  error "registre de plateformes introuvable : dev-hub/tools/mobile-platform-registry.txt"
fi

app_count=0
while read -r app; do
  # Packages partages : pas des apps, aucun binaire, aucune identite.
  [[ "$app" == "leopardo_core" || "$app" == "leopardo_surface" ]] && continue
  app_count=$((app_count + 1))
  dir="$APPS_DIR/$app"

  aid="$(android_id "$dir")"
  iid="$(ios_id "$dir")"

  if [[ -z "$aid" ]]; then
    error "$app : aucun applicationId Android declare (android/app/build.gradle[.kts])"
  else
    if [[ -n "${android_owner[$aid]:-}" ]]; then
      error "$app : applicationId '$aid' deja utilise par ${android_owner[$aid]} — deux apps ne peuvent pas partager un identifiant Android"
    else
      android_owner["$aid"]="$app"
      info "$app → android $aid"
    fi
  fi

  plats="${platform_of[$app]:-}"
  if [[ -z "$plats" ]]; then
    error "$app : absent de dev-hub/tools/mobile-platform-registry.txt — declarer ses plateformes (android / android,ios), jamais les deduire par oubli"
    plats="android,ios"
  fi

  has_ios_dir=0
  [[ -d "$dir/ios" ]] && has_ios_dir=1
  if [[ "$plats" == *ios* && "$has_ios_dir" -eq 0 ]]; then
    error "$app : la plateforme 'ios' est declaree mais aucun dossier ios/ n'existe"
  fi
  if [[ "$plats" != *ios* && "$has_ios_dir" -eq 1 ]]; then
    error "$app : dossier ios/ present mais 'ios' non declare dans le registre de plateformes"
  fi

  if [[ "$plats" == *ios* ]]; then
    if [[ -z "$iid" ]]; then
      error "$app : aucun PRODUCT_BUNDLE_IDENTIFIER iOS declare"
    elif [[ -n "${ios_owner[$iid]:-}" ]]; then
      error "$app : bundle id iOS '$iid' deja utilise par ${ios_owner[$iid]}"
    else
      ios_owner["$iid"]="$app"
      info "$app → ios $iid"
    fi

    if [[ -n "$aid" && -n "$iid" && "$aid" != "$iid" ]]; then
      error "$app : Android ($aid) et iOS ($iid) ne designent pas la meme identite — corriger l'un des deux, jamais les deux en silence"
    fi
  fi

  if [[ -z "${registry[$app]:-}" ]]; then
    error "$app : absent de dev-hub/tools/mobile-layer-registry.txt (classer l'app, jamais heriter d'un defaut par oubli)"
  fi

  for wf in "$CI_ANALYZE" "$CI_DISTRIBUTE_MAIN" "$CI_DISTRIBUTE_TAGS"; do
    if [[ ! -f "$wf" ]]; then
      error "workflow introuvable : ${wf#"$ROOT"/}"
    elif ! grep -q "front/mobile_apps/$app\([^a-z_]\|$\)" "$wf"; then
      error "$app : absent de la matrice ${wf#"$ROOT"/} — l'app existe sur disque mais n'est ni analysee ni distribuee par la CI"
    fi
  done
done < <(apps_in_disk)

if [[ "$app_count" -eq 0 ]]; then
  echo "::error::aucune app trouvee sous front/mobile_apps — la garde ne peut pas etre vide"
  exit 1
fi

# --- Chaque app de matrice a SA branche dans CHAQUE ternaire d'app id --------
# Un ternaire d'app id, c'est : l'env `FIREBASE_APP_ID:` et le `appId:` de
# l'action de distribution. Les deux doivent couvrir la meme matrice : sinon
# l'app id VALIDE par l'etape `mobilesdk_app_id` et l'app id UTILISE pour
# l'upload divergent.
for wf in "$CI_DISTRIBUTE_MAIN" "$CI_DISTRIBUTE_TAGS"; do
  [[ -f "$wf" ]] || continue   # deja signale dans la boucle des apps
  rel="${wf#"$ROOT"/}"
  pairs="$(matrix_pairs "$wf")"
  if [[ -z "$pairs" ]]; then
    error "$rel : aucune paire (app, firebase_secret) lisible dans la matrice — la garde refuse de valider une matrice vide"
    continue
  fi
  ternary_count=0
  while IFS= read -r line; do
    [[ -n "$line" ]] || continue
    ternary_count=$((ternary_count + 1))
    before_fail="$fail"
    check_ternary_line "$line" "$pairs"
    if [[ "$fail" -ne "$before_fail" ]]; then
      error "  ^ dans ${rel}, ligne : $(echo "$line" | cut -c1-120)…"
    fi
  done < <(grep "FIREBASE_APP_ID: \${{.*matrix.app.name ==\|appId: \${{.*matrix.app.name ==" "$wf")
  if [[ "$ternary_count" -eq 0 ]]; then
    error "$rel : aucun ternaire d'app id par app — la distribution ne peut pas viser l'app de sa matrice"
  else
    info "${rel#"$ROOT"/} : $ternary_count ternaire(s) d'app id couvrant $(echo "$pairs" | wc -l | tr -d ' ') apps"
  fi
done

if [[ "$fail" -ne 0 ]]; then
  echo "Garde des identites mobiles : ECHEC ($app_count apps examinees)"
  exit 1
fi

echo "Garde des identites mobiles : OK ($app_count apps, identifiants uniques, matrices et ternaires d'app id complets)"
exit 0
