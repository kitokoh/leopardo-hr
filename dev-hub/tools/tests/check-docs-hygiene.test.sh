#!/usr/bin/env bash
#
# check-docs-hygiene.test.sh — auto-test rouge→vert de la garde #9772.
#
# Principe (règle du dépôt) : une garde n'existe que si on l'a vue ROUGE sans le
# correctif. Chaque cas construit une fixture isolée (mktemp) et vérifie le code
# de sortie de `check-docs-hygiene.sh --root <fixture>` :
#
#   1. arbre propre                       -> 0
#   2. identifiant ADR en doublon         -> 1  (défaut historique : 0028 x2)
#   3. ADR absente du registre            -> 1  (défaut historique : 5 ADR)
#   4. ligne de tableau malformée         -> 1  (défaut historique : ligne 0010)
#   5. registre pointant une ADR absente  -> 1
#   6. fichier non déclaré à docs/ racine -> 1
#   7. collision de casse entre dossiers  -> 1  (défaut historique : DECISIONS)
#
# Sortie : 0 si toutes les attentes tiennent, 1 sinon.
#
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
GUARD="$REPO_ROOT/dev-hub/tools/check-docs-hygiene.sh"

if [[ ! -f "$GUARD" ]]; then
  echo "❌ garde introuvable : $GUARD"
  exit 1
fi

fail=0
ok() { echo "✅ $1"; }
ko() { echo "❌ $1"; fail=1; }

fixture() {
  local d
  d="$(mktemp -d)"
  mkdir -p "$d/docs/architecture/adr"
  printf '# Registre\n\n| ADR | Statut | Decision |\n|---|---|---|\n| [0001](0001-a.md) | Acceptee | A |\n' > "$d/docs/architecture/adr/README.md"
  printf '# ADR 0001 - A\n\n## Statut\n\nAcceptee.\n' > "$d/docs/architecture/adr/0001-a.md"
  printf '# Docs\n' > "$d/docs/README.md"
  echo "$d"
}

expect() { # expect <code_attendu> <libelle> <fixture>
  local want="$1" label="$2" dir="$3" got=0
  bash "$GUARD" --root "$dir" >/dev/null 2>&1 || got=$?
  if [[ "$got" -eq "$want" ]]; then
    ok "$label (exit=$got)"
  else
    ko "$label — attendu exit=$want, obtenu exit=$got"
  fi
  rm -rf "$dir"
}

# 1. arbre propre
d="$(fixture)"
expect 0 "arbre propre" "$d"

# 2. identifiant en doublon
d="$(fixture)"
printf '# ADR 0001 - B\n' > "$d/docs/architecture/adr/0001-b.md"
expect 1 "identifiant ADR en doublon" "$d"

# 3. ADR absente du registre
d="$(fixture)"
printf '# ADR 0002 - C\n' > "$d/docs/architecture/adr/0002-c.md"
expect 1 "ADR absente du registre" "$d"

# 4. ligne de tableau malformée
d="$(fixture)"
printf '# Registre\n\n| ADR | Statut | Decision |\n|---|---|---|\n| [0001](0001-a.md)\n| Acceptee | A |\n' > "$d/docs/architecture/adr/README.md"
expect 1 "ligne de tableau malformee" "$d"

# 5. registre pointant une ADR inexistante
d="$(fixture)"
printf '# Registre\n\n| ADR | Statut | Decision |\n|---|---|---|\n| [0001](0001-a.md) | Acceptee | A |\n| [0009](0009-ghost.md) | Acceptee | X |\n' > "$d/docs/architecture/adr/README.md"
expect 1 "registre pointant une ADR inexistante" "$d"

# 6. fichier non déclaré à la racine de docs/
d="$(fixture)"
printf '# Extra\n' > "$d/docs/EXTRA.md"
expect 1 "fichier non declare a la racine de docs/" "$d"

# 7. collision de casse entre dossiers
d="$(fixture)"
mkdir -p "$d/docs/decisions" "$d/docs/DECISIONS"
expect 1 "collision de casse entre dossiers" "$d"

if [[ "$fail" -eq 0 ]]; then
  echo
  echo "Auto-test docs-hygiene : 7/7 OK."
  exit 0
fi
echo
echo "Auto-test docs-hygiene : ECHEC."
exit 1
