import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/core/i18n/app_strings.dart';

/// Tests du catalogue de messages — le contrat de textes du couloir 16.
///
/// ## Pourquoi ces tests existent
///
/// Le cahier des charges impose 4 langues (fr, en, ar RTL, tr) et « un catalogue
/// de messages à clés stables ». Une clé manquante dans UNE langue ne casse
/// aucun build : elle affiche silencieusement le français à un utilisateur
/// arabophone, ou la clé brute si le repli échoue. C'est le genre de régression
/// qui se découvre en production, sur la langue qu'on ne teste jamais.
///
/// Ces tests sont donc une GARDE, pas une formalité.
void main() {
  group('AppStrings — locales supportées', () {
    test('les 4 locales annoncées sont réellement présentes', () {
      expect(AppStrings.supportedLocales, ['fr', 'en', 'ar', 'tr']);

      for (final locale in AppStrings.supportedLocales) {
        final strings = AppStrings.of(locale);
        expect(strings.locale, locale, reason: 'locale $locale non résolue');
      }
    });

    test('le repli est le français, jamais l\'anglais', () {
      // La langue canonique du backend est fr (voir AppStrings.defaultLocale).
      // Un repli en anglais ferait passer un utilisateur turc pour un
      // anglophone dès qu'une clé manque.
      expect(AppStrings.of(null).locale, 'fr');
      expect(AppStrings.of('').locale, 'fr');
      expect(AppStrings.of('de').locale, 'fr');
      expect(AppStrings.of('xx-YY').locale, 'fr');
    });

    test('les variantes régionales retombent sur la bonne base', () {
      // `Accept-Language` envoie `fr-CA`, `ar-MA`, `en-GB` : sans le découpage
      // sur `-`/`_`, tous ces utilisateurs obtiendraient le français.
      expect(AppStrings.of('fr-CA').locale, 'fr');
      expect(AppStrings.of('ar_MA').locale, 'ar');
      expect(AppStrings.of('en-GB').locale, 'en');
      expect(AppStrings.of('tr_TR').locale, 'tr');
    });

    test('seul l\'arabe est RTL', () {
      expect(AppStrings.of('ar').isRtl, isTrue);
      expect(AppStrings.of('fr').isRtl, isFalse);
      expect(AppStrings.of('en').isRtl, isFalse);
      expect(AppStrings.of('tr').isRtl, isFalse);
    });
  });

  group('AppStrings — parité des clés entre locales', () {
    test('aucune clé n\'est manquante dans en, ar ou tr', () {
      final reference = AppStrings.of('fr').keys;
      expect(reference, isNotEmpty);

      for (final locale in ['en', 'ar', 'tr']) {
        final strings = AppStrings.of(locale);
        final missing = reference.difference(strings.keys);
        final extra = strings.keys.difference(reference);

        expect(
          missing,
          isEmpty,
          reason: 'clés absentes en $locale : ${missing.join(", ")}',
        );
        expect(
          extra,
          isEmpty,
          reason: 'clés en trop en $locale (jamais lues) : ${extra.join(", ")}',
        );
      }
    });

    test('aucune traduction n\'est vide ou blanche', () {
      for (final locale in AppStrings.supportedLocales) {
        final strings = AppStrings.of(locale);
        for (final key in strings.keys) {
          expect(
            strings.t(key).trim(),
            isNotEmpty,
            reason: 'clé "$key" vide en $locale',
          );
        }
      }
    });

    test('les clés utilisées par les écrans existent réellement', () {
      // Liste des clés référencées par l'interface M1. Si un écran utilise
      // `strings.t('x')` avec une clé absente du catalogue, `t()` retourne la
      // clé brute — donc « stateEmptyTitle » s'affiche à l'écran. Ce test
      // attrape le cas AVANT que ça arrive chez un utilisateur.
      const used = [
        'tabHome', 'tabMessages', 'tabActivity', 'tabProfile',
        'discoverySearchHint', 'discoveryCityLabel', 'discoveryCityHint',
        'discoveryViewList', 'discoveryViewMap', 'discoveryOpenNow',
        'discoveryNearby', 'discoveryUseLocation', 'discoveryLocationDenied',
        'stateLoading', 'stateEmptyTitle', 'stateEmptyBody',
        'stateNoResultsTitle', 'stateNoResultsBody', 'stateNoResultsWiden',
        'stateErrorTitle', 'stateErrorBody', 'stateRetry',
        'stateOfflineTitle', 'stateOfflineBody',
        'stateNotFoundTitle', 'stateNotFoundBody', 'stateNotFoundAction',
        'businessOpenNow', 'businessClosedNow', 'businessVerified',
        'businessHours', 'businessAddress', 'businessItinerary',
        'businessShare', 'businessReport', 'businessContact',
        'businessOrder', 'businessBooking', 'businessMenu', 'businessAbout',
        'businessActionsUnavailable',
        'commonCancel', 'commonClose', 'commonBack', 'commonShareText',
        'commonNoModule',
        'messagesEmptyTitle', 'messagesEmptyBody', 'messagesAssistant',
        'messagesAssistantBadge',
        'activityEmptyTitle', 'activityEmptyBody',
        'profileLanguage', 'profilePrivacy', 'profileSessions',
        'profileExport', 'profileDelete', 'profileNotSignedIn',
        'profileNotSignedInBody', 'profileComingSoon',
      ];

      final reference = AppStrings.of('fr').keys;
      for (final key in used) {
        expect(
          reference,
          contains(key),
          reason: 'clé "$key" utilisée par un écran mais absente du catalogue',
        );
      }
    });
  });

  group('AppStrings.t — comportement de repli', () {
    test('une clé inconnue retourne la clé brute, pas un vide', () {
      // Repli DÉLIBÉRÉ : voir « clé visible = bug visible ». Un texte vide
      // laisserait un bouton muet que personne ne remarque.
      expect(AppStrings.of('fr').t('__cle_inexistante__'), '__cle_inexistante__');
    });

    test('le texte arabe est réellement en arabe', () {
      final strings = AppStrings.of('ar');
      // Contrôle minimal : le texte contient au moins un caractère de la plage
      // arabe (U+0600–U+06FF). Un copier-coller du français serait attrapé.
      final arabic = RegExp(r'[\u0600-\u06FF]');
      expect(arabic.hasMatch(strings.t('tabHome')), isTrue);
      expect(arabic.hasMatch(strings.t('businessContact')), isTrue);
    });

    test('le texte turc n\'est pas du français recopié', () {
      final tr = AppStrings.of('tr');
      final fr = AppStrings.of('fr');
      // Quelques clés à fort risque de copier-coller (vocabulaire proche).
      expect(tr.t('tabHome'), isNot(fr.t('tabHome')));
      expect(tr.t('businessContact'), isNot(fr.t('businessContact')));
      expect(tr.t('stateRetry'), isNot(fr.t('stateRetry')));
    });

    test('les gabarits %s sont présents dans les 4 locales', () {
      // `discoveryResultsCount` et `commonShareText` sont formatés par
      // `replaceFirst('%s', …)`. Si le gabarit disparaît d'une traduction, la
      // valeur est perdue silencieusement.
      for (final locale in AppStrings.supportedLocales) {
        final strings = AppStrings.of(locale);
        expect(
          strings.t('discoveryResultsCount'),
          contains('%s'),
          reason: 'gabarit %s absent de discoveryResultsCount en $locale',
        );
        expect(
          strings.t('commonShareText'),
          contains('%s'),
          reason: 'gabarit %s absent de commonShareText en $locale',
        );
      }
    });
  });
}
