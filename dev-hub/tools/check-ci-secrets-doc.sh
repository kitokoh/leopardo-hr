#!/usr/bin/env bash
#
# Issue #7271 — garde anti-dérive de la documentation des secrets CI/CD.
#
# `docs/CI_CD_SECRETS.md` se présente comme la « single source of truth » des
# secrets/variables des workflows, mais rien n'empêchait la dérive : un
# re-audit (2026-09-13) a trouvé 16 secrets et 17 variables référencés par
# `.github/workflows/**` et absents du document — dont *tous* les secrets de
# déploiement production et les accès base utilisés par les crons de
# supervision. Une doc de secrets incomplète est pire qu'absente : elle donne
# l'illusion d'un inventaire à jour lors d'une rotation ou d'une reprise sur
# incident.
#
# Cette garde échoue dès qu'un `secrets.<NOM>` ou `vars.<NOM>` présent dans un
# workflow n'apparaît pas dans `docs/CI_CD_SECRETS.md`.
#
# Usage : bash dev-hub/tools/check-ci-secrets-doc.sh

set -euo pipefail

DOC="docs/CI_CD_SECRETS.md"
WORKFLOWS_DIR=".github/workflows"

if [[ ! -f "$DOC" ]]; then
  echo "::error::$DOC introuvable — impossible de vérifier la parité documentaire."
  exit 1
fi

if [[ ! -d "$WORKFLOWS_DIR" ]]; then
  echo "::error::$WORKFLOWS_DIR introuvable — impossible d'inventorier les secrets."
  exit 1
fi

status=0

# report <prefixe> <libelle>
report() {
  local prefix="$1"
  local label="$2"
  local -a undocumented=()
  local name

  while IFS= read -r name; do
    [[ -z "$name" ]] && continue
    # Frontière explicite plutôt que `\b` : les noms ne contiennent que
    # [A-Z0-9_] et `\b` se comporte mal entre `_` et une lettre.
    if ! grep -qE "(^|[^A-Z0-9_])${name}([^A-Z0-9_]|\$)" "$DOC"; then
      undocumented+=("$name")
    fi
  # Les lignes entièrement commentées sont ignorées : un commentaire qui cite
  # `secrets.NAME` (ex. justification CodeQL dans mobile-distribute.yml) n'est
  # pas une référence et ne doit pas exiger une entrée documentaire.
  done < <(grep -rhE "${prefix}\.[A-Z0-9_]+" "$WORKFLOWS_DIR" \
             | grep -vE '^[[:space:]]*#' \
             | grep -oE "${prefix}\.[A-Z0-9_]+" \
             | sed "s/${prefix}\.//" \
             | sort -u)

  if [[ ${#undocumented[@]} -gt 0 ]]; then
    echo "::error::${#undocumented[@]} ${label} référencés par les workflows sont ABSENTS de ${DOC} :"
    printf '  - %s\n' "${undocumented[@]}"
    echo "Ajoutez-les au document (nom, usage, obligation, rotation) — la doc doit rester la source de vérité."
    status=1
  else
    echo "OK — les ${label} des workflows sont tous documentés dans ${DOC}."
  fi
}

report "secrets" "secrets"
report "vars" "variables"

exit "$status"
