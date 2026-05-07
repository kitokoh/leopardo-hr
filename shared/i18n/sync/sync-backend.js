const path = require('path');
const { localeCatalogs, repoRoot, updateVersions, writePhpArray } = require('./utils');

const outputDir = path.join(repoRoot, 'api', 'lang');

function withoutMetadata(data) {
  const payload = { ...data };
  delete payload._version;
  delete payload._updated_at;
  delete payload._locale;
  return payload;
}

updateVersions();

for (const { locale, data } of localeCatalogs()) {
  const payload = withoutMetadata(data);
  const localeDir = path.join(outputDir, locale);

  writePhpArray(path.join(localeDir, 'shared.php'), {
    app: payload.app,
    welcome: payload.welcome,
    auth: payload.auth,
    common: payload.common,
    modules: payload.modules,
  });

  writePhpArray(path.join(localeDir, 'emails.enterprise.php'), payload.emails);
}

console.log('I18N_SYNC_BACKEND_OK');
