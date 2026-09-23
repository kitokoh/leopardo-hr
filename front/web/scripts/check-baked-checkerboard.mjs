#!/usr/bin/env node
/**
 * check-baked-checkerboard — anti-régression guard for marketing assets
 * (issue #8064).
 *
 * Detects a "transparency checkerboard" rasterized into the pixels of an
 * export (the classic alternating light-gray tiles ~RGB 235 / ~RGB 253 baked
 * into an opaque background) instead of a real alpha channel.
 *
 * Heuristic: sample the 4 border rows/cols of each image. A baked checker
 * background shows up as
 *   1. mostly OPAQUE border pixels,
 *   2. low-chroma light grays clustered in TWO bands (~228-240 and ~246-255),
 *   3. periodic alternation between the two bands (several runs of roughly
 *      constant tile width).
 * A real alpha export has transparent borders; a legit flat background has a
 * single band and no alternation — neither trips the three conditions.
 *
 * Exits 1 and prints the offending file(s) when a baked checkerboard is
 * detected, so CI turns red if a bad export is reintroduced.
 *
 * Usage: node scripts/check-baked-checkerboard.mjs [dir]  (default: public/brand)
 */

import { readdirSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';
import sharp from 'sharp';

const ROOT = process.argv[2] ?? 'public/brand';
const EXTS = new Set(['.webp', '.png']);

function* walk(dir) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) yield* walk(p);
    else if (EXTS.has(extname(name).toLowerCase())) yield p;
  }
}

/** Classify one RGBA pixel: 'lo' | 'hi' checker band, or null. */
function band(r, g, b, a) {
  if (a < 250) return null; // transparent border ⇒ real alpha, not baked
  const mx = Math.max(r, g, b);
  const mn = Math.min(r, g, b);
  if (mx - mn > 14) return null; // chromatic ⇒ artwork, not checker gray
  const m = (r + g + b) / 3;
  if (m >= 228 && m <= 241) return 'lo';
  if (m >= 245) return 'hi';
  return null;
}

/** Count band alternations with plausible tile widths (4–40 px). */
function alternations(line) {
  let runs = 0;
  let cur = null;
  let len = 0;
  for (const v of line) {
    if (v === cur) {
      len += 1;
      continue;
    }
    if (cur !== null && len >= 4 && len <= 40) runs += 1;
    cur = v;
    len = 1;
  }
  return runs;
}

async function hasBakedCheckerboard(file) {
  const { data, info } = await sharp(file)
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });
  const { width: w, height: h, channels: c } = info;
  const px = (x, y) => {
    const i = (y * w + x) * c;
    return band(data[i], data[i + 1], data[i + 2], data[i + 3]);
  };

  const lines = [
    Array.from({ length: w }, (_, x) => px(x, 1)), // top
    Array.from({ length: w }, (_, x) => px(x, h - 2)), // bottom
    Array.from({ length: h }, (_, y) => px(1, y)), // left
    Array.from({ length: h }, (_, y) => px(w - 2, y)), // right
  ];

  let suspicious = 0;
  for (const line of lines) {
    const nLo = line.filter((v) => v === 'lo').length;
    const nHi = line.filter((v) => v === 'hi').length;
    const coverage = (nLo + nHi) / line.length;
    // Needs both bands present, dominating the border, and alternating.
    if (
      coverage > 0.55 &&
      nLo / line.length > 0.15 &&
      nHi / line.length > 0.15 &&
      alternations(line) >= 6
    ) {
      suspicious += 1;
    }
  }
  // A baked checker background surrounds the subject: ≥2 borders trip.
  return suspicious >= 2;
}

const offenders = [];
for (const file of walk(ROOT)) {
  // eslint-disable-next-line no-await-in-loop
  if (await hasBakedCheckerboard(file)) offenders.push(file);
}

if (offenders.length > 0) {
  console.error(
    'check-baked-checkerboard: transparency checkerboard baked into pixels (re-export with a real alpha channel, see issue #8064):'
  );
  for (const f of offenders) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('check-baked-checkerboard: OK — no baked checkerboard detected.');
