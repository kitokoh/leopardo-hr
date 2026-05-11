#!/usr/bin/env bash
# Create organized GitHub labels for Leopardo RH.
# Usage: GITHUB_TOKEN=xxx ./scripts/setup-labels.sh
set -euo pipefail

REPO="kitokoh/leopardo-hr"

labels=(
  "good first issue|#7057ff|Facile pour un nouveau contributeur"
  "help wanted|#008672|Besoin d aide de la communaute"
  "bug|#d73a4a|Quelque chose ne fonctionne pas"
  "enhancement|#a2eeef|Nouvelle fonctionnalite"
  "documentation|#0075ca|Amelioration de la documentation"
  "module:payroll|#e4e669|Module Paie"
  "module:leave|#e4e669|Module Conges"
  "module:recruitment|#e4e669|Module Recrutement"
  "module:training|#e4e669|Module Formation"
  "module:tracking|#e4e669|Module Tracking vehicules"
  "module:ai|#e4e669|Module IA"
  "module:billing|#e4e669|Module Billing"
  "surface:api|#bfdadc|Backend API"
  "surface:web|#bfdadc|Dashboard web"
  "surface:mobile|#bfdadc|App mobile Flutter"
  "surface:kiosk|#bfdadc|Kiosk ZKTeco"
  "priority:critical|#b60205|Bloquant"
  "priority:high|#d93f0b|Important"
  "priority:medium|#fbca04|Normal"
  "priority:low|#0e8a16|Bas"
  "i18n|#c5def5|Internationalisation"
  "ci/cd|#c5def5|Integration continue"
  "tests|#c5def5|Tests"
)

for entry in "${labels[@]}"; do
  IFS='|' read -r name color description <<< "$entry"
  echo "Creating label: $name"
  gh label create "$name" --color "${color#\#}" --description "$description" --repo "$REPO" --force 2>/dev/null || true
done

echo "Done — all labels created."
