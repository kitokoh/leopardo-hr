#!/usr/bin/env node
/**
 * Garde « configuration Next ⇒ dépendance réellement installable » (issue #7531).
 *
 * ## Le trou qu'elle ferme
 *
 * `front/web/next.config.ts` active des expériences Next qui exigent un paquet
 * **non fourni par Next** : `experimental.optimizeCss: true` fait faire à Next
 * `require('critters')` (`next/dist/server/post-process.js`, appelé par
 * `server/render.js` — donc **au rendu**, en production, pas seulement au
 * build). Rien dans `package.json` ne déclarait `critters` : après un
 * `npm ci` propre, toute page répondait **500 `Cannot find module 'critters'`**.
 * Le comportement ne tenait qu'à un `node_modules` non reproductible — le
 * paquet était présent « par accident » sur les postes de recette, et un
 * `npm ci` (ou `vercel build`, ou la CI) le retirait (élagage du lockfile).
 *
 * ## Ce qui est vérifié, pour chaque drapeau activé
 *
 * 1. le module requis est **déclaré** dans `package.json`, dans la section
 *    exigée par l'entrée (`dependencies` pour un `require()` de rendu, qui doit
 *    survivre à `npm ci --omit=dev` ; `devDependencies` toléré pour un besoin
 *    strictement outillage) ;
 * 2. il est **résolvable** (`require.resolve`) depuis `front/web` ;
 * 3. il figure dans `package-lock.json`, donc `npm ci` (CI, Vercel) l'installe.
 *
 * Les trois points comptent séparément : déclarer sans verrouiller laisse
 * `npm ci` retirer le paquet (c'était le cas de figure), verrouiller sans
 * déclarer le fait disparaître au prochain `npm install` propre.
 *
 * ## Usage
 *
 *   node scripts/check-next-config-deps.mjs              # vérifie le dépôt
 *   node scripts/check-next-config-deps.mjs --self-test   # éprouve la garde elle-même
 *
 * Le `--self-test` est une **épreuve par mutation** : il rejoue la logique sur
 * des entrées fabriquées et exige que chaque défaut soit détecté (et qu'un
 * dépôt sain passe). Une garde qui ne sait pas échouer ne prouve rien — elle
 * décrit.
 */

import { readFileSync, existsSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const HERE = dirname(fileURLToPath(import.meta.url));
const WEB_ROOT = resolve(HERE, "..");

/**
 * Table de correspondance « drapeau de `next.config.ts` ⇒ paquet requis ».
 *
 * `section` : la section `package.json` où le paquet DOIT vivre.
 *   - `dependencies`    : le paquet est requis au **rendu** (runtime du serveur
 *                         Next) — il doit survivre à `npm ci --omit=dev`.
 *   - `devDependencies` : le paquet n'est requis qu'au build / à l'outillage.
 * `runtimeRequire` : chemin & ligne du `require()` dans Next, en preuve (affiché
 *   dans les messages d'erreur pour que le remède soit vérifiable à la source).
 */
export const FLAG_REQUIREMENTS = [
  {
    flag: "optimizeCss",
    module: "critters",
    section: "dependencies",
    runtimeRequire: "next/dist/server/post-process.js — require('critters'), appelé par server/render.js (rendu)",
  },
];

/** Extrait la valeur littérale d'un drapeau dans le texte d'une config Next. */
export function readFlag(source, flag) {
  // Cherche `flag:` (objet de config), en ignorant les commentaires de ligne.
  const withoutComments = source
    .split("\n")
    .map((line) => {
      const i = line.indexOf("//");
      return i === -1 ? line : line.slice(0, i);
    })
    .join("\n");

  const re = new RegExp(`(?:^|[{,\\s])${flag}\\s*:\\s*([^,}\\n]+)`);
  const m = withoutComments.match(re);
  if (!m) return { present: false, literal: null, raw: null };

  const raw = m[1].trim().replace(/[;,]\s*$/, "");
  if (raw === "true") return { present: true, literal: true, raw };
  if (raw === "false") return { present: true, literal: false, raw };
  // Expression non littérale (`process.env.X === 'y'`, variable…) : on ne peut
  // pas prouver qu'elle est fausse → on la traite comme ACTIVÉE (conservateur).
  return { present: true, literal: null, raw };
}

function readJson(path) {
  return JSON.parse(readFileSync(path, "utf8"));
}

/**
 * `deps` = { configSource, pkg, lockText, resolveModule(name) }
 * Renvoie la liste des problèmes (chaîne vide = garde verte).
 */
export function audit({ configSource, pkg, lockText, resolveModule }) {
  const problems = [];

  for (const req of FLAG_REQUIREMENTS) {
    const flag = readFlag(configSource, req.flag);

    if (!flag.present || flag.literal === false) continue;

    const where = `next.config.ts → ${req.flag}${flag.literal === null ? ` (= ${flag.raw}, non littéral → traité comme activé)` : " = true"}`;

    const inDeps = !!(pkg.dependencies && pkg.dependencies[req.module]);
    const inDev = !!(pkg.devDependencies && pkg.devDependencies[req.module]);

    if (!inDeps && !inDev) {
      problems.push(
        `${where} exige le paquet « ${req.module} », qui n'est déclaré ni dans dependencies ni dans devDependencies ` +
          `de package.json. Requis par : ${req.runtimeRequire}. ` +
          `Remède : npm install ${req.section === "dependencies" ? "" : "-D "}${req.module} (dans front/web).`,
      );
      continue;
    }

    if (req.section === "dependencies" && !inDeps) {
      problems.push(
        `${where} exige « ${req.module} » au RENDU, mais il n'est que dans devDependencies : ` +
          `un install de production (npm ci --omit=dev) le retirera et le rendu repassera en 500. ` +
          `Requis par : ${req.runtimeRequire}. Remède : déplacer « ${req.module} » vers dependencies.`,
      );
    }

    if (!lockText.includes(`node_modules/${req.module}"`)) {
      problems.push(
        `${where} exige « ${req.module} », déclaré mais ABSENT de package-lock.json : ` +
          `npm ci (CI, Vercel) ne l'installera pas et l'écart « config ⇒ dépendance » reviendra. ` +
          `Remède : lancer npm install dans front/web et committer le lockfile.`,
      );
    }

    if (!resolveModule(req.module)) {
      problems.push(
        `${where} exige « ${req.module} », déclaré mais non résolvable dans node_modules : ` +
          `l'arbre installé ne correspond pas au lockfile. Remède : npm ci dans front/web.`,
      );
    }
  }

  return problems;
}

function buildContext() {
  const configPath = join(WEB_ROOT, "next.config.ts");
  const pkgPath = join(WEB_ROOT, "package.json");
  const lockPath = join(WEB_ROOT, "package-lock.json");

  if (!existsSync(configPath) || !existsSync(pkgPath) || !existsSync(lockPath)) {
    return { error: `front/web incomplet : next.config.ts / package.json / package-lock.json requis (cherché dans ${WEB_ROOT}).` };
  }

  const webRequire = createRequire(join(WEB_ROOT, "package.json"));
  return {
    configSource: readFileSync(configPath, "utf8"),
    pkg: readJson(pkgPath),
    lockText: readFileSync(lockPath, "utf8"),
    resolveModule: (name) => {
      try {
        webRequire.resolve(name);
        return true;
      } catch {
        return false;
      }
    },
  };
}

function runSelfTest() {
  // Fabriques : chaque cas doit produire EXACTEMENT le verdict attendu.
  const basePkg = { dependencies: { next: "16.3.4", critters: "^0.0.25" }, devDependencies: {} };
  const lockWith = '{"packages":{"node_modules/critters":{"version":"0.0.25"}}}';
  const config = "experimental: {\n  optimizeCss: true,\n},\n";

  const cases = [
    {
      name: "dépôt sain (déclaré, verrouillé, résolvable) → vert",
      ctx: { configSource: config, pkg: basePkg, lockText: lockWith, resolveModule: () => true },
      expect: 0,
    },
    {
      name: "paquet absent de package.json → rouge",
      ctx: { configSource: config, pkg: { dependencies: { next: "16.3.4" }, devDependencies: {} }, lockText: lockWith, resolveModule: () => true },
      expect: 1,
    },
    {
      name: "paquet seulement en devDependencies alors que le require est au rendu → rouge",
      ctx: { configSource: config, pkg: { dependencies: { next: "16.3.4" }, devDependencies: { critters: "^0.0.25" } }, lockText: lockWith, resolveModule: () => true },
      expect: 1,
    },
    {
      name: "déclaré mais absent du lockfile (npm ci l'élague) → rouge",
      ctx: { configSource: config, pkg: basePkg, lockText: '{"packages":{}}', resolveModule: () => true },
      expect: 1,
    },
    {
      name: "déclaré mais non résolvable dans node_modules → rouge",
      ctx: { configSource: config, pkg: basePkg, lockText: lockWith, resolveModule: () => false },
      expect: 1,
    },
    {
      name: "drapeau désactivé (optimizeCss: false) → aucune exigence, vert",
      ctx: { configSource: "experimental: {\n  optimizeCss: false,\n},\n", pkg: { dependencies: { next: "16.3.4" }, devDependencies: {} }, lockText: '{"packages":{}}', resolveModule: () => false },
      expect: 0,
    },
    {
      name: "drapeau non littéral (process.env…) → traité comme activé, rouge si absent",
      ctx: { configSource: "experimental: {\n  optimizeCss: process.env.OPTIMIZE_CSS === 'true',\n},\n", pkg: { dependencies: { next: "16.3.4" }, devDependencies: {} }, lockText: lockWith, resolveModule: () => true },
      expect: 1,
    },
    {
      name: "drapeau absent de la config → vert",
      ctx: { configSource: "const nextConfig = { reactStrictMode: true };\n", pkg: { dependencies: { next: "16.3.4" }, devDependencies: {} }, lockText: '{"packages":{}}', resolveModule: () => false },
      expect: 0,
    },
  ];

  let failures = 0;
  for (const c of cases) {
    const problems = audit(c.ctx);
    const got = problems.length > 0 ? 1 : 0;
    const ok = got === c.expect;
    if (!ok) failures += 1;
    console.log(`${ok ? "  ok  " : " FAIL "} ${c.name}${ok ? "" : ` (attendu=${c.expect} obtenu=${got})`}`);
    if (!ok) problems.forEach((p) => console.log(`          └ ${p}`));
  }
  console.log(failures === 0 ? `\nSelf-test: ${cases.length}/${cases.length} cas conformes.` : `\nSelf-test: ${failures} cas en échec.`);
  return failures === 0 ? 0 : 1;
}

function main() {
  if (process.argv.includes("--self-test")) {
    process.exit(runSelfTest());
  }

  const ctx = buildContext();
  if (ctx.error) {
    console.error(`::error::${ctx.error}`);
    process.exit(1);
  }

  const problems = audit(ctx);
  if (problems.length === 0) {
    console.log("check-next-config-deps: OK — chaque expérience Next activée est déclarée, verrouillée et installable.");
    process.exit(0);
  }

  console.error("check-next-config-deps: configuration Next et dépendances désalignées (issue #7531) :\n");
  for (const p of problems) console.error(`  - ${p}\n`);
  process.exit(1);
}

// N'exécute la vérification que lorsqu'on lance le script directement
// (il est importé par le self-test).
if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main();
}
