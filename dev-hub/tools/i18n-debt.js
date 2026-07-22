#!/usr/bin/env node
/**
 * i18n-debt.js — Reliable hardcoded-text debt scanner.
 *
 * Replaces `validate-i18n-debt.ps1` (PowerShell, counted CSS/Tailwind
 * utility classes as "text" and inflated the total to 11,642 signals,
 * masking the real signal — see docs/PLAN_ACTION2/10_AUDIT_I18N_MULTILINGUE.md
 * section 5). This Node rewrite:
 *
 *   1. Filters out CSS/Tailwind utility class lists (space-separated
 *      lowercase utility tokens like `flex items-center gap-2`).
 *   2. Filters out technical routes/paths (`/api/v1/employees/{id}`).
 *   3. Separates developer/log-only text (console.*, print, debugPrint,
 *      Log.*, // TODO, error codes) from user-visible UI text, so the
 *      P1/P2 counts only reflect strings a real user could see.
 *
 * Usage:
 *   node dev-hub/tools/i18n-debt.js [--strict] [--report <path>]
 *
 * --strict exits non-zero when P1 (user-visible, high-priority) signals
 * remain, mirroring the previous script's --Strict switch.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..');

const args = process.argv.slice(2);
const strict = args.includes('--strict');
const reportFlagIndex = args.indexOf('--report');
const todayStamp = new Date().toISOString().slice(0, 10).replace(/-/g, '_');
const defaultReportPath = `docs/validation/I18N_DEBT_REPORT_${todayStamp}.md`;
const reportPath = reportFlagIndex !== -1 && args[reportFlagIndex + 1]
  ? args[reportFlagIndex + 1]
  : defaultReportPath;

const surfaces = [
  {
    name: 'mobile_employee',
    dir: 'front/mobile_apps/leopardo_employee/lib',
    extensions: ['.dart'],
    priority: ['login', 'account', 'settings', 'attendance', 'notifications'],
  },
  {
    name: 'mobile_manager',
    dir: 'front/mobile_apps/leopardo_manager/lib',
    extensions: ['.dart'],
    priority: ['login', 'account', 'team', 'attendance', 'approvals', 'notifications'],
  },
  {
    name: 'mobile_platform_admin',
    dir: 'front/mobile_apps/leopardo_platform_admin/lib',
    extensions: ['.dart'],
    priority: ['login', 'companies', 'company', 'dashboard'],
  },
  {
    name: 'web_client',
    dir: 'front/web/src',
    extensions: ['.ts', '.tsx'],
    priority: ['login', 'pricing', 'demo', 'contact', 'account'],
  },
  {
    name: 'admin_dashboard',
    dir: 'front/admin-dashboard/src',
    extensions: ['.js', '.vue'],
    priority: ['login', 'dashboard', 'companies', 'users', 'settings'],
  },
  {
    name: 'kiosk',
    dir: 'front/zkteco-kiosk',
    extensions: ['.ts', '.tsx', '.js', '.jsx', '.vue', '.html'],
    priority: ['kiosk', 'punch', 'employee', 'offline'],
  },
];

const ignorePathPatterns = [
  'generated',
  '.g.dart',
  '.freezed.dart',
  '.gen.dart',
  'node_modules',
  'dist',
  'build',
  '.next',
  'coverage',
  '/locales/',
  '/l10n/',
  '/i18n/locales/',
];

// Matches a single quoted or double quoted string literal, non-greedy,
// tolerant of escaped quotes inside.
const stringLiteralPattern = /(['"])((?:\\.|(?!\1).)*)\1/g;

// Lines that are dev/log-only: the string inside them is developer output,
// not something an end user reads on screen.
const devLogLinePattern = /\b(console\.(log|warn|error|info|debug)|debugPrint|print(?:ln)?|Log\.[dewiv]|logger\.(debug|info|warn|error)|dev\.log)\s*\(/i;
const todoLinePattern = /\/\/\s*TODO|#\s*TODO/i;

// Tokens that look like Tailwind/CSS utility classes: lowercase/digits,
// optional responsive/state prefixes (sm:, hover:, dark:, group-hover:),
// hyphenated utility names, optional bracket/arbitrary values.
const tailwindTokenPattern = /^(?:[a-z0-9-]+:)*-?\[?[a-z0-9]+(?:[-./%#\[\]][a-z0-9.]*)*\]?$/;
const bareUtilityWords = new Set([
  'flex', 'grid', 'hidden', 'block', 'inline', 'absolute', 'relative', 'fixed', 'sticky',
  'container', 'truncate', 'uppercase', 'lowercase', 'capitalize', 'italic', 'underline',
  'border', 'shadow', 'rounded', 'transition', 'cursor', 'outline', 'isolate', 'contents',
  'table', 'static', 'visible', 'invisible', 'antialiased', 'select-none', 'sr-only',
]);

// Matches inline CSS declaration lists, e.g. `display:flex; gap:12px;`
// (property:value; pairs), distinct from a single Tailwind class list.
const cssDeclarationPattern = /^([a-z-]+\s*:\s*[^;]+;\s*)+$/i;

function isCssDeclarationList(value) {
  return cssDeclarationPattern.test(value.trim());
}

function isCssClassList(value) {
  const trimmed = value.trim();
  if (!trimmed) return false;
  const tokens = trimmed.split(/\s+/);
  if (tokens.length === 0) return false;
  let hyphenatedCount = 0;
  for (const token of tokens) {
    if (!tailwindTokenPattern.test(token) && !bareUtilityWords.has(token)) {
      return false;
    }
    if (token.includes('-') || token.includes(':')) {
      hyphenatedCount += 1;
    }
    // Any accented / non-ASCII letter breaks the "pure utility class" theory.
    if (/[^\x00-\x7f]/.test(token)) {
      return false;
    }
  }
  // Require at least one utility-shaped token (hyphen/colon) or a known
  // bare word so plain lowercase identifiers ("ok", "done") are not
  // misclassified as CSS.
  return hyphenatedCount > 0 || tokens.some((token) => bareUtilityWords.has(token));
}

// Mirrors the original PowerShell Test-HumanText gate: reject pure
// identifier/technical tokens (snake_case keys, routes, HTTP verbs,
// constants) that never read as human-visible text regardless of case.
function isTechnicalToken(value) {
  const trimmed = value.trim();
  if (!trimmed) return true;
  // Single identifier-shaped token: letters/digits/underscore/hyphen/dot/
  // interpolation-sigil only, no whitespace - e.g. `work_type`, `gps_lat`,
  // `$logId`, `offline_punches`.
  if (/^[a-zA-Z0-9_.\-$]+$/.test(trimmed)) {
    return true;
  }
  // Path-like or query-string-like: route-safe characters plus interpolated
  // path segments (`$var`), and a structural separator.
  if (/^[a-zA-Z0-9_\-./:#?=&{}%$]+$/.test(trimmed) && (trimmed.includes('/') || trimmed.includes('{'))) {
    return true;
  }
  // Leftover fragment of a template-literal expression that our regex-based
  // quote matcher could not fully resolve (nested quotes inside `${...}`),
  // e.g. `${date.year.toString().padLeft(4,`. Recognizable by an unbalanced
  // `${` / method-call shape with no natural-language spacing around it.
  if (/\$\{|\.(toString|padLeft|padRight|encodeComponent)\(/.test(trimmed)) {
    return true;
  }
  // CSS/DOM selector literal, e.g. `#contact-form`, `#companyName`.
  if (/^#[a-zA-Z][\w-]*$/.test(trimmed)) {
    return true;
  }
  // Next.js/React module directives, not user-visible text.
  if (trimmed === 'use client' || trimmed === 'use server' || trimmed === 'use strict') {
    return true;
  }
  // Package import specifier, e.g. `@heroicons/vue/24/outline`, `lodash/get`.
  if (/^@?[a-zA-Z0-9_.-]+(?:\/[a-zA-Z0-9_.-]+)+$/.test(trimmed)) {
    return true;
  }
  return /^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS|Bearer\s|https?:\/\/|api\/|\/api|[A-Z_]{2,})$/.test(trimmed);
}

// Vue/JSX template attribute values that are actually bound JS expressions,
// not literal user-visible text (`:class="['a', b]"`, `v-for="x in y"`,
// `@click="$emit('x')"`, computed hex colors used as style values, etc.).
// The naive quote-matching scanner cannot distinguish a text node from an
// attribute-bound expression, so this filters the common expression shapes.
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

function isIgnoredPath(filePath) {
  const normalized = filePath.split(path.sep).join('/');
  return ignorePathPatterns.some((pattern) => normalized.includes(pattern));
}

function classify(rawValue, line) {
  const value = rawValue.trim();
  if (value.length < 4) return null;
  if (!/[\p{Letter}]/u.test(value)) return null;
  if (isCssClassList(value)) return null;
  if (isCssDeclarationList(value)) return null;
  if (isTechnicalToken(value)) return null;
  if (isCodeExpression(value)) return null;
  const isDev = devLogLinePattern.test(line) || todoLinePattern.test(line);
  return { text: value, isDev };
}

function severity(relativePath, priorityTerms) {
  const lower = relativePath.toLowerCase();
  return priorityTerms.some((term) => lower.includes(term)) ? 'P1' : 'P2';
}

function walk(dir, extensions, files = []) {
  if (!fs.existsSync(dir)) return files;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, entry.name);
    if (isIgnoredPath(fullPath)) continue;
    if (entry.isDirectory()) {
      walk(fullPath, extensions, files);
    } else if (extensions.includes(path.extname(entry.name))) {
      files.push(fullPath);
    }
  }
  return files;
}

function scanSurface(surface) {
  const absoluteDir = path.join(repoRoot, surface.dir);
  const files = walk(absoluteDir, surface.extensions);
  const findings = [];
  const devFindings = [];

  for (const filePath of files) {
    const relativePath = path.relative(repoRoot, filePath).split(path.sep).join('/');
    const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);
    lines.forEach((line, index) => {
      if (/^\s*(import|export)\s/.test(line)) return;
      let match;
      stringLiteralPattern.lastIndex = 0;
      while ((match = stringLiteralPattern.exec(line)) !== null) {
        const classified = classify(match[2], line);
        if (!classified) continue;
        const record = {
          surface: surface.name,
          severity: severity(relativePath, surface.priority),
          file: relativePath,
          line: index + 1,
          text: classified.text,
        };
        if (classified.isDev) {
          devFindings.push(record);
        } else {
          findings.push(record);
        }
      }
    });
  }

  return { findings, devFindings };
}

function buildReport(results) {
  const allFindings = results.flatMap((r) => r.findings);
  const allDev = results.flatMap((r) => r.devFindings);
  const p1 = allFindings.filter((f) => f.severity === 'P1').length;
  const p2 = allFindings.filter((f) => f.severity === 'P2').length;
  const date = new Date().toISOString().slice(0, 10);

  const lines = [];
  lines.push(`# I18N Hardcoded-Text Debt Report — ${date}`);
  lines.push('');
  lines.push('Generated by `dev-hub/tools/i18n-debt.js` (Node rewrite, PA2-I18N-015). Replaces the');
  lines.push('previous PowerShell-based `I18N_DEBT_REPORT_2026_06_06.md`, whose detector counted');
  lines.push('CSS/Tailwind utility classes as text signals and inflated the total to 11,642 —');
  lines.push('masking the real debt (see `docs/PLAN_ACTION2/10_AUDIT_I18N_MULTILINGUE.md` section 5).');
  lines.push('This scanner filters out utility-class strings and technical routes, and separates');
  lines.push('developer/log-only text (console/print/debugPrint/TODO) from user-visible UI text so');
  lines.push('the counts below only reflect strings a real user could actually see on screen.');
  lines.push('');
  lines.push('## Summary');
  lines.push('');
  lines.push(`- Total user-visible signals: ${allFindings.length}`);
  lines.push(`- P1 (priority screens: login/attendance/account/...): ${p1}`);
  lines.push(`- P2 (other screens): ${p2}`);
  lines.push(`- Developer/log-only signals (informational, not counted above): ${allDev.length}`);
  lines.push('');
  lines.push('## By surface');
  lines.push('');

  for (const result of results) {
    const surfaceFindings = result.findings;
    const surfaceP1 = surfaceFindings.filter((f) => f.severity === 'P1').length;
    const surfaceP2 = surfaceFindings.filter((f) => f.severity === 'P2').length;
    lines.push(`### ${result.surface}`);
    lines.push('');
    lines.push(`- Signals: ${surfaceFindings.length}`);
    lines.push(`- P1: ${surfaceP1}`);
    lines.push(`- P2: ${surfaceP2}`);
    lines.push(`- Dev/log-only (informational): ${result.devFindings.length}`);
    lines.push('');
    const sorted = [...surfaceFindings].sort((a, b) => {
      if (a.severity !== b.severity) return a.severity === 'P1' ? -1 : 1;
      if (a.file !== b.file) return a.file.localeCompare(b.file);
      return a.line - b.line;
    });
    for (const item of sorted.slice(0, 25)) {
      let text = item.text;
      if (text.length > 90) text = `${text.slice(0, 87)}...`;
      lines.push(`- [${item.severity}] \`${item.file}:${item.line}\` ${text}`);
    }
    if (sorted.length > 25) {
      lines.push(`- ... ${sorted.length - 25} more signals`);
    }
    lines.push('');
  }

  lines.push('## Execution rule');
  lines.push('');
  lines.push('1. Migrate P1 signals first (login, account, attendance, client creation, trial');
  lines.push('   showcase, kiosk screens).');
  lines.push('2. Add new keys to `shared/i18n/locales/fr.json`, then translate EN/AR/TR using the');
  lines.push('   Jules guide prompts (`docs/GUIDES/GUIDE_JULES_TRADUCTION_MULTILINGUE.md`).');
  lines.push('3. Sync to frontend/mobile targets with the existing `shared/i18n/sync/*.js` scripts');
  lines.push('   once a target-specific sync exists for the surface.');
  lines.push('4. Keep technical text, routes, API codes, and developer logs out of translation —');
  lines.push('   this scanner already excludes them, do not re-flag them manually.');
  lines.push('');

  return { report: lines.join('\n'), p1Count: p1, p2Count: p2, total: allFindings.length };
}

function main() {
  const results = surfaces.map((surface) => ({ surface: surface.name, ...scanSurface(surface) }));
  const { report, p1Count, p2Count, total } = buildReport(results);

  const absoluteReportPath = path.join(repoRoot, reportPath);
  fs.mkdirSync(path.dirname(absoluteReportPath), { recursive: true });
  fs.writeFileSync(absoluteReportPath, `${report}\n`, 'utf8');

  console.log(`I18N_DEBT_REPORT_WRITTEN ${reportPath}`);
  console.log(`I18N_DEBT_TOTAL ${total}`);
  console.log(`I18N_DEBT_P1 ${p1Count}`);
  console.log(`I18N_DEBT_P2 ${p2Count}`);

  if (strict && p1Count > 0) {
    console.error(`I18N debt strict mode failed: ${p1Count} P1 hardcoded text signals remain.`);
    process.exitCode = 1;
  }
}

main();
