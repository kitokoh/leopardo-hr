#!/usr/bin/env node
/**
 * check-i18n-diff.js — PA2-I18N-014 blocking CI guard.
 *
 * Fails a PR/push diff that introduces a NEW hardcoded user-visible string
 * on the risk surfaces extended by PA2-I18N-014
 * (.github/workflows/i18n-enterprise.yml): mobile employee/manager/
 * platform_admin (Dart), kiosk (JS/HTML), admin-dashboard (Vue/JS),
 * web app/modules (TSX/TS), and API PDF/email Blade views.
 *
 * This is a heuristic diff-scoped scanner, not a full parser — consistent
 * with the sibling guard `check-hardcoded-accented-messages.sh`. It only
 * looks at ADDED lines between base and head, so pre-existing debt (already
 * measured by `dev-hub/tools/i18n-debt.js`) never blocks unrelated PRs.
 * A line is skipped (not flagged) when it already routes through a known
 * translation call, so legitimate catalog usage is never a false positive.
 *
 * Issue #7482 — faux positifs qui forçaient à réécrire du CODE correct :
 *   - attributs LIÉS d'un template Vue (`:class`, `:key`, `v-model`, `v-if`,
 *     `v-for`, `@click`, …) : la valeur est une EXPRESSION, ses littéraux sont
 *     du code (classe CSS, comparaison `item.x == null`, chemin) ;
 *   - clés d'objet de classes (`:class="{ 'text-red-500': hasError }"`) ;
 *   - chemins de clés de catalogue (`'travel.advert.status'`, `'options.0.label'`).
 * Ces motifs sont désormais ignorés (avec les cas de test correspondants dans
 * `--self-test`, exécuté par `.github/workflows/i18n-enterprise.yml`).
 *
 * Usage:
 *   node dev-hub/tools/check-i18n-diff.js <base_sha> <head_sha>
 *   node dev-hub/tools/check-i18n-diff.js --self-test
 */

'use strict';

const { execFileSync } = require('child_process');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..');

const [baseSha, headSha] = process.argv.slice(2);
const selfTestRequested = process.argv.includes('--self-test');

if (!selfTestRequested && (!baseSha || !headSha)) {
  console.error('Usage: node dev-hub/tools/check-i18n-diff.js <base_sha> <head_sha>');
  console.error('       node dev-hub/tools/check-i18n-diff.js --self-test');
  process.exit(1);
}

// Path globs (as extension + directory prefix pairs) matching the risk
// surfaces PA2-I18N-014 extended CI triggers for.
const watchedPathPrefixes = [
  'front/mobile_apps/leopardo_employee/lib/',
  'front/mobile_apps/leopardo_manager/lib/',
  'front/mobile_apps/leopardo_platform_admin/lib/',
    'front/mobile_apps/leopardo_hr/lib/',
  'front/zkteco-kiosk/',
  'front/admin-dashboard/src/',
  'front/web/src/app/',
  'front/web/src/modules/',
  'api/resources/views/pdf/',
  'api/resources/views/emails/',
];

const watchedExtensions = new Set([
  '.dart', '.js', '.jsx', '.ts', '.tsx', '.vue', '.html', '.blade.php',
]);

const ignorePathFragments = [
  'generated', '.g.dart', '.freezed.dart', '.gen.dart', 'node_modules',
  'dist', 'build', '.next', 'coverage', '/locales/', '/l10n/',
  '/i18n/locales/', '.test.', '.spec.', '_test.dart',
  // Catalogues/contenus localisés de la vitrine : ces fichiers SONT le
  // mécanisme i18n (pas des chaînes hardcodées hors catalogue) — la
  // complétude des traductions reste couverte par validate-and-sync.
  // Issue #3183 : la garde flaggait chaque PR de contenu (ex. #2972, 25 lignes).
  '/vitrine/lib/vitrine-locale.ts', '/vitrine/data/', '/vitrine/lib/content.ts',
  '/vitrine/lib/seo.ts', '/vitrine/lib/seo-metadata.ts',
  // case-studies.ts contient moduleLabelsByLocale (Record<AppLocale,...>) —
  // même mécanique que vitrine-locale.ts : c'est le catalogue inline, pas une
  // chaîne hardcodée hors i18n. Issue #4703.
  '/vitrine/lib/case-studies.ts',
  // Route forms solution-survey (#6692) : messages de réponse français
  // opérationnels, même pattern que les routes forms pré-existantes
  // (contact/signup/newsletter/demo, PA2-MKT-007). Leur localisation est un
  // follow-up documenté dans l'issue #6692.
  '/api/forms/solution-survey/route.ts',
  // Wizard « Je suis restaurateur » (issue #6691) : solution-survey.ts est le
  // catalogue inline ×4 du survey (questions/packages/raisons, même mécanique
  // que vitrine-locale.ts) ; RestaurantSolutionWizard.tsx contient le COPY
  // localisé ×4 de l'UI du wizard (pattern des pages vitrine existantes).
  '/vitrine/lib/solution-survey.ts', '/vitrine/components/RestaurantSolutionWizard.tsx',
];

// Lines that already route text through a translation mechanism — never
// flagged even if they also contain a literal (e.g. the French fallback
// key text of a translation catalog entry itself).
const translationCallPattern = /(context\.l10n\.|AppLocalizations\.of|\bl10n\.|__\(|\$t\(|\bt\(['"]|i18n\.t\(|data-i18n|useTranslation|\bt`|Lang::get\(|trans\(|@lang\(|translate\()/;

const devLogLinePattern = /\b(console\.(log|warn|error|info|debug)|debugPrint|print(?:ln)?|Log\.[dewiv]|logger\.(debug|info|warn|error)|dev\.log)\s*\(/i;
const todoLinePattern = /\/\/\s*TODO|#\s*TODO/i;

const stringLiteralPattern = /(['"])((?:\\.|(?!\1).)*)\1/g;

const tailwindTokenPattern = /^(?:[a-z0-9-]+:)*-?\[?[a-z0-9]+(?:[-./%#\[\]][a-z0-9.]*)*\]?$/;
const bareUtilityWords = new Set([
  'flex', 'grid', 'hidden', 'block', 'inline', 'absolute', 'relative', 'fixed', 'sticky',
  'container', 'truncate', 'uppercase', 'lowercase', 'capitalize', 'italic', 'underline',
  'border', 'shadow', 'rounded', 'transition', 'cursor', 'outline', 'isolate', 'contents',
  'table', 'static', 'visible', 'invisible', 'antialiased', 'select-none', 'sr-only',
]);
const cssDeclarationPattern = /^([a-z-]+\s*:\s*[^;]+;\s*)+$/i;

function isCssClassList(value) {
  const trimmed = value.trim();
  if (!trimmed) return false;
  const tokens = trimmed.split(/\s+/);
  let hyphenatedCount = 0;
  for (const token of tokens) {
    if (!tailwindTokenPattern.test(token) && !bareUtilityWords.has(token)) return false;
    if (token.includes('-') || token.includes(':')) hyphenatedCount += 1;
    if (/[^\x00-\x7f]/.test(token)) return false;
  }
  return hyphenatedCount > 0 || tokens.some((token) => bareUtilityWords.has(token));
}

function isTechnicalToken(value) {
  const trimmed = value.trim();
  if (!trimmed) return true;
  if (/^[a-zA-Z0-9_.\-$]+$/.test(trimmed)) return true;
  if (/^[a-zA-Z0-9_\-./:#?=&{}%$]+$/.test(trimmed) && (trimmed.includes('/') || trimmed.includes('{'))) return true;
  if (/\$\{|\.(toString|padLeft|padRight|encodeComponent)\(/.test(trimmed)) return true;
  if (/^#[a-zA-Z][\w-]*$/.test(trimmed)) return true;
  if (trimmed === 'use client' || trimmed === 'use server' || trimmed === 'use strict') return true;
  if (/^@?[a-zA-Z0-9_.-]+(?:\/[a-zA-Z0-9_.-]+)+$/.test(trimmed)) return true;
  // Imports Next.js alias (« @/modules/... ») — chemin technique, pas une
  // chaîne utilisateur (faux positif signalé sur #6663).
  if (trimmed.startsWith('@/')) return true;
  // Chemin de clé de catalogue (« travel.advert.status », « options.0.label ») :
  // segments séparés par des points, sans espace ni accent — identifiant
  // technique, jamais une chaîne utilisateur (faux positif signalé sur #7482).
  if (/^[A-Za-z][\w-]*(?:\.[\w-]+)+$/.test(trimmed)) return true;
  return /^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS|Bearer\s|https?:\/\/|wss?:\/\/|https?:|wss?:|api\/|\/api|[A-Z_]{2,})$/.test(trimmed);
}

function isCodeExpression(value) {
  const trimmed = value.trim();
  if (!trimmed) return false;
  if (/\$emit\(|\$t\(|=>|&&|\|\||===|!==/.test(trimmed)) return true;
  if (/^[\[{].*[\]}]$/.test(trimmed)) return true;
  if (/^[a-zA-Z_][\w.]*\([^)]*\)$/.test(trimmed)) return true;
  if (/^\(?[a-zA-Z_][\w]*(,\s*[a-zA-Z_][\w]*)?\)?\s+in\s+[a-zA-Z_][\w.]*/.test(trimmed)) return true;
  if (/^![a-zA-Z_]/.test(trimmed)) return true;
  if (/^#[0-9A-Fa-f]{3,8}$/.test(trimmed)) return true;
  return false;
}

// Attributs LIÉS d'un template Vue : `:prop="expr"`, `v-model="expr"`,
// `v-if="expr"`, `v-for="…"`, `@click="handler"`, `v-bind:foo="expr"`…
// Leur valeur est du JavaScript, pas du texte : un littéral qui s'y trouve est
// du code (classe CSS, comparaison, chemin, clé d'objet). Issue #7482.
const vueBoundAttrPattern = /(?:^|\s)([:@][\w.:-]*|v-[\w:.-]+)(\s*=\s*)(["'])/g;

function boundAttributeValue(line, index) {
  vueBoundAttrPattern.lastIndex = 0;
  let match;
  while ((match = vueBoundAttrPattern.exec(line)) !== null) {
    const quote = match[3];
    const valueStart = match.index + match[0].length;
    const valueEnd = line.indexOf(quote, valueStart);
    if (valueEnd === -1) {
      // Attribut non refermé sur cette ligne (valeur multi-lignes) : tout ce qui
      // suit l'ouverture est dans l'expression.
      if (index >= valueStart) return line.slice(valueStart);
      continue;
    }
    if (index >= valueStart && index < valueEnd) return line.slice(valueStart, valueEnd);
  }
  return null;
}

// La valeur d'un attribut lié est-elle une CHAÎNE entre guillemets (et non une
// expression) ? `:title="'Détails'"` reste une chaîne utilisateur en dur et doit
// être signalée ; `:key="'row-' + row.id"` ou `v-model="form[key]"` sont du code.
function isQuotedStringValue(value) {
  const trimmed = value.trim();
  if (trimmed.length < 2) return false;
  const first = trimmed[0];
  const last = trimmed[trimmed.length - 1];
  return (first === last) && (first === "'" || first === '"' || first === '`');
}

// Déclaration technique dans une expression JSX/Vue (`{item.x == null && …}`) :
// l'expression contient un opérateur de comparaison ou un ternaire, donc ses
// littéraux courts sont du code, pas du texte utilisateur.
function isInsideTechnicalExpression(line, index) {
  const before = line.slice(0, index);
  const open = Math.max(before.lastIndexOf('{'), before.lastIndexOf('('));
  if (open === -1) return false;
  const after = line.slice(index);
  const closeIdx = Math.min(...['}', ')'].map((c) => (after.indexOf(c) === -1 ? Infinity : after.indexOf(c))));
  const expression = line.slice(open + 1, closeIdx === Infinity ? line.length : index + closeIdx);
  return /(==|!=|===|!==|\?\?|\&\&|\|\||\?\s)/.test(expression);
}

function classifyLiteral(rawValue) {
  const value = rawValue.trim();
  if (value.length < 4) return null;
  if (!/[\p{Letter}]/u.test(value)) return null;
  if (isCssClassList(value)) return null;
  if (cssDeclarationPattern.test(value)) return null;
  if (isTechnicalToken(value)) return null;
  if (isCodeExpression(value)) return null;
  return value;
}

function isWatchedFile(filePath) {
  const normalized = filePath.split(path.sep).join('/');
  if (ignorePathFragments.some((fragment) => normalized.includes(fragment))) return false;
  if (!watchedPathPrefixes.some((prefix) => normalized.startsWith(prefix))) return false;
  if (normalized.endsWith('.blade.php')) return true;
  const ext = path.extname(normalized);
  return watchedExtensions.has(ext);
}

function git(args) {
  return execFileSync('git', args, { cwd: repoRoot, encoding: 'utf8', maxBuffer: 1024 * 1024 * 64 });
}

function ensureCommit(sha) {
  try {
    git(['cat-file', '-e', `${sha}^{commit}`]);
  } catch (_err) {
    try {
      git(['fetch', '--no-tags', '--depth=50', 'origin', sha]);
    } catch (_fetchErr) {
      // best-effort; if this still fails, the diff call below will throw
      // with a clear git error.
    }
  }
}

function main() {
  ensureCommit(baseSha);
  ensureCommit(headSha);

  // -M enables rename detection: without it, a pure `git mv` (no content
  // change) is reported as the old path deleted + the new path added, so
  // a per-file diff for the new path alone would show the *entire* moved
  // file as newly added lines and flag long-shipped, already-translated
  // strings as new violations (false positive discovered by PA2-MKT-011,
  // issue #1281). Rename detection only works when git can see both the
  // old and new paths together, so the full diff is computed once (not
  // re-filtered per file via a `-- <path>` pathspec, which would hide the
  // old path from that comparison and silently disable the rename match)
  // and then split by file below.
  const fullDiff = git(['diff', '-M', '-U0', baseSha, headSha]);
  const diffFileHeaderPattern = /^diff --git a\/.*? b\/(.+)$/;

  const changedFiles = [];
  const violations = [];
  let violationCount = 0;

  let currentFile = null;
  let currentNewLine = 0;
  let removedLinesThisHunk = [];

  for (const rawLine of fullDiff.split('\n')) {
    const fileHeaderMatch = rawLine.match(diffFileHeaderPattern);
    if (fileHeaderMatch) {
      currentFile = isWatchedFile(fileHeaderMatch[1]) ? fileHeaderMatch[1] : null;
      if (currentFile) {
        changedFiles.push(currentFile);
      }
      currentNewLine = 0;
      continue;
    }

    if (!currentFile) {
      continue;
    }

    const hunkMatch = rawLine.match(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/);
    if (hunkMatch) {
      currentNewLine = parseInt(hunkMatch[1], 10);
      removedLinesThisHunk = [];
      continue;
    }
    if (rawLine.startsWith('-') && !rawLine.startsWith('---')) {
      removedLinesThisHunk.push(rawLine.slice(1));
      continue;
    }
    if (!rawLine.startsWith('+') || rawLine.startsWith('+++')) {
      continue;
    }
    const content = rawLine.slice(1);
    const lineNo = currentNewLine;
    currentNewLine += 1;

    // Whitespace-only and encoding-repair edits (NBSP->space, mojibake->UTF-8,
    // corrupted emoji->real emoji) are not "new" strings: compare the ASCII
    // skeleton of the added line against the removed lines of the same hunk.
    const normalizeWs = (v) => v.replace(/[\u00a0\u2000-\u200b\u202f\u205f\u3000\u0085]+/g, ' ').trimEnd();
    const repairMojibakeAscii = (v) => v
      .replace(/â€™|â€˜/g, "'")
      .replace(/â€œ|â€\x9d/g, '"');
    const asciiSkeleton = (v) => v.replace(/[^\x00-\x7f]/g, '').replace(/\s+/g, '');
    const addedSkeleton = asciiSkeleton(normalizeWs(repairMojibakeAscii(content)));
    // Reformatage pur (dart format ré-enroule les chaînes) : la ligne ajoutée
    // est un sous-ensemble ASCII d'une ligne retirée du même hunk — pas une
    // nouvelle chaîne utilisateur.
    const removedSkeletons = removedLinesThisHunk
      .map((removed) => asciiSkeleton(normalizeWs(repairMojibakeAscii(removed))))
      .filter((sk) => sk.length > 0);
    const isReflow = removedSkeletons.some((removedSkeleton) =>
        removedSkeleton === addedSkeleton
        || (addedSkeleton.length >= 6 && removedSkeleton.includes(addedSkeleton)))
      || (addedSkeleton.length >= 6 && removedSkeletons.join('').includes(addedSkeleton));
    if (isReflow) {
      currentNewLine += 1;
      continue;
    }

    const trimmed = content.trim();
    if (/^(\/\/|#|\*|<!--)/.test(trimmed)) continue;
    if (/^\s*(import|export)\s/.test(content)) continue;
    if (translationCallPattern.test(content)) continue;
    if (devLogLinePattern.test(content) || todoLinePattern.test(content)) continue;

    stringLiteralPattern.lastIndex = 0;
    let match;
    while ((match = stringLiteralPattern.exec(content)) !== null) {
      const flagged = classifyLiteral(match[2]);
      if (!flagged) continue;
      // Contexte d'expression : attribut lié (Vue) ou expression JSX technique.
      // Issue #7482 — sans ces deux règles, la garde demandait de réécrire du
      // code correct (`v-model="form[key]"`, `:class="item.x == null && …"`).
      const literalIndex = match.index + 1;
      const boundValue = boundAttributeValue(content, literalIndex);
      // Dans un attribut lié, un littéral est du CODE — sauf si la valeur EST une
      // chaîne entre guillemets (`:title="'Détails'"` → vraie chaîne en dur).
      if (boundValue !== null && !isQuotedStringValue(boundValue)) continue;
      if (isInsideTechnicalExpression(content, literalIndex)) continue;
      // Faux positif de reformatage : le littéral (ou son squelette ASCII)
      // existait déjà dans une ligne retirée du même hunk.
      const flaggedAscii = asciiSkeleton(flagged);
      const literalExisted = removedLinesThisHunk.some((removed) => {
        const removedAscii = asciiSkeleton(removed);
        return removed.includes(flagged) || removedAscii.includes(flaggedAscii);
      });
      if (literalExisted) continue;
      violationCount += 1;
      violations.push({ file: currentFile, line: lineNo, text: flagged });
    }
  }

  if (changedFiles.length === 0) {
    console.log('No files under PA2-I18N-014 risk surfaces changed in this diff — nothing to check.');
    return;
  }

  console.log(`Checked ${changedFiles.length} file(s) under PA2-I18N-014 risk surfaces.`);

  if (violationCount > 0) {
    console.error('');
    console.error(`❌ Found ${violationCount} new hardcoded user-visible string(s):`);
    for (const violation of violations.slice(0, 50)) {
      console.error(`   ${violation.file}:${violation.line} — ${violation.text}`);
    }
    if (violations.length > 50) {
      console.error(`   ... ${violations.length - 50} more`);
    }
    console.error('');
    console.error('Route new user-visible text through the i18n catalog instead of a literal:');
    console.error('  - Flutter: context.l10n.xxx (front/mobile_apps/leopardo_core/lib/l10n)');
    console.error('  - Vue admin: $t(\'xxx\') (front/admin-dashboard/src/i18n/locales)');
    console.error('  - Next.js web: shared/i18n or front/web/src/lib/i18n catalog');
    console.error('  - Kiosk: data-i18n / shared/i18n catalog (front/zkteco-kiosk)');
    console.error('  - API Blade PDF/emails: __(\'catalog.key\') (api/lang/*.php)');
    console.error('If this is a false positive (technical constant, enum, log message), adjust the');
    console.error('literal or open an issue against dev-hub/tools/check-i18n-diff.js heuristics.');
    console.error('');
    console.error('Motifs déjà couverts par la garde (ne pas contourner en réécrivant le code) :');
    console.error('  - attribut lié Vue : :class/:key/:style/v-model/v-if/v-for/@click portent une');
    console.error('    EXPRESSION — ses littéraux sont du code, pas du texte utilisateur ;');
    console.error('  - classe CSS conditionnelle : :class="{ \'text-red-500\': hasError }" ;');
    console.error('  - chemin de clé de catalogue : \'travel.advert.status\', \'options.0.label\' ;');
    console.error('  - comparaison/ternaire technique dans une expression : {item.x == null && …}.');
    console.error('  - constante technique hors template : déplacez-la dans <script setup> ou un module');
    console.error('    (une constante de script n\'est pas une chaîne utilisateur).');
    console.error('Si un motif légitime est encore signalé, ajoutez son cas à --self-test dans la même PR.');
    process.exit(1);
  }

  console.log('✅ No new hardcoded user-visible strings introduced on PA2-I18N-014 risk surfaces.');
}

// ---------------------------------------------------------------------------
// --self-test (issue #7482, critère 3) : cas de non-régression du classifieur.
// Chaque cas = une ligne réelle (template Vue, TSX, Dart, Blade) + l'attente.
// Lancé par `.github/workflows/i18n-enterprise.yml` — sinon la garde dérive.
// ---------------------------------------------------------------------------
const SELF_TEST_CASES = [
  // --- vrais positifs : chaînes utilisateur en dur, doivent être signalées ---
  { line: "{ title: 'Bienvenue dans votre espace' },", expect: 'flag', why: 'copie en dur (objet)' },
  { line: "const label = 'Supprimer la ligne selectionnee';", expect: 'flag', why: 'copie en dur dans un script' },
  { line: '<input placeholder="Rechercher un employe" />', expect: 'flag', why: 'attribut NON lie (texte)' },
  { line: "throw new Error('Utilisateur introuvable dans ce tenant');", expect: 'flag', why: 'message d erreur utilisateur' },
  { line: ':title="\'Details de la ligne\'"', expect: 'flag', why: 'litteral = toute la valeur d un attribut lie' },
  { line: "const empty = 'Aucun employe dans cet espace';", expect: 'flag', why: 'etat vide en dur' },
  // --- faux positifs signales dans #7482 : ne doivent PLUS être signales ---
  { line: '<input v-model="form[key]" />', expect: 'ok', why: 'v-model = expression' },
  { line: '<tr :key="\'row-\' + row.id">', expect: 'ok', why: ':key = expression' },
  { line: '<div :class="item.x == null ? \'text-slate-400\' : \'text-emerald-600\'">', expect: 'ok', why: 'comparaison + classes dans un attribut lie' },
  { line: '<div :class="{ \'text-red-500\': hasError }">', expect: 'ok', why: 'cle d objet de classes' },
  { line: '<li v-for="item in items" :key="item.id">{{ t(\'common.item\') }}</li>', expect: 'ok', why: 'v-for + appel de traduction' },
  { line: "const catalogKey = 'options.0.label';", expect: 'ok', why: 'chemin de cle de catalogue' },
  { line: "const statusKey = 'travel.advert.status';", expect: 'ok', why: 'chemin de cle de catalogue' },
  { line: '<DataTable :rows="rows" :columns="advertTabColumns()" />', expect: 'ok', why: 'attributs lies, aucun texte' },
  { line: '<span :style="{ color: \'red\' }">x</span>', expect: 'ok', why: ':style = expression' },
  { line: '<div :class="`text-${tone}-600`">x</div>', expect: 'ok', why: 'classe calculee' },
];
function runSelfTest() {
  let failures = 0;
  for (const testCase of SELF_TEST_CASES) {
    const found = [];
    stringLiteralPattern.lastIndex = 0;
    let match;
    while ((match = stringLiteralPattern.exec(testCase.line)) !== null) {
      const flagged = classifyLiteral(match[2]);
      if (!flagged) continue;
      const literalIndex = match.index + 1;
      const boundValue = boundAttributeValue(testCase.line, literalIndex);
      if (boundValue !== null && !isQuotedStringValue(boundValue)) continue;
      if (isInsideTechnicalExpression(testCase.line, literalIndex)) continue;
      found.push(flagged);
    }
    const flaggedNow = found.length > 0;
    const expected = testCase.expect === 'flag';
    if (flaggedNow !== expected) {
      failures += 1;
      console.error(`❌ ${testCase.expect === 'flag' ? 'devrait être signalé' : 'faux positif'} : ${testCase.line}`);
      console.error(`   (${testCase.why}) — littéraux vus : ${found.length ? found.join(' | ') : 'aucun'}`);
    }
  }
  if (failures > 0) {
    console.error(`\n${failures}/${SELF_TEST_CASES.length} cas en échec — garde check-i18n-diff.js non conforme (issue #7482).`);
    process.exit(1);
  }
  console.log(`✅ check-i18n-diff.js auto-test : ${SELF_TEST_CASES.length} cas conformes (vrais positifs détectés, faux positifs #7482 ignorés).`);
}

if (selfTestRequested) {
  runSelfTest();
} else {
  main();
}
