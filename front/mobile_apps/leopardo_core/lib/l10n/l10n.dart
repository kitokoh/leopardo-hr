import 'package:flutter/widgets.dart';
import 'package:leopardo_core/core/i18n/error_messages.dart';
import 'package:leopardo_core/l10n/generated/app_localizations.dart';

export 'package:leopardo_core/l10n/generated/app_localizations.dart';

extension AppLocalizationsX on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
}

/// Localisations pour la locale de l'appareil, SANS BuildContext
/// (providers, repositories, services, callbacks async).
///
/// Pattern #5278 : les classes sans widget ne peuvent pas appeler
/// `context.l10n` ; elles utilisent ce helper qui dérive la locale de
/// l'appareil (fr/en/tr/ar, repli fr) — même logique que
/// [deviceIntlDateLocale] / le catalogue `error_messages.dart`.
AppLocalizations get deviceL10n =>
    lookupAppLocalizations(Locale(deviceUiLocale));
