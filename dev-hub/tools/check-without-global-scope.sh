#!/usr/bin/env bash
# Garde #7960 — encadrement des bypass du scope tenant `BelongsToCompany`.
#
# Constat (audit 2026-09-20) : ~260 usages de `withoutGlobalScope(s)` dans
# api/app, chacun étant un bypass potentiel de l'isolation tenant,
# inauditables à la main.
#
# Règle :
#   1. Tout usage de `withoutGlobalScope('company')` / `withoutGlobalScopes(`
#      dans api/app est compté PAR FICHIER et comparé à la baseline
#      dev-hub/governance/without-global-scope-baseline.json.
#   2. ÉCHEC si un fichier dépasse son compte baseline ou si un fichier
#      absent de la baseline en introduit : le nouveau code DOIT passer par
#      les wrappers explicites du trait (issue #7960) :
#        - Model::forCompany($company)
#        - Model::crossTenantForPlatformAdmin()
#        - Model::crossTenantForSystemTask('raison #issue')
#      Une exception légitime (nouveau wrapper, cas documenté) se justifie
#      en PR en régénérant la baseline : `bash $0 --update-baseline`.
#   3. Si un fichier passe SOUS son compte baseline, la garde échoue aussi
#      tant que la baseline n'est pas resserrée (ratchet : la dette ne peut
#      que baisser, et la baisse doit être capturée).
#
# Le trait porteur des wrappers (api/app/Shared/Traits/BelongsToCompany.php)
# est exclu : c'est le seul point d'appel légitime de withoutGlobalScope.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BASELINE="$ROOT/dev-hub/governance/without-global-scope-baseline.json"
API_APP="$ROOT/api/app"
WRAPPER_TRAIT="app/Shared/Traits/BelongsToCompany.php"
PATTERN="withoutGlobalScopes?\('company'\)|withoutGlobalScopes\("

scan() {
  # Sortie : "<chemin relatif à api/>\t<count>" trié.
  ( cd "$ROOT/api" \
    && grep -rEc "$PATTERN" --include='*.php' app 2>/dev/null \
      | awk -F: '$2 > 0 {print $1 "\t" $2}' \
      | grep -v "^${WRAPPER_TRAIT}	" \
      | sort ) || true
}

if [[ "${1:-}" == "--update-baseline" ]]; then
  TMP="$(mktemp)"; trap 'rm -f "$TMP"' EXIT
  scan > "$TMP"
  python3 - "$BASELINE" "$TMP" <<'PY'
import json, sys
files = {}
with open(sys.argv[2]) as scan_fh:
    for line in scan_fh:
        path, count = line.rstrip("\n").split("\t")
        files[path] = int(count)
data = {
    "_comment": "Garde #7960 — baseline des usages bruts de withoutGlobalScope(s) dans api/app. Régénérer via: bash dev-hub/tools/check-without-global-scope.sh --update-baseline. Le total ne doit jamais croître : le nouveau code passe par forCompany()/crossTenantForPlatformAdmin()/crossTenantForSystemTask().",
    "issue": 7960,
    "total": sum(files.values()),
    "files": files,
}
with open(sys.argv[1], "w") as fh:
    json.dump(data, fh, indent=2, ensure_ascii=False, sort_keys=False)
    fh.write("\n")
print(f"Baseline régénérée : {len(files)} fichiers, {data['total']} occurrences.")
PY
  exit 0
fi

if [[ ! -f "$BASELINE" ]]; then
  echo "❌ Baseline absente : $BASELINE (générer via --update-baseline)" >&2
  exit 1
fi

scan > "${TMP:=$(mktemp)}"
trap 'rm -f "$TMP"' EXIT
python3 - "$BASELINE" "$TMP" <<'PY'
import json, sys

baseline = json.load(open(sys.argv[1]))
base_files = baseline.get("files", {})

current = {}
with open(sys.argv[2]) as scan_fh:
    for line in scan_fh:
        path, count = line.rstrip("\n").split("\t")
        current[path] = int(count)

errors, shrunk = [], []
for path, count in sorted(current.items()):
    allowed = base_files.get(path)
    if allowed is None:
        errors.append(f"NOUVEAU  {path} : {count} usage(s) brut(s) de withoutGlobalScope")
    elif count > allowed:
        errors.append(f"CROISSANCE  {path} : {count} > baseline {allowed}")
    elif count < allowed:
        shrunk.append(f"{path} : {count} < baseline {allowed}")
for path, allowed in sorted(base_files.items()):
    if path not in current:
        shrunk.append(f"{path} : 0 < baseline {allowed} (fichier assaini ou supprimé)")

total_now = sum(current.values())
total_base = baseline.get("total", sum(base_files.values()))
print(f"withoutGlobalScope(s) bruts dans api/app : {total_now} (baseline : {total_base})")

if errors:
    print("\n❌ Garde #7960 — nouveaux bypass du scope tenant détectés :")
    for e in errors:
        print(f"  - {e}")
    print("\nUtiliser les wrappers du trait BelongsToCompany (#7960) :")
    print("  Model::forCompany($company) / crossTenantForPlatformAdmin() / crossTenantForSystemTask('raison #issue')")
    print("Exception justifiée ? Régénérer la baseline : bash dev-hub/tools/check-without-global-scope.sh --update-baseline")
    sys.exit(1)

if shrunk:
    print("\n❌ Garde #7960 — dette réduite mais baseline non resserrée (ratchet) :")
    for s in shrunk:
        print(f"  - {s}")
    print("\nCapturer la baisse : bash dev-hub/tools/check-without-global-scope.sh --update-baseline")
    sys.exit(1)

print("✅ Garde #7960 OK — aucun nouveau bypass, baseline à jour.")
PY
