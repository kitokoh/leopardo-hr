#!/usr/bin/env python3
"""Poll a PR's required checks and merge squash when green, then delete branch."""
import json, subprocess, os, sys, time

TOK = os.environ['GH_TOKEN']
R = 'kitokoh/leopardo-hr'
PR = sys.argv[1]
BRANCH = sys.argv[2]
TITLE = sys.argv[3]

REQ = [
    'PHPStan — Strict (Core/Modules/Shared, level 8)',
    'Module Structure Validator',
    'Frontend — ESLint + TypeScript',
    'actionlint (+ shellcheck)',
    'validate-and-sync',
    'Check PR/issue governance',
]


def api(path, method='GET', data=None):
    cmd = ['curl', '-s', '-X', method, '-H', f'Authorization: token {TOK}',
           '-H', 'Accept: application/vnd.github+json',
           f'https://api.github.com/repos/{R}{path}']
    if data is not None:
        cmd += ['-d', json.dumps(data)]
    return json.loads(subprocess.check_output(cmd))


for attempt in range(80):
    pr = api(f'/pulls/{PR}')
    if pr.get('merged'):
        print(f'ALREADY MERGED sha={pr.get("merge_commit_sha")}')
        sys.exit(0)
    sha = pr['head']['sha']
    runs = api(f'/commits/{sha}/check-runs?per_page=100')['check_runs']
    m = {r['name']: r for r in runs}
    allok = True
    pend = []
    for n in REQ:
        r = m.get(n)
        st = r['status'] if r else 'MISSING'
        cc = r['conclusion'] if r else '-'
        if st != 'completed' or cc not in ('success', 'skipped', 'neutral'):
            allok = False
            pend.append(f'{n}={st}/{cc}')
    fails = [r['name'] for r in runs if r['conclusion'] == 'failure']
    if allok and pr.get('mergeable'):
        res = api(f'/pulls/{PR}/merge', 'PUT', {'merge_method': 'squash', 'commit_title': TITLE})
        print(f'MERGE merged={res.get("merged")} sha={res.get("sha")} msg={res.get("message")}')
        d = api(f'/git/refs/heads/{BRANCH}', 'DELETE')
        print(f'delete branch: {d.get("message", "ok")}')
        sys.exit(0)
    print(f'attempt {attempt}: pending={pend} fails={fails} mergeable={pr.get("mergeable")}', flush=True)
    time.sleep(30)

print('TIMEOUT: still pending')
sys.exit(1)
