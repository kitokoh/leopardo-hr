const path = require('path');
const { localeCatalogs, repoRoot, updateVersions, writeJson } = require('./utils');

// Note: the legacy `front/mobile` Flutter app was deleted repo-wide in #754
// ("Delete deprecated legacy front/mobile/ codebase") and superseded by
// `front/mobile_apps/leopardo_core`. This sync target was left behind and
// kept re-materializing an untracked `front/mobile/lib/l10n/` directory on
// every run, which `git diff --exit-code` in the I18N Enterprise CI job
// never saw as a failure by itself but which also masked the real drift
// below. Removed; leopardo_core is the only live mobile l10n target.
const outputDirs = [
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
  'twoFa.title': 'twoFaTitle',
  'twoFa.subtitle': 'twoFaSubtitle',
  'twoFa.scanPrompt': 'twoFaScanPrompt',
  'twoFa.enterCode': 'twoFaEnterCode',
  'twoFa.confirm': 'twoFaConfirm',
  'twoFa.disable': 'twoFaDisable',
  'twoFa.disableHint': 'twoFaDisableHint',
  'twoFa.regenerate': 'twoFaRegenerate',
  'twoFa.regenerateConfirm': 'twoFaRegenerateConfirm',
  'twoFa.requiredBanner': 'twoFaRequiredBanner',
  'twoFa.statusEnabled': 'twoFaStatusEnabled',
  'twoFa.statusEnabledHint': 'twoFaStatusEnabledHint',
  'twoFa.statusDisabled': 'twoFaStatusDisabled',
  'twoFa.statusDisabledHint': 'twoFaStatusDisabledHint',
  'twoFa.copySecret': 'twoFaCopySecret',
  'twoFa.copied': 'twoFaCopied',
  'twoFa.copyAll': 'twoFaCopyAll',
  'twoFa.allCopied': 'twoFaAllCopied',
  'twoFa.recoveryTitle': 'twoFaRecoveryTitle',
  'twoFa.recoveryHint': 'twoFaRecoveryHint',
  'twoFa.doneHint': 'twoFaDoneHint',
  'twoFa.genericError': 'twoFaGenericError',
  'twoFa.invalidCode': 'twoFaInvalidCode',
  'twoFa.loading': 'twoFaLoading',
  'twoFa.activate': 'twoFaActivate',
  'twoFa.cancel': 'twoFaCancel',
  'twoFa.codeLabel': 'twoFaCodeLabel',
  'twoFa.codeHint': 'twoFaCodeHint',
  'twoFa.confirmHint': 'twoFaConfirmHint',
  'twoFa.challengeTitle': 'twoFaChallengeTitle',
  'twoFa.challengeSubtitle': 'twoFaChallengeSubtitle',
  'twoFa.challengeCodeHint': 'twoFaChallengeCodeHint',
  'twoFa.challengeRecoveryHint': 'twoFaChallengeRecoveryHint',
  'twoFa.challengeRecoveryToggle': 'twoFaChallengeRecoveryToggle',
  'twoFa.challengeVerifyBtn': 'twoFaChallengeVerifyBtn',
  'twoFa.settingsTile': 'twoFaSettingsTile',
  'twoFa.settingsTileSubtitle': 'twoFaSettingsTileSubtitle',
  'settingsTheme.title': 'settingsThemeTitle',
  'settingsTheme.hint': 'settingsThemeHint',
  'settingsTheme.system': 'settingsThemeSystem',
  'settingsTheme.light': 'settingsThemeLight',
  'settingsTheme.dark': 'settingsThemeDark',
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
      // Preserve already-camelized top-level compatibility aliases. The canonical
      // catalogs contain these legacy keys flat; passing them through sanitizePart()
      // lowercases the internal capitals (e.g. attendanceCheckinLabel becomes
      // attendancecheckinlabel), so Flutter never generates the getter used by
      // the legacy mobile screens.
      const isTopLevelCamelCase = current.length === 0
        && /^[a-z][A-Za-z0-9]*$/.test(key)
        && /[A-Z]/.test(key);
      const mobileKey = keyAliases[dotKey]
        || (isTopLevelCamelCase ? key : defaultKey(dotKey));
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
