#!/usr/bin/env node
/**
 * check-vercel-config.js — garde sur `front/web/vercel.json` (incident 2026-09-10).
 *
 * Deux pièges ont coûté la disponibilité du web public ; cette garde les couvre :
 *
 * 1. **Longueur.** Vercel valide `vercel.json` contre un schéma où
 *    `ignoreCommand` est limité à **256 caractères**. Au-delà, la validation
 *    échoue et **chaque** déploiement part en `ERROR` — plus rien ne se déploie,
 *    y compris sur `main` (« vercel.json schema validation failed with the
 *    following message: `ignoreCommand` should NOT be longer than 256
 *    characters »).
 *
 * 2. **Répertoire d'exécution.** Le projet Vercel a `rootDirectory: front/web`,
 *    donc `ignoreCommand` s'exécute **depuis `front/web/`**. Un pathspec
 *    `-- front/web` y devient `front/web/front/web` : il ne matche rien,
 *    `git diff --quiet` renvoie 0, Vercel interprète `exit 0` comme
 *    « build ignoré » et **tous** les builds étaient sautés (web public figé
 *    du 2026-08-19 au 2026-09-10, 100 % des déploiements `CANCELED`, CI verte).
 *    La commande doit donc résoudre la racine du dépôt (`--show-toplevel` ou
 *    `git -C`) avant de différencier.
 *
 * Usage: node dev-hub/tools/check-vercel-config.js
 * Exit 0 = config saine ; exit 1 = régression (message ::error::-compatible).
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const CONFIG_PATH = path.join(ROOT, 'front', 'web', 'vercel.json');
const MAX_IGNORE_COMMAND = 256; // limite du schéma vercel.json (Vercel)

const errors = [];

let config;
try {
  config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
} catch (error) {
  console.error('❌ VERIF_CONFIG_VERCEL_FAILED');
  console.error(`- front/web/vercel.json illisible ou JSON invalide : ${error.message}`);
  process.exit(1);
}

const command = config.ignoreCommand;

if (typeof command !== 'string' || command.trim() === '') {
  errors.push('`ignoreCommand` absent ou vide (le garde-fou de quota ne jouerait plus)');
} else {
  if (command.length > MAX_IGNORE_COMMAND) {
    errors.push(
      `\`ignoreCommand\` fait ${command.length} caractères (limite ${MAX_IGNORE_COMMAND}) — ` +
        'Vercel rejette le schéma et TOUS les déploiements passent en ERROR',
    );
  }

  // Exécuté depuis `front/web` (rootDirectory) : sans résolution de la racine,
  // un pathspec `front/web` ne matche rien et le build est toujours sauté.
  if (!/--show-toplevel|-C\s/.test(command)) {
    errors.push(
      '`ignoreCommand` ne résout pas la racine du dépôt (`git rev-parse --show-toplevel` ' +
        'ou `git -C <racine>`) : exécuté depuis front/web, le pathspec `front/web` ne matche rien',
    );
  }

  if (!command.includes('front/web')) {
    errors.push("`ignoreCommand` ne restreint pas le diff à `front/web` (garde de quota absent)");
  }
}

if (errors.length > 0) {
  console.error('❌ VERIF_CONFIG_VERCEL_FAILED');
  for (const message of errors) {
    console.error(`- ${message}`);
  }
  process.exit(1);
}

console.log(
  `check-vercel-config : OK (ignoreCommand ${command.length}/${MAX_IGNORE_COMMAND} caractères, racine du dépôt résolue)`,
);
