const path = require('path');
const { localeCatalogs, repoRoot, updateVersions, writeJson } = require('./utils');

/**
 * sync-web.js — Sync shared i18n catalogs to all web front-end targets.
 *
 * Targets:
 *   1. front/admin-dashboard/src/i18n/locales/  (Vue admin, vue-i18n)
 *   2. front/web/src/lib/i18n/locales/          (Next.js client portal)
 */

const outputDirs = [
  path.join(repoRoot, 'front', 'admin-dashboard', 'src', 'i18n', 'locales'),
  path.join(repoRoot, 'front', 'web', 'src', 'lib', 'i18n', 'locales'),
];

updateVersions();

for (const { locale, data } of localeCatalogs()) {
  const payload = { ...data };
  for (const dir of outputDirs) {
    writeJson(path.join(dir, `${locale}.json`), payload);
  }
}

console.log('I18N_SYNC_WEB_OK');
