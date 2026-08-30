#!/usr/bin/env bash
# check-pii-classification.sh — Garde du catalogue PII (MAT-011, issue #5869).
#
# Vérifie l'intégrité de `dev-hub/tools/pii-classification.json` :
#   1. JSON valide + schéma (schema_version, purpose, listes de référence) ;
#   2. chaque champ possède une politique COMPLÈTE (key, context, category,
#      classification, retention, anonymization, encryption, access) ;
#   3. les valeurs appartiennent aux listes de référence du fichier ;
#   4. chaque contexte déclaré existe dans `contexts` ;
#   5. clés uniques.
#
# Usage : bash dev-hub/tools/check-pii-classification.sh [json_path]
set -uo pipefail

JSON="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/pii-classification.json}"

fail() {
  echo "❌ $*" >&2
  exit 1
}

if [[ ! -f "${JSON}" ]]; then
  fail "catalogue introuvable : ${JSON}"
fi

if ! python3 - "${JSON}" << 'PYEOF'
import json, sys
path = sys.argv[1]
with open(path, encoding="utf-8") as fh:
    data = json.load(fh)

errors = []

if not isinstance(data.get("schema_version"), str) or not data.get("schema_version"):
    errors.append("schema_version manquant")
if not data.get("purpose"):
    errors.append("purpose manquant")

refs = {k: set(data.get(k, [])) for k in
        ("categories", "classifications", "retentions", "anonymizations", "encryptions")}

if not data.get("categories"):
    errors.append("categories vides (référentiel)")
if not data.get("classifications"):
    errors.append("classifications vides (référentiel)")
if not data.get("retentions"):
    errors.append("retentions vides (référentiel)")
if not data.get("anonymizations"):
    errors.append("anonymizations vides (référentiel)")
if not data.get("encryptions"):
    errors.append("encryptions vides (référentiel)")

contexts = set(data.get("contexts", {}).keys())
fields = data.get("fields", [])
if not isinstance(fields, list) or not fields:
    errors.append("fields vide — aucun champ sensible catalogué")

seen = set()
for i, f in enumerate(fields):
    key = f.get("key", "")
    if not key:
        errors.append(f"field[{i}] : key manquante")
        continue
    if key in seen:
        errors.append(f"clé dupliquée : {key}")
    seen.add(key)

    for attr in ("context", "category", "classification", "retention", "anonymization", "encryption", "access"):
        if not f.get(attr):
            errors.append(f"{key} : {attr} manquant")

    if f.get("context") not in contexts:
        errors.append(f"{key} : contexte inconnu '{f.get('context')}'")
    if f.get("category") not in refs["categories"]:
        errors.append(f"{key} : catégorie inconnue '{f.get('category')}'")
    if f.get("classification") not in refs["classifications"]:
        errors.append(f"{key} : classification inconnue '{f.get('classification')}'")
    if f.get("retention") not in refs["retentions"]:
        errors.append(f"{key} : rétention inconnue '{f.get('retention')}'")
    if f.get("anonymization") not in refs["anonymizations"]:
        errors.append(f"{key} : anonymisation inconnue '{f.get('anonymization')}'")
    if f.get("encryption") not in refs["encryptions"]:
        errors.append(f"{key} : chiffrement inconnu '{f.get('encryption')}'")
    if not f.get("access"):
        errors.append(f"{key} : accès manquant")

# Tout champ référencé par un contexte doit exister (pas de déclaration morte).
for ctx, keys in data.get("contexts", {}).items():
    for k in keys:
        if k not in seen:
            errors.append(f"contexte {ctx} : champ déclaré introuvable '{k}'")

if errors:
    print(";".join(errors))
    sys.exit(1)

print(f"OK — {len(fields)} champs PII catalogués, {len(contexts)} contextes.")
PYEOF
then
  fail "catalogue PII invalide — voir erreurs ci-dessus"
fi

echo "✅ check-pii-classification : catalogue PII valide (MAT-011, #5869)."
exit 0
