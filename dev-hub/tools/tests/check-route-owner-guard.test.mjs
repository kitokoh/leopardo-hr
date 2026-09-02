// check-route-owner-guard.test.mjs — tests du guard MAT-003 (issue #5861)
//
// Usage : node --test dev-hub/tools/tests/check-route-owner-guard.test.mjs
// Prérequis : bash + jq (sinon skipped). Couvre la couche statique (sans PHP).

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const TOOL = join(import.meta.dirname, '..', 'check-route-owner-guard.sh');
const skip = spawnSync('jq', ['--version'], { stdio: 'ignore' }).status !== 0 ? 'jq absent — test ignoré' : false;

const REGISTRY = {
  _meta: {
    title: 'fixture',
    platform_guard_middleware: 'Authenticate:super_admin_api',
    tenant_middleware: 'TenantMiddleware',
    updated: '2026-08-28',
  },
  scope_by_default: 'tenant',
  platform_uri_prefixes: ['api/v1/platform', 'api/v1/admin'],
  route_files: {
    'api/routes/api.php': 'mixed',
    'api/routes/ai.php': 'tenant',
    'api/routes/modules/absence.php': 'tenant',
    'api/routes/modules/payroll_engine.php': 'tenant',
  },
  platform_controller_exceptions: [],
  tenant_controller_exceptions: [
    {
      controller: 'App\\Modules\\Platform\\Interfaces\\Api\\V1\\Controllers\\AIWorkflowController',
      reason: 'workflows IA tenant documentés',
    },
  ],
};

function buildFixture({ platformRef = false, missingFile = false, badScope = false } = {}) {
  const root = mkdtempSync(join(tmpdir(), 'routeowner-'));
  mkdirSync(join(root, 'api/routes/modules'), { recursive: true });
  mkdirSync(join(root, 'dev-hub/governance'), { recursive: true });

  const reg = structuredClone(REGISTRY);
  if (badScope) reg.route_files['api/routes/modules/absence.php'] = 'weird';
  if (missingFile) delete reg.route_files['api/routes/modules/payroll_engine.php'];
  writeFileSync(join(root, 'dev-hub/governance/route-owners.json'), JSON.stringify(reg));

  writeFileSync(join(root, 'api/routes/api.php'), '<?php\n');
  const ai = platformRef
    ? '<?php\nuse App\\Modules\\Platform\\Interfaces\\Api\\V1\\Controllers\\OtherPlatformController;\n'
    : '<?php\nuse App\\Modules\\Platform\\Interfaces\\Api\\V1\\Controllers\\AIWorkflowController;\n';
  writeFileSync(join(root, 'api/routes/ai.php'), ai);
  writeFileSync(join(root, 'api/routes/modules/absence.php'), '<?php\n');
  // Le fichier physique existe toujours ; selon le scénario, sa déclaration
  // dans route-owners.json peut manquer (règle inverse : tout fichier de
  // routes modules existant doit être déclaré).
  writeFileSync(join(root, 'api/routes/modules/payroll_engine.php'), '<?php\n');
  return root;
}

function run(root) {
  return spawnSync('bash', [TOOL, root], { encoding: 'utf-8' });
}

test('registre valide + exceptions OK → exit 0', { skip }, () => {
  const root = buildFixture();
  try {
    const res = run(root);
    assert.equal(res.status, 0, res.stderr);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('contrôleur platform non déclaré dans un fichier tenant → exit 1', { skip }, () => {
  const root = buildFixture({ platformRef: true });
  try {
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /contrôleur platform référencé dans un fichier tenant/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('fichier de routes non déclaré dans le registre → exit 1', { skip }, () => {
  const root = buildFixture({ missingFile: true });
  try {
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /non déclaré dans route-owners\.json/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('scope invalide → exit 1', { skip }, () => {
  const root = buildFixture({ badScope: true });
  try {
    const res = run(root);
    assert.equal(res.status, 1);
    assert.match(res.stderr, /Scope invalide/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
