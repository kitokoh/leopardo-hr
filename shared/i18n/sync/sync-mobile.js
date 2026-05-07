const path = require('path');
const { localeCatalogs, repoRoot, updateVersions, writeJson } = require('./utils');

const outputDir = path.join(repoRoot, 'mobile', 'lib', 'l10n');
const keyAliases = {
  'app.title': 'appTitle',
  'welcome.brand.subtitle': 'welcomeBrandSubtitle',
  'welcome.hero.title': 'welcomeHeroTitle',
  'welcome.hero.description': 'welcomeHeroDescription',
  'welcome.story.clarity.title': 'welcomeStoryClarityTitle',
  'welcome.story.clarity.body': 'welcomeStoryClarityBody',
  'welcome.story.field.title': 'welcomeStoryFieldTitle',
  'welcome.story.field.body': 'welcomeStoryFieldBody',
  'welcome.story.modules.title': 'welcomeStoryModulesTitle',
  'welcome.story.modules.body': 'welcomeStoryModulesBody',
  'auth.login.title': 'login',
  'auth.employee.invitation.access': 'employeeInvitationAccess',
  'auth.personal_account.create': 'createPersonalAccount',
  'auth.personal_account.explanation': 'personalAccountExplanation',
};

function defaultKey(pathValue) {
  const parts = pathValue.split('.');
  return parts
    .map((part, index) => (index === 0 ? part : part.charAt(0).toUpperCase() + part.slice(1)))
    .join('');
}

function flatten(node, current = [], result = {}) {
  for (const [key, value] of Object.entries(node)) {
    if (key.startsWith('_') && current.length === 0) {
      continue;
    }
    const next = [...current, key];
    if (typeof value === 'string') {
      const dotKey = next.join('.');
      const mobileKey = keyAliases[dotKey] || defaultKey(dotKey);
      result[mobileKey] = value;
      continue;
    }
    flatten(value, next, result);
  }
  return result;
}

updateVersions();

for (const { locale, data } of localeCatalogs()) {
  const arb = {
    '@@locale': locale,
    ...flatten(data),
  };
  writeJson(path.join(outputDir, `app_${locale}.arb`), arb);
}

console.log('I18N_SYNC_MOBILE_OK');
