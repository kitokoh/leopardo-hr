#!/usr/bin/env node
/**
 * #9875 — Garde-fou : les scripts npm racine doivent pointer vers des
 * répertoires `front/*` qui existent réellement.
 *
 * Contexte : `front/marketplace` et `front/travel-web` ont été retirés
 * (commit b046aa491, #9630) mais le `package.json` racine continuait de les
 * référencer. Résultat : `npm run lint`, `npm run build` et `npm run test`
 * échouaient systématiquement en ENOENT — la commande documentée comme point
 * d'entrée du monorepo ne pouvait pas fonctionner.
 *
 * Ce script échoue si un script `--prefix front/<x>` pointe vers un
 * répertoire absent, ou si un agrégat (`lint`/`build`/`test`) référence un
 * script lui-même cassé.
 *
 * Usage : node scripts/check-front-scripts.mjs
 */

import { readFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const pkg = JSON.parse(readFileSync(join(ROOT, 'package.json'), 'utf8'));
const scripts = pkg.scripts ?? {};

const errors = [];

// 1. Tout `--prefix front/<x>` doit exister.
const prefixRe = /--prefix\s+(front\/[A-Za-z0-9._-]+)/g;
for (const [name, cmd] of Object.entries(scripts)) {
  for (const match of cmd.matchAll(prefixRe)) {
    const dir = match[1];
    if (!existsSync(join(ROOT, dir))) {
      errors.push(`script "${name}" pointe vers "${dir}" qui n'existe pas`);
    }
  }
}

// 2. Les agrégats ne doivent référencer que des scripts définis.
const AGGREGATES = ['lint', 'build', 'test'];
for (const agg of AGGREGATES) {
  const cmd = scripts[agg];
  if (!cmd) continue;
  for (const match of cmd.matchAll(/npm run ([A-Za-z0-9:_-]+)/g)) {
    const target = match[1];
    if (!(target in scripts)) {
      errors.push(`agrégat "${agg}" référence le script "${target}" qui n'existe pas`);
    }
  }
}

// 3. Aucun script ne doit référencer un front retiré.
const RETIRED = ['front/travel-web', 'front/marketplace'];
for (const [name, cmd] of Object.entries(scripts)) {
  for (const retired of RETIRED) {
    if (cmd.includes(retired)) {
      errors.push(`script "${name}" référence le front retiré "${retired}" (#9630)`);
    }
  }
}

if (errors.length > 0) {
  console.error('❌ Scripts npm racine incohérents avec l\'arborescence front/ :\n');
  for (const e of errors) console.error(`   - ${e}`);
  console.error('\nCorrigez package.json (ou l\'arborescence) avant de merger.');
  process.exit(1);
}

const checked = Object.keys(scripts).length;
console.log(`✅ ${checked} scripts npm racine cohérents avec front/ (aucun front retiré référencé).`);
