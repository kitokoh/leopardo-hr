#!/usr/bin/env bash
#
# Garde CI de parité table-docs ↔ modules réels (issue #5589).
#
# La documentation d'architecture doit refléter le code : chaque dossier de
# `api/app/Modules/` doit apparaître dans les 4 documents canoniques, et
# aucun module fantôme (documenté mais absent du disque) ne doit y figurer.
# Historique des dérives : `SmartAttendance` (supprimé #5356) et `Training`
# (jamais créé) documentés comme actifs ; `Accounting` (129 fichiers PHP,
# 2ᵉ module du backend) absent de toutes les docs.
#
# Échoue (::error:: + exit 1) si :
#   1. un module du disque est absent d'un des 4 documents ;
#   2. un module fantôme (`Modules/<Name>` sans dossier) est documenté ;
#   3. le décompte annoncé (« N modules ») diffère du nombre réel de dossiers.
#
# Usage : dev-hub/tools/check-architecture-docs-parity.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

violations=0

# --- 1. Modules réels (disque) ---------------------------------------------
mapfile -t modules < <(find api/app/Modules -maxdepth 1 -mindepth 1 -type d -printf '%f\n' | sort)
count="${#modules[@]}"

if [[ "$count" -eq 0 ]]; then
  echo "::error::Aucun module trouvé sous api/app/Modules — chemin inattendu."
  exit 1
fi

# --- 2. Décompte annoncé dans les docs -------------------------------------
check_count() { # fichier, regex
  local file="$1" regex="$2"
  local announced
  announced="$(grep -oE "$regex" "$file" | grep -oE '[0-9]+' | head -1 || true)"
  if [[ -n "$announced" ]] && [[ "$announced" != "$count" ]]; then
    echo "::error::${file} annonce $announced modules mais le disque en contient $count (issue #5589)."
    violations=$((violations + 1))
  fi
}
check_count ARCHITECTURE.md 'Modules actifs \(([0-9]+)'
check_count docs/ARCHITECTURE_STATUS.md 'Tableau de l.état DDD — ([0-9]+) modules'
check_count docs/CONTRIBUTING_DDD.md 'Modules existants \(([0-9]+) modules\)'

# --- 3. Chaque module réel est documenté ------------------------------------
for m in "${modules[@]}"; do
  # ARCHITECTURE.md : liste « Modules actifs (N, ...) : `Absence`, ... »
  if ! grep -q 'Modules actifs' ARCHITECTURE.md || ! grep -qE "Modules actifs \([0-9]+, sous .api/app/Modules..\) : .*\`${m}\`" ARCHITECTURE.md; then
    echo "::error::ARCHITECTURE.md ne documente pas le module réel ${m} (issue #5589)."
    violations=$((violations + 1))
  fi
  # docs/ARCHITECTURE_STATUS.md : ligne de tableau | **Nom** |
  if ! grep -qE "^\| \*\*${m}\*\*" docs/ARCHITECTURE_STATUS.md; then
    echo "::error::docs/ARCHITECTURE_STATUS.md ne documente pas le module réel ${m} (issue #5589)."
    violations=$((violations + 1))
  fi
  # docs/CONTRIBUTING_DDD.md : ligne de tableau | `Nom` |
  if ! grep -qE "^\| \`${m}\`" docs/CONTRIBUTING_DDD.md; then
    echo "::error::docs/CONTRIBUTING_DDD.md ne documente pas le module réel ${m} (issue #5589)."
    violations=$((violations + 1))
  fi
  # api/ARCHITECTURE.md : ligne | `Modules/Nom` |
  if ! grep -qE "Modules/${m}(\`|\|)" api/ARCHITECTURE.md; then
    echo "::error::api/ARCHITECTURE.md ne documente pas le module réel ${m} (issue #5589)."
    violations=$((violations + 1))
  fi
done

# --- 4. Modules fantômes interdits ------------------------------------------
# Noms de modules supprimés/jamais créés : ne doivent plus apparaître comme
# modules actifs dans les docs.
if grep -qE 'Modules actifs \([0-9]+, sous .api/app/Modules..\) : .*\`(SmartAttendance|Training)\`' ARCHITECTURE.md \
  || grep -qE '^\| \*\*(SmartAttendance|Training)\*\*' docs/ARCHITECTURE_STATUS.md \
  || grep -qE '^\| \`(SmartAttendance|Training)\`' docs/CONTRIBUTING_DDD.md \
  || grep -qE 'Modules/(SmartAttendance|Training)(\`|\|)' api/ARCHITECTURE.md; then
  echo "::error::Module(s) fantôme(s) SmartAttendance/Training encore documenté(s) comme actifs (issue #5589, supprimé #5356 / jamais créé #4936)."
  violations=$((violations + 1))
fi

if [[ "$violations" -gt 0 ]]; then
  echo "::error::Parité docs ↔ modules : $violations divergence(s) (issue #5589)."
  exit 1
fi

echo "Architecture docs parity OK (${count} modules documentés ×4, 0 fantôme)."
