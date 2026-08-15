#!/usr/bin/env bash
#
# #2512 (PROCESS P3) — Garde de cohérence post-merge : détecter les issues
# référencées par une PR mergée mais restées OUVERTES faute de mot-clé
# `Closes #` dans le body (une mention entre parenthèses « (#1234) » ne
# ferme rien côté GitHub).
#
# Usage : check-issues-left-open-by-merged-prs.sh <owner/repo> [--since-days N]
#   --since-days N : ne considérer que les PRs mergées depuis N jours (défaut 7).
#
# Nécessite un `gh` authentifié (GITHUB_TOKEN en lecture suffit : pull-requests,
# issues). Sortie :
#   - lignes « OPEN #<issue> <- PR #<pr> » pour chaque issue référencée et
#     toujours ouverte avec aucune PR ouverte la ciblant ;
#   - code de sortie 0 même si des issues sont listées (rôle = rapport pour
#     arbitrage, PAS bloquant — la fermeture reste manuelle avec preuve code).
#
# Pattern volontairement aligné sur check-openapi-route-coverage.py
# (garde de cohérence, sortie lisible, pas de mutation).

set -euo pipefail

REPO="${1:?usage: check-issues-left-open-by-merged-prs.sh <owner/repo> [--since-days N]}"
SINCE_DAYS=7
if [[ "${2:-}" == "--since-days" && -n "${3:-}" ]]; then
  SINCE_DAYS="$3"
fi

SINCE_ISO=$(date -u -d "${SINCE_DAYS} days ago" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null \
  || date -u -v-${SINCE_DAYS}d +%Y-%m-%dT%H:%M:%SZ)

# 1) PRs mergées récemment (titre + body, pour extraire toutes les mentions #N).
PRS=$(gh api "repos/${REPO}/pulls?state=closed&sort=updated&direction=desc&per_page=100" \
  --jq '[.[] | select(.merged_at != null and .merged_at >= "'"${SINCE_ISO}"'")]')

TMP_DIR=$(mktemp -d)
trap 'rm -rf "${TMP_DIR}"' EXIT

echo "${PRS}" > "${TMP_DIR}/prs.json"

cat > "${TMP_DIR}/lp_mentions.py" <<'PYSCRIPT'

import json, sys, re

prs = json.load(open(sys.argv[1]))
# Toutes les issues référencées (titre + body), avec ou sans mot-clé Closes.
mention_re = re.compile(r"#(\d{3,5})")
closes_re = re.compile(r"(?:close|closes|closed|fix|fixes|fixed|resolve|resolves|resolved)\s*:?\s*#(\d{3,5})", re.I)

mentions = {}
for pr in prs:
    text = (pr.get("title") or "") + "\n" + (pr.get("body") or "")
    refs = set(int(m) for m in mention_re.findall(text))
    closed = set(int(m) for m in closes_re.findall(text))
    for ref in refs:
        mentions.setdefault(ref, {"prs": [], "explicitly_closed": set()})
        mentions[ref]["prs"].append(pr["number"])
        if ref in closed:
            mentions[ref]["explicitly_closed"].add(pr["number"])

for m in mentions.values():
    m["prs"] = sorted(set(m["prs"]))
    m["explicitly_closed"] = sorted(m["explicitly_closed"])

with open(sys.argv[2], "w") as f:
    json.dump(mentions, f)

PYSCRIPT

python3 "${TMP_DIR}/lp_mentions.py" "${TMP_DIR}/prs.json" "${TMP_DIR}/mentions.json"


# 2) Pour chaque issue mentionnée : état courant + PRs ouvertes la ciblant.
cat > "${TMP_DIR}/lp_report.py" <<'PYSCRIPT'

import json, sys, subprocess, os

REPO = sys.argv[1]
with open(sys.argv[2]) as f:
    mentions = json.load(f)

results = []
for issue_num in sorted(int(n) for n in mentions):
    try:
        state = subprocess.run(
            ["gh", "api", f"repos/{REPO}/issues/{issue_num}", "--jq", ".state"],
            capture_output=True, text=True, check=True,
        ).stdout.strip()
    except subprocess.CalledProcessError:
        continue  # issue supprimée / inexistante
    if state != "open":
        continue
    # Une PR ouverte cible déjà cette issue ? → ne pas la lister (en cours).
    open_prs = subprocess.run(
        ["gh", "api", f"repos/{REPO}/pulls?state=open&per_page=100", "--jq",
         f"[.[] | select((.title + \" \" + (.body // \"\")) | contains(\"#{issue_num}\"))] | length"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    if int(open_prs) > 0:
        continue
    prs = mentions[str(issue_num)]["prs"]
    results.append((issue_num, prs))

results.sort()
for issue_num, prs in results:
    print(f"OPEN #{issue_num} <- PR(s) mergée(s): {', '.join('#' + str(p) for p in prs)}")

print(f"\n{len(results)} issue(s) référencée(s) par des PRs mergées mais restée(s) ouverte(s) "
      f"(aucune PR ouverte en cours). Fermeture manuelle avec preuve code recommandée.")

PYSCRIPT

python3 "${TMP_DIR}/lp_report.py" "${REPO}" "${TMP_DIR}/mentions.json"

