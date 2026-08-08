#!/usr/bin/env node
/* zkteco-kiosk — tests i18n (issue #1501).
 *
 * Sans dépendance : vérifie que
 *   1. chaque langue supportée a un catalogue complet (parité de clés),
 *   2. chaque `data-i18n="KEY"` / `data-i18n-*="KEY"` utilisé dans les
 *      pages HTML existe dans les 4 catalogues,
 *   3. chaque `t('KEY')` utilisé dans app.js/admin.js existe aussi.
 */
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const i18nSource = readFileSync(join(root, 'i18n.js'), 'utf8');

// Shim `window` (avec un DOM minimal : l'init d'i18n.js touche document)
const window = {
  document: {
    documentElement: { dir: '', setAttribute() {}, getAttribute: () => null },
    querySelectorAll: () => [],
    querySelector: () => null,
    createElement: () => ({ style: {}, value: '', addEventListener() {} }),
  },
};
global.window = window;
eval(i18nSource); // eslint-disable-line no-eval
const KioskI18n = window.KioskI18n;
if (!KioskI18n) {
  console.error('❌ KioskI18n introuvable après évaluation de i18n.js');
  process.exit(1);
}

const CATALOG = KioskI18n._catalog || extractCatalog();
function extractCatalog() {
  // Fallback : ré-évaluer en capturant le CATALOG via une sonde
  const probe = {};
  global.window = probe;
  const src = i18nSource.replace('})(window);', '})(globalThis);');
  const m = src.match(/var CATALOG = (\{[\s\S]*?\n  \});/);
  if (!m) throw new Error('CATALOG introuvable');
  return Function('"use strict"; return (' + m[1] + ');')();
}

const supported = KioskI18n.supported;
const langKeys = {};
let errors = 0;

for (const lang of supported) {
  const keys = Object.keys(CATALOG[lang] || {});
  langKeys[lang] = new Set(keys);
  if (keys.length === 0) {
    console.error(`❌ Catalogue vide pour "${lang}"`);
    errors++;
  }
}

// 1. Parité des clés entre langues
const ref = supported[0];
for (const lang of supported.slice(1)) {
  const missing = [...langKeys[ref]].filter((k) => !langKeys[lang].has(k));
  const extra = [...langKeys[lang]].filter((k) => !langKeys[ref].has(k));
  if (missing.length) { console.error(`❌ [${lang}] clés manquantes vs ${ref}: ${missing.join(', ')}`); errors++; }
  if (extra.length) { console.error(`❌ [${lang}] clés en trop vs ${ref}: ${extra.join(', ')}`); errors++; }
}

// 2. data-i18n* dans les pages
for (const page of ['index.html', 'admin.html']) {
  const html = readFileSync(join(root, page), 'utf8');
  for (const key of html.matchAll(/data-i18n(?:-placeholder|-aria-label)?="([^"]+)"/g)) {
    if (!langKeys[ref].has(key[1])) {
      console.error(`❌ ${page}: clé i18n inconnue "${key[1]}"`);
      errors++;
    }
  }
}

// 3. t('KEY') dans les JS
for (const js of ['app.js', 'admin.js']) {
  const code = readFileSync(join(root, js), 'utf8');
  for (const key of code.matchAll(/\bt\(\s*'([^']+)'\s*(?:,|\()/g)) {
    if (!langKeys[ref].has(key[1])) {
      console.error(`❌ ${js}: clé i18n inconnue "${key[1]}"`);
      errors++;
    }
  }
}

// 4. RTL
if (!KioskI18n.isRtl('ar')) {
  console.error('❌ ar doit être RTL');
  errors++;
}

if (errors) {
  console.error(`\n✖ ${errors} problème(s) i18n`);
  process.exit(1);
}
console.log(`✓ i18n OK : ${supported.length} langues (${supported.join(',')}), ${langKeys[ref].size} clés, parité + usage vérifiés.`);
