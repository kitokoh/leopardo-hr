#!/usr/bin/env node
/**
 * check-french-copy — garde anti-régression orthographe/accents FR de la vitrine (issue #8069).
 *
 * Échoue si un mot de la liste noire (formes sans accent ou anglicismes
 * corrigés lors de la passe #8069) réapparaît dans les strings FR exposées :
 *   - bloc `fr:` de src/modules/vitrine/lib/vitrine-locale.ts (copy landing)
 *   - zones FR de src/modules/vitrine/data/**.ts(x), détectées selon 3 formes :
 *       a) blocs indentés `  fr: {` / `  fr: [` (jusqu'à `  en:`/`  tr:`/`  ar:`)
 *       b) blocs top-level `const fr:` / `export const …Fr:` (jusqu'au bloc
 *          `const en:` / `…En:` équivalent)
 *       c) locales en ligne `{ fr: '…', en: '…' }` → seul le segment `fr:` est scanné
 *     Un fichier sans aucune de ces formes est scanné en entier (fichier FR pur).
 *
 * Ignorés : lignes `slug:`/`href:`/`url:`, clés slug (`'mon-slug': {`),
 * cibles de liens markdown, URLs, chemins internes, ids (`id: 'temoignages'`),
 * et le nom d'app `web-offline`.
 *
 * Usage : node scripts/check-french-copy.mjs   (depuis front/web)
 */

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

// Formes interdites : mot français attesté sur la vitrine sans son accent,
// ou anglicisme remplacé par #8069. Sensibles à la casse, mot entier.
// (les noms de plans Free/Pilot/Operations/Enterprise sont assumés — #8069)
const BLACKLIST = [
  // — vitrine-locale.ts (audit #8069) —
  'Creer', 'Telecharger', 'Temoignages', 'temoignages', 'Biometrique',
  'Generez', 'Visibilite', 'Complexite', 'eparpilles', 'casse-tete',
  'Securisez', 'revolutionnaire', 'Decouvrez', 'frequentes', 'reserves',
  'dediee', 'equipes', 'systeme', 'automatises', 'Compatibilite',
  'Integration', 'Recommande', 'Etudes', 'Etude', 'Succes', 'employes',
  'presence', 'Dernieres', 'editorialise', 'detaille', 'depot', 'adapte',
  'recue', 'Dashboards',
  // — data/*.ts (passe #8069 complémentaire) —
  'precise', 'presences', 'compatibilite', 'biometrie', 'avancee',
  'Geolocalisation', 'geolocalisation', 'conges', 'equipe',
  'automatisee', 'automatise', 'reglementations', 'generation', 'echeances',
  'Hebergement', 'donnees', 'accelerer', 'decisions', 'predictive',
  'Syntheses', 'Acces', 'Experience', 'adoptee', 'enormement',
  'methodes', 'supportees', 'offline', 'tres', 'ecritures',
];

const PHRASES = [
  "sans risque d'erreur", // promesse absolue sur la paie — formulation défendable exigée
  'Mobile-First Company OS', // footer FR quasi anglais
  'Self-Service Employe',
];

const LOCALES_FILE = 'src/modules/vitrine/lib/vitrine-locale.ts';
const DATA_DIR = 'src/modules/vitrine/data';

const wordPatterns = BLACKLIST.map(
  (w) => [w, new RegExp(`(?<![A-Za-zÀ-ÿ])${w}(?![A-Za-zÀ-ÿ])`)],
);
const phrasePatterns = PHRASES.map(
  (p) => [p, new RegExp(p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))],
);
const PATTERNS = [...wordPatterns, ...phrasePatterns];

const INDENTED_FR_START = /^  fr: [\{\[]/;
const INDENTED_LOCALE_END = /^  (en|tr|ar): [\{\[]/;
const TOPLEVEL_FR_START = /^(?:export )?const (?:fr|\w+Fr)\b/;
const TOPLEVEL_LOCALE_END = /^(?:export )?const (?:en|tr|ar|\w+(?:En|Tr|Ar))\b/;

/** Segments [texte, offsetCol] à scanner pour une ligne, ou null si ligne ignorée. */
function segmentsOf(rawLine) {
  if (/\b(slug|href|url)\s*:/i.test(rawLine)) return null;
  if (/^\s*'[a-z0-9-]+':\s*[\{\[]/.test(rawLine)) return null; // clé slug
  let line = rawLine
    .replace(/\]\([^)]*\)/g, ']()') // cibles de liens markdown
    .replace(/https?:\/\/\S+/g, '') // URLs
    .replace(/\/[a-z0-9-]+\/[a-z0-9\-/]*/g, '') // chemins internes (/employes/…)
    .replace(/\bid: '[a-z0-9-]+'/g, "id: ''") // ids HTML/ancres
    .replace(/web-offline/g, 'web_app'); // nom d'app, pas de la copy
  // Locales en ligne : { fr: '…', en: '…' } → segment fr uniquement
  if (/\bfr:\s*'/.test(line) && /\ben:\s*'/.test(line)) {
    const m = line.match(/\bfr:\s*'((?:[^'\\]|\\.)*)'/);
    return m ? [[m[1], (m.index ?? 0) + m[0].indexOf(m[1])]] : null;
  }
  return [[line, 0]];
}

/**
 * Itère les [lineNo, rawLine] des zones FR du fichier.
 * Machine à états : blocs indentés, blocs top-level, ou fichier entier.
 */
function* frenchLines(file, lines) {
  const hasIndentedFr = lines.some((l) => INDENTED_FR_START.test(l));
  const hasToplevelFr = lines.some((l) => TOPLEVEL_FR_START.test(l));
  const fullFile = !hasIndentedFr && !hasToplevelFr;
  let inFr = false;
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    // Fin de zone FR : le fichier bascule sur des traductions localisées (ex. blog.ts)
    if (/^(?:export )?const localized/.test(line)) return;
    if (inFr) {
      if (INDENTED_LOCALE_END.test(line) || TOPLEVEL_LOCALE_END.test(line)) {
        inFr = false;
        continue;
      }
      yield [i + 1, line];
    } else if (INDENTED_FR_START.test(line) || TOPLEVEL_FR_START.test(line)) {
      inFr = true;
      yield [i + 1, line];
    } else if (fullFile || /\bfr:\s*'/.test(line)) {
      // fichier FR pur, ou ligne à locales en ligne hors bloc
      yield [i + 1, line];
    }
  }
}

function* walk(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      if (entry === '__tests__') continue;
      yield* walk(full);
    } else if (['.ts', '.tsx'].includes(extname(full))) {
      yield full;
    }
  }
}

let failures = 0;

function scanFile(file) {
  const lines = readFileSync(file, 'utf8').split('\n');
  for (const [lineNo, rawLine] of frenchLines(file, lines)) {
    const segments = segmentsOf(rawLine);
    if (segments === null) continue;
    for (const [text, offset] of segments) {
      for (const [label, pattern] of PATTERNS) {
        const m = text.match(pattern);
        if (m) {
          failures++;
          const col = offset + (m.index ?? 0) + 1;
          console.error(`${file}:${lineNo}:${col} — forme interdite « ${label} » (passe FR #8069)`);
        }
      }
    }
  }
}

scanFile(LOCALES_FILE);
for (const file of walk(DATA_DIR)) scanFile(file);

if (failures > 0) {
  console.error(`\ncheck-french-copy: ${failures} forme(s) interdite(s) — voir issue #8069 (accents FR vitrine).`);
  process.exit(1);
}
console.log('check-french-copy: OK — aucune forme interdite dans la copy FR de la vitrine.');
