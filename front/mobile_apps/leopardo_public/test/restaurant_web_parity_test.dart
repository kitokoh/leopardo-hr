import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/journeys/domain/restaurant_order_models.dart';

/// Écarts web ↔ mobile refermés par la mission de parité restaurant.
///
/// ## Pourquoi ces tests existent, précisément
///
/// Chaque assertion couvre un défaut qui existait réellement :
///
/// 1. `hours` et `cuisine_types` étaient dans la réponse publique et **pas dans
///    le modèle** : la fiche mobile perdait silencieusement trois informations
///    que la vitrine web affiche. Un modèle qui ignore un champ ne le réclame à
///    personne — seul un test le fait remarquer.
/// 2. `day_of_week` suit la convention RESTO-805 (0 = lundi) alors que
///    `DateTime.weekday` suit 1 = lundi. Un décalage d'un cran afficherait les
///    horaires du mardi le lundi et ferait mentir « ouvert maintenant » un jour
///    sur sept : ces cas sont donc testés des deux côtés de la frontière.
/// 3. Un avis ne doit jamais afficher une note inventée : un DTO sans `rating`
///    exploitable est écarté, pas transformé en 3/5.
/// 4. Un service qui passe minuit (22:00 → 02:00) est un VRAI cas restaurant,
///    et une comparaison naïve `open <= now < close` le déclare toujours fermé.
void main() {
  group('RestaurantHour — convention RESTO-805 (0 = lundi)', () {
    test('parse day_of_week, heures et état fermé', () {
      final hour = RestaurantHour.tryParse({
        'day_of_week': 2,
        'opens_at': '12:00',
        'closes_at': '14:30',
        'is_closed': false,
      });

      expect(hour, isNotNull);
      expect(hour!.dayOfWeek, 2);
      expect(hour.opensAt, '12:00');
      expect(hour.closesAt, '14:30');
      expect(hour.isClosed, isFalse);
      expect(hour.isServed, isTrue);
    });

    test('un jour fermé n’est pas servi, même avec des heures', () {
      final hour = RestaurantHour.tryParse({
        'day_of_week': 0,
        'opens_at': '12:00',
        'closes_at': '14:30',
        'is_closed': true,
      });

      expect(hour!.isClosed, isTrue);
      expect(hour.isServed, isFalse);
      expect(hour.isOpenAt(DateTime(2026, 11, 23, 13)), isFalse);
    });

    test('un jour sans day_of_week est écarté (jamais deviné)', () {
      expect(RestaurantHour.tryParse({'opens_at': '12:00'}), isNull);
      expect(RestaurantHour.tryParse('12:00-14:00'), isNull);
    });

    test('un day_of_week hors bornes est ramené dans 0..6', () {
      // 7 → 0 (dimanche mal numéroté par un import) : on normalise au lieu de
      // jeter la ligne, sinon la fiche perd un jour entier.
      expect(RestaurantHour.tryParse({'day_of_week': 7})!.dayOfWeek, 0);
      expect(RestaurantHour.tryParse({'day_of_week': -1})!.dayOfWeek, 6);
    });

    test('isOpenAt respecte la plage du jour', () {
      final hour = RestaurantHour.tryParse({
        'day_of_week': 0,
        'opens_at': '11:30',
        'closes_at': '15:00',
      })!;

      expect(hour.isOpenAt(DateTime(2026, 11, 23, 11, 29)), isFalse);
      expect(hour.isOpenAt(DateTime(2026, 11, 23, 11, 30)), isTrue);
      expect(hour.isOpenAt(DateTime(2026, 11, 23, 14, 59)), isTrue);
      expect(hour.isOpenAt(DateTime(2026, 11, 23, 15, 0)), isFalse);
    });

    test('un service qui passe minuit est ouvert des deux côtés', () {
      final night = RestaurantHour.tryParse({
        'day_of_week': 4,
        'opens_at': '22:00',
        'closes_at': '02:00',
      })!;

      expect(night.isOpenAt(DateTime(2026, 11, 27, 23, 0)), isTrue);
      expect(night.isOpenAt(DateTime(2026, 11, 27, 1, 0)), isTrue);
      expect(night.isOpenAt(DateTime(2026, 11, 27, 3, 0)), isFalse);
      expect(night.isOpenAt(DateTime(2026, 11, 27, 21, 59)), isFalse);
    });

    test('une heure mal formée ne fait pas planter le parsing', () {
      final hour = RestaurantHour.tryParse({
        'day_of_week': 1,
        'opens_at': 'pas-une-heure',
        'closes_at': '25:99',
      })!;

      expect(hour.isServed, isTrue);
      expect(hour.isOpenAt(DateTime(2026, 11, 24, 12)), isFalse);
    });
  });

  group('RestaurantPublicProfile — hours et cuisine_types', () {
    Map<String, dynamic> profile() => {
          'slug': 'le-bistrot',
          'name': 'Le Bistrot',
          'establishment_type': 'restaurant',
          'cuisine_types': ['marocaine', 'méditerranéenne', ''],
          'rating_avg': 4.5,
          'reviews_count': 12,
          'hours': [
            {'day_of_week': 0, 'opens_at': '12:00', 'closes_at': '15:00'},
            {'day_of_week': 2, 'opens_at': '12:00', 'closes_at': '15:00'},
            {'day_of_week': 4, 'is_closed': true},
          ],
          'menu': [
            {
              'name': 'Entrées',
              'products': [
                {'code': 'HARIRA', 'name': 'Harira', 'price_minor': 2500},
              ],
            },
          ],
        };

    test('parse cuisine_types en écartant les valeurs vides', () {
      final parsed = RestaurantPublicProfile.tryParse(profile());

      expect(parsed, isNotNull);
      expect(parsed!.cuisineTypes, ['marocaine', 'méditerranéenne']);
    });

    test('parse hours et les trie du lundi au dimanche', () {
      final parsed = RestaurantPublicProfile.tryParse(profile())!;

      expect(parsed.hours, hasLength(3));
      expect(parsed.sortedHours.map((h) => h.dayOfWeek).toList(), [0, 2, 4]);
    });

    test('isOpenAt vaut null quand aucun horaire n’est publié', () {
      // Ne pas savoir ≠ être fermé : l'écran n'affiche alors aucun badge.
      final parsed = RestaurantPublicProfile.tryParse({
        'slug': 'x',
        'name': 'X',
        'menu': const [],
      })!;

      expect(parsed.isOpenAt(DateTime(2026, 11, 23, 12)), isNull);
    });

    test('isOpenAt suit le jour réel (lundi = index 0)', () {
      final parsed = RestaurantPublicProfile.tryParse(profile())!;

      // 2026-11-23 est un lundi → index 0 → 12:00-15:00.
      expect(parsed.isOpenAt(DateTime(2026, 11, 23, 13)), isTrue);
      expect(parsed.isOpenAt(DateTime(2026, 11, 23, 16)), isFalse);

      // 2026-11-24 est un mardi → aucune ligne → fermé.
      expect(parsed.isOpenAt(DateTime(2026, 11, 24, 13)), isFalse);
    });

    test('un profil sans horaires ni cuisines reste valide', () {
      final parsed = RestaurantPublicProfile.tryParse({
        'slug': 'vide',
        'name': 'Vide',
        'menu': const [],
      })!;

      expect(parsed.hours, isEmpty);
      expect(parsed.cuisineTypes, isEmpty);
      expect(parsed.hasMenu, isFalse);
    });
  });

  group('RestaurantReview — jamais de note inventée', () {
    test('parse un avis publié complet', () {
      final review = RestaurantReview.tryParse({
        'author_name': 'Amina',
        'rating': 5,
        'comment': 'Excellent',
        'reply': 'Merci !',
        'replied_at': '2026-11-20',
        'date': '2026-11-19',
      });

      expect(review, isNotNull);
      expect(review!.authorName, 'Amina');
      expect(review.rating, 5);
      expect(review.hasReply, isTrue);
    });

    test('un avis sans rating est écarté, pas noté 3/5', () {
      expect(RestaurantReview.tryParse({'author_name': 'X'}), isNull);
      expect(RestaurantReview.tryParse('5'), isNull);
    });

    test('une note hors 1..5 est ramenée dans la plage', () {
      expect(RestaurantReview.tryParse({'rating': 9})!.rating, 5);
      expect(RestaurantReview.tryParse({'rating': 0})!.rating, 1);
    });

    test('une réponse vide ne compte pas comme réponse', () {
      final review = RestaurantReview.tryParse({'rating': 4, 'reply': '   '})!;

      expect(review.hasReply, isFalse);
    });

    test('la pagination lit meta et détecte les pages restantes', () {
      final page = RestaurantReviewPage.tryParse({
        'data': [
          {'rating': 5, 'author_name': 'A'},
          {'rating': 4, 'author_name': 'B'},
        ],
        'meta': {'total': 40, 'current_page': 1, 'per_page': 20},
      });

      expect(page, isNotNull);
      expect(page!.items, hasLength(2));
      expect(page.total, 40);
      expect(page.hasMore, isTrue);

      final last = RestaurantReviewPage.tryParse({
        'data': const [],
        'meta': {'total': 40, 'current_page': 2, 'per_page': 20},
      })!;

      expect(last.hasMore, isFalse);
      expect(last.isEmpty, isTrue);
    });

    test('un corps sans data rend null plutôt qu’une page vide trompeuse', () {
      expect(RestaurantReviewPage.tryParse({'meta': {'total': 0}}), isNull);
    });
  });

  group('statuts terminaux — le suivi automatique s’arrête au bon moment', () {
    test('les statuts terminaux sont reconnus, casse comprise', () {
      for (final status in ['served', 'paid', 'closed', 'cancelled', 'refunded']) {
        expect(isRestaurantTerminalStatus(status), isTrue, reason: status);
        expect(isRestaurantTerminalStatus(status.toUpperCase()), isTrue);
      }
    });

    test('un statut en cours est NON terminal', () {
      for (final status in ['pending', 'confirmed', 'preparing', 'ready']) {
        expect(isRestaurantTerminalStatus(status), isFalse, reason: status);
      }
    });

    test('un statut inconnu reste non terminal (une interrogation de trop)', () {
      expect(isRestaurantTerminalStatus(null), isFalse);
      expect(isRestaurantTerminalStatus('some_new_state'), isFalse);
    });
  });
}
