#!/usr/bin/env node
/**
 * check-mojibake — anti-régression guard for double-encoded UTF-8 text (issue #2275).
 *
 * Scans front/web/src for the signature sequences of UTF-8 bytes that were
 * decoded as latin-1 / cp1252 (mojibake), e.g.:
 *   - Arabic:   'Ø§Ù„'  (U+00D8-U+00DF followed by a UTF-8 continuation byte
 *               rendered as latin-1/cp1252) instead of 'ال'
 *   - Latin:    'Ã©' (é), 'â€™' ('), 'Â·' (·), 'Â«' ('), 'ÅŸ' (ş), 'Ä±' (ı), …
 *
 * Exits 1 (and prints the offending file:line:col) as soon as anything matches,
 * so CI turns red when double-encoded text is reintroduced.
 *
 * Usage: node scripts/check-mojibake.mjs [dir]   (default: src)
 */

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, extname, basename } from 'node:path';

// U+00D8–U+00DF ('Ø Ù Ú Û Ü Ý Þ ß') are the latin-1 renderings of the leading
// bytes of every UTF-8 Arabic character. They are only ever followed by a
// continuation byte (U+0080–U+00BF as latin-1, or the cp1252 "smart" chars),
// never by a plain letter — so this pattern cannot hit legitimate text
// (e.g. Danish 'Ø' or German 'Ü' are always followed by an ASCII letter).
const ARABIC_MOJI =
  /[\u00D8-\u00DF](?:[\u0080-\u00BF]|[\u20AC\u201A\u0192\u201E\u2026\u2020\u2021\u02C6\u2030\u0160\u2039\u0152\u017D\u2018\u2019\u201C\u201D\u2022\u2013\u2014\u02DC\u2122\u0161\u203A\u0153\u017E\u0178])/g;

// 'Ã Â Ä Å' (U+00C2–U+00C5) are the latin-1 renderings of the leading bytes of
// é/è/ê/…, 'â€¦' (…), 'ÅŸ' (ş), 'Ä±' (ı)… A legit 'Ã'/'Â'/'Ä'/'Å' (Portuguese,
// German, Scandinavian) is always followed by an ASCII letter.
const LATIN_MOJI = /[\u00C2-\u00C5](?=[^\x00-\x7F])/g;

// 'â' (U+00E2) followed by a cp1252 "smart" char: â€™ ('), â€" (—), â€¢ (•)…
// A legit French 'â' is always followed by an ASCII letter.
const LATIN_MOJI_SMART =
  /â[\u20AC\u201A\u0192\u201E\u2026\u2020\u2021\u02C6\u2030\u0160\u2039\u0152\u017D\u2018\u2019\u201C\u201D\u2022\u2013\u2014\u02DC\u2122\u0161\u203A\u0153\u017E\u0178\u0080-\u009F]/g;

const PATTERNS = [
  ['Arabic (UTF-8 double-encoded)', ARABIC_MOJI],
  ['Latin (Ã/Â/Ä/Å + non-ASCII)', LATIN_MOJI],
  ['Latin (â + smart punctuation)', LATIN_MOJI_SMART],
];

const CODE_EXT = new Set(['.ts', '.tsx', '.js', '.jsx', '.mjs', '.cjs', '.json']);

function* walk(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      yield* walk(full);
    } else if (CODE_EXT.has(extname(full))) {
      yield full;
    }
  }
}

const root = process.argv[2] ?? 'src';
let failures = 0;

for (const file of walk(root)) {
  if (basename(file) === 'check-mojibake.mjs') continue; // doc examples in this file
  let content;
  try {
    content = readFileSync(file, 'utf8');
  } catch {
    continue; // binary / unreadable files are not our business
  }
  for (const [label, re] of PATTERNS) {
    re.lastIndex = 0;
    let m;
    while ((m = re.exec(content)) !== null) {
      const line = content.slice(0, m.index).split('\n').length;
      const col = m.index - content.lastIndexOf('\n', m.index - 1);
      const snippet = m[0].length > 60 ? m[0].slice(0, 57) + '…' : m[0];
      console.error(
        `check-mojibake: ${label} detected in ${file}:${line}:${col} -> ${JSON.stringify(snippet)}`
      );
      failures++;
    }
  }
}

if (failures > 0) {
  console.error(`\ncheck-mojibake: ${failures} mojibake sequence(s) found. ` +
    'Fix the double-encoded strings (decode with latin-1/cp1252 -> UTF-8) before merging.');
  process.exit(1);
}
console.log('check-mojibake: OK — no double-encoded UTF-8 found.');
