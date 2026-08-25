import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_accounting/core/i18n/app_strings.dart';

void main() {
  group('AppStrings — i18n ×4 (issue #5236)', () {
    test('chaque locale expose exactement le même jeu de clés que fr', () {
      final frKeys = AppStrings.of(AppStrings.defaultLocale).keys;

      for (final locale in AppStrings.supportedLocales) {
        final keys = AppStrings.of(locale).keys;
        expect(
          keys,
          frKeys,
          reason: 'la locale "$locale" doit avoir exactement les clés de fr',
        );
      }
    });

    test('aucune valeur n\'est vide dans les 4 locales', () {
      for (final locale in AppStrings.supportedLocales) {
        final app = AppStrings.of(locale);
        for (final key in app.keys) {
          expect(
            app.t(key).trim(),
            isNotEmpty,
            reason: '$locale/$key ne doit pas être vide',
          );
        }
      }
    });

    test('résolution : locale inconnue et préfixes régionaux', () {
      expect(AppStrings.of(null).locale, AppStrings.defaultLocale);
      expect(AppStrings.of('').locale, AppStrings.defaultLocale);
      expect(AppStrings.of('xx').locale, AppStrings.defaultLocale);
      expect(AppStrings.of('fr-FR').locale, 'fr');
      expect(AppStrings.of('en_US').locale, 'en');
      expect(AppStrings.of('ar-MA').locale, 'ar');
      expect(AppStrings.of('tr-TR').locale, 'tr');
    });

    test('clé inconnue renvoyée telle quelle (jamais de crash)', () {
      expect(AppStrings.of('fr').t('cle.inexistante'), 'cle.inexistante');
    });

    test('ar est la seule locale RTL', () {
      expect(AppStrings.of('ar').isRtl, isTrue);
      for (final locale in ['fr', 'tr', 'en']) {
        expect(AppStrings.of(locale).isRtl, isFalse, reason: locale);
      }
    });

    test('les 4 locales sont supportées par MaterialApp', () {
      expect(
        AppStrings.supportedLocales,
        containsAll(['fr', 'ar', 'tr', 'en']),
      );
    });
  });
}
