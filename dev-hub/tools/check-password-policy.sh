#!/usr/bin/env bash
# Garde #7995/#8021 — politique de mots de passe unique (norme #5620).
#
# Règle : toute surface qui VALIDE un mot de passe (création de compte,
# changement, réinitialisation, activation d'invitation) DOIT passer par
# App\Shared\Rules\PasswordPolicy (Password::min(12)->numbers() +
# NotCommonPassword). Un champ `password`/`new_password` qui « ressemble à une
# politique » sans le helper est une régression : le compte serait moins
# protégé à sa création qu'à son changement (#8021 : les comptes plateforme et
# 6 sites inline dupliquaient encore la règle).
#
# HORS PÉRIMÈTRE (ignorés) : les champs de VÉRIFICATION d'un mot de passe
# existant (login, re-auth 2FA) qui ne portent que `['required','string']`, et
# les affectations techniques (`'password' => Hash::make(...)`, `config(...)`).
# Un site n'est examiné que si son BLOC DE RÈGLES « ressemble à une politique » :
# `min:N`, `Password::…`, `NotCommonPassword` ou `confirmed`. Le bloc de règles
# est borné par l'équilibre des crochets — pas de fenêtre glissante qui
# déborderait sur la règle suivante. Les lignes de COMMENTAIRE (docblocks
# d'exemple) sont ignorées : seule la politique exécutée compte.
#
# Exception canonique : dev-hub/governance/password-policy-exceptions.json
#   - `exceptions` : site justifié (jamais de contournement silencieux) ;
#   - `legacy_pending_review` : dette pré-#8021 documentée, signalée sans
#     bloquer — une garde qui échoue sur l'existant ne serait jamais mergée
#     (leçon #7999).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
API="$ROOT/api"
EXCEPTIONS="$ROOT/dev-hub/governance/password-policy-exceptions.json"

if [ ! -f "$EXCEPTIONS" ]; then
  echo "❌ Liste canonique absente : dev-hub/governance/password-policy-exceptions.json (#8021)"
  exit 1
fi

python3 - "$API/app" "$ROOT" "$EXCEPTIONS" <<'PY'
#!/usr/bin/env python3
"""Scanner de la politique mots de passe (#7995/#8021) — voir l'en-tête du .sh."""
import json
import re
import sys
from pathlib import Path

app_dir, root, exceptions_path = Path(sys.argv[1]), Path(sys.argv[2]), Path(sys.argv[3])

# `password`, `new_password`, `password_confirmation`, `new_password_confirmation`.
FIELD_RE = re.compile(r"""['"]((?:new_)?password(?:_confirmation)?)['"]\s*=>""")
COMMENT_RE = re.compile(r"^\s*(?:\*|/\*|//|#)")
MAX_BLOCK_LINES = 30

data = json.loads(exceptions_path.read_text())
exceptions = {f"{e['file']}:{e['field']}" for e in data.get('exceptions', [])}
legacy = set(data.get('legacy_pending_review', []))

findings = []

for path in sorted(app_dir.rglob('*.php')):
    lines = path.read_text(encoding='utf-8').splitlines()
    rel = str(path.relative_to(root))

    for index, line in enumerate(lines):
        match = FIELD_RE.search(line)
        if match is None or COMMENT_RE.match(line):
            continue

        field = match.group(1)
        # Bloc de règles : depuis la valeur jusqu'à l'équilibre des crochets.
        value = line[match.end():]
        depth = value.count('[') - value.count(']')
        end = index
        while depth > 0 and end + 1 < len(lines) and (end - index) < MAX_BLOCK_LINES:
            end += 1
            value += '\n' + lines[end]
            depth += lines[end].count('[') - lines[end].count(']')

        # Vérification d'un mot de passe existant (login / re-auth) : ni
        # longueur minimale, ni robustesse → hors périmètre de la norme.
        if not re.search(r"min:\d+|min\(\d+\)|Password\b|NotCommonPassword|['\"]confirmed['\"]", value):
            continue

        # Helper unique : conforme par construction.
        if 'PasswordPolicy' in value:
            continue

        reasons = []
        if not re.search(r"Password::min\(12\)|['\"]min:12['\"]", value):
            reasons.append('minimum-absent-ou-<12')
        if 'numbers()' not in value:
            reasons.append('chiffre-(numbers())-manquant')
        if 'NotCommonPassword' not in value:
            reasons.append('blocklist-(NotCommonPassword)-manquante')
        reasons.append('regle-dupliquee-(utiliser-PasswordPolicy)')

        findings.append((rel, field, index + 1, reasons))

violations = []
warned = 0

for rel, field, line, reasons in findings:
    key = f"{rel}:{field}"
    if key in exceptions:
        continue
    if key in legacy:
        print(f"⚠️  {rel}:{line} — '{field}' legacy_pending_review (#8021)")
        warned += 1
        continue
    violations.append((rel, field, line, reasons))

if violations:
    print("❌ Politique mots de passe contournée (#7995/#8021, norme #5620) :")
    for rel, field, line, reasons in violations:
        print(f"   {rel}:{line} — '{field}' : " + ', '.join(reasons))
    print("")
    print(r"→ utiliser App\Shared\Rules\PasswordPolicy::required()/optional().")
    sys.exit(1)

print(f"✅ Politique mots de passe unique respectée (#7995/#8021) — {warned} legacy documenté(s).")
PY
