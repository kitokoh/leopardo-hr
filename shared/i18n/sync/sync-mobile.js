const path = require('path');
const { localeCatalogs, repoRoot, updateVersions, writeJson } = require('./utils');

const outputDirs = [
  path.join(repoRoot, 'front', 'mobile', 'lib', 'l10n'),
  path.join(repoRoot, 'front', 'mobile_apps', 'leopardo_core', 'lib', 'l10n'),
];
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

function sanitizePart(part) {
  return part
    .replace(/[^a-zA-Z0-9]+/g, ' ')
    .split(' ')
    .filter(Boolean)
    .map((token, index) => {
      const lower = token.toLowerCase();
      return index === 0
        ? lower.charAt(0).toUpperCase() + lower.slice(1)
        : lower.charAt(0).toUpperCase() + lower.slice(1);
    })
    .join('');
}

function defaultKey(pathValue) {
  const parts = pathValue.split('.');
  return parts
    .map((part, index) => {
      const sanitized = sanitizePart(part);
      if (!sanitized) {
        return '';
      }
      return index === 0
        ? sanitized.charAt(0).toLowerCase() + sanitized.slice(1)
        : sanitized.charAt(0).toUpperCase() + sanitized.slice(1);
    })
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
  for (const outputDir of outputDirs) {
    writeJson(path.join(outputDir, `app_${locale}.arb`), arb);
  }
}

console.log('I18N_SYNC_MOBILE_OK');
