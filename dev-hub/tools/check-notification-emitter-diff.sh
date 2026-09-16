#!/usr/bin/env bash
# check-notification-emitter-diff.sh — garde CI de l'ADR-0013 (issue #7481).
#
# L'ADR 0013 tranche la coexistence des deux stores de notifications in-app :
# le canal CIBLE est `app_notifications` (écrit par `NotificationDispatcher`,
# exposé via le contrat Core `InAppNotifier`). La table historique
# `notifications` sort progressivement ; elle ne doit plus gagner de NOUVEAU
# émetteur, sinon la dette s'aggrave au lieu de se résorber.
#
# La garde est DIFF-SCOPED **au niveau de la ligne**, comme
# `check-i18n-diff.js` : elle n'examine que les lignes AJOUTÉES entre base et
# head. C'est volontaire : une garde par fichier refuserait une PR qui ne fait
# que corriger une coquille dans un fichier qui importait déjà le modèle
# historique. Ici, seule une NOUVELLE ligne d'import est refusée — la dette
# existante ne bloque jamais une PR qui ne l'aggrave pas.
#
# Usage : check-notification-emitter-diff.sh <base_sha> <head_sha>
#   GUARD_DIFF_FILE : (auto-test) fichier contenant un diff à analyser, au lieu
#                     d'interroger git.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

BASE_SHA="${1:-}"
HEAD_SHA="${2:-}"

if [[ -n "${GUARD_DIFF_FILE:-}" ]]; then
  # Mode auto-test : la garde analyse un diff fourni, sans git.
  diff_text="$(cat "$GUARD_DIFF_FILE")"
else
  if [[ -z "$BASE_SHA" || -z "$HEAD_SHA" ]]; then
    echo "usage: $0 <base_sha> <head_sha>" >&2
    exit 2
  fi
  # Les deux révisions DOIVENT être résolubles. Sans ce contrôle, `git diff`
  # échoue, la substitution vide le diff, et la garde **passe en silence** —
  # le pire mode d'échec pour une garde : un faux négatif qui rassure.
  # (Constaté pour de vrai dans un clone superficiel, où une révision de base
  # non récupérée donnait un « ✓ » mensonger.)
  for rev in "$BASE_SHA" "$HEAD_SHA"; do
    if ! git rev-parse --verify --quiet "${rev}^{commit}" >/dev/null; then
      echo "::error::ADR-0013 : révision introuvable « ${rev} » — la garde ne peut pas comparer."
      echo "::error::Récupérer la révision (git fetch --depth=1 origin <sha>) avant de relancer."
      exit 2
    fi
  done
  # `|| true` a été RETIRÉ : une erreur git doit faire échouer la garde, pas
  # l'ignorer. On distingue « pas de diff » (0 fichier) d'« échec de git ».
  if ! diff_text="$(git diff --no-color "$BASE_SHA" "$HEAD_SHA" -- '*.php')"; then
    echo "::error::ADR-0013 : \`git diff\` a échoué entre ${BASE_SHA} et ${HEAD_SHA} — garde non concluante."
    exit 2
  fi
fi

[[ -z "$diff_text" ]] && { echo "✓ ADR-0013 : aucun diff PHP à analyser (0 ligne)."; exit 0; }

# Chemin des fichiers dont une nouvelle ligne d'import est un vrai émetteur.
# ERE (=~) : `+` quantifie, et `\\` matche UN antislash littéral (un `use` PHP
# contient un seul antislash par séparateur de namespace).
LEGACY_IMPORT='use[[:space:]]+App\\Modules\\Notification\\Domain\\Models\\Notification'

# Chemins de MAINTENANCE : une migration doit pouvoir écrire la table, un test
# doit pouvoir la lire, et le modèle vit forcément chez lui. Comparaison par
# préfixe/suffixe explicites — une regex d'allowlist avait déjà laissé passer
# un `\\.php` (antislash littéral) qui ne matchait rien.
is_allowlisted() {
  local f="$1"
  case "$f" in
    api/database/migrations/*) return 0 ;;
    api/tests/*)               return 0 ;;
    *Notification/Domain/Models/Notification.php) return 0 ;;
  esac
  return 1
}

violations=""
current=""
while IFS= read -r line; do
  case "$line" in
    "+++ b/"*) current="${line#+++ b/}" ; continue ;;
    "+++"*)    continue ;;
  esac
  # ligne ajoutée (et pas l'en-tête +++ qui commence aussi par +)
  [[ "$line" == +* ]] || continue
  [[ "$line" == "+++"* ]] && continue
  content="${line#+}"
  # Le filtre `*.php` de `git diff` ne s'applique qu'au chemin git. En mode
  # auto-test (diff fourni), il faut le refaire ici — sinon le test local ne
  # parcourt pas le même chemin que la CI (constaté : le script d'auto-test se
  # flaggait lui-même, parce qu'il contient la chaîne recherchée).
  [[ "$current" == *.php ]] || continue
  if [[ "$content" =~ $LEGACY_IMPORT ]]; then
    if [[ -n "$current" ]] && ! is_allowlisted "$current"; then
      violations+="${current}"$'\n'
    fi
  fi
done <<< "$diff_text"

if [[ -n "$violations" ]]; then
  echo "::error::ADR-0013 — nouvel émetteur sur le canal historique \`notifications\` :"
  printf '%s' "$violations" | sort -u | while IFS= read -r f; do echo "::error::  - $f"; done
  echo "Le canal cible est \`app_notifications\` : passer par NotificationDispatcher"
  echo "ou le contrat Core InAppNotifier (docs/architecture/adr/0013-notifications-read-path-unification.md)."
  exit 1
fi

echo "✓ ADR-0013 : aucun NOUVEAU émetteur sur le canal historique \`notifications\`."
exit 0
