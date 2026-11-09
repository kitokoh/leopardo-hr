#!/usr/bin/env node
/**
 * Couloir 16 (M4, #9106) — garde de poids des images de la surface publique.
 *
 * ## Pourquoi ce script existe
 *
 * Le critère d'acceptation n°1 de M4 est : « Budget de poids écrit dans le
 * dépôt **et vérifié en CI** ». `docs/design/PUBLIC_APP_BUDGETS.md` §4 fixe des
 * budgets par image (tuile de résultat ≤ 40 Ko, en-tête de fiche ≤ 120 Ko) et
 * explique pourquoi : « sur les marchés visés, le forfait data est une
 * contrainte réelle. Une grille de 20 résultats à 200 Ko l'unité coûte 4 Mo ».
 *
 * Or **aucun contrôle de poids n'existait** : le budget était écrit, jamais
 * mesuré. Un budget non mesuré ne ferme rien — c'est exactement ce que la règle
 * « un lot ne se ferme pas si un seuil est dépassé » interdit.
 *
 * ## Ce que ce script vérifie — et ne vérifie pas
 *
 * Il mesure les images **versionnées dans le dépôt** (`front/web/public/**`),
 * c'est-à-dire celles qu'un déploiement sert sans transformation. Il ne mesure
 * PAS les images de commerces, qui viennent de l'API à l'exécution
 * (`PublicDiscoveryItem.image_url`) et ne sont pas dans le dépôt : leur poids
 * relève du pipeline d'images côté service, pas d'un contrôle de fichiers.
 *
 * ## Pourquoi les seuils sont des PLAFONDS et non les cibles du document
 *
 * Les valeurs de `PUBLIC_APP_BUDGETS.md` §4 sont des **propositions non
 * calibrées** (§7 : « à calibrer en M0 par mesure sur un appareil Android
 * d'entrée de gamme »). Les figer ici fermerait des lots sur des chiffres
 * inventés. Ce script applique donc des **plafonds de non-régression** : il
 * échoue si une image dépasse le plafond, ou si le total dépasse le plafond
 * global. Il ne prétend pas que la cible est atteinte.
 *
 * Usage :
 *   node scripts/check-public-image-budget.mjs            # vérifie
 *   node scripts/check-public-image-budget.mjs --report   # affiche le détail
 *
 * Code de sortie 1 si un plafond est dépassé.
 */

import { readdirSync, statSync } from 'node:fs';
import { join, extname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const PUBLIC_DIR = fileURLToPath(new URL('../public', import.meta.url));
const RASTER = new Set(['.png', '.jpg', '.jpeg', '.webp', '.avif']);

/**
 * Plafonds de non-régression, en octets.
 *
 * `perFile` : aucune image versionnée ne doit dépasser ce poids. Les captures
 * d'écran produit (`screenshots/`) sont exclues : ce sont des visuels de
 * documentation, pas des assets de la surface publique.
 *
 * `total` : plafond du total des images versionnées. Mesuré à 1 075 028 octets
 * (19 fichiers) le 8 novembre 2026 ; plafond posé à 1,5 Mo pour laisser une
 * marge de manœuvre sans autoriser une dérive silencieuse.
 */
const BUDGET = {
  perFile: 300 * 1024,
  total: 1536 * 1024,
  /** Dossiers exclus : visuels de documentation, pas des assets servis. */
  excludedDirs: ['screenshots'],
};

function walk(dir) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      if (BUDGET.excludedDirs.includes(entry.name)) continue;
      out.push(...walk(full));
    } else if (RASTER.has(extname(entry.name).toLowerCase())) {
      out.push(full);
    }
  }
  return out;
}

const files = walk(PUBLIC_DIR);
const report = process.argv.includes('--report');

let total = 0;
const over = [];

for (const f of files) {
  const size = statSync(f).size;
  total += size;
  if (size > BUDGET.perFile) over.push({ f, size });
}

const kb = (n) => `${(n / 1024).toFixed(1)} Ko`;

if (report) {
  console.log(`Images versionnées mesurées : ${files.length}`);
  for (const f of files.sort((a, b) => statSync(b).size - statSync(a).size)) {
    console.log(`  ${kb(statSync(f).size).padStart(10)}  ${relative(PUBLIC_DIR, f)}`);
  }
  console.log(`  ${kb(total).padStart(10)}  TOTAL`);
}

let failed = false;

if (over.length > 0) {
  failed = true;
  console.error(
    `\n✗ ${over.length} image(s) dépassent le plafond par fichier (${kb(BUDGET.perFile)}) :`,
  );
  for (const { f, size } of over) {
    console.error(`    ${kb(size)}  ${relative(PUBLIC_DIR, f)}`);
  }
}

if (total > BUDGET.total) {
  failed = true;
  console.error(
    `\n✗ Total des images versionnées ${kb(total)} > plafond ${kb(BUDGET.total)}.`,
  );
}

if (failed) {
  console.error(
    '\nBudget de poids des images publiques DÉPASSÉ (M4 #9106, PUBLIC_APP_BUDGETS.md §4).\n' +
      'Compresser en AVIF/WebP, ou dimensionner à l’usage réel — ne pas relever le plafond\n' +
      'sans mesure sur appareil de référence (§7).',
  );
  process.exit(1);
}

console.log(
  `✓ Budget de poids des images publiques respecté : ${files.length} fichiers, ${kb(total)} ` +
    `(plafond ${kb(BUDGET.total)}, par fichier ${kb(BUDGET.perFile)}).`,
);
