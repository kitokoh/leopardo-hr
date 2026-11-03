#!/usr/bin/env bash
# check-mobile-app-identity.sh — garde d'IDENTITÉ PRODUIT des apps Flutter.
#
# ## Le défaut que ce garde empêche
#
# Une app Flutter est scaffoldée en copiant une autre. Le code Dart est alors
# réécrit, mais les fichiers d'identité produit — manifeste web, index.html,
# Info.plist, AndroidManifest — restent ceux de l'app d'origine. Résultat : une
# application qui s'appelle « leopardo_rh » dans le tiroir d'applications, dont
# le manifeste PWA déclare une autre app, et dont l'Info.plist porte le nom
# d'une troisième.
#
# C'est exactement ce qui s'est produit pour `leopardo_public` (Couloir 16) :
# la surface publique grand public se présentait comme le produit RH. Le même
# défaut avait déjà été corrigé côté web en M1-bis (#9103) pour la PWA
# `/decouvrir` ; il subsistait côté Flutter. Corrigé en M1-ter (#9110).
#
# ## Portée volontairement étroite
#
# Le garde ne juge QUE les champs qui définissent l'identité d'une application
# pour un utilisateur ou un store :
#
#   - `web/manifest.json`  → `name`, `short_name`, `description`
#   - `web/index.html`     → `<title>`, `apple-mobile-web-app-title`, `description`
#   - `ios/Runner/Info.plist` → `CFBundleName`, `CFBundleDisplayName`
#   - `android/.../AndroidManifest.xml` → `android:label`
#
# Il ne lit PAS les README : un README parle légitimement de `leopardo_core`
# (la bibliothèque partagée) et de l'écosystème. Juger la prose produirait des
# faux positifs en série, et un garde qui crie au loup est désactivé.
#
# ## Dette figée, pas dette ignorée
#
# Plusieurs apps existantes portent déjà une identité empruntée (dette
# antérieure à ce garde). Le dépôt a déjà tranché ce cas pour la duplication
# Flutter (#7652) : une **baseline** fige la dette existante et refuse toute
# NOUVELLE entrée. Ce garde suit le même patron.
#
#   - violation absente de la baseline  → ÉCHEC (nouvelle régression)
#   - violation présente dans la baseline → tolérée, mais signalée
#   - entrée de baseline devenue obsolète → signalée (la liste doit maigrir)
#
# Régénérer après un correctif :
#   bash dev-hub/tools/check-mobile-app-identity.sh --update-baseline
#
# Usage : bash dev-hub/tools/check-mobile-app-identity.sh [--verbose|--update-baseline]
# Sortie : 0 = conforme, 1 = au moins une NOUVELLE violation.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APPS_ROOT="$REPO_ROOT/front/mobile_apps"
BASELINE="$REPO_ROOT/dev-hub/tools/mobile-app-identity-baseline.txt"

MODE="check"
case "${1:-}" in
  --verbose) MODE="verbose" ;;
  --update-baseline) MODE="update" ;;
  "") ;;
  *) echo "usage: $0 [--verbose|--update-baseline]" >&2; exit 2 ;;
esac

[[ -d "$APPS_ROOT" ]] || { echo "::error::front/mobile_apps introuvable"; exit 1; }

# Toutes les apps réelles (dossier avec un pubspec.yaml).
mapfile -t APPS < <(
  for d in "$APPS_ROOT"/*/; do
    [[ -f "$d/pubspec.yaml" ]] && basename "$d"
  done | sort
)
[[ ${#APPS[@]} -gt 0 ]] || { echo "::error::aucune app Flutter trouvée"; exit 1; }

# Noms d'apps HISTORIQUES qui n'ont plus de dossier mais restent des identités
# empruntées reconnaissables. `leopardo_rh` est le cas d'école : l'app RH a été
# scindée en `leopardo_hr`, mais son nom survit dans les manifestes copiés.
# Sans cette liste, réintroduire `leopardo_rh` passerait le garde — c'est
# précisément la régression que le test de mutation doit attraper.
HISTORICAL_APP_TOKENS=("leopardo_rh" "leopardo_mobile_legacy")

# `leopardo_core` est une BIBLIOTHÈQUE partagée, pas une app : la citer est
# normal et attendu. On l'exclut de la comparaison « nom d'une autre app ».
OTHER_APPS=()
for a in "${APPS[@]}"; do
  [[ "$a" == "leopardo_core" ]] && continue
  OTHER_APPS+=("$a")
done
# Les noms historiques comptent comme des identités empruntées : ils n'ont plus
# de dossier, donc la boucle ci-dessus ne les verrait jamais.
for t in "${HISTORICAL_APP_TOKENS[@]}"; do
  OTHER_APPS+=("$t")
done

# Marqueurs du produit RH. Légitimes dans la famille RH, interdits ailleurs.
RH_FAMILY=" leopardo_hr leopardo_employee leopardo_manager leopardo_platform_admin leopardo_core "
RH_MARKERS=("gestionemployerbackend" "api.leopardo-rh.com" "Leopardo RH" "gestion RH")

violations=()

add() { violations+=("$1|$2"); }

# Extrait une valeur de manifeste JSON sans dépendre de jq (présent en CI, mais
# le garde doit tourner partout).
manifest_field() {
  # $1 = fichier, $2 = clé
  sed -n "s/.*\"$2\"[[:space:]]*:[[:space:]]*\"\([^\"]*\)\".*/\1/p" "$1" | head -1
}

for app in "${APPS[@]}"; do
  app_dir="$APPS_ROOT/$app"
  is_rh=0
  [[ "$RH_FAMILY" == *" $app "* ]] && is_rh=1

  # ── 1. Manifeste web ────────────────────────────────────────────────────
  manifest="$app_dir/web/manifest.json"
  if [[ -f "$manifest" ]]; then
    rel="${manifest#"$REPO_ROOT"/}"
    for key in name short_name description; do
      value="$(manifest_field "$manifest" "$key")"
      [[ -z "$value" ]] && continue

      if [[ "$value" == "A new Flutter project." ]]; then
        add "$rel" "description placeholder Flutter non remplacée"
      fi

      for other in "${OTHER_APPS[@]}"; do
        [[ "$other" == "$app" ]] && continue
        if [[ "$value" == *"$other"* ]]; then
          add "$rel" "champ '$key' nomme l'app '$other' (app courante : '$app')"
        fi
      done

      if [[ $is_rh -eq 0 ]]; then
        for marker in "${RH_MARKERS[@]}"; do
          [[ "$value" == *"$marker"* ]] && add "$rel" "champ '$key' porte le marqueur RH « $marker »"
        done
      fi
    done
  fi

  # ── 2. index.html ───────────────────────────────────────────────────────
  index="$app_dir/web/index.html"
  if [[ -f "$index" ]]; then
    rel="${index#"$REPO_ROOT"/}"
    title="$(sed -n 's:.*<title>\(.*\)</title>.*:\1:p' "$index" | head -1)"
    apple="$(sed -n 's:.*apple-mobile-web-app-title" content="\([^"]*\)".*:\1:p' "$index" | head -1)"
    desc="$(sed -n 's:.*name="description" content="\([^"]*\)".*:\1:p' "$index" | head -1)"

    [[ "$desc" == "A new Flutter project." ]] && add "$rel" "meta description placeholder Flutter non remplacée"

    for pair in "title:$title" "apple-title:$apple"; do
      label="${pair%%:*}"; value="${pair#*:}"
      [[ -z "$value" ]] && continue
      for other in "${OTHER_APPS[@]}"; do
        [[ "$other" == "$app" ]] && continue
        [[ "$value" == *"$other"* ]] && add "$rel" "$label nomme l'app '$other' (app courante : '$app')"
      done
      if [[ $is_rh -eq 0 ]]; then
        for marker in "${RH_MARKERS[@]}"; do
          [[ "$value" == *"$marker"* ]] && add "$rel" "$label porte le marqueur RH « $marker »"
        done
      fi
    done
  fi

  # ── 3. Info.plist iOS ───────────────────────────────────────────────────
  plist="$app_dir/ios/Runner/Info.plist"
  if [[ -f "$plist" ]]; then
    rel="${plist#"$REPO_ROOT"/}"
    for key in CFBundleName CFBundleDisplayName; do
      value="$(awk -v k="$key" '
        $0 ~ "<key>"k"</key>" { getline; gsub(/.*<string>|<\/string>.*/, ""); print; exit }
      ' "$plist")"
      [[ -z "$value" ]] && continue
      for other in "${OTHER_APPS[@]}"; do
        [[ "$other" == "$app" ]] && continue
        [[ "$value" == *"$other"* ]] && add "$rel" "$key nomme l'app '$other' (app courante : '$app')"
      done
    done
  fi

  # ── 4. AndroidManifest label ────────────────────────────────────────────
  am="$app_dir/android/app/src/main/AndroidManifest.xml"
  if [[ -f "$am" ]]; then
    rel="${am#"$REPO_ROOT"/}"
    label="$(sed -n 's|.*android:label="\([^"]*\)".*|\1|p' "$am" | head -1)"
    if [[ -n "$label" ]]; then
      for other in "${OTHER_APPS[@]}"; do
        [[ "$other" == "$app" ]] && continue
        [[ "$label" == *"$other"* ]] && add "$rel" "android:label nomme l'app '$other' (app courante : '$app')"
      done
    fi
  fi
done

# ── 5. Cohérence melos ────────────────────────────────────────────────────
# Toute app doit être déclarée, sinon `melos analyze` / `melos test` ne la
# couvrent pas : « CI verte » serait vrai sans que l'app soit analysée.
MELOS="$REPO_ROOT/melos.yaml"
if [[ -f "$MELOS" ]]; then
  for app in "${APPS[@]}"; do
    grep -qF "front/mobile_apps/$app" "$MELOS" \
      || add "melos.yaml" "l'app '$app' n'est pas déclarée (melos ne la couvre pas)"
  done
fi

# Dédoublonnage stable.
mapfile -t violations < <(printf '%s\n' "${violations[@]:-}" | sed '/^$/d' | sort -u)

# ── Mode régénération de baseline ─────────────────────────────────────────
if [[ "$MODE" == "update" ]]; then
  {
    echo "# Baseline d'identité produit des apps Flutter (garde #9110)."
    echo "#"
    echo "# Chaque ligne = une violation PRÉEXISTANTE, figée pour ne pas bloquer"
    echo "# main. Toute NOUVELLE violation est refusée par"
    echo "# dev-hub/tools/check-mobile-app-identity.sh."
    echo "#"
    echo "# Cette liste doit MAIGRIR : corriger une app puis régénérer."
    echo "# Régénérer : bash dev-hub/tools/check-mobile-app-identity.sh --update-baseline"
    printf '%s\n' "${violations[@]:-}"
  } > "$BASELINE"
  echo "✅ Baseline régénérée : ${#violations[@]} entrée(s) → ${BASELINE#"$REPO_ROOT"/}"
  exit 0
fi

# ── Mode vérification ─────────────────────────────────────────────────────
declare -A known=()
if [[ -f "$BASELINE" ]]; then
  while IFS= read -r line; do
    [[ -z "$line" || "$line" == \#* ]] && continue
    known["$line"]=1
  done < "$BASELINE"
fi

new_count=0
frozen_count=0
for v in "${violations[@]:-}"; do
  [[ -z "$v" ]] && continue
  file="${v%%|*}"
  msg="${v#*|}"
  if [[ -n "${known[$v]:-}" ]]; then
    frozen_count=$((frozen_count + 1))
    [[ "$MODE" == "verbose" ]] && echo "  (dette figée) $file — $msg"
  else
    echo "::error file=$file::$msg"
    new_count=$((new_count + 1))
  fi
done

# Entrées de baseline devenues obsolètes : la liste doit maigrir.
stale=0
if [[ -f "$BASELINE" ]]; then
  while IFS= read -r line; do
    [[ -z "$line" || "$line" == \#* ]] && continue
    found=0
    for v in "${violations[@]:-}"; do
      [[ "$v" == "$line" ]] && { found=1; break; }
    done
    if [[ $found -eq 0 ]]; then
      echo "::notice::baseline obsolète (à retirer) : $line"
      stale=$((stale + 1))
    fi
  done < "$BASELINE"
fi

echo ""
if [[ $new_count -gt 0 ]]; then
  echo "❌ $new_count NOUVELLE(S) violation(s) d'identité produit."
  echo "   Une app doit se présenter comme ELLE-MÊME : manifeste web, index.html,"
  echo "   Info.plist et AndroidManifest ne doivent nommer aucune autre app."
  exit 1
fi

echo "✅ Identité produit conforme : ${#APPS[@]} app(s) vérifiée(s), 0 nouvelle violation."
echo "   Dette figée : $frozen_count · baseline obsolète : $stale"
