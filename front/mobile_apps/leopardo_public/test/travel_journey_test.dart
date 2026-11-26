import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/journeys/data/travel_gateway.dart';
import 'package:leopardo_public/features/journeys/domain/travel_models.dart';

/// Contrat de la verticale Billets (#9943).
///
/// ## Ce que ces tests protègent
///
/// Le parcours travel est le premier de l'app qui envoie une **liste de
/// passagers** et un **consentement de notification**. Une erreur de forme ici
/// ne se voit pas à l'écran : elle revient en 422 et l'utilisateur croit que
/// l'agence a refusé sa réservation. Ces tests figent la forme exacte de
/// `StoreTravelMarketplaceBookingRequest`.
void main() {
  group('parsing d’un trajet', () {
    test('un trajet sans id numérique n’est pas réservable', () {
      expect(TravelTrip.tryParse({'code': 'TR-1'}), isNull);
      expect(TravelTrip.tryParse({'id': 'abc'}), isNull);
      expect(TravelTrip.tryParse(null), isNull);
    });

    test('les champs publics sont lus, y compris le nom d’agence', () {
      final trip = TravelTrip.tryParse({
        'id': 42,
        'code': 'TR-42',
        'departure_date': '2026-12-01',
        'departure_time': '08:30',
        'available_seats': 12,
        'origin_city': {'name': 'Casablanca', 'country_iso2': 'MA'},
        'destination_city': {'name': 'Rabat', 'country_iso2': 'MA'},
        'prices': [
          {
            'class_id': 3,
            'adult_price_minor': 12000,
            'child_price_minor': 6000,
            'currency': 'MAD',
          },
        ],
        'price_from_minor': 12000,
        'currency': 'MAD',
        'agency': {'name': 'Agence Atlas'},
      });

      expect(trip, isNotNull);
      expect(trip!.id, 42);
      expect(trip.route, 'Casablanca → Rabat');
      expect(trip.agencyName, 'Agence Atlas');
      expect(trip.isBookable, isTrue);
      expect(trip.defaultPrice?.classId, 3);
    });

    test('un trajet complet n’est pas réservable', () {
      final trip = TravelTrip.tryParse({'id': 1, 'available_seats': 0});
      expect(trip!.isBookable, isFalse);
    });

    test('des places inconnues ne déclarent pas le trajet complet', () {
      final trip = TravelTrip.tryParse({'id': 1});
      expect(trip!.availableSeats, isNull);
      expect(trip.isBookable, isTrue);
    });

    test('un itinéraire incomplet rend null plutôt qu’une flèche seule', () {
      final trip = TravelTrip.tryParse({
        'id': 1,
        'origin_city': {'name': 'Casablanca'},
      });
      expect(trip!.route, isNull);
    });
  });

  group('tarifs par catégorie d’âge', () {
    const price = TravelTripPrice(
      classId: 1,
      adultPriceMinor: 10000,
      childPriceMinor: 5000,
      currency: 'MAD',
    );

    test('un nourrisson ne paie pas de siège', () {
      expect(price.priceFor(TravelAgeCategory.infant), 0);
    });

    test('adulte et enfant paient leur tarif', () {
      expect(price.priceFor(TravelAgeCategory.adult), 10000);
      expect(price.priceFor(TravelAgeCategory.child), 5000);
    });
  });

  group('corps de réservation', () {
    List<TravelPassengerDraft> passengers({int count = 1}) => [
          for (var i = 0; i < count; i++)
            TravelPassengerDraft(
              fullName: 'Passager ${i + 1}',
              ageCategory: TravelAgeCategory.adult,
              classId: 3,
            ),
        ];

    test('le corps porte les clés exactes du contrat', () {
      final payload = TravelGateway.buildBookingPayload(
        tripId: 42,
        passengers: passengers(),
        idempotencyKey: 'pub-12345678',
        contactEmail: 'amina@example.com',
        notifyConsent: true,
      );

      expect(payload['trip_id'], 42);
      expect(payload['idempotency_key'], 'pub-12345678');
      expect(payload['contact_email'], 'amina@example.com');
      expect(payload['notify_consent'], isTrue);
      expect(payload['passengers'], hasLength(1));

      final passenger = (payload['passengers'] as List).first as Map;
      expect(passenger['full_name'], 'Passager 1');
      expect(passenger['age_category'], 'adult');
      expect(passenger['class_id'], 3);
      // Pas de siège choisi : la clé est OMISE, pas envoyée à null.
      expect(passenger.containsKey('seat_number'), isFalse);
    });

    test('un contact vide est omis, pas envoyé à chaîne vide', () {
      final payload = TravelGateway.buildBookingPayload(
        tripId: 42,
        passengers: passengers(),
        idempotencyKey: 'pub-12345678',
        contactEmail: '',
        contactPhone: '0600000000',
      );

      expect(payload.containsKey('contact_email'), isFalse);
      expect(payload['contact_phone'], '0600000000');
    });

    test('sans aucun contact, la réservation est refusée localement', () {
      expect(
        () => TravelGateway.buildBookingPayload(
          tripId: 42,
          passengers: passengers(),
          idempotencyKey: 'pub-12345678',
        ),
        throwsArgumentError,
      );
    });

    test('un passager sans nom est refusé', () {
      expect(
        () => TravelGateway.buildBookingPayload(
          tripId: 42,
          passengers: const [
            TravelPassengerDraft(
              fullName: '   ',
              ageCategory: TravelAgeCategory.adult,
              classId: 3,
            ),
          ],
          idempotencyKey: 'pub-12345678',
          contactPhone: '0600000000',
        ),
        throwsArgumentError,
      );
    });

    test('plus de 20 passagers est refusé (règle serveur max:20)', () {
      expect(
        () => TravelGateway.buildBookingPayload(
          tripId: 42,
          passengers: passengers(count: 21),
          idempotencyKey: 'pub-12345678',
          contactPhone: '0600000000',
        ),
        throwsArgumentError,
      );
    });

    test('un trajet absent est refusé', () {
      expect(
        () => TravelGateway.buildBookingPayload(
          tripId: 0,
          passengers: passengers(),
          idempotencyKey: 'pub-12345678',
          contactPhone: '0600000000',
        ),
        throwsArgumentError,
      );
    });
  });

  group('suivi de réservation', () {
    test('le statut lit le trajet et les billets imbriqués', () {
      final status = TravelBookingStatus.tryParse(
        {
          'reference': 'BK-1',
          'status': 'confirmed',
          'payment_status': 'paid',
          'passenger_count': 2,
          'total_amount_minor': 24000,
          'currency': 'MAD',
          'trip': {
            'code': 'TR-42',
            'departure_date': '2026-12-01',
            'departure_time': '08:30',
          },
          'tickets': [
            {'id': 7, 'ticket_number': 'TK-7', 'status': 'issued'},
          ],
        },
        fallbackReference: 'BK-1',
      );

      expect(status, isNotNull);
      expect(status!.tripCode, 'TR-42');
      expect(status.tickets, hasLength(1));
      expect(status.tickets.first.number, 'TK-7');
    });

    test('une réponse sans référence retombe sur celle demandée', () {
      final status = TravelBookingStatus.tryParse(
        {'status': 'pending'},
        fallbackReference: 'BK-9',
      );
      expect(status!.reference, 'BK-9');
    });
  });
}
