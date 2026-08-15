import 'dart:ui' show PlatformDispatcher;

/// Locale intl pour le formatage des dates côté appareil.
///
/// #3405 — les écrans formataient les dates avec `'fr_FR'` codé en dur alors
/// que l'app est 4-locales (fr/ar/tr/en) : les utilisateurs AR/TU/EN voyaient
/// des mois/jours français. On dérive la locale de l'appareil (comme
/// `Accept-Language` déjà envoyé par `ApiClient`), avec repli français.
String get deviceIntlDateLocale {
  final locale = PlatformDispatcher.instance.locale;
  final language = locale.languageCode.toLowerCase();

  return switch (language) {
    'ar' => 'ar',
    'tr' => 'tr',
    'en' => 'en',
    _ => 'fr',
  };
}
