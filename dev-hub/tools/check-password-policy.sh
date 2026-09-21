#!/usr/bin/env bash
# Garde #7995 — politique de mots de passe unique (norme #5620).
#
# Toute surface qui collecte un mot de passe DOIT passer par
# App\Shared\Rules\PasswordPolicy (Password::min(12)->numbers() +
# NotCommonPassword). Un `min:8` (ou tout min:<12) sur un champ password
# dans api/app est une régression : le compte serait moins protégé à sa
# création qu'à son changement de mot de passe.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
API="$ROOT/api"

# Champ `password` validé avec une règle min: faible, sur la même ligne ou
# dans les 6 lignes suivantes (tableaux de règles multi-lignes).
HITS="$(grep -RInE --include='*.php' "'password(_confirmation)?'\s*=>" "$API/app" \
  | grep -v "PasswordPolicy" \
  | cut -d: -f1-2 \
  | while IFS=: read -r file line; do
      end=$((line + 6))
      if sed -n "${line},${end}p" "$file" | grep -qE "'min:[1-9]'|min\([1-9]\)|'min:1[01]'"; then
        echo "$file:$line"
      fi
    done)"

if [ -n "$HITS" ]; then
  echo "❌ Politique mots de passe contournée (#7995, norme #5620) :"
  echo "$HITS" | while read -r hit; do echo "   $hit"; done
  echo ""
  echo "→ utiliser App\\Shared\\Rules\\PasswordPolicy::required()/optional()."
  exit 1
fi

echo "✅ Politique mots de passe unique respectée (#7995)."
