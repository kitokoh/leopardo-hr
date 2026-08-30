#!/usr/bin/env bash
# check-issue-claim-unique.sh — Garde anti-doublon « une issue = une PR » (issue #5442)
#
# Pourquoi : des agents parallèles ouvrent régulièrement PLUSIEURS PR pour la
# même issue sans le savoir (constats #5239 ×2-3, #5270 ×2, #5235 ×2, #5356 ×2,
# #5418/#5420). Le garde PA2 (check-plan-action2-claim.sh) ne couvre que les
# tickets PA2-*, pas les issues GitHub. Coût : conflits de merge en cascade,
# CI rouge, gaspillage de capacité, confusion du wave-PM.
#
# Règle : pour chaque PR OUVERTE du repo, extraire les références de clôture
# `Closes/Fixes/Resolves #N` (grammaire GitHub) du titre + body ; si 2+ PR
# ouvertes référencent la MÊME issue → ::error + exit 1 (la PR en doublon doit
# être fermée, protocole #2400 : 1 PR = 1 issue, renvoi vers la PR canonique).
#
# Exception (docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md, 2026-08-29) : une PR
# `bc/<code>-*` peut légitimement fermer PLUSIEURS issues d'un même lot BC —
# cette garde reste valide sans modification, elle interdit seulement qu'une
# MÊME issue soit référencée par 2+ PR ouvertes différentes, indépendamment du
# nombre d'issues qu'une seule PR ferme.
#
# Usage : dev-hub/tools/check-issue-claim-unique.sh <owner/repo>
# Requiert `gh` authentifié (GITHUB_TOKEN/GH_TOKEN suffit : lecture PRs).
set -uo pipefail

REPO="${1:?usage: check-issue-claim-unique.sh <owner/repo>}"

PRS_JSON=$(gh api "repos/${REPO}/pulls" -f state=open -f per_page=100 \
  --jq '[.[] | {number, title, user: .user.login, body: (.body // "")}]')

OUTPUT=$(PRS_JSON="$PRS_JSON" python3 - "$REPO" 2>&1 <<'PYEOF' || true
import json, os, re, sys

repo = sys.argv[1]
prs = json.loads(os.environ["PRS_JSON"])
ref_re = re.compile(
    r"(?<![\w-])(?:close|closes|closed|fix|fixes|fixed|resolve|resolves|resolved)"
    r"[:\s]*#(\d+)",
    re.IGNORECASE,
)

claims = {}   # issue -> {pr -> author}
for pr in prs:
    text = f"{pr.get('title') or ''}\n{pr.get('body') or ''}"
    for m in ref_re.findall(text):
        claims.setdefault(int(m), {})[pr["number"]] = pr.get("user") or "?"

dups = {i: p for i, p in claims.items() if len(p) > 1}
for issue in sorted(claims):
    owners = claims[issue]
    if len(owners) == 1:
        n, author = next(iter(owners.items()))
        print(f"OK    issue #{issue} -> PR #{n} ({author})")
    else:
        desc = " + ".join(f"PR #{n} ({a})" for n, a in sorted(owners.items()))
        print(f"DUP   issue #{issue} -> {desc}")

if dups:
    print(f"::error::Garde anti-doublon « une issue = une PR » (issue #5442) : {len(dups)} issue(s) référencée(s) par plusieurs PR ouvertes :")
    for issue in sorted(dups):
        owners = " + ".join(f"PR #{n}" for n in sorted(dups[issue]))
        print(f"::error::  issue #{issue} -> {owners}")
    print("::error::→ Protocole #2400 : fermer la PR redondante avec un commentaire de renvoi vers la PR canonique (1 PR = 1 issue).")
    print("RESULT=FAIL")
    sys.exit(1)

print("✓ Aucune collision de claim d'issue : chaque issue ouverte est référencée par au plus une PR.")
print("RESULT=OK")
sys.exit(0)
PYEOF
)
printf '%s\n' "${OUTPUT}"

if printf '%s\n' "${OUTPUT}" | grep -q '^RESULT=FAIL'; then
  exit 1
fi
exit 0
