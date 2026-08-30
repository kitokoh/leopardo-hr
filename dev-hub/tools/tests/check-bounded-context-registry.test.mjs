// check-bounded-context-registry.test.mjs — tests du guard MAT-001 (issue #5859)
//
// Usage : node --test dev-hub/tools/tests/check-bounded-context-registry.test.mjs
// Prérequis : bash + jq dans le PATH (sinon les tests sont marqués skipped).

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const TOOL = join(import.meta.dirname, '..', 'check-bounded-context-registry.sh');

function haveJq() {
  return spawnSync('jq', ['--version'], { stdio: 'ignore' }).status === 0;
}

const skip = !haveJq() ? 'jq absent — test ignoré' : false;

function buildFixture(overrides = {}) {
  const root = mkdtempSync(join(tmpdir(), 'bcreg-'));
  const dirs = [
    'api/app/Modules/Platform',
    'api/app/Modules/HR',
    'api/app/Modules/Payroll',
    'api/app/Core/Tenant',
    'api/app/AI',
  ];
  for (const d of dirs) mkdirSync(join(root, d), { recursive: true });
  mkdirSync(join(root, 'dev-hub/governance'), { recursive: true });
  mkdirSync(join(root, 'api/routes/modules'), { recursive: true });
  mkdirSync(join(root, 'api/database/migrations/tenant'), { recursive: true });
  writeFileSync(join(root, 'api/routes/modules/dashboard.php'), '<?php\n');

  const registry = {
    _meta: { title: 'fixture' },
    _shared_exceptions: [
      { path: 'api/app/Core/Http', reason: 'shared' },
    ],
    bounded_contexts: [
      {
        code: 'BC-01', name: 'PLATFORM', context: 'Platform Core',
        responsibility: 'r', owner: 'Agent 01', priority: 'P0', status: 'active',
        paths: [
          { path: 'api/app/Modules/Platform', status: 'active' },
          { path: 'api/app/Modules/Onboarding', status: 'planned' },
        ],
        routes: ['api/routes/modules/dashboard.php'],
        migrations: ['api/database/migrations/tenant'],
        events: [], dependencies: [],
      },
      {
        code: 'BC-02', name: 'TENANT', context: 'Tenant & Isolation',
        responsibility: 'r', owner: 'Agent 02', priority: 'P0', status: 'active',
        paths: [{ path: 'api/app/Core/Tenant', status: 'active' }],
        routes: [], migrations: [], events: [], dependencies: ['BC-01'],
      },
      {
        code: 'BC-04', name: 'HR', context: 'HR',
        responsibility: 'r', owner: 'Agent 04', priority: 'P1', status: 'active',
        paths: [{ path: 'api/app/Modules/HR', status: 'active' }],
        routes: [], migrations: [], events: [], dependencies: ['BC-02'],
      },
      {
        code: 'BC-07', name: 'PAYROLL', context: 'Payroll',
        responsibility: 'r', owner: 'Agent 07', priority: 'P0', status: 'active',
        paths: [{ path: 'api/app/Modules/Payroll', status: 'active' }],
        routes: [], migrations: [], events: [], dependencies: ['BC-02'],
      },
      {
        code: 'BC-23', name: 'AI', context: 'AI',
        responsibility: 'r', owner: 'Agent 23', priority: 'P2', status: 'active',
        paths: [{ path: 'api/app/AI', status: 'active' }],
        routes: [], migrations: [], events: [], dependencies: [],
      },
    ],
  };
  writeFileSync(join(root, 'dev-hub/governance/bounded-context-registry.json'), JSON.stringify(registry));
  const codeowners = [
    '# fixture',
    '* @kitokoh',
    '/api/ @kitokoh',
    '/api/app/Modules/Platform/ @kitokoh',
    '/api/app/Modules/HR/ @kitokoh',
    '/api/app/Modules/Payroll/ @kitokoh',
    '/api/app/Core/Tenant/ @kitokoh',
    '/api/app/AI/ @kitokoh',
    '/api/app/Http/Middleware/ @kitokoh',
  ];
  writeFileSync(join(root, 'CODEOWNERS'), codeowners.join('\n') + '\n');
  return root;
}

function run(root) {
  return spawnSync('bash', [TOOL, root], { encoding: 'utf-8' });
}

test('registre valide → exit 0', { skip }, () => {
  const root = buildFixture();
  try {
    const res = run(root);
    assert.equal(res.status, 0, res.stderr);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('chemin actif supprimé → exit 1', { skip }, () => {
  const root = buildFixture();
  try {
    rmSync(join(root, 'api/app/Modules/Payroll'), { recursive: true, force: true });
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /chemin actif introuvable/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('module non déclaré dans le registre → exit 1 (couverture)', { skip }, () => {
  const root = buildFixture();
  try {
    mkdirSync(join(root, 'api/app/Modules/Billing'), { recursive: true });
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /non déclaré dans le registre/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('ligne CODEOWNERS dédiée manquante → exit 1', { skip }, () => {
  const root = buildFixture();
  try {
    const coPath = join(root, 'CODEOWNERS');
    const co = execFileSync('sed', ['/api\\/app\\/Modules\\/Platform\\//d', coPath], { encoding: 'utf-8' });
    writeFileSync(coPath, co);
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /sans ligne CODEOWNERS dédiée/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('dépendance inconnue → exit 1', { skip }, () => {
  const root = buildFixture();
  try {
    const regPath = join(root, 'dev-hub/governance/bounded-context-registry.json');
    const reg = JSON.parse(execFileSync('cat', [regPath], { encoding: 'utf-8' }));
    reg.bounded_contexts[0].dependencies = ['BC-99'];
    writeFileSync(regPath, JSON.stringify(reg));
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /dépendance 'BC-99' inconnue/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
