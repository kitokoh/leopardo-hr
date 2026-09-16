#!/usr/bin/env node
/**
 * check-horizontal-tools-parity.mjs — garde de cohérence du catalogue des
 * outils horizontaux (issue #7476).
 *
 * Deux listes décrivent le même contrat, à deux endroits :
 *   - côté API  : `Company::HORIZONTAL_TOOLS` (+ `HORIZONTAL_TOOL_FEATURES` pour
 *                 le flag plateforme miroir) — allowlist **fail-closed**, une
 *                 clé absente répond 422 sur
 *                 `POST /company/modules/{key}/activate` ;
 *   - côté web  : `SELF_ACTIVATABLE_MODULE_KEYS` + `CLIENT_MODULES` — ce que le
 *                 panneau « Modules & plan » propose au client.
 *
 * Le défaut réel (#7476) est né de cet écart : `cameras` existait comme feature
 * flag, mais dans AUCUNE des deux listes → la vidéosurveillance n'était
 * activable que par la console plateforme, et personne ne le voyait.
 *
 * Contrôles :
 *   1. les deux listes sont **identiques** (symétrie stricte) ;
 *   2. chaque clé existe dans `CLIENT_MODULES` **avec un `href`** — sans cible
 *      de navigation, `layout.tsx` ne l'affiche pas (`filter(m => m.href)`) et
 *      l'activation serait inaccessible ;
 *   3. chaque flag miroir (`HORIZONTAL_TOOL_FEATURES`) existe réellement dans
 *      `api/config/feature-flags.php` (sinon on active un flag fantôme) ;
 *   4. `TEAM_TOOLS ⊆ HORIZONTAL_TOOLS` (un outil d'équipe désactivé pour un
 *      profil `solo` doit rester un outil horizontal connu) ;
 *   5. aucune liste vide / doublon (erreur de parsing silencieuse).
 *
 * Usage :
 *   node dev-hub/tools/check-horizontal-tools-parity.mjs [--self-test]
 *
 * Sortie : 0 conforme · 1 écart détecté · 2 erreur d'environnement.
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

const API_MODEL = 'api/app/Core/Tenant/Domain/Models/Company.php';
const FRONT_CATALOG = 'front/web/src/lib/client-features.ts';
const FEATURE_FLAGS = 'api/config/feature-flags.php';

/** Extrait les chaînes `'clé'` d'un littéral de tableau PHP `const NAME = [...]`. */
export function phpArrayKeys(source, constName) {
  const start = source.indexOf(`const ${constName}`);
  if (start === -1) throw new Error(`constant PHP introuvable : ${constName}`);
  const open = source.indexOf('[', start);
  const close = source.indexOf('];', open);
  if (open === -1 || close === -1) throw new Error(`littéral illisible : ${constName}`);
  const body = source.slice(open + 1, close);
  // On ignore les commentaires // pour ne pas lire une clé citée en exemple.
  const withoutComments = body
    .split('\n')
    .filter((line) => !line.trim().startsWith('//'))
    .join('\n');
  return [...withoutComments.matchAll(/'([a-z0-9_]+)'\s*(?:=>|,|\])/gi)].map((m) => m[1]);
}

/** Extrait les clés d'une map PHP `'clé' => 'valeur'`. */
export function phpMapEntries(source, constName) {
  const start = source.indexOf(`const ${constName}`);
  if (start === -1) throw new Error(`constant PHP introuvable : ${constName}`);
  const open = source.indexOf('[', start);
  const close = source.indexOf('];', open);
  const body = source.slice(open + 1, close);
  const withoutComments = body
    .split('\n')
    .filter((line) => !line.trim().startsWith('//'))
    .join('\n');
  return [...withoutComments.matchAll(/'([a-z0-9_]+)'\s*=>\s*'([a-z0-9_]+)'/gi)]
    .map(([, key, value]) => [key, value]);
}

/** Extrait un tableau de chaînes TypeScript `export const NAME: T[] = ['a', 'b'];`. */
export function tsArrayKeys(source, constName) {
  const start = source.indexOf(constName);
  if (start === -1) throw new Error(`constante TS introuvable : ${constName}`);
  const open = source.indexOf('[', start);
  const close = source.indexOf('];', open);
  if (open === -1 || close === -1) throw new Error(`littéral TS illisible : ${constName}`);
  const body = source.slice(open + 1, close);
  const withoutComments = body
    .split('\n')
    .filter((line) => !line.trim().startsWith('//'))
    .join('\n');
  return [...withoutComments.matchAll(/'([a-z0-9_]+)'/gi)].map((m) => m[1]);
}

/** Extrait les modules du catalogue front : clé + présence d'un `href`. */
export function tsCatalogEntries(source) {
  const start = source.indexOf('export const CLIENT_MODULES');
  if (start === -1) throw new Error('CLIENT_MODULES introuvable');
  const end = source.indexOf('\n];', start);
  const body = source.slice(start, end === -1 ? source.length : end);
  const entries = new Map();
  const blocks = body.split(/\n\s*\{\s*\n/).slice(1);
  for (const block of blocks) {
    const key = block.match(/key:\s*'([a-z0-9_]+)'/i);
    if (!key) continue;
    entries.set(key[1], { hasHref: /href:\s*'[^']+'/.test(block) });
  }
  return entries;
}

function fail(message) {
  console.error(`FAIL: ${message}`);
}

export function check({ root = ROOT } = {}) {
  const api = readFileSync(resolve(root, API_MODEL), 'utf8');
  const front = readFileSync(resolve(root, FRONT_CATALOG), 'utf8');
  const flags = readFileSync(resolve(root, FEATURE_FLAGS), 'utf8');

  const horizontalTools = phpArrayKeys(api, 'HORIZONTAL_TOOLS');
  const teamTools = phpArrayKeys(api, 'TEAM_TOOLS');
  const mirrors = phpMapEntries(api, 'HORIZONTAL_TOOL_FEATURES');
  const selfActivable = tsArrayKeys(front, 'SELF_ACTIVATABLE_MODULE_KEYS');
  const catalog = tsCatalogEntries(front);

  const problems = [];
  const duplicates = (list) => list.filter((item, index) => list.indexOf(item) !== index);

  if (horizontalTools.length === 0 || selfActivable.length === 0 || catalog.size === 0) {
    problems.push('une des listes lues est VIDE — parsing cassé (garde inutile).');
  }
  for (const [label, list] of [['HORIZONTAL_TOOLS', horizontalTools], ['SELF_ACTIVATABLE_MODULE_KEYS', selfActivable]]) {
    const dupes = duplicates(list);
    if (dupes.length > 0) problems.push(`${label} contient des doublons : ${dupes.join(', ')}`);
  }

  const apiSet = new Set(horizontalTools);
  const frontSet = new Set(selfActivable);
  for (const key of [...apiSet].sort()) {
    if (!frontSet.has(key)) {
      problems.push(`« ${key} » est activable côté API (HORIZONTAL_TOOLS) mais absent de SELF_ACTIVATABLE_MODULE_KEYS : le client ne peut pas l'activer.`);
    }
  }
  for (const key of [...frontSet].sort()) {
    if (!apiSet.has(key)) {
      problems.push(`« ${key} » est proposé au client (SELF_ACTIVATABLE_MODULE_KEYS) mais absent de HORIZONTAL_TOOLS : l'activation répondra 422 (allowlist fail-closed).`);
    }
  }

  for (const key of [...apiSet].sort()) {
    const entry = catalog.get(key);
    if (!entry) {
      problems.push(`« ${key} » est activable mais n'existe pas dans CLIENT_MODULES : le panneau « Modules & plan » ne peut pas l'afficher.`);
    } else if (!entry.hasHref) {
      problems.push(`« ${key} » est activable mais n'a pas de \`href\` : la liste des outils activables exige un \`href\` (layout.tsx), donc le bouton n'apparaîtrait jamais.`);
    }
  }

  for (const [key, flag] of mirrors) {
    if (!apiSet.has(key)) {
      problems.push(`HORIZONTAL_TOOL_FEATURES mappe « ${key} », qui n'est pas dans HORIZONTAL_TOOLS.`);
    }
    if (!new RegExp(`'${flag}'\\s*=>`).test(flags)) {
      problems.push(`le flag miroir « ${flag} » (pour l'outil « ${key} ») n'existe pas dans api/config/feature-flags.php : on activerait un flag fantôme.`);
    }
  }

  for (const key of teamTools) {
    if (!apiSet.has(key)) {
      problems.push(`TEAM_TOOLS contient « ${key} », absent de HORIZONTAL_TOOLS.`);
    }
  }

  return { problems, horizontalTools, selfActivable };
}

function selfTest() {
  const fixtures = [
    {
      label: 'détecte un outil activable côté API mais absent du web (cas #7476)',
      api: `const HORIZONTAL_TOOLS = [\n        'crm',\n        'cameras',\n    ];\n const TEAM_TOOLS = [\n        'payroll',\n    ];\n const HORIZONTAL_TOOL_FEATURES = [\n        'crm' => 'crm',\n    ];`,
      front: `export const CLIENT_MODULES: ClientModule[] = [\n  {\n    key: 'crm',\n    href: '/crm',\n  },\n];\nexport const SELF_ACTIVATABLE_MODULE_KEYS: ClientModuleKey[] = [\n  'crm',\n];`,
      flags: `'crm' => [`,
      expectProblem: /cameras/,
    },
    {
      label: 'accepte un catalogue symétrique',
      api: `const HORIZONTAL_TOOLS = [\n        'crm',\n    ];\n const TEAM_TOOLS = [\n    ];\n const HORIZONTAL_TOOL_FEATURES = [\n        'crm' => 'crm',\n    ];`,
      front: `export const CLIENT_MODULES: ClientModule[] = [\n  {\n    key: 'crm',\n    href: '/crm',\n  },\n];\nexport const SELF_ACTIVATABLE_MODULE_KEYS: ClientModuleKey[] = [\n  'crm',\n];`,
      flags: `'crm' => [`,
      expectProblem: null,
    },
  ];

  let failed = 0;
  for (const fixture of fixtures) {
    const horizontalTools = phpArrayKeys(fixture.api, 'HORIZONTAL_TOOLS');
    const selfActivable = tsArrayKeys(fixture.front, 'SELF_ACTIVATABLE_MODULE_KEYS');
    const catalog = tsCatalogEntries(fixture.front);
    const mirrors = phpMapEntries(fixture.api, 'HORIZONTAL_TOOL_FEATURES');

    const problems = [];
    const apiSet = new Set(horizontalTools);
    for (const key of apiSet) {
      if (!new Set(selfActivable).has(key)) problems.push(`« ${key} » activable côté API mais absent du web`);
    }
    for (const key of apiSet) {
      const entry = catalog.get(key);
      if (!entry) problems.push(`« ${key} » absent de CLIENT_MODULES`);
      else if (!entry.hasHref) problems.push(`« ${key} » sans href`);
    }
    for (const [key, flag] of mirrors) {
      if (!new RegExp(`'${flag}'\\s*=>`).test(fixture.flags)) problems.push(`flag fantôme ${flag}`);
    }

    const matched = fixture.expectProblem
      ? problems.some((p) => fixture.expectProblem.test(p))
      : problems.length === 0;
    if (!matched) {
      failed += 1;
      console.error(`FAIL self-test — ${fixture.label} → ${problems.join(' | ') || 'aucun problème détecté'}`);
    } else {
      console.log(`OK self-test — ${fixture.label}`);
    }
  }

  return failed === 0 ? 0 : 1;
}

const isMain = process.argv[1] && import.meta.url.endsWith(process.argv[1].split('/').pop());
if (isMain) {
  if (process.argv.includes('--self-test')) {
    process.exit(selfTest());
  }

  let result;
  try {
    result = check();
  } catch (error) {
    console.error(`Erreur d'environnement : ${error.message}`);
    process.exit(2);
  }

  if (result.problems.length > 0) {
    for (const problem of result.problems) fail(problem);
    console.error('\n→ CATALOGUE INCOHÉRENT (#7476). Une clé doit être dans HORIZONTAL_TOOLS (API), dans SELF_ACTIVATABLE_MODULE_KEYS et dans CLIENT_MODULES avec un `href`.');
    process.exit(1);
  }

  console.log(`OK — ${result.horizontalTools.length} outils horizontaux déclarés de façon symétrique (API ↔ web), tous avec une cible de navigation.`);
}
