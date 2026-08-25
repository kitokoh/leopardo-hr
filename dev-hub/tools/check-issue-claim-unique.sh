#!/usr/bin/env bash
#
# Garde anti-doublon « une issue = une PR » (issue #5442).
#
# Détecte les issues GitHub référencées par PLUSIEURS PR ouvertes via les
# mots-clés de fermeture `Closes/Fixes/Resolves #N` (titre + body). Le guard
# PA2 existant (check-plan-action2-claim.sh) ne couvre que les tickets
# PA2-*, pas les issues GitHub — d'où des doublons récurrents (#5239 ×3,
# #5270 ×2, #5235 ×2, #5356 ×2).
#
# Comportement par défaut : `::warning` non bloquant (rollout progressif —
# des doublons connus existent encore). Passer en bloquant (`--blocking`)
# une fois les doublons résolus (cf. issue #5442).
#
# Prérequis : GH_TOKEN (GITHUB_TOKEN en CI) + jq.
# Usage : dev-hub/tools/check-issue-claim-unique.sh [--blocking]
set -euo pipefail

REPO="${GITHUB_REPOSITORY:-kitokoh/leopardo-hr}"
TOKEN="${GH_TOKEN:-}"
BLOCKING=0
if [[ "${1:-}" == "--blocking" ]]; then
  BLOCKING=1
fi

if [[ -z "$TOKEN" ]]; then
  echo "::error::GH_TOKEN requis pour la garde anti-doublon (issue #5442)."
  exit 1
fi

PRS_JSON="$(curl -sf --max-time 30 -H "Authorization: Bearer $TOKEN" \
  "https://api.github.com/repos/$REPO/pulls?state=open&per_page=100")" || {
  echo "::error::Impossible de lister les PR ouvertes (API GitHub)."
  exit 1
}

# issue -> liste de PR (titre+body analysés pour Closes/Fixes/Resolves #N)
declare -A CLAIMS
while IFS=$'\t' read -r number title body; do
  refs="$(printf '%s %s' "$title" "$body" \
    | grep -oE '(Closes|Fixes|Resolves) #[0-9]+' \
    | grep -oE '#[0-9]+' | sort -u || true)"
  for ref in $refs; do
    issue="${ref#\#}"
    CLAIMS["$issue"]="${CLAIMS[$issue]:-}${CLAIMS[$issue]:+,}$number"
  done
done < <(jq -r '.[] | [.number, (.title // ""), (.body // "")] | @tsv' <<<"$PRS_JSON")

duplicates=0
for issue in "${!CLAIMS[@]}"; do
  prs="${CLAIMS[$issue]}"
  count="$(tr ',' '\n' <<<"$prs" | sort -u | wc -l | tr -d ' ')"
  if (( count > 1 )); then
    duplicates=$((duplicates + 1))
    if (( BLOCKING )); then
      echo "::error::Doublon détecté : issue #$issue fermée par $count PR ouvertes ($prs). Protocole #2400 : fermer les PR redondantes."
    else
      echo "::warning::Doublon détecté : issue #$issue fermée par $count PR ouvertes ($prs). Protocole #2400 : fermer les PR redondantes."
    fi
  fi
done

if (( duplicates > 0 )); then
  if (( BLOCKING )); then
    echo "::error::$duplicates issue(s) avec PR multiples — une issue = une PR (issue #5442)."
    exit 1
  fi
  echo "::warning::$duplicates issue(s) avec PR multiples (mode non bloquant — voir issue #5442)."
  exit 0
fi

echo "::notice::Anti-doublon OK : aucune issue fermée par plus d'une PR ouverte."
