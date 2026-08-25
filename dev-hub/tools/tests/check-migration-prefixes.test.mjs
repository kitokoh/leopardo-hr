'use strict';

import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';

const toolPath = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '..',
  'check-migration-prefixes.mjs',
);
const mod = await import(pathToFileURL(toolPath).href);

function makeTmpDir(prefix) {
  return fs.mkdtempSync(path.join(os.tmpdir(), prefix));
}

test('extractPrefix : extrait le préfixe YYYY_MM_DD_HHMMSS', () => {
  assert.equal(
    mod.extractPrefix('2026_08_25_000001_create_foo_table.php'),
    '2026_08_25_000001',
  );
  assert.equal(
    mod.extractPrefix('api/database/migrations/tenant/2026_08_23_000010_x.php'),
    '2026_08_23_000010',
  );
});

test('extractPrefix : null pour les fichiers hors convention', () => {
  assert.equal(mod.extractPrefix('README.md'), null);
  assert.equal(mod.extractPrefix('2026_8_25_1_create.php'), null);
  assert.equal(mod.extractPrefix('create_foo_table.php'), null);
});

test('findCollisions : préfixe partagé avec main → collision', () => {
  const current = ['2026_08_25_000001_create_foo.php'];
  const main = ['2026_08_25_000001_create_bar.php'];
  const collisions = mod.findCollisions(current, [
    { scope: 'main', names: main },
  ]);
  assert.equal(collisions.length, 1);
  assert.equal(collisions[0].prefix, '2026_08_25_000001');
  assert.equal(collisions[0].scope, 'main');
});

test('findCollisions : préfixe partagé avec une autre PR → collision', () => {
  const current = ['2026_08_25_000007_create_alpha.php'];
  const other = [
    { scope: 'PR #5406', names: ['2026_08_25_000007_create_beta.php'] },
  ];
  const collisions = mod.findCollisions(current, other);
  assert.equal(collisions.length, 1);
  assert.equal(collisions[0].scope, 'PR #5406');
});

test('findCollisions : préfixe libre → aucune collision', () => {
  const current = ['2026_08_25_000001_create_foo.php'];
  const other = [
    { scope: 'main', names: ['2026_08_24_000003_create_bar.php'] },
    {
      scope: 'PR #5406',
      names: ['2026_08_25_000002_create_baz.php', '2026_08_25_000003_create_qux.php'],
    },
  ];
  assert.equal(mod.findCollisions(current, other).length, 0);
});

test('findCollisions : ignore les doublons internes à un même scope', () => {
  // Un scope avec un préfixe dupliqué en interne ne crée PAS de collision
  // avec la branche courante si le préfixe n'y est pas.
  const current = ['2026_08_25_000009_create_foo.php'];
  const other = [
    {
      scope: 'PR #9999',
      names: ['2026_08_25_000001_dup_a.php', '2026_08_25_000001_dup_b.php'],
    },
  ];
  assert.equal(mod.findCollisions(current, other).length, 0);
});

test('mode --local : échec (exit 1) sur collision simulée', () => {
  const cur = makeTmpDir('cur-');
  const mainDir = makeTmpDir('main-');
  const other = makeTmpDir('other-');
  fs.writeFileSync(path.join(cur, '2026_08_25_000001_create_a.php'), '<?php');
  fs.writeFileSync(path.join(mainDir, '2026_08_24_000001_create_b.php'), '<?php');
  fs.writeFileSync(path.join(other, '2026_08_25_000001_create_c.php'), '<?php');
  let thrown = null;
  try {
    execFileSync('node', [toolPath, '--local', cur, mainDir, other], {
      encoding: 'utf8',
      stdio: 'pipe',
    });
  } catch (e) {
    thrown = e;
  }
  assert.ok(thrown, 'le mode --local doit échouer (exit 1) sur collision');
  assert.equal(thrown.status, 1);
  assert.match(String(thrown.stderr), /collision/i);
  fs.rmSync(cur, { recursive: true, force: true });
  fs.rmSync(mainDir, { recursive: true, force: true });
  fs.rmSync(other, { recursive: true, force: true });
});

test('mode --local : succès (exit 0) sans collision', () => {
  const cur = makeTmpDir('cur-');
  const mainDir = makeTmpDir('main-');
  const other = makeTmpDir('other-');
  fs.writeFileSync(path.join(cur, '2026_08_25_000001_create_a.php'), '<?php');
  fs.writeFileSync(path.join(mainDir, '2026_08_24_000001_create_b.php'), '<?php');
  fs.writeFileSync(path.join(other, '2026_08_25_000002_create_c.php'), '<?php');
  const out = execFileSync('node', [toolPath, '--local', cur, mainDir, other], {
    encoding: 'utf8',
    stdio: 'pipe',
  });
  assert.match(out, /OK: 1 migration/);
  fs.rmSync(cur, { recursive: true, force: true });
  fs.rmSync(mainDir, { recursive: true, force: true });
  fs.rmSync(other, { recursive: true, force: true });
});
