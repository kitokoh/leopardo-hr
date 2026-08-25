#!/usr/bin/env node
/**
 * check-migration-prefixes.mjs — #5437 — garde anti-collision de préfixes de
 * migrations ENTRE PRs ouvertes (complémentaire de
 * check-migration-basename-collisions.sh qui ne couvre que l'intra-branche).
 *
 * Problème (issue #1962, 4 occurrences) : deux agents créent en parallèle
 * `2026_08_25_000001_create_x_table.php` et `2026_08_25_000001_create_y_table.php`
 * sur des branches différentes. Chaque branche est cohérente seule (le garde
 * intra-branche passe), mais le merge crée un ordre de migration ambigu /
 * un écrasement silencieux (Migrator keyBy basename).
 *
 * Ce garde liste les PRs ouvertes via l'API GitHub, extrait les préfixes
 * `YYYY_MM_DD_HHMMSS` des migrations de chaque head + de main, et échoue si
 * la PR courante introduit un préfixe déjà présent sur main ou sur une autre
 * PR ouverte.
 *
 * Usage (CI) :
 *   GITHUB_TOKEN=... GITHUB_REPOSITORY=kitokoh/leopardo-hr \
 *   GITHUB_HEAD_SHA=<sha> GITHUB_BASE_SHA=<sha> \
 *   node dev-hub/tools/check-migration-prefixes.mjs
 *
 * Usage (test local, sans réseau) :
 *   node dev-hub/tools/check-migration-prefixes.mjs --local <dirCourant> <dirMain> [<dirAutrePR>...]
 *
 * Tests : node --test dev-hub/tools/tests/check-migration-prefixes.test.mjs
 */

'use strict';

import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const REPO_ROOT = path.resolve(__dirname, '..', '..');

/** Répertoires de migrations contrôlés (tenant + shared, pas public). */
export const MIGRATION_SUBDIRS = ['tenant', 'shared'];

/**
 * Extrait le préfixe de séquence `YYYY_MM_DD_HHMMSS` du nom de fichier.
 * @param {string} name
 * @returns {string|null}
 */
export function extractPrefix(name) {
  const m = /^(\d{4}_\d{2}_\d{2}_\d{6})/.exec(path.basename(name));
  return m ? m[1] : null;
}

/**
 * Scanner : nom de fichier → Map<prefix, string[]>.
 * @param {string[]} names
 * @returns {Map<string, string[]>}
 */
export function scanFilenames(names) {
  const map = new Map();
  for (const name of names) {
    const prefix = extractPrefix(name);
    if (!prefix) continue;
    if (!map.has(prefix)) map.set(prefix, []);
    map.get(prefix).push(name);
  }
  return map;
}

/**
 * Détecte les collisions de préfixes entre la branche courante et d'autres
 * scopes (main, autres PRs).
 *
 * @param {string[]} currentNames  fichiers de migration de la branche courante
 * @param {Array<{scope: string, names: string[]}>} otherScopes
 * @returns {Array<{prefix: string, scope: string, currentFiles: string[], otherFiles: string[]}>}
 */
export function findCollisions(currentNames, otherScopes) {
  const current = scanFilenames(currentNames);
  const currentPrefixes = new Set(current.keys());
  const collisions = [];
  for (const { scope, names } of otherScopes) {
    const other = scanFilenames(names);
    for (const [prefix, otherFiles] of other) {
      if (currentPrefixes.has(prefix)) {
        collisions.push({
          prefix,
          scope,
          currentFiles: current.get(prefix),
          otherFiles,
        });
      }
    }
  }
  return collisions;
}

/** Liste récursive des fichiers .php d'un répertoire local (relatifs). */
export function scanLocalDir(dir) {
  const out = [];
  if (!fs.existsSync(dir)) return out;
  const walk = (d, rel) => {
    for (const entry of fs.readdirSync(d, { withFileTypes: true })) {
      const full = path.join(d, entry.name);
      const relPath = rel ? `${rel}/${entry.name}` : entry.name;
      if (entry.isDirectory()) walk(full, relPath);
      else if (entry.name.endsWith('.php')) out.push(relPath);
    }
  };
  walk(dir, '');
  return out;
}

/** Liste récursive des fichiers de migration d'un SHA via l'API Git trees. */
async function treeMigrationFiles(sha) {
  const repo = process.env.GITHUB_REPOSITORY || 'kitokoh/leopardo-hr';
  const token = process.env.GITHUB_TOKEN;
  const res = await fetch(
    `https://api.github.com/repos/${repo}/git/trees/${sha}?recursive=1`,
    {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/vnd.github+json',
        'User-Agent': 'leopardo-devhub',
      },
    },
  );
  if (res.status === 403) {
    throw new Error('API GitHub : rate limit atteint (403) — réessayer plus tard.');
  }
  if (res.status === 404) {
    throw new Error(`SHA introuvable : ${sha} (la PR a peut-être été fermée).`);
  }
  if (!res.ok) {
    throw new Error(`API GitHub : ${res.status} ${res.statusText} (${sha})`);
  }
  const data = await res.json();
  const base = 'api/database/migrations/';
  return (data.tree || [])
    .filter((e) => e.type === 'blob' && e.path.endsWith('.php'))
    .map((e) => e.path)
    .filter((p) => MIGRATION_SUBDIRS.some((s) => p.startsWith(`${base}${s}/`)));
}

/** Liste paginée des PRs ouvertes. */
async function listOpenPRs() {
  const repo = process.env.GITHUB_REPOSITORY || 'kitokoh/leopardo-hr';
  const token = process.env.GITHUB_TOKEN;
  const prs = [];
  for (let page = 1; page <= 5; page++) {
    const res = await fetch(
      `https://api.github.com/repos/${repo}/pulls?state=open&per_page=100&page=${page}`,
      {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/vnd.github+json',
          'User-Agent': 'leopardo-devhub',
        },
      },
    );
    if (!res.ok) {
      throw new Error(`API GitHub (pulls) : ${res.status} ${res.statusText}`);
    }
    const batch = await res.json();
    prs.push(...batch);
    if (batch.length < 100) break;
  }
  return prs;
}

/** Mode local (tests / smoke) : --local <cur> <main> [<other>...] */
function runLocal(args) {
  const [curDir, ...others] = args;
  if (!curDir) {
    console.error('usage: --local <dirCourant> <dirMain> [<dirAutrePR>...]');
    process.exit(2);
  }
  const currentNames = scanLocalDir(curDir);
  const otherScopes = others.map((dir, i) => ({
    scope: i === 0 ? 'main' : `PR #? (${path.basename(dir)})`,
    names: scanLocalDir(dir),
  }));
  const collisions = findCollisions(currentNewNames, otherScopes);
  report(collisions, currentNewNames.length);
  console.log(
    `(filtré : ${currentNames.length - currentNewNames.length} migration(s) déjà sur main exclues du contrôle)`,
  );
  process.exit(collisions.length > 0 ? 1 : 0);
}

/** Sortie GitHub Actions (::error::) + console. */
function report(collisions, currentCount) {
  if (collisions.length === 0) {
    console.log(`OK: ${currentCount} migration(s) courante(s) — aucun préfixe en collision avec main ou les PRs ouvertes.`);
    return;
  }
  for (const c of collisions) {
    console.error(
      `::error title=Collision de préfixe de migration (${c.prefix})::` +
        `le préfixe ${c.prefix} existe déjà dans ${c.scope} ` +
        `(${c.otherFiles.join(', ')}). Renommer votre migration ` +
        `(${c.currentFiles.join(', ')}) avec le prochain préfixe libre ` +
        `(YYYY_MM_DD_NNNNNN) avant merge — voir issue #1962/#5437.`,
    );
  }
  console.error(
    `FAIL: ${collisions.length} collision(s) de préfixe avec main/PRs ouvertes.`,
  );
}

async function main() {
  const args = process.argv.slice(2);
  if (args[0] === '--local') {
    runLocal(args.slice(1));
    return;
  }

  if (!process.env.GITHUB_TOKEN) {
    console.error('GITHUB_TOKEN manquant — impossible de lister les PRs ouvertes.');
    process.exit(2);
  }

  const headSha = process.env.GITHUB_HEAD_SHA || process.env.GITHUB_SHA;
  const baseSha = process.env.GITHUB_BASE_SHA;
  if (!headSha) {
    console.error('GITHUB_HEAD_SHA manquant (préciser via env en dehors de GitHub Actions).');
    process.exit(2);
  }

  console.log('Listing des PRs ouvertes (API GitHub)…');
  const prs = await listOpenPRs();

  const currentPR = prs.find((p) => p.head.sha === headSha);
  const others = prs.filter((p) => p.head.sha !== headSha);
  console.log(
    `${prs.length} PR(s) ouverte(s)${currentPR ? ` — PR courante #${currentPR.number}` : ' — head non rattaché à une PR ouverte'} ; ${others.length} autre(s) à comparer.`,
  );

  const currentNames = await treeMigrationFiles(headSha);

  // Préfixes déjà présents sur main : ils ne constituent PAS une collision
  // (déjà fusionnés) — seule compte l'introduction de préfixes NOUVEAUX par
  // cette PR, en concurrence avec main ou d'autres PRs ouvertes.
  const mainNames = baseSha ? await treeMigrationFiles(baseSha) : [];
  const mainPrefixes = new Set(scanFilenames(mainNames).keys());
  const currentNewNames = currentNames.filter(
    (n) => !mainPrefixes.has(extractPrefix(n)),
  );
  const otherScopes = [];

  if (baseSha) {
    otherScopes.push({ scope: 'main (base)', names: mainNames });
  }

  for (const pr of others.slice(0, 30)) {
    try {
      otherScopes.push({ scope: `PR #${pr.number}`, names: await treeMigrationFiles(pr.head.sha) });
    } catch (e) {
      console.warn(`  (ignoré) PR #${pr.number} : ${e.message}`);
    }
  }

  const collisions = findCollisions(currentNewNames, otherScopes);
  report(collisions, currentNewNames.length);
  console.log(
    `(filtré : ${currentNames.length - currentNewNames.length} migration(s) déjà sur main exclues du contrôle)`,
  );
  process.exit(collisions.length > 0 ? 1 : 0);
}

// Exécution directe uniquement (les tests importent les fonctions pures sans
// déclencher l'appel réseau).
if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  main().catch((e) => {
    console.error(`::error::${e.message}`);
    process.exit(1);
  });
}
