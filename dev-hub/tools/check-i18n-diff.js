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

// Test-only override (issue #7482): the self-test
// (dev-hub/tools/check-i18n-diff-test.sh) runs this guard against a throwaway
// git repository of fixtures. In CI the variable is unset and the guard keeps
// scanning the repository it lives in.
const repoRoot = process.env.I18N_DIFF_REPO_ROOT
  ? path.resolve(process.env.I18N_DIFF_REPO_ROOT)
  : path.resolve(__dirname, '..', '..');

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
  // Chargement des traceurs (consentement, #7593) : ce fichier ne rend AUCUN
  // texte — il injecte les scripts de mesure, dont le bootstrap tiers de
  // Mixpanel (« (stub) », liste des méthodes de l'API, URL du CDN) et celui de
  // Google. Ce sont des extraits du fournisseur, déjà présents dans le dépôt
  // (ils vivaient dans layout.tsx, non signalés car non ajoutés par un diff).
  '/vitrine/components/ConsentScripts.tsx',
  // Pages légales (#7593) : legal-content.ts est le catalogue de contenu ×4 du
  // droit applicable (politique de confidentialité, CGU, mentions légales) —
  // exactement le même cas que vitrine-locale.ts ci-dessus : ce fichier EST le
  // mécanisme de localisation, pas des chaînes hors catalogue. La complétude des
  // 4 langues reste couverte par validate-and-sync.
  '/vitrine/lib/legal-content.ts',
  // Catalogue i18n du kiosque ZKTeco (#7651) : `front/zkteco-kiosk/i18n.js`
  // EST le mécanisme de localisation (catalogue inline ×4 fr/en/tr/ar,
  // PA2-I18N-013) — exactement le même cas que vitrine-locale.ts ci-dessus.
  // La parité des 4 langues reste couverte par tests/i18n.test.mjs (Lint +
  // i18n tests, bloquant).
  'front/zkteco-kiosk/i18n.js',
];

// Lines that already route text through a translation mechanism — never
// flagged even if they also contain a literal (e.g. the French fallback
// key text of a translation catalog entry itself).
// BC-17 (#7675) : la signature maison du portail Next.js est
// `t(locale, 'clé.imbriquée', 'Texte de repli FR')` (src/lib/i18n/locale-catalog.ts,
// utilisée par TOUTES les pages travel/commerce) — le repli FR est le 3e
// argument d'un appel de catalogue, pas une chaîne hors i18n. Le motif
// `\bt\(['"]` ne couvrait que `t('clé')` : chaque page gérant était signalée
// à tort (constat mesuré sur le diff BC-24 #7643, 200 faux positifs).
const translationCallPattern = /(context\.l10n\.|AppLocalizations\.of|\bl10n\.|__\(|\$t\(|\bt\(['"]|\bt\(\s*locale\s*,\s*['"]|i18n\.t\(|data-i18n|useTranslation|\bt`|Lang::get\(|trans\(|@lang\(|translate\()/;

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

// Limite connue, volontairement conservée (issue #7482) : un littéral d'un seul
// mot sans espace est classé « jeton technique ». Un `aria-label="Supprimer"`
// d'un seul mot passe donc la garde, alors qu'un `aria-label="Supprimer le
// compte"` est détecté (cas 2 du self-test). Resserrer cette règle créerait de
// nouveaux faux positifs sur les libellés courts légitimes (états, clés
// d'API) — exactement ce que l'issue #7482 demande d'arrêter.
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
  // Query string sans espace (« per_page=100 », « status=open&page=2 ») :
  // paramètres d'API, jamais du texte utilisateur (constat BC-17 #7675 —
  // `listQuery: 'per_page=100'` des tableaux CRUD config-driven).
  if (/^[\w.-]+=[\w.%&=-]*$/.test(trimmed)) return true;
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

// ─── Valeurs d'attribut : distinguer le code du texte (issue #7482) ──────────
//
// Constat mesuré (issue #7482) : la garde lisait la VALEUR d'un attribut entre
// guillemets comme une chaîne utilisateur. Elle signalait donc du code correct :
// `v-model="form[key]"`, `:class="active ? 'bg-emerald-500' : 'bg-slate-100'"`,
// `v-if="item.x == null"` — trois faux positifs reproduits dans
// dev-hub/tools/check-i18n-diff-test.sh.
//
// Règle : dans un template, un attribut LIÉ porte une expression
// (`:class`, `:title`, `v-model`, `v-if`, `@click`, `#default`…) — ses
// guillemets ne délimitent PAS une chaîne utilisateur, ce sont des délimiteurs
// de syntaxe. On les retire avant l'extraction des littéraux : seuls les
// littéraux réellement écrits DANS l'expression sont analysés. C'est ce qui
// garde `:label="'Supprimer'"` détecté tout en laissant
// `:class="active ? 'bg-emerald-500' : 'bg-slate-100'"` tranquille (les classes
// restent filtrées par isCssClassList).
//
// Un attribut STATIQUE (`title="Enregistrer la fiche"`,
// `aria-label="Supprimer"`, `placeholder="Nom de l'entreprise"`) reste, lui, du
// texte utilisateur : sa valeur est conservée telle quelle et reste analysée.
//
// Cas particulier : les attributs purement structurels (style, liaison de
// modèle, conditions, itération, clés, identifiants, tailles/variantes) ne
// portent jamais de texte utilisateur, même écrits en statique.
const structuralAttributes = new Set([
  'class', ':class', 'className', 'style', ':style',
  ':value', ':checked', ':selected', ':multiple', ':readonly', ':required',
  ':clearable', ':filterable', ':loading',
  ':key', ':id', ':name', ':for', ':type', ':role', ':dir', ':lang', ':tabindex',
  ':to', ':href', ':src', ':target', ':rel', ':method', ':action',
  ':size', ':variant', ':density', ':tone', ':color', ':icon', ':width',
  ':height', ':cols', ':rows', ':colspan', ':rowspan', ':span', ':min',
  ':max', ':step', ':precision', ':minlength', ':maxlength', ':autocomplete',
  ':autofocus', ':pattern', ':mask', ':offset', ':gap', ':align', ':justify',
  // Formes STATIQUES de la même famille : les dimensions d'image ne portent
  // jamais de texte utilisateur. Constat mesuré (audit vitrine, 2026-09-16) :
  // `<Image … sizes="(min-width: 1024px) 33vw, 100vw" />` était signalé comme
  // « nouvelle chaîne en dur » et poussait à réécrire un appel correct.
  'sizes', 'width', 'height', 'srcset', 'loading', 'decoding', 'fetchpriority',
]);

// Noms d'attribut : `:class`, `@click`, `v-model`, `#default`, `aria-label`…
const attributeNamePattern = '[:#@a-zA-Z][\\w:.#@-]*';
const attributeValueRegex = new RegExp(
  `(^|[\\s<])(${attributeNamePattern})\\s*=\\s*("([^"]*)"|'([^']*)')`,
  'g',
);

// Un attribut lié porte une expression JS ; les attributs structurels sont du
// code par nature, quelle que soit leur écriture.
function isCodeValuedAttribute(name) {
  return structuralAttributes.has(name)
    || name.startsWith(':')
    || name.startsWith('@')
    || name.startsWith('v-')
    || name.startsWith('#');
}

// Retire les guillemets de délimitation d'une valeur liée (en conservant la
// longueur de la ligne) pour que l'analyseur ne prenne pas l'expression entière
// pour une chaîne utilisateur.
function maskLinkedAttributeValues(line) {
  if (!line.includes('=')) return line;
  return line.replace(attributeValueRegex, (match, prefix, name, quoted) => {
    if (!isCodeValuedAttribute(name)) return match;
    if (quoted.length < 2) return match;
    return `${prefix}${name}= ${quoted.slice(1, -1)} `;
  });
}

// Chemin de clé de catalogue : `options.0.label`, `settings.billing.title` sont
// des ADRESSES dans le catalogue, pas du texte utilisateur (constat n°2 de
// l'issue #7482 — la garde les signalait comme des chaînes en dur).
const catalogKeyPathPattern = /^[a-zA-Z_][\w-]*(?:\.[\w-]+)+$/;

function classifyLiteral(rawValue) {
  const value = rawValue.trim();
  if (value.length < 4) return null;
  if (catalogKeyPathPattern.test(value)) return null;
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

// Motif technique probable vs texte utilisateur : sert uniquement à orienter
// le message d'erreur (issue #7482, critère 3).
const technicalConstantPattern = /^[A-Z][A-Z0-9_]*$|^[a-z][\w-]*$|^[\w.-]+\.(?:json|png|svg|jpe?g|webp|pdf|csv|xlsx?|zip)$/i;

function hintFor(literal) {
  if (technicalConstantPattern.test(literal)) {
    return 'constante technique probable (enum, slug, nom de fichier) : sortez-la dans une constante nommée hors du template plutôt que de réécrire l\'appel';
  }
  return 'texte utilisateur : passez-le par le catalogue i18n (pistes ci-dessous)';
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
    // Commentaires : jamais du texte utilisateur. Le cas JSX `{/* ... */}`
    // manquait (constaté sur #7562 : un commentaire français du tunnel
    // d'inscription était lu comme une chaîne à cause de ses apostrophes —
    // « d'indicateur d'étapes »). Un `/* ... */` d'ouverture est couvert aussi.
    if (/^(\/\/|#|\*|<!--|\{\/\*|\/\*)/.test(trimmed)) continue;
    if (/^\s*(import|export)\s/.test(content)) continue;
    if (translationCallPattern.test(content)) continue;
    if (devLogLinePattern.test(content) || todoLinePattern.test(content)) continue;

    // Issue #7482 : un attribut lié porte une expression, pas une chaîne
    // utilisateur — on retire ses guillemets de délimitation avant analyse.
    const scanned = maskLinkedAttributeValues(content);

    stringLiteralPattern.lastIndex = 0;
    let match;
    while ((match = stringLiteralPattern.exec(scanned)) !== null) {
      const flagged = classifyLiteral(match[2]);
      if (!flagged) continue;
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
      // Critère 3 de l'issue #7482 : le message dit QUOI FAIRE, pour que
      // l'agent suivant n'ait pas à redécouvrir le contournement.
      console.error(`      ↳ ${hintFor(violation.text)}`);
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
    console.error('');
    console.error('If a flagged value really is a technical constant that the heuristics cannot');
    console.error('recognise (enum, slug, event name, CSS utility), do NOT rewrite correct call');
    console.error('sites to please the guard (issue #7482): add the pattern to');
    console.error('dev-hub/tools/check-i18n-diff.js and a fixture to');
    console.error('dev-hub/tools/check-i18n-diff-test.sh.');
    process.exit(1);
  }

  console.log('✅ No new hardcoded user-visible strings introduced on PA2-I18N-014 risk surfaces.');
}

main();
