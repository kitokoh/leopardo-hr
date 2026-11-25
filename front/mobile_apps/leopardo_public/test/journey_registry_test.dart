import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/core/i18n/app_strings.dart';
import 'package:leopardo_public/features/journeys/data/contact_request_gateway.dart';
import 'package:leopardo_public/features/journeys/domain/journey_registry.dart';

/// Le registre des parcours est la seule chose qui empêche une verticale de
/// disparaître en silence.
///
/// ## Ce que ces tests protègent
///
/// Le 2026-11-23 (#9883), une app analysée SEULE ne voyait pas qu'elle portait
/// l'identifiant d'une autre. Ici, la version silencieuse du même défaut serait
/// « sept verticales sur huit ont un parcours, la huitième n'a plus rien et
/// personne ne le remarque ». Ces tests échouent si :
/// - une verticale de `canonicalJourneyCodes` manque au registre ;
/// - deux verticales partagent un code ou un type de découverte ;
/// - une verticale se déclare « bloquée » sans motif, ou « native » sans
///   capacité ;
/// - un motif ou un libellé n'existe pas dans les 4 locales ;
/// - une verticale déclare un `contactTargetType` que la passerelle refuse
///   (elle enverrait une demande vouée au 422).
void main() {
  group('couverture des 8 verticales', () {
    test('chaque code canonique est présent exactement une fois', () {
      expect(journeyVerticals, hasLength(canonicalJourneyCodes.length));

      for (final code in canonicalJourneyCodes) {
        final matches = journeyVerticals.where((v) => v.code == code).toList();
        expect(
          matches,
          hasLength(1),
          reason: 'la verticale « $code » doit être déclarée une seule fois',
        );
      }
    });

    test('aucun code hors du canonique n’est introduit sans mise à jour', () {
      final declared = journeyVerticals.map((v) => v.code).toSet();
      expect(declared.difference(canonicalJourneyCodes.toSet()), isEmpty);
    });

    test('les types de découverte sont uniques', () {
      final types = journeyVerticals
          .map((v) => v.discoveryType)
          .whereType<String>()
          .toList();

      expect(types.toSet(), hasLength(types.length));
    });

    test('le lookup par type est fail-closed', () {
      expect(journeyByType('type-inexistant'), isNull);
      expect(journeyByType(null), isNull);
      expect(journeyByType(''), isNull);

      expect(journeyByType('retail_shop')?.code, 'retail');
      expect(journeyByType('hospitality_property')?.code, 'hospitality');
      expect(journeyByType('pharmacy')?.code, 'pharmacy');
    });

    test('le lookup par code est fail-closed', () {
      expect(journeyByCode('inconnu'), isNull);
      expect(journeyByCode('restaurant')?.code, 'restaurant');
    });
  });

  group('cohérence mode / capacités / blocages', () {
    test('un mode natif implique une capacité native', () {
      for (final vertical in journeyVerticals) {
        if (vertical.mode == JourneyMode.order) {
          expect(vertical.hasNativeOrder, isTrue, reason: vertical.code);
          expect(vertical.nativeActions, contains('order'));
        }
        if (vertical.mode == JourneyMode.booking) {
          expect(vertical.hasNativeBooking, isTrue, reason: vertical.code);
          expect(vertical.nativeActions, contains('booking'));
        }
        if (vertical.mode == JourneyMode.request ||
            vertical.mode == JourneyMode.blocked) {
          expect(vertical.nativeActions, isEmpty, reason: vertical.code);
        }
      }
    });

    test('une verticale bloquée porte au moins un motif nommé', () {
      for (final vertical in journeyVerticals) {
        if (vertical.mode == JourneyMode.blocked) {
          expect(
            vertical.blockers,
            isNotEmpty,
            reason: '« ${vertical.code} » est bloquée sans dire pourquoi',
          );
          for (final blocker in vertical.blockers) {
            expect(blocker.capability, isNotEmpty);
            expect(blocker.reasonKey, isNotEmpty);
          }
        }
      }
    });

    test('blockerFor rend null quand la capacité n’est pas bloquée', () {
      final retail = journeyByCode('retail')!;
      expect(retail.blockerFor('order'), isNull);

      final restaurant = journeyByCode('restaurant')!;
      expect(restaurant.blockerFor('order'), 'journeyBlockerRestaurantOrder');
    });
  });

  group('contrat de la passerelle de demande', () {
    test('un contactTargetType déclaré est accepté par la passerelle', () {
      for (final vertical in journeyVerticals) {
        final target = vertical.contactTargetType;
        if (target == null) continue;

        expect(
          contactTargetTypes,
          contains(target),
          reason:
              '« ${vertical.code} » déclare $target, que la passerelle refuse '
              '— la demande échouerait en 422',
        );

        // Et la passerelle doit réellement construire le corps.
        final payload = ContactRequestGateway.buildPayload(
          targetType: target,
          targetSlug: 'demo-slug',
          name: 'Amina',
          email: 'amina@example.com',
          message: 'Bonjour, je souhaite un devis.',
          idempotencyKey: 'pub-12345678',
        );
        expect(payload['target_type'], target);
      }
    });

    test('une verticale bloquée ne promet pas de contact', () {
      final pharmacy = journeyByCode('pharmacy')!;
      expect(pharmacy.canSendRequest, isFalse);
      expect(pharmacy.blockerFor('contact'), isNotNull);
    });
  });

  group('i18n des motifs et des libellés', () {
    test('chaque libellé et chaque motif existent dans les 4 locales', () {
      for (final locale in AppStrings.supportedLocales) {
        final strings = AppStrings.of(locale);
        final keys = strings.keys;

        for (final vertical in journeyVerticals) {
          expect(
            keys,
            contains(vertical.labelKey),
            reason: '${vertical.labelKey} manque en $locale',
          );

          for (final blocker in vertical.blockers) {
            expect(
              keys,
              contains(blocker.reasonKey),
              reason: '${blocker.reasonKey} manque en $locale',
            );
          }
        }
      }
    });

    test('aucun motif ne rend la clé brute', () {
      // `AppStrings.t` retombe sur la clé brute : une clé oubliée est donc
      // visible, mais il faut la chercher explicitement pour la remarquer.
      for (final vertical in journeyVerticals) {
        for (final blocker in vertical.blockers) {
          for (final locale in AppStrings.supportedLocales) {
            final text = AppStrings.of(locale).t(blocker.reasonKey);
            expect(text, isNot(blocker.reasonKey), reason: locale);
            expect(text.trim(), isNotEmpty);
          }
        }
      }
    });
  });
}
