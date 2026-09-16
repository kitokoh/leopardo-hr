#!/usr/bin/env bash
# check-notification-emitter-diff.sh — garde CI de la décision #7481 (issue #7481).
#
# DÉCISION EN VIGUEUR (2026-09-16, PR #7537) : le store canonique de la
# notification in-app est la table `notifications`, servie par
# `GET /api/v1/notifications` et écrite par `NotificationDispatcher`. Le modèle
# `AppNotification` (table `app_notifications`) est DÉPRÉCIÉ : plus aucun lecteur
# ni écrivain de production. L'ancienne ADR-0013 prévoyait l'inverse (bascule
# vers `app_notifications`) ; elle ne s'applique plus et est marquée « Remplacée ».
#
# Cette garde empêche la dérive inverse : un NOUVEL émetteur qui réimporterait
# `AppNotification` dans le code de production recréerait deux vérités à faire
# diverger — exactement le défaut que #7481 a corrigé (une notification écrite
# par le dispatcher était invisible dans l'inbox).
#
# La garde est DIFF-SCOPED **au niveau de la ligne**, comme `check-i18n-diff.js` :
# seules les lignes AJOUTÉES entre base et head sont examinées. C'est volontaire —
# une garde par fichier refuserait une PR qui corrige une coquille dans un fichier
# qui importait déjà le modèle déprécié. La dette existante ne bloque jamais une
# PR qui ne l'aggrave pas.
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
  diff_text="$(cat "$GUARD_DIFF_FILE")"
else
  if [[ -z "$BASE_SHA" || -z "$HEAD_SHA" ]]; then
    echo "usage: $0 <base_sha> <head_sha>" >&2
    exit 2
  fi
  # Les deux révisions DOIVENT être résolubles. Sans ce contrôle, `git diff`
  # échoue, le diff est vide et la garde passe en silence — le pire mode
  # d'échec pour une garde (un faux négatif qui rassure).
  for rev in "$BASE_SHA" "$HEAD_SHA"; do
    if ! git rev-parse --verify --quiet "${rev}^{commit}" >/dev/null; then
      echo "::error::#7481 : révision introuvable « ${rev} » — la garde ne peut pas comparer."
      exit 2
    fi
  done
  if ! diff_text="$(git diff --no-color "$BASE_SHA" "$HEAD_SHA" -- '*.php')"; then
    echo "::error::#7481 : \`git diff\` a échoué entre ${BASE_SHA} et ${HEAD_SHA} — garde non concluante."
    exit 2
  fi
fi

[[ -z "$diff_text" ]] && { echo "✓ #7481 : aucun diff PHP à analyser (0 ligne)."; exit 0; }

violations=0
current_file=""
line_no=0

while IFS= read -r raw; do
  case "$raw" in
    "diff --git "*) current_file="${raw##* b/}" ; line_no=0 ; continue ;;
    "@@"*)
      # @@ -a,b +c,d @@  → on suit le numéro de ligne du NOUVEAU fichier
      hunk="${raw#*+}"
      line_no="${hunk%%,*}"; line_no="${line_no%% *}"
      [[ "$line_no" =~ ^[0-9]+$ ]] || line_no=0
      continue
      ;;
  esac
  [[ -z "$current_file" ]] && continue

  case "$raw" in
    "+"*) ;;
    *) line_no=$((line_no + 1)); continue ;;
  esac

  content="${raw#+}"
  line_no=$((line_no + 1))

  # Surfaces de production uniquement : `api/app/**` et `api/routes/**`.
  # Les tests qui vérifient la dépréciation (`api/tests/**`), les migrations et
  # le fichier du modèle déprécié lui-même restent légitimes.
  case "$current_file" in
    api/app/*|api/routes/*) ;;
    *) continue ;;
  esac
  case "$current_file" in
    *Domain/Models/AppNotification.php) continue ;;
  esac

  # Un NOUVEL import du modèle déprécié, ou une utilisation directe.
  if [[ "$content" =~ ^[[:space:]]*use[[:space:]]+App\\Modules\\Notification\\Domain\\Models\\AppNotification[[:space:]]*\; ]] \
     || [[ "$content" =~ (new[[:space:]]+AppNotification|AppNotification::|AppNotification\$) ]]; then
    echo "::error file=${current_file},line=${line_no}::#7481 — nouveau code de production sur le modèle DÉPRÉCIÉ AppNotification (table app_notifications). Le store canonique est \`Notification\` (table \`notifications\`), servi par \`GET /api/v1/notifications\` et écrit par \`NotificationDispatcher\`."
    violations=$((violations + 1))
  fi
done <<< "$diff_text"

if [[ "$violations" -gt 0 ]]; then
  echo ""
  echo "❌ ${violations} nouvelle(s) ligne(s) de production sur le modèle déprécié \`AppNotification\`."
  echo ""
  echo "Que faire :"
  echo "  - écrire la notification par le chemin canonique (\`NotificationDispatcher\`,"
  echo "    ou le port \`InAppNotifier\`) — il applique préférences, heures calmes et audit ;"
  echo "  - lire par \`App\\Modules\\Notification\\Domain\\Models\\Notification\`"
  echo "    (\`GET /api/v1/notifications\`)."
  echo ""
  echo "Si l'écriture sur \`app_notifications\` est réellement nécessaire (migration de"
  echo "données, test de la dépréciation), elle doit vivre dans \`api/tests/**\` ou dans"
  echo "une migration — pas dans le code de production (issue #7481)."
  exit 1
fi

echo "✓ #7481 : aucun nouvel émetteur sur le modèle déprécié \`AppNotification\` (0 violation)."
