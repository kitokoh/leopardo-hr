#!/usr/bin/env node
/**
 * #8075 — garde « pages orphelines » : toute page publiée sous (landing)
 * doit être atteignable par un lien interne (navbar, footer, contenu) —
 * sinon elle est construite mais ne reçoit aucun trafic interne.
 *
 * Pour chaque route top-level de src/app/(landing)/\x{2026}/page.tsx, on cherche
 * une référence `"/route"` (lien, bouton, data de navigation) dans src/,
 * en excluant : le dossier de la page elle-même, les tests, le sitemap
 * (le sitemap rendrait la garde aveugle — il liste toutes les routes sans
 * leur apporter de trafic).
 *
 * Usage : node scripts/check-orphan-pages.mjs — exit 1 si une page est orpheline.
 */

import { readdirSync, readFileSync, existsSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const SRC = new URL('../src', import.meta.url).pathname;
const LANDING = join(SRC, 'app', '(landing)');

/** Routes top-level publiées (dossiers avec page.tsx, hors dynamiques/tests). */
const routes = readdirSync(LANDING)
  .filter((name) => !name.startsWith('__') && !name.startsWith('[') && !name.startsWith('('))
  .filter((name) => {
    const dir = join(LANDING, name);
    return statSync(dir).isDirectory() && existsSync(join(dir, 'page.tsx'));
  })
  .map((name) => ({ name, route: `/${name}`, dir: join('app', '(landing)', name) }));

/** Tous les fichiers source candidats à contenir un lien. */
function* walk(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      if (entry === 'node_modules' || entry.startsWith('.')) continue;
      yield* walk(full);
    } else if (/\.(tsx?|jsx?|mjs|json)$/.test(entry)) {
      yield full;
    }
  }
}

const SKIP = (rel) =>
  rel.includes('__tests__') ||
  rel.includes('.test.') ||
  rel.includes('sitemap') ||
  rel.includes('opengraph') ||
  rel.includes('icon.');

const files = [...walk(SRC)].filter((f) => !SKIP(relative(SRC, f)));
const contents = files.map((f) => ({ rel: relative(SRC, f), text: readFileSync(f, 'utf8') }));

let orphans = 0;
for (const { name, route, dir } of routes) {
  // Référence entrante : "/route" suivi d'une frontière (quote, ?, #, fin).
  const needle = new RegExp(`['"\`]${route}(['"\`?#]|$)`);
  const inbound = contents.filter(
    ({ rel, text }) => !rel.startsWith(dir) && needle.test(text),
  );
  if (inbound.length === 0) {
    orphans++;
    console.error(`✗ PAGE ORPHELINE : ${route} — aucun lien interne entrant (navbar/footer/contenu).`);
    console.error('    → Lier la page (footer/nav) ou la dépublier. Cf. #8075.');
  } else {
    console.log(`✓ ${route} (${inbound.length} référence${inbound.length > 1 ? 's' : ''})`);
  }
}

if (orphans) {
  console.error(`\n${orphans} page(s) orpheline(s) — chaque page publiée doit être atteignable en ≤ 2 clics depuis la home.`);
  process.exit(1);
}
console.log(`\n${routes.length} pages (landing) : toutes sont liées en interne.`);
