#!/usr/bin/env node
/**
 * #8064 — Garde « pas de damier de transparence incrusté » pour les assets de marque.
 *
 * Un export raté rasterise parfois le damier d'aperçu (alternance de gris
 * ~235/~254) dans les pixels au lieu d'un vrai canal alpha. Ce script détecte
 * cette signature dans les images de public/brand :
 *   - image sans transparence réelle (< 5 % de pixels transparents) ;
 *   - deux familles de gris clairs quasi neutres (A ≈ 232-242, B ≥ 246) chacune
 *     présente à plus de 10 % des pixels opaques ;
 *   - alternance spatiale périodique A/B sur des lignes échantillons.
 *
 * Usage : node scripts/check-brand-assets.mjs [fichiers...]
 * Défaut : tous les .webp/.png/.jpg/.jpeg de public/brand.
 * Code de sortie 1 si au moins un asset présente la signature damier.
 */

import { readdirSync } from 'node:fs';
import { join, extname } from 'node:path';
import sharp from 'sharp';

const BRAND_DIR = new URL('../public/brand', import.meta.url).pathname;
const EXTS = new Set(['.webp', '.png', '.jpg', '.jpeg']);

const targets = process.argv.slice(2).length
  ? process.argv.slice(2)
  : readdirSync(BRAND_DIR)
      .filter((f) => EXTS.has(extname(f).toLowerCase()))
      .map((f) => join(BRAND_DIR, f));

/** Familles de gris du damier : A ≈ 235, B ≈ 254, quasi neutres. */
function family(r, g, b) {
  const mx = Math.max(r, g, b);
  const mn = Math.min(r, g, b);
  if (mx - mn > 6) return 0; // pas neutre
  const avg = (r + g + b) / 3;
  if (avg >= 228 && avg <= 242) return 1; // A
  if (avg >= 246) return 2; // B
  return 0;
}

async function inspect(file) {
  const { data, info } = await sharp(file)
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });
  const { width, height, channels } = info;
  const total = width * height;

  let transparent = 0;
  let famA = 0;
  let famB = 0;
  const cls = new Uint8Array(total); // famille par pixel (0/1/2)

  for (let i = 0; i < total; i++) {
    const o = i * channels;
    if (data[o + 3] < 128) {
      transparent++;
      continue;
    }
    const f = family(data[o], data[o + 1], data[o + 2]);
    cls[i] = f;
    if (f === 1) famA++;
    else if (f === 2) famB++;
  }

  const transparentRatio = transparent / total;
  const opaque = total - transparent || 1;
  const aRatio = famA / opaque;
  const bRatio = famB / opaque;

  // Alternance spatiale : sur des lignes échantillons, compter les transitions
  // A<->B entre blocs de 4 px consécutifs (le damier change de famille à
  // période régulière, contrairement à un fond uni ou à une photo).
  let transitions = 0;
  let sampled = 0;
  for (const yr of [0.05, 0.15, 0.3, 0.5, 0.7, 0.85, 0.95]) {
    const y = Math.min(height - 1, Math.floor(height * yr));
    let prev = 0;
    for (let x = 0; x < width; x += 4) {
      const f = cls[y * width + x];
      if (f !== 0 && prev !== 0 && f !== prev) transitions++;
      if (f !== 0) prev = f;
      sampled++;
    }
  }

  const hasRealAlpha = transparentRatio > 0.05;
  const bothFamilies = aRatio >= 0.1 && bRatio >= 0.1;
  const alternates = transitions >= 12; // signature périodique nette
  const suspect = !hasRealAlpha && bothFamilies && alternates;

  return {
    file,
    suspect,
    detail: `alpha-transparent=${(transparentRatio * 100).toFixed(1)}% grisA=${(aRatio * 100).toFixed(1)}% grisB=${(bRatio * 100).toFixed(1)}% transitions=${transitions}`,
  };
}

let failed = 0;
for (const file of targets) {
  try {
    const r = await inspect(file);
    if (r.suspect) {
      failed++;
      console.error(`✗ DAMIER INCRUSTÉ : ${r.file}\n    ${r.detail}`);
    } else {
      console.log(`✓ ${r.file}\n    ${r.detail}`);
    }
  } catch (err) {
    failed++;
    console.error(`✗ LECTURE IMPOSSIBLE : ${file} — ${err.message}`);
  }
}

if (failed) {
  console.error(`\n${failed} asset(s) avec damier de transparence incrusté (cf. #8064).`);
  process.exit(1);
}
console.log(`\n${targets.length} asset(s) OK — aucun damier incrusté.`);
