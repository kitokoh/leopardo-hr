const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

// Doit rester IDENTIQUE a shared/i18n/validators/validate.js:9 — sinon le
// checksum calcule ici ne correspondrait pas a celui que le validateur attend.
const metadataKeys = new Set(['_version', '_updated_at', '_locale']);

function stable(v) {
  if (Array.isArray(v)) return '[' + v.map(stable).join(',') + ']';
  if (v && typeof v === 'object') {
    return '{' + Object.keys(v).sort().map((k) => JSON.stringify(k) + ':' + stable(v[k])).join(',') + '}';
  }
  return JSON.stringify(v);
}

function leaves(node, cur = [], res = new Map()) {
  for (const [k, v] of Object.entries(node)) {
    if (metadataKeys.has(k) && cur.length === 0) continue;
    const np = [...cur, k];
    if (typeof v === 'string') res.set(np.join('.'), v);
    else if (v && typeof v === 'object') leaves(v, np, res);
  }
  return res;
}

const ck = (v) => crypto.createHash('sha256').update(stable(v)).digest('hex');

const vp = 'shared/i18n/versions/versions.json';
const versions = JSON.parse(fs.readFileSync(vp, 'utf8'));

for (const [surface, dir] of Object.entries({
  web: 'front/web/src/lib/i18n/locales',
  admin: 'front/admin-dashboard/src/i18n/locales',
})) {
  const expected = versions.surfaces && versions.surfaces[surface] && versions.surfaces[surface].checksums;
  if (!expected) continue;
  for (const f of fs.readdirSync(dir).filter((x) => x.endsWith('.json'))) {
    const loc = path.basename(f, '.json');
    if (!(loc in expected)) continue;
    const data = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
    expected[loc] = ck(Object.fromEntries(leaves(data)));
  }
}

fs.writeFileSync(vp, JSON.stringify(versions, null, 2) + '\n');
console.log('checksums recalculated');
