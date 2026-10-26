#!/usr/bin/env bash
#
# check-mobile-feature-duplication.sh — garde CI « pas de fork de feature
# mobile » (issue #7652, audit structurel 2026-09-19).
#
# Règle du `front/mobile_apps/README.md` : toute feature partagée vit dans
# `leopardo_core/lib/features/<X>/`. Une app qui recrée le même chemin
# `lib/features/<X>/` fork la feature : les correctifs et les fixes de
# sécurité ne se propagent plus (trois copies divergentes du même écran).
#
# Cette garde bloque toute NOUVELLE duplication. L'existant est gelé dans
# `dev-hub/tools/mobile-feature-duplication-baseline.txt` : la dette ne peut
# que décroître (même doctrine que les baselines PHPStan, #7655).
#
# Usage :
#   bash dev-hub/tools/check-mobile-feature-duplication.sh            # vérifie
#   bash dev-hub/tools/check-mobile-feature-duplication.sh --update   # régénère
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

APPS_DIR="front/mobile_apps"
CORE="${APPS_DIR}/leopardo_core/lib/features"
BASELINE="dev-hub/tools/mobile-feature-duplication-baseline.txt"

if [[ ! -d "${CORE}" ]]; then
  echo "OK — ${CORE} absent (garde sans objet)."
  exit 0
fi

# Liste triée des couples « app/feature » dupliqués depuis le core.
current="$(python3 - "${APPS_DIR}" "${CORE}" <<'PY'
import os, sys

apps_dir, core = sys.argv[1], sys.argv[2]
core_feats = {d for d in os.listdir(core) if os.path.isdir(os.path.join(core, d))}

rows = []
for app in sorted(os.listdir(apps_dir)):
    if not app.startswith('leopardo_') or app == 'leopardo_core':
        continue
    feats = os.path.join(apps_dir, app, 'lib', 'features')
    if not os.path.isdir(feats):
        continue
    for feat in sorted(os.listdir(feats)):
        if feat in core_feats and os.path.isdir(os.path.join(feats, feat)):
            rows.append(f'{app}/lib/features/{feat}')

print('\n'.join(rows))
PY
)"

if [[ "${1:-}" == "--update" ]]; then
  {
    echo "# Features mobiles dupliquées core ↔ apps — gel de la dette #7652."
    echo "#"
    echo "# Chaque ligne est un fork à réconcilier : la version canonique doit"
    echo "# retourner dans leopardo_core (paramétrable par rôle/app via config,"
    echo "# jamais par fork), puis la copie de l'app est supprimée."
    echo "#"
    echo "# La garde check-mobile-feature-duplication.sh refuse toute NOUVELLE"
    echo "# ligne : cette liste ne peut que décroître."
    echo "#"
    echo "# Régénérer : bash dev-hub/tools/check-mobile-feature-duplication.sh --update"
    echo "${current}"
  } > "${BASELINE}"
  echo "Baseline régénérée : ${BASELINE} ($(printf '%s\n' "${current}" | grep -c . || true) entrées)."
  exit 0
fi

if [[ ! -f "${BASELINE}" ]]; then
  echo "ERREUR — baseline absente : ${BASELINE}" >&2
  echo "Générer avec : bash dev-hub/tools/check-mobile-feature-duplication.sh --update" >&2
  exit 1
fi

# Entrées de la baseline (hors commentaires et lignes vides).
baseline="$(grep -v '^#' "${BASELINE}" | grep -v '^[[:space:]]*$' | sort || true)"

new_dupes="$(comm -23 <(printf '%s\n' "${current}" | grep -v '^$' | sort) <(printf '%s\n' "${baseline}" | grep -v '^$' | sort) || true)"

if [[ -n "${new_dupes}" ]]; then
  echo "ERREUR — nouvelle duplication de feature mobile (issue #7652) :" >&2
  echo "${new_dupes}" | sed 's/^/  - /' >&2
  echo "" >&2
  echo "Une feature partagée doit vivre dans leopardo_core/lib/features/." >&2
  echo "Paramétrer le comportement par rôle/app via config, jamais par fork." >&2
  exit 1
fi

resolved="$(comm -13 <(printf '%s\n' "${current}" | grep -v '^$' | sort) <(printf '%s\n' "${baseline}" | grep -v '^$' | sort) || true)"
if [[ -n "${resolved}" ]]; then
  echo "INFO — duplication résorbée, retirer ces lignes de ${BASELINE} :"
  echo "${resolved}" | sed 's/^/  - /'
fi

echo "OK — aucune nouvelle duplication de feature mobile (issue #7652)."
