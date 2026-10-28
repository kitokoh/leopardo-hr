#!/usr/bin/env bash
# check-root-hygiene.sh — garde CI (correctif #8973)
#
# Refuse les fichiers parasites à la RACINE du dépôt.
#
# Deux cas réels l'ont motivée :
#
#   1. Le commit e355952 (#8973) a ajouté un fichier nommé
#        CHANGELOG entry present on main? ==="
#      contenant 150 lignes de la sortie de `man`. Cause : une substitution de
#      commande non quotée du type `git commit -m "... $(man git-commit)"` —
#      la sortie a été découpée en arguments, un `?` et un `=` ont survécu, et
#      le shell a créé un fichier dont le NOM est une question.
#
#   2. `RAPPORT_FINAL_MISSION.md` (commit 8802dfb) : le rapport de session d'un
#      agent commité à la racine. Un rapport de mission est une pièce jointe de
#      conversation, pas un livrable du produit ; il n'a rien à faire dans
#      l'arbre de `main`.
#
# Pourquoi une garde : la revue porte sur le diff de `api/`. Un fichier ajouté
# à la racine ne figure dans aucun diff qu'on regarde, et `git status` reste
# propre — il faut une vérification dédiée pour le voir.
#
# Usage   : bash dev-hub/tools/check-root-hygiene.sh [ref]
#           ref par défaut = HEAD (utiliser origin/main en pré-merge)
# Sortie  : 0 si la racine est propre, 1 sinon (liste dans stderr)

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${REPO_ROOT}"

REF="${1:-HEAD}"

# Liste blanche explicite et exhaustive : chaque entrée existe pour une raison.
# Ajouter un fichier racine doit être un choix conscient — c'est précisément ce
# que cette garde force.
ALLOWED_FILES=(
  .dockerignore
  .editorconfig
  .gitattributes
  .gitignore
  .pre-commit-config.yaml
  .secrets.baseline
  .trivyignore.yaml
  AGENTS.md
  ARCHITECTURE.md
  BRANCH_PROTECTION_REQUIRED.md
  CHANGELOG.md
  CODEOWNERS
  CODE_OF_CONDUCT.md
  CONTRIBUTING.md
  CONVENTIONS.md
  DEVELOPMENT.md
  LICENSE
  Makefile
  README.md
  SECURITY.md
  SUPPORT.md
  docker-compose.yml
  melos.yaml
  package.json
  redocly-strict.yaml
  redocly.yaml
  render.dev-b.yaml
  render.prod-b.yaml
  render.prod.yaml
  render.yaml
)

is_allowed() {
  local candidate="$1" entry
  for entry in "${ALLOWED_FILES[@]}"; do
    [[ "${candidate}" == "${entry}" ]] && return 0
  done
  return 1
}

offenders=()

while IFS= read -r entry; do
  [[ -z "${entry}" ]] && continue
  # Les répertoires sont légitimes : on ne juge que les FICHIERS racine.
  [[ -d "${entry}" ]] && continue
  is_allowed "${entry}" || offenders+=("${entry}")
done < <(git ls-tree --name-only -z "${REF}" | tr '\0' '\n')

if (( ${#offenders[@]} > 0 )); then
  echo "❌  Fichier(s) inattendu(s) à la racine du dépôt (${REF}) :" >&2
  for f in "${offenders[@]}"; do
    # Un nom contenant un espace, `?`, `=`, `"` ou `$` est presque toujours le
    # residu d'une substitution de commande non quotee : on le dit, parce que
    # le nom seul ne suffit pas a faire comprendre la cause.
    case "${f}" in
      *' '*|*'?'*|*'='*|*'"'*|*'$'*)
        echo "    '${f}'  <- nom suspect : residu de substitution de commande non quotee ?" >&2
        ;;
      *)
        echo "    '${f}'" >&2
        ;;
    esac
  done
  echo "" >&2
  echo "Si ce fichier est legitime, ajoutez-le a ALLOWED_FILES dans" >&2
  echo "dev-hub/tools/check-root-hygiene.sh, dans le meme commit." >&2
  exit 1
fi

echo "✅  Racine du depot propre (${REF}) : 0 fichier inattendu."
