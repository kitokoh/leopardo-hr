#!/usr/bin/env bash
#
# #4816 (PROCESS P1) — Garde « ghost close » : détecter les issues clôturées
# SANS correctif mergé. Complément inverse de check-issues-left-open-by-merged-prs.sh
# (#2512) : celui-ci signale les PRs mergées dont l'issue est restée ouverte ;
# celui-là signale les issues clôturées sans commit de fermeture ET sans PR
# mergée les référençant — symptôme d'une clôture administrative sans code
# (cf. vague du 2026-08-17 : #4690/#4687/#4688/#4305/#4410 fermées non résolues).
#
# Usage : check-issues-closed-without-merge.sh <owner/repo> [--since-days N]
#   --since-days N : issues clôturées depuis N jours (défaut 7).
#
# Nécessite un `gh` authentifié (GITHUB_TOKEN lecture : issues + pulls).
# Sortie : lignes « GHOST #<issue> (closed <date>) <- <titre tronqué> » pour
# chaque issue suspecte. Code de sortie 0 (rôle = rapport pour arbitrage, PAS
# bloquant — la ré-ouverture/correction reste manuelle, comme sa sœur #2512).

set -euo pipefail

REPO="${1:?usage: check-issues-closed-without-merge.sh <owner/repo> [--since-days N]}"
SINCE_DAYS=7
if [[ "${2:-}" == "--since-days" && -n "${3:-}" ]]; then
  SINCE_DAYS="$3"
fi

SINCE_ISO=$(date -u -d "${SINCE_DAYS} days ago" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null \
  || date -u -v-${SINCE_DAYS}d +%Y-%m-%dT%H:%M:%SZ)

TMP_DIR=$(mktemp -d)
trap 'rm -rf "${TMP_DIR}"' EXIT

# 1) Issues clôturées récemment.
gh api "repos/${REPO}/issues?state=closed&sort=updated&direction=desc&per_page=100&since=${SINCE_ISO}" \
  --jq '[.[] | select(.pull_request == null)]' > "${TMP_DIR}/issues.json"

# 2) PRs mergées récemment (pour détecter les fermetures par merge SANS
#    commit_id au timeline — ex. mot-clé Closes dans un squash).
gh api "repos/${REPO}/pulls?state=closed&sort=updated&direction=desc&per_page=100" \
  --jq '[.[] | select(.merged_at != null and .merged_at >= "'"${SINCE_ISO}"'")]' > "${TMP_DIR}/prs.json"

cat > "${TMP_DIR}/ghost_report.py" <<'PYSCRIPT'
import json, re, subprocess, sys

REPO = sys.argv[1]
with open(sys.argv[2]) as f:
    issues = json.load(f)
with open(sys.argv[3]) as f:
    prs = json.load(f)

# Issues référencées par une PR mergée (titre + body).
mention_re = re.compile(r"#(\d{3,5})")
merged_refs = set()
for pr in prs:
    text = (pr.get("title") or "") + "\n" + (pr.get("body") or "")
    merged_refs.update(int(m) for m in mention_re.findall(text))

ghost = []
for issue in issues:
    num = issue["number"]
    # Fermeture par commit (merge auto-close ou commit direct) = correctif tracé.
    try:
        timeline = subprocess.run(
            ["gh", "api", f"repos/{REPO}/issues/{num}/timeline",
             "-H", "Accept: application/vnd.github+json"],
            capture_output=True, text=True, check=True,
        )
        events = json.loads(timeline.stdout)
    except (subprocess.CalledProcessError, json.JSONDecodeError):
        continue
    closed_with_commit = any(
        ev.get("event") == "closed" and ev.get("commit_id")
        for ev in events
    )
    # Fermeture via PR mergée référencée = correctif tracé (même sans commit_id).
    if closed_with_commit or num in merged_refs:
        continue
    ghost.append((num, issue.get("closed_at", "")[:10], (issue.get("title") or "")[:80]))

for num, closed_at, title in sorted(ghost):
    print(f"GHOST #{num} (closed {closed_at}) <- {title}")
print(f"-- {len(ghost)} issue(s) suspecte(s) de clôture sans correctif mergé "
      f"({len(issues)} issues clôturées analysées, {len(prs)} PRs mergées référencées).")
PYSCRIPT

python3 "${TMP_DIR}/ghost_report.py" "${REPO}" "${TMP_DIR}/issues.json" "${TMP_DIR}/prs.json"
