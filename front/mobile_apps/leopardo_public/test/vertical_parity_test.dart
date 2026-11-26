import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/journeys/data/vertical_directory_gateway.dart';
import 'package:leopardo_public/features/journeys/domain/journey_registry.dart';
import 'package:leopardo_public/features/journeys/domain/vertical_directory_models.dart';

/// Contrat des cinq verticales restantes (#9948).
///
/// ## Ce que ces tests protègent
///
/// Ces verticales n'ajoutent presque aucune mise en page : elles ajoutent des
/// **règles de refus**. Une entrée sans slug, un devis sans coordonnées, une
/// course sans téléphone — chacun de ces cas reviendrait en 422 et
/// l'utilisateur croirait que le professionnel l'a refusé. Ces tests figent la
/// forme exacte des contrats publics.
void main() {
  group('parsing d’une entrée d’annuaire', () {
    test('un item sans slug n’est pas ouvrable', () {
      expect(VerticalEntry.tryParse({'name': 'Café'}), isNull);
      expect(VerticalEntry.tryParse({'slug': '   '}), isNull);
      expect(VerticalEntry.tryParse(null), isNull);
    });

    test('les champs publics sont lus', () {
      final entry = VerticalEntry.tryParse({
        'type': 'restaurant',
        'slug': 'cafe-central',
        'name': 'Café Central',
        'category': 'cafe',
        'city': 'Casablanca',
        'country': 'MA',
        'rating_avg': 4.5,
        'reviews_count': 12,
      });

      expect(entry, isNotNull);
      expect(entry!.slug, 'cafe-central');
      expect(entry.location, 'Casablanca, MA');
      expect(entry.hasRating, isTrue);
    });

    test('une note sans avis n’est pas affichée', () {
      // Une note sans le moindre avis inventerait une réputation.
      final entry = VerticalEntry.tryParse({
        'slug': 'x',
        'name': 'X',
        'rating_avg': 5.0,
      });

      expect(entry!.ratingAvg, 5.0);
      expect(entry.hasRating, isFalse);
    });

    test('une distance absente reste nulle, pas zéro', () {
      // « 0 km » sans mesure ferait croire à une proximité calculée.
      final entry = VerticalEntry.tryParse({'slug': 'x', 'name': 'X'});
      expect(entry!.distanceKm, isNull);
    });

    test('panne et annuaire vide sont deux états distincts', () {
      expect(VerticalDirectory.unavailable.available, isFalse);
      expect(VerticalDirectory.unavailable.isEmpty, isFalse);

      const empty = VerticalDirectory(available: true, entries: []);
      expect(empty.isEmpty, isTrue);
    });
  });

  group('contrat VTC', () {
    const pickup = GeoPoint(lat: 33.5731, lng: -7.5898);
    const dropoff = GeoPoint(lat: 33.6, lng: -7.6);

    test('le corps du devis respecte estimate', () {
      final payload = VtcGateway.buildEstimatePayload(
        operator: ' taxi-vert ',
        pickup: pickup,
        dropoff: dropoff,
      );

      expect(payload, {
        'operator': 'taxi-vert',
        'pickup': {'lat': 33.5731, 'lng': -7.5898},
        'dropoff': {'lat': 33.6, 'lng': -7.6},
      });
    });

    test('un opérateur vide est refusé avant l’envoi', () {
      expect(
        () => VtcGateway.buildEstimatePayload(
          operator: '  ',
          pickup: pickup,
          dropoff: dropoff,
        ),
        throwsArgumentError,
      );
    });

    test('un point hors bornes terrestres est refusé', () {
      expect(const GeoPoint(lat: 91, lng: 0).isValid, isFalse);
      expect(const GeoPoint(lat: 0, lng: 181).isValid, isFalse);
      expect(
        () => VtcGateway.buildEstimatePayload(
          operator: 'op',
          pickup: const GeoPoint(lat: 91, lng: 0),
          dropoff: dropoff,
        ),
        throwsArgumentError,
      );
    });

    test('une saisie « lat,lng » est parsée, le reste refusé', () {
      expect(GeoPoint.tryParseText('33.5731, -7.5898')?.lat, 33.5731);
      expect(GeoPoint.tryParseText('33.5731'), isNull);
      expect(GeoPoint.tryParseText('abc,def'), isNull);
      expect(GeoPoint.tryParseText('91,0'), isNull);
    });

    test('le corps de la course omet les adresses vides', () {
      // Envoyer `""` ferait échouer une validation `string|min:1` alors que
      // le champ est facultatif.
      final payload = VtcGateway.buildRidePayload(
        operator: 'op',
        idempotencyKey: 'key-1',
        pickup: pickup,
        dropoff: dropoff,
        passengerName: ' Amina ',
        passengerPhone: '0600000000',
        pickupAddress: '   ',
        dropoffAddress: 'Rue X',
      );

      expect(payload.containsKey('pickup_address'), isFalse);
      expect(payload['dropoff_address'], 'Rue X');
      expect(payload['passenger_name'], 'Amina');
    });

    test('nom et téléphone sont requis', () {
      expect(
        () => VtcGateway.buildRidePayload(
          operator: 'op',
          idempotencyKey: 'k',
          pickup: pickup,
          dropoff: dropoff,
          passengerName: '  ',
          passengerPhone: '0600000000',
        ),
        throwsArgumentError,
      );
      expect(
        () => VtcGateway.buildRidePayload(
          operator: 'op',
          idempotencyKey: 'k',
          pickup: pickup,
          dropoff: dropoff,
          passengerName: 'Amina',
          passengerPhone: '  ',
        ),
        throwsArgumentError,
      );
    });

    test('une course sans référence n’est pas une course', () {
      expect(GuestRide.tryParse({'status': 'requested'}), isNull);
      expect(GuestRide.tryParse(null), isNull);
    });

    test('un devis sans prix n’est pas déclaré tarifé', () {
      final quote = RideEstimate.tryParse({
        'distance_m': 4200,
        'duration_s': 900,
      });

      expect(quote!.isPriced, isFalse);
      expect(quote.distanceM, 4200);
    });
  });

  // ── Registre ──
  group('registre des verticales', () {
    test('les 5 verticales restantes ont une entrée', () {
      for (final code in ['restaurant', 'retail', 'hospitality', 'trade', 'vtc']) {
        expect(journeyByCode(code), isNotNull, reason: 'verticale $code');
      }
    });

    test('le blocage VTC `ride` est retiré : le parcours est natif', () {
      // La vitrine web ne géocode pas davantage : elle demande « lat,lng »
      // et appelle le même contrat. Le blocage était donc périmé.
      expect(journeyByCode('vtc')!.blockerFor('ride'), isNull);
      expect(journeyByCode('vtc')!.hasNativeBooking, isTrue);
    });

    test('les verticales d’annuaire sont joignables par la découverte', () {
      for (final code in ['restaurant', 'retail', 'hospitality', 'trade']) {
        final vertical = journeyByCode(code)!;
        expect(vertical.discoveryType, isNotNull, reason: code);
        expect(vertical.blockerFor('entry'), isNull, reason: code);
      }
    });

    test('le blocage `order` du restaurant reste justifié', () {
      // `X-Restaurant-Shop-Token` est un jeton par tenant : l'exposer au grand
      // public est une décision de sécurité serveur, pas un oubli mobile.
      expect(journeyByCode('restaurant')!.blockerFor('order'), isNotNull);
    });

    test('le blocage `quote` du trade reste justifié', () {
      // Aucun endpoint de devis structuré côté public.
      expect(journeyByCode('trade')!.blockerFor('quote'), isNotNull);
    });
  });
}
