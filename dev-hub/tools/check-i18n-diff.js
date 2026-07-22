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
 * Usage:
 *   node dev-hub/tools/check-i18n-diff.js <base_sha> <head_sha>
 */

'use strict';

const { execFileSync } = require('child_process');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..');

const [baseSha, headSha] = process.argv.slice(2);

if (!baseSha || !headSha) {
  console.error('Usage: node dev-hub/tools/check-i18n-diff.js <base_sha> <head_sha>');
  process.exit(1);
}

// Path globs (as extension + directory prefix pairs) matching the risk
// surfaces PA2-I18N-014 extended CI triggers for.
const watchedPathPrefixes = [
  'front/mobile_apps/leopardo_employee/lib/',
  'front/mobile_apps/leopardo_manager/lib/',
  'front/mobile_apps/leopardo_platform_admin/lib/',
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
];

// Lines that already route text through a translation mechanism — never
// flagged even if they also contain a literal (e.g. the French fallback
// key text of a translation catalog entry itself).
const translationCallPattern = /(context\.l10n\.|AppLocalizations\.of|\bl10n\.|__\(|\$t\(|\bt\(['"]|i18n\.t\(|data-i18n|useTranslation|\bt`|Lang::get\(|trans\(|@lang\()/;

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
  return /^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS|Bearer\s|https?:\/\/|api\/|\/api|[A-Z_]{2,})$/.test(trimmed);
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

  const changedFilesRaw = git(['diff', '--name-only', '--diff-filter=ACMR', baseSha, headSha]);
  const changedFiles = changedFilesRaw.split('\n').filter(Boolean).filter(isWatchedFile);

  if (changedFiles.length === 0) {
    console.log('No files under PA2-I18N-014 risk surfaces changed in this diff — nothing to check.');
    return;
  }

  let violationCount = 0;
  const violations = [];

  for (const filePath of changedFiles) {
    const diffOutput = git(['diff', '-U0', baseSha, headSha, '--', filePath]);
    const lines = diffOutput.split('\n');
    let currentNewLine = 0;

    for (const rawLine of lines) {
      const hunkMatch = rawLine.match(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/);
      if (hunkMatch) {
        currentNewLine = parseInt(hunkMatch[1], 10);
        continue;
      }
      if (!rawLine.startsWith('+') || rawLine.startsWith('+++')) {
        continue;
      }
      const content = rawLine.slice(1);
      const lineNo = currentNewLine;
      currentNewLine += 1;

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
        violationCount += 1;
        violations.push({ file: filePath, line: lineNo, text: flagged });
      }
    }
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
    process.exit(1);
  }

  console.log('✅ No new hardcoded user-visible strings introduced on PA2-I18N-014 risk surfaces.');
}

main();
