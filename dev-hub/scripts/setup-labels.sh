#!/bin/bash
set -e

# Script to setup GitHub labels for Leopardo RH
# Requires GitHub CLI (gh) and to be authenticated

# List of labels to create
labels=(
  "good first issue:#7057ff:Ideal for new contributors"
  "help wanted:#008672:Needs help from the community"
  "bug:#d73a4a:Something isn't working"
  "enhancement:#a2eeef:New feature or improvement"
  "documentation:#0075ca:Improvements to documentation"
  "module:payroll:#e4e669:Payroll module"
  "module:leave:#e4e669:Leave management module"
  "module:recruitment:#e4e669:Recruitment module"
  "module:training:#e4e669:Training module"
  "module:tracking:#e4e669:Vehicle tracking module"
  "module:ai:#e4e669:AI features"
  "module:billing:#e4e669:Billing and subscription"
  "surface:api:#bfdadc:Backend API"
  "surface:web:#bfdadc:Dashboard/Web frontend"
  "surface:mobile:#bfdadc:Flutter mobile app"
  "surface:kiosk:#bfdadc:ZKTeco kiosk application"
  "priority:critical:#b60205:Blocking issue"
  "priority:high:#d93f0b:High priority"
  "priority:medium:#fbca04:Medium priority"
  "priority:low:#0e8a16:Low priority"
  "i18n:#c5def5:Internationalization"
  "ci/cd:#c5def5:Continuous Integration / Deployment"
  "tests:#c5def5:Testing related"
)

echo "Setting up GitHub labels for Leopardo RH..."

for label_info in "${labels[@]}"; do
  IFS=":" read -r name color description <<< "$label_info"
  echo "Creating label: $name"
  gh label create "$name" --color "$color" --description "$description" --force
done

echo "Labels setup complete!"
