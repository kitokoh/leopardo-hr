#!/usr/bin/env node
/* zkteco-kiosk — tests feedback audio (issue #1628).
 *
 * Sans dépendance : vérifie que le module `feedback` existe dans app.js,
 * expose success()/error(), et que submitPunch appelle le feedback sur les
 * chemins succès/échec (scan statique du source).
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const appJs = readFileSync(join(root, 'app.js'), 'utf8');
const indexHtml = readFileSync(join(root, 'index.html'), 'utf8');

let failed = false;
const check = (cond, label) => {
  if (cond) console.log(`✅ ${label}`);
  else { console.error(`❌ ${label}`); failed = true; }
};

check(/const feedback = \(\(\) => \{[\s\S]*?success\(\)[\s\S]*?error\(\)[\s\S]*?\}\)\(\);/.test(appJs),
  'module feedback avec success()/error()');
check(/feedback\.success\(\)/.test(appJs), 'feedback.success() appelé sur succès');
check(/feedback\.error\(\)/.test(appJs), 'feedback.error() appelé sur échec');
check(/status-pulse-ok/.test(indexHtml) && /status-pulse-error/.test(indexHtml),
  'classes de pulse visuel définies dans index.html');
check(/@keyframes statusPulseOk/.test(indexHtml) && /@keyframes statusPulseError/.test(indexHtml),
  'keyframes de pulse définis');

if (failed) process.exit(1);
console.log('feedback.test OK');
