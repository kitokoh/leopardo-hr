const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const rootDir = path.resolve(__dirname, '..');
const localesDir = path.join(rootDir, 'locales');
const glossaryPath = path.join(rootDir, 'glossary', 'glossary.json');
const versionsPath = path.join(rootDir, 'versions', 'versions.json');
const metadataKeys = new Set(['_version', '_updated_at', '_locale']);
const placeholderRegex = /:([a-zA-Z0-9_]+)/g;
const mojibakeRegex = /Ã|Â|Ù|Ø/;
const rtlRegex = /[\u0600-\u06FF]/;
const maxLengths = [
  { pattern: /\.subject$/, limit: 120 },
  { pattern: /\.title$/, limit: 90 },
  { pattern: /\.action$/, limit: 40 },
];

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function stableStringify(value) {
  if (Array.isArray(value)) {
    return `[${value.map(stableStringify).join(',')}]`;
  }
  if (value && typeof value === 'object') {
    return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${stableStringify(value[key])}`).join(',')}}`;
  }
  return JSON.stringify(value);
}

function checksum(value) {
  return crypto.createHash('sha256').update(stableStringify(value)).digest('hex');
}

function collectLeafPaths(node, current = [], result = new Map()) {
  for (const [key, value] of Object.entries(node)) {
    if (metadataKeys.has(key) && current.length === 0) {
      continue;
    }
    const nextPath = [...current, key];
    if (typeof value === 'string') {
      const flat = nextPath.join('.');
      if (result.has(flat)) {
        throw new Error(`Duplicate key detected: ${flat}`);
      }
      result.set(flat, value);
      continue;
    }
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      throw new Error(`Invalid node at ${nextPath.join('.')}`);
    }
    collectLeafPaths(value, nextPath, result);
  }
  return result;
}

function extractPlaceholders(value) {
  return [...value.matchAll(placeholderRegex)].map((match) => match[1]).sort();
}

function ensureMetadata(locale, data, errors) {
  if (data._locale !== locale) {
    errors.push(`[${locale}] _locale must match filename`);
  }
  if (!/^\d+\.\d+\.\d+$/.test(String(data._version || ''))) {
    errors.push(`[${locale}] _version must follow semver`);
  }
  if (!/^\d{4}-\d{2}-\d{2}T/.test(String(data._updated_at || ''))) {
    errors.push(`[${locale}] _updated_at must be an ISO datetime`);
  }
}

function ensureLengths(locale, leaves, errors) {
  for (const [key, value] of leaves.entries()) {
    for (const rule of maxLengths) {
      if (rule.pattern.test(key) && value.length > rule.limit) {
        errors.push(`[${locale}] ${key} exceeds ${rule.limit} chars`);
      }
    }
  }
}

function ensureRtl(locale, leaves, errors) {
  if (locale !== 'ar') {
    return;
  }
  for (const [key, value] of leaves.entries()) {
    if (mojibakeRegex.test(value)) {
      errors.push(`[${locale}] ${key} looks mojibaked`);
    }
    if (/[\p{Letter}]/u.test(value) && !rtlRegex.test(value) && !/:/.test(value)) {
      errors.push(`[${locale}] ${key} should contain Arabic script`);
    }
  }
}

function ensureGlossary(glossary, localeCatalogs, errors) {
  for (const [term, definition] of Object.entries(glossary)) {
    for (const [locale, catalog] of Object.entries(localeCatalogs)) {
      const expected = definition.translations[locale];
      if (!expected || !definition.locked) {
        continue;
      }
      if (!catalog.has('modules.' + term) && !catalog.has(`emails.${term}`)) {
        continue;
      }
      const actual = catalog.get('modules.' + term) ?? catalog.get(`emails.${term}`);
      if (typeof actual === 'string' && actual.toLowerCase() !== expected.toLowerCase()) {
        errors.push(`[${locale}] glossary drift for term "${term}"`);
      }
    }
  }
}

function validate() {
  const errors = [];
  const localeFiles = fs.readdirSync(localesDir).filter((file) => file.endsWith('.json')).sort();
  const glossary = readJson(glossaryPath);
  const versions = readJson(versionsPath);
  const localeLeaves = {};

  let referenceKeys = null;
  for (const fileName of localeFiles) {
    const locale = path.basename(fileName, '.json');
    const data = readJson(path.join(localesDir, fileName));
    ensureMetadata(locale, data, errors);
    const leaves = collectLeafPaths(data);
    localeLeaves[locale] = leaves;

    for (const [key, value] of leaves.entries()) {
      if (!String(value).trim()) {
        errors.push(`[${locale}] ${key} is empty`);
      }
      if (mojibakeRegex.test(value)) {
        errors.push(`[${locale}] ${key} looks mojibaked`);
      }
    }

    ensureLengths(locale, leaves, errors);
    ensureRtl(locale, leaves, errors);

    if (!referenceKeys) {
      referenceKeys = [...leaves.keys()].sort();
    } else {
      const localeKeys = [...leaves.keys()].sort();
      for (const key of referenceKeys) {
        if (!leaves.has(key)) {
          errors.push(`[${locale}] missing key ${key}`);
        }
      }
      for (const key of localeKeys) {
        if (!referenceKeys.includes(key)) {
          errors.push(`[${locale}] orphan key ${key}`);
        }
      }
    }
  }

  if (referenceKeys) {
    const referenceLocale = localeFiles[0].replace('.json', '');
    const referenceLeaves = localeLeaves[referenceLocale];
    for (const [locale, leaves] of Object.entries(localeLeaves)) {
      if (locale === referenceLocale) {
        continue;
      }
      for (const key of referenceKeys) {
        const expected = extractPlaceholders(referenceLeaves.get(key));
        const actual = extractPlaceholders(leaves.get(key) || '');
        if (expected.join('|') !== actual.join('|')) {
          errors.push(`[${locale}] placeholder mismatch on ${key}`);
        }
      }
    }
  }

  ensureGlossary(glossary, localeLeaves, errors);

  for (const [locale, leaves] of Object.entries(localeLeaves)) {
    const expectedChecksum = versions.locales?.[locale]?.checksum;
    const actualChecksum = checksum(Object.fromEntries(leaves));
    if (expectedChecksum && expectedChecksum !== actualChecksum) {
      errors.push(`[${locale}] checksum mismatch in versions.json`);
    }
  }

  if (errors.length > 0) {
    console.error('I18N_VALIDATION_FAILED');
    for (const error of errors) {
      console.error(`- ${error}`);
    }
    process.exit(1);
  }

  console.log(`I18N_VALIDATION_OK (${localeFiles.length} locales)`);
}

validate();
