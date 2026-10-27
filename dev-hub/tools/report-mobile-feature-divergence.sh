#!/usr/bin/env bash
#
# report-mobile-feature-divergence.sh — inventaire des divergences entre la
# copie canonique d'une feature (`leopardo_core/lib/features/<X>/`) et ses
# forks dans les apps (issue #7652).
#
# La garde `check-mobile-feature-duplication.sh` empêche la dette de croître ;
# ce rapport mesure la dette restante pour piloter la réconciliation : pour
# chaque couple app/feature, combien de fichiers divergent, lesquels sont
# absents d'un côté, et le volume de lignes concerné.
#
# Usage :
#   bash dev-hub/tools/report-mobile-feature-divergence.sh            # tableau
#   bash dev-hub/tools/report-mobile-feature-divergence.sh --details  # + fichiers
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

APPS_DIR="front/mobile_apps"
CORE="${APPS_DIR}/leopardo_core/lib/features"
BASELINE="dev-hub/tools/mobile-feature-duplication-baseline.txt"
DETAILS="${1:-}"

if [[ ! -f "${BASELINE}" ]]; then
  echo "ERREUR — baseline absente : ${BASELINE}" >&2
  exit 1
fi

printf '%-28s %-22s %8s %8s %8s %8s\n' "APP" "FEATURE" "DIVERGE" "CORE_ONLY" "APP_ONLY" "LIGNES"
printf '%.0s-' {1..90}; echo

total_files=0
total_lines=0

while IFS= read -r entry; do
  [[ -z "${entry}" || "${entry}" == \#* ]] && continue

  app="${entry%%/lib/features/*}"
  feature="${entry##*/lib/features/}"
  core_dir="${CORE}/${feature}"
  app_dir="${APPS_DIR}/${app}/lib/features/${feature}"

  [[ -d "${core_dir}" && -d "${app_dir}" ]] || continue

  diverge=0; core_only=0; app_only=0; lines=0

  # Fichiers présents des deux côtés mais différents.
  while IFS= read -r f; do
    [[ -z "${f}" ]] && continue
    rel="${f#${core_dir}/}"
    if [[ -f "${app_dir}/${rel}" ]]; then
      if ! cmp -s "${f}" "${app_dir}/${rel}"; then
        diverge=$((diverge + 1))
        n=$(diff "${f}" "${app_dir}/${rel}" | grep -c '^[<>]' || true)
        lines=$((lines + n))
        [[ "${DETAILS}" == "--details" ]] && echo "  ~ ${app}/${feature}/${rel} (${n} lignes)"
      else
        core_only=$((core_only + 0))
      fi
    else
      core_only=$((core_only + 1))
      [[ "${DETAILS}" == "--details" ]] && echo "  + core seul : ${app}/${feature}/${rel}"
    fi
  done < <(find "${core_dir}" -type f -name '*.dart' | sort)

  # Fichiers présents uniquement dans l'app.
  while IFS= read -r f; do
    [[ -z "${f}" ]] && continue
    rel="${f#${app_dir}/}"
    if [[ ! -f "${core_dir}/${rel}" ]]; then
      app_only=$((app_only + 1))
      [[ "${DETAILS}" == "--details" ]] && echo "  - app seul : ${app}/${feature}/${rel}"
    fi
  done < <(find "${app_dir}" -type f -name '*.dart' | sort)

  printf '%-28s %-22s %8d %8d %8d %8d\n' "${app}" "${feature}" "${diverge}" "${core_only}" "${app_only}" "${lines}"
  total_files=$((total_files + diverge + core_only + app_only))
  total_lines=$((total_lines + lines))
done < "${BASELINE}"

printf '%.0s-' {1..90}; echo
printf 'TOTAL : %d fichiers divergents/absents, %d lignes de diff\n' "${total_files}" "${total_lines}"
echo
echo "Réconciliation : porter la version canonique dans leopardo_core (comportement"
echo "paramétré par rôle/app via config), puis supprimer la copie de l'app et"
echo "retirer la ligne correspondante de ${BASELINE}."
