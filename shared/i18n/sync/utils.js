const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const sharedRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(sharedRoot, '..', '..');
const localesDir = path.join(sharedRoot, 'locales');
const versionsPath = path.join(sharedRoot, 'versions', 'versions.json');
const metadataKeys = new Set(['_version', '_updated_at', '_locale']);

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function writeJson(filePath, data) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, `${JSON.stringify(data, null, 2)}\n`, 'utf8');
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

function localeCatalogs() {
  return fs
    .readdirSync(localesDir)
    .filter((file) => file.endsWith('.json'))
    .sort()
    .map((file) => ({
      locale: path.basename(file, '.json'),
      data: readJson(path.join(localesDir, file)),
    }));
}

function flatten(node, current = [], result = {}) {
  for (const [key, value] of Object.entries(node)) {
    if (metadataKeys.has(key) && current.length === 0) {
      continue;
    }
    const next = [...current, key];
    if (typeof value === 'string') {
      result[next.join('.')] = value;
      continue;
    }
    flatten(value, next, result);
  }
  return result;
}

function toPhpArray(node, depth = 0) {
  const indent = '    '.repeat(depth);
  const childIndent = '    '.repeat(depth + 1);
  const entries = Object.entries(node).map(([key, value]) => {
    if (typeof value === 'string') {
      return `${childIndent}${JSON.stringify(key)} => ${JSON.stringify(value)},`;
    }
    return `${childIndent}${JSON.stringify(key)} => [\n${toPhpArray(value, depth + 1)}\n${childIndent}],`;
  });
  return entries.join('\n');
}

function writePhpArray(filePath, payload) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  const body = toPhpArray(payload);
  fs.writeFileSync(filePath, `<?php\n\nreturn [\n${body}\n];\n`, 'utf8');
}

function updateVersions() {
  const versions = readJson(versionsPath);
  const catalogs = localeCatalogs();
  const generatedAt = catalogs
    .map(({ data }) => data._updated_at)
    .filter(Boolean)
    .sort()
    .at(-1) || versions.generated_at;
  versions.generated_at = generatedAt;
  for (const { locale, data } of catalogs) {
    const flattened = flatten(data);
    versions.locales[locale] = versions.locales[locale] || {};
    versions.locales[locale].checksum = checksum(flattened);
    versions.locales[locale].version = data._version;
    versions.locales[locale].updated_at = data._updated_at;
  }
  writeJson(versionsPath, versions);
  return versions;
}

module.exports = {
  checksum,
  flatten,
  localeCatalogs,
  readJson,
  repoRoot,
  updateVersions,
  versionsPath,
  writeJson,
  writePhpArray,
};
