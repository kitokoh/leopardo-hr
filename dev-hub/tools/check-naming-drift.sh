#!/usr/bin/env bash
# ============================================================
# check-naming-drift.sh — garde de non-régression du nommage produit
# (issue #7428, décision : docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md)
#
# Contexte : le produit était présenté comme un « logiciel RH » / « HR SaaS »
# alors que c'est une **suite métier** (RH + outils horizontaux + verticales).
# La correction des copies publiques se fait par étapes (copy marketing, e-mails,
# stores) : tant que cette dette existe, elle ne doit pas **grossir**.
#
# Cette garde MESURE la dette au lieu de la masquer :
#   - `dev-hub/tools/naming-baseline.json` fixe le nombre d'occurrences
#     interdites **par fichier** (instantané de référence) ;
#   - toute occurrence SUPPLÉMENTAIRE (fichier de la baseline, ou fichier
#     nouvellement touché) fait échouer la garde ;
#   - une BAISSE est signalée comme un progrès (et doit être reportée dans la
#     baseline via `--update-baseline`, visible en revue).
#
# Elle vérifie aussi que la **règle** est toujours écrite (MESSAGE.md) : une
# décision effacée est un bug de gouvernance.
#
# Usage :
#   dev-hub/tools/check-naming-drift.sh                 # vérifie (CI)
#   dev-hub/tools/check-naming-drift.sh --update-baseline
#   dev-hub/tools/check-naming-drift.sh --self-test     # prouve le détecteur
#
# Sortie : 0 conforme · 1 dérive détectée · 2 erreur d'usage/environnement
# ============================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BASELINE="${ROOT}/dev-hub/tools/naming-baseline.json"

# Surfaces publiques à surveiller (chemins relatifs au dépôt).
SCAN_PATHS=(
  "README.md"
  "front/web/src/app/layout.tsx"
  "front/web/src/app/manifest/route.ts"
  "front/web/src/lib/i18n.ts"
  "front/web/src/lib/i18n/locales"
  "front/web/src/modules/vitrine"
  "front/admin-dashboard/index.html"
  "front/admin-dashboard/src/i18n/locales"
  "site/gh-pages/index.html"
  "api/lang"
)

# Catégories INTERDITES : elles définissent le produit comme un logiciel RH.
# Volontairement en minuscules, comparées sur une forme normalisée.
FORBIDDEN_PATTERNS=(
  "logiciel rh"
  "saas rh"
  "hr saas"
  "hr software"
  "ik yazilimi"
  "نظام موارد بشرية سحابي"
)

normalize() { tr '[:upper:]' '[:lower:]' | sed -e 's/[[:space:]]\+/ /g'; }

# Compte, par fichier, les occurrences interdites. Sortie : "<chemin>\t<total>".
count_occurrences() {
  local root="$1"; shift
  local paths=("$@")
  local -A per_file=()
  local rel p pattern hits

  for rel in "${paths[@]}"; do
    p="${root}/${rel}"
    [[ -e "${p}" ]] || continue
    if [[ -d "${p}" ]]; then
      while IFS= read -r f; do
        [[ -f "${f}" ]] || continue
        per_file["${f#${root}/}"]=0
      done < <(find "${p}" -type f \( -name '*.md' -o -name '*.ts' -o -name '*.tsx' -o -name '*.json' -o -name '*.html' -o -name '*.php' \) | sort)
    else
      per_file["${rel}"]=0
    fi
  done

  for rel in "${!per_file[@]}"; do
    for pattern in "${FORBIDDEN_PATTERNS[@]}"; do
      hits="$(normalize < "${root}/${rel}" | grep -o -F "${pattern}" | wc -l | tr -d ' ')"
      per_file["${rel}"]=$(( per_file["${rel}"] + hits ))
    done
  done

  for rel in "${!per_file[@]}"; do
    printf '%s\t%s\n' "${rel}" "${per_file[${rel}]}"
  done | sort
}

run_self_test() {
  local tmp rc=0
  tmp="$(mktemp -d)"
  trap 'rm -rf "${tmp}"' RETURN

  # Fixture 1 — une phrase de présentation interdite DOIT être détectée.
  mkdir -p "${tmp}/dirty/front/web/src/app"
  printf '%s\n' "Leopardo RH, le logiciel RH le plus complet." > "${tmp}/dirty/README.md"
  printf '%s\n' "export const t = 'HR SaaS for field teams';" > "${tmp}/dirty/front/web/src/app/layout.tsx"
  local dirty
  dirty="$(count_occurrences "${tmp}/dirty" README.md front/web/src/app/layout.tsx)"
  if [[ "$(printf '%s' "${dirty}" | awk -F'\t' '{s+=$2} END{print s+0}')" != "2" ]]; then
    echo "FAIL self-test : le détecteur a manqué une phrase interdite → ${dirty}" >&2
    rc=1
  fi

  # Fixture 2 — les usages LÉGITIMES ne doivent PAS déclencher :
  #   - « RH & paie » comme contenu, « suite métier » comme catégorie ;
  #   - un identifiant technique figé (dépôt/domaine) hors phrase de présentation.
  mkdir -p "${tmp}/clean/front/web/src/app"
  {
    printf '%s\n' "Leopardo est la suite métier des entreprises de terrain — RH & paie, pointage, CRM."
    printf '%s\n' "cd kitokoh/leopardo-hr && git remote -v  # identifiant technique figé"
  } > "${tmp}/clean/README.md"
  printf '%s\n' "export const NAME = 'Leopardo'; export const CATEGORY = 'business suite';" > "${tmp}/clean/front/web/src/app/layout.tsx"
  local clean total
  clean="$(count_occurrences "${tmp}/clean" README.md front/web/src/app/layout.tsx)"
  total="$(printf '%s' "${clean}" | awk -F'\t' '{s+=$2} END{print s+0}')"
  if [[ "${total}" != "0" ]]; then
    echo "FAIL self-test : faux positif sur un usage légitime → ${clean}" >&2
    rc=1
  fi

  if [[ "${rc}" = "0" ]]; then
    echo "OK self-test : 2 détections attendues, 0 faux positif sur les usages légitimes et les identifiants figés."
  fi
  return "${rc}"
}

mode="${1:-check}"
case "${mode}" in
  --self-test) run_self_test; exit $? ;;
  --update-baseline)
    # Seuls les fichiers à DETTE sont inscrits : un fichier absent vaut 0.
    count_occurrences "${ROOT}" "${SCAN_PATHS[@]}" \
      | awk -F'\t' '$2 > 0 {printf "  \"%s\": %s,\n", $1, $2}' > /tmp/naming-baseline.body
    {
      echo "{"
      echo "  \"_comment\": \"#7428 — occurrences restantes de catégories interdites (SaaS RH / HR SaaS / HR software…), mesurées par fichier. Toutes sont des MOTS-CLÉS SEO ou des usages descriptifs conservés volontairement (critère 4 : ne pas perdre le trafic) — voir POSITIONNEMENT_SUITE_METIER.md §6. Ce nombre ne peut que DÉCROÎTRE : toute hausse = dérive de nommage. Régénérer avec dev-hub/tools/check-naming-drift.sh --update-baseline.\","
      sed -e '$ s/,$//' /tmp/naming-baseline.body
      echo "}"
    } > "${BASELINE}"
    echo "Baseline mise à jour : ${BASELINE}"
    exit 0
    ;;
  check|"") ;;
  *) echo "Usage: check-naming-drift.sh [--update-baseline|--self-test]" >&2; exit 2 ;;
esac

command -v jq >/dev/null 2>&1 || { echo "jq requis" >&2; exit 2; }
[[ -f "${BASELINE}" ]] || { echo "Baseline absente : ${BASELINE}" >&2; exit 2; }

fail=0
current="$(count_occurrences "${ROOT}" "${SCAN_PATHS[@]}")"

while IFS=$'\t' read -r file count; do
  allowed="$(jq -r --arg f "${file}" '.[$f] // 0' "${BASELINE}")"
  [[ "${allowed}" == "null" ]] && allowed=0
  if (( count > allowed )); then
    echo "FAIL: ${file} contient ${count} catégorie(s) interdite(s) (référence : ${allowed})." >&2
    echo "      Utiliser « suite métier » / « business suite » (docs/REFERENTIEL_PRODUIT/TERMES.md)." >&2
    fail=1
  elif (( count < allowed )); then
    echo "PROGRÈS: ${file} : ${count} (référence ${allowed}) — reporter dans la baseline (--update-baseline)."
  fi
done <<< "${current}"

# La règle doit rester écrite : une décision effacée est un bug de gouvernance.
for required in "suite métier" ; do
  grep -q -F "${required}" "${ROOT}/docs/REFERENTIEL_PRODUIT/MESSAGE.md" \
    || { echo "FAIL: MESSAGE.md ne porte plus la catégorie « ${required} » (#7428)." >&2; fail=1; }
done

if (( fail )); then
  echo "→ DÉRIVE DE NOMMAGE (#7428)." >&2
  exit 1
fi

echo "OK — pas de nouvelle catégorie interdite (dette gelée, mesurée)."
