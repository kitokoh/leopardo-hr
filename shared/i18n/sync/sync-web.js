const path = require('path');
const fs = require('fs');
const { localeCatalogs, repoRoot, updateVersions, writeJson } = require('./utils');

/**
 * sync-web.js — Sync shared i18n catalogs to all web front-end targets.
 *
 * Targets:
 *   1. front/admin-dashboard/src/i18n/locales/  (Vue admin, vue-i18n)
 *   2. front/web/src/lib/i18n/locales/          (Next.js client portal)
 *
 * Union semantics for admin-dashboard (issue #3853) :
 *   front/admin-dashboard/src/i18n/locales/{fr,en,tr,ar}.json sont des
 *   catalogues UNION (clés partagées + clés admin-only : adminChat.*,
 *   dashboard.leo_*, marketing.oauth.*, webhooks, signup, companies, seo...).
 *   Les écraser avec le catalogue partagé pur fait disparaître ~40 clés par
 *   locale → runtime `$t()` retombe sur la clé brute et validate-and-sync
 *   détecte un checksum incohérent. On préserve donc les clés admin-only :
 *   merge profond où la valeur partagée gagne sur les clés partagées, et les
 *   clés admin-only gardent leur valeur existante.
 */

const adminDir = path.join(repoRoot, 'front', 'admin-dashboard', 'src', 'i18n', 'locales');
const webDir = path.join(repoRoot, 'front', 'web', 'src', 'lib', 'i18n', 'locales');

function deepMerge(base, override) {
  const out = { ...base };
  for (const [key, value] of Object.entries(override)) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      out[key] = deepMerge(
        base[key] && typeof base[key] === 'object' && !Array.isArray(base[key]) ? base[key] : {},
        value,
      );
    } else {
      out[key] = value; // la valeur partagée gagne sur les clés partagées
    }
  }
  return out;
}

function readJsonOr(filePath, fallback) {
  try {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch {
    return fallback;
  }
}

updateVersions();

for (const { locale, data } of localeCatalogs()) {
  // Admin-dashboard : union (shared gagne, admin-only préservées)
  const adminCurrent = readJsonOr(path.join(adminDir, `${locale}.json`), {});
  writeJson(path.join(adminDir, `${locale}.json`), deepMerge(adminCurrent, data));

  // Web vitrine/portail : catalogue partagé pur (aucune clé app-specific)
  writeJson(path.join(webDir, `${locale}.json`), { ...data });
}

console.log('I18N_SYNC_WEB_OK');
