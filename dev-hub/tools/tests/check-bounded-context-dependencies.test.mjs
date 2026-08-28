// check-bounded-context-dependencies.test.mjs — tests du guard MAT-002 (issue #5860)
//
// Usage : node --test dev-hub/tools/tests/check-bounded-context-dependencies.test.mjs
// Prérequis : bash + jq + python3 dans le PATH (sinon skipped).

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const TOOL = join(import.meta.dirname, '..', 'check-bounded-context-dependencies.sh');

function have(bin) {
  return spawnSync(bin, ['--version'], { stdio: 'ignore' }).status === 0;
}
const skip = !have('jq') || !have('python3') ? 'jq/python3 absent — test ignoré' : false;

const REGISTRY = {
  _meta: { title: 'fixture' },
  _shared_exceptions: [],
  bounded_contexts: [
    { code: 'BC-01', name: 'PLATFORM', context: 'x', responsibility: 'r', owner: 'o', priority: 'P0', status: 'active', paths: [{ path: 'api/app/Modules/Platform', status: 'active' }], routes: [], migrations: [], events: [], dependencies: [] },
    { code: 'BC-02', name: 'TENANT', context: 'x', responsibility: 'r', owner: 'o', priority: 'P0', status: 'active', paths: [{ path: 'api/app/Core/Tenant', status: 'active' }], routes: [], migrations: [], events: [], dependencies: [] },
    { code: 'BC-04', name: 'HR', context: 'x', responsibility: 'r', owner: 'o', priority: 'P1', status: 'active', paths: [{ path: 'api/app/Modules/HR', status: 'active' }], routes: [], migrations: [], events: [], dependencies: [] },
    { code: 'BC-07', name: 'PAYROLL', context: 'x', responsibility: 'r', owner: 'o', priority: 'P0', status: 'active', paths: [{ path: 'api/app/Modules/Payroll', status: 'active' }], routes: [], migrations: [], events: [], dependencies: [] },
  ],
};

function buildFixture({ allowed = true } = {}) {
  const root = mkdtempSync(join(tmpdir(), 'bcdep-'));
  for (const d of ['api/app/Modules/Platform', 'api/app/Modules/HR', 'api/app/Modules/Payroll', 'api/app/Core/Tenant']) {
    mkdirSync(join(root, d), { recursive: true });
  }
  mkdirSync(join(root, 'dev-hub/governance'), { recursive: true });
  writeFileSync(join(root, 'dev-hub/governance/bounded-context-registry.json'), JSON.stringify(REGISTRY));
  const edges = allowed
    ? [{ from: 'BC-04', to: 'BC-07', baseline: true, note: 'HR → Payroll' }]
    : [{ from: 'BC-01', to: 'BC-02', baseline: true, note: 'Plateforme → Tenant' }];
  writeFileSync(join(root, 'dev-hub/governance/bounded-context-dependencies.json'), JSON.stringify({
    _meta: { title: 'fixture', policy: 'deny-by-default', updated: '2026-08-28' },
    edges,
  }));
  // fichier HR qui importe Payroll (arête BC-04 → BC-07)
  writeFileSync(
    join(root, 'api/app/Modules/HR/SomeService.php'),
    '<?php\n\nnamespace App\\Modules\\HR\\Application;\n\nuse App\\Modules\\Payroll\\Contracts\\PayrollContract;\n\nclass SomeService {}\n',
  );
  return root;
}

function run(root) {
  return spawnSync('bash', [TOOL, root], { encoding: 'utf-8' });
}

test('import cross-BC déclaré dans la matrice → exit 0', { skip }, () => {
  const root = buildFixture({ allowed: true });
  try {
    const res = run(root);
    assert.equal(res.status, 0, res.stderr);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('import cross-BC non déclaré → exit 1 (message actionnable)', { skip }, () => {
  const root = buildFixture({ allowed: false });
  try {
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /arête BC-04 → BC-07 non déclarée/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('matrice référençant un BC inconnu → exit 1', { skip }, () => {
  const root = buildFixture({ allowed: true });
  try {
    const p = join(root, 'dev-hub/governance/bounded-context-dependencies.json');
    const m = JSON.parse(execFileSync('cat', [p], { encoding: 'utf-8' }));
    m.edges[0].to = 'BC-99';
    writeFileSync(p, JSON.stringify(m));
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /BC cible inconnu 'BC-99'/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
