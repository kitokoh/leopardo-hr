const path = require('path');
const { localeCatalogs, repoRoot, updateVersions, writeJson } = require('./utils');

const outputDir = path.join(repoRoot, 'admin-dashboard', 'src', 'i18n', 'locales');

updateVersions();

for (const { locale, data } of localeCatalogs()) {
  const payload = { ...data };
  writeJson(path.join(outputDir, `${locale}.json`), payload);
}

console.log('I18N_SYNC_WEB_OK');
