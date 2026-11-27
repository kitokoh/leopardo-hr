import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_surface/src/features/journeys/data/contact_request_gateway.dart';
import 'package:leopardo_surface/src/features/journeys/data/retail_order_gateway.dart';
import 'package:leopardo_surface/src/features/journeys/data/stay_reservation_gateway.dart';
import 'package:leopardo_surface/src/features/journeys/domain/journey_models.dart';

/// Les corps de requête sont testés SANS réseau, contre les règles serveur.
///
/// ## Pourquoi ces tests existent
///
/// Un parcours peut être visuellement parfait et systématiquement refusé en
/// 422 : un champ renommé (`customer_name` au lieu de `customer.name`), un
/// `consent` oublié, une date au mauvais format. La CI d'une app ne voit pas
/// ces fautes — elle analyse le Dart, elle n'appelle pas l'API. Ces tests
/// verrouillent donc la FORME exacte des corps attendus par :
/// - `StoreMarketOrderRequest`
/// - `StoreHospitalityPublicReservationRequest`
/// - `StorePublicContactRequest`
void main() {
  const offer = JourneyOffer(
    id: '42',
    title: 'Café 250 g',
    priceMinor: 3500,
    currency: 'MAD',
  );

  group('commande boutique — StoreMarketOrderRequest', () {
    test('corps exact, quantités incluses', () {
      final payload = RetailOrderGateway.buildOrderPayload(
        sellerSlug: 'epicerie-nadia',
        lines: [JourneyCartLine(offer: offer, quantity: 2)],
        customer: const JourneyCustomer(
          name: 'Amina',
          phone: '+212600000000',
          email: 'amina@example.com',
        ),
        delivery: const JourneyAddress(
          address: '12 rue des Fleurs',
          city: 'Casablanca',
          notes: 'Sonner deux fois',
        ),
        paymentMethod: 'cash',
        idempotencyKey: 'pub-1234567890',
      );

      expect(payload, {
        'seller': 'epicerie-nadia',
        'items': [
          {'product_id': 42, 'quantity': 2},
        ],
        'customer': {
          'name': 'Amina',
          'phone': '+212600000000',
          'email': 'amina@example.com',
        },
        'delivery': {
          'address': '12 rue des Fleurs',
          'city': 'Casablanca',
          'notes': 'Sonner deux fois',
        },
        'payment_method': 'cash',
        'idempotency_key': 'pub-1234567890',
      });
    });

    test('deux lignes du même produit sont fusionnées', () {
      final payload = RetailOrderGateway.buildOrderPayload(
        sellerSlug: 'epicerie-nadia',
        lines: [
          JourneyCartLine(offer: offer, quantity: 2),
          JourneyCartLine(offer: offer, quantity: 1),
        ],
        customer: const JourneyCustomer(name: 'Amina', phone: '0600000000'),
        delivery: const JourneyAddress(address: 'Rue 1', city: 'Rabat'),
        paymentMethod: 'online',
        idempotencyKey: 'pub-1234567890',
      );

      expect(payload['items'], [
        {'product_id': 42, 'quantity': 3},
      ]);
    });

    test('une quantité hors bornes est ramenée à la règle serveur 1..999', () {
      final payload = RetailOrderGateway.buildOrderPayload(
        sellerSlug: 'epicerie-nadia',
        lines: [JourneyCartLine(offer: offer, quantity: 5000)],
        customer: const JourneyCustomer(name: 'Amina', phone: '0600000000'),
        delivery: const JourneyAddress(address: 'Rue 1', city: 'Rabat'),
        paymentMethod: 'cash',
        idempotencyKey: 'pub-1234567890',
      );

      expect((payload['items'] as List).first, {
        'product_id': 42,
        'quantity': 999,
      });
    });

    test('un panier vide est refusé côté client', () {
      expect(
        () => RetailOrderGateway.buildOrderPayload(
          sellerSlug: 'epicerie-nadia',
          lines: const [],
          customer: const JourneyCustomer(name: 'Amina', phone: '0600000000'),
          delivery: const JourneyAddress(address: 'Rue 1', city: 'Rabat'),
          paymentMethod: 'cash',
          idempotencyKey: 'pub-1234567890',
        ),
        throwsArgumentError,
      );
    });

    test('un téléphone manquant est refusé (règle customer.phone required)',
        () {
      expect(
        () => RetailOrderGateway.buildOrderPayload(
          sellerSlug: 'epicerie-nadia',
          lines: [JourneyCartLine(offer: offer, quantity: 1)],
          customer: const JourneyCustomer(name: 'Amina', phone: '  '),
          delivery: const JourneyAddress(address: 'Rue 1', city: 'Rabat'),
          paymentMethod: 'cash',
          idempotencyKey: 'pub-1234567890',
        ),
        throwsArgumentError,
      );
    });

    test('un moyen de paiement inconnu est refusé', () {
      expect(
        () => RetailOrderGateway.buildOrderPayload(
          sellerSlug: 'epicerie-nadia',
          lines: [JourneyCartLine(offer: offer, quantity: 1)],
          customer: const JourneyCustomer(name: 'Amina', phone: '0600000000'),
          delivery: const JourneyAddress(address: 'Rue 1', city: 'Rabat'),
          paymentMethod: 'bitcoin',
          idempotencyKey: 'pub-1234567890',
        ),
        throwsArgumentError,
      );
    });

    test('un identifiant produit non numérique est éliminatoire', () {
      expect(
        () => RetailOrderGateway.buildOrderPayload(
          sellerSlug: 'epicerie-nadia',
          lines: const [
            JourneyCartLine(
              offer: JourneyOffer(id: 'abc', title: 'X', priceMinor: 100),
              quantity: 1,
            ),
          ],
          customer: const JourneyCustomer(name: 'Amina', phone: '0600000000'),
          delivery: const JourneyAddress(address: 'Rue 1', city: 'Rabat'),
          paymentMethod: 'cash',
          idempotencyKey: 'pub-1234567890',
        ),
        throwsArgumentError,
      );
    });
  });

  group('réservation séjour — StoreHospitalityPublicReservationRequest', () {
    test('corps exact avec contact e-mail', () {
      final payload = StayReservationGateway.buildReservationPayload(
        roomTypeId: 7,
        guestName: 'Amina',
        checkIn: '2026-12-01',
        checkOut: '2026-12-03',
        email: 'amina@example.com',
        adults: 2,
        children: 1,
        idempotencyKey: 'pub-1234567890',
      );

      expect(payload, {
        'room_type_id': 7,
        'guest_name': 'Amina',
        'contact_email': 'amina@example.com',
        'check_in': '2026-12-01',
        'check_out': '2026-12-03',
        'adults': 2,
        'children': 1,
        'idempotency_key': 'pub-1234567890',
      });
    });

    test(
        'un contact vide est refusé : la réservation ne serait pas confirmable',
        () {
      expect(
        () => StayReservationGateway.buildReservationPayload(
          roomTypeId: 7,
          guestName: 'Amina',
          checkIn: '2026-12-01',
          checkOut: '2026-12-03',
        ),
        throwsArgumentError,
      );
    });

    test('une date mal formée est refusée', () {
      expect(
        () => StayReservationGateway.buildReservationPayload(
          roomTypeId: 7,
          guestName: 'Amina',
          checkIn: '01/12/2026',
          checkOut: '2026-12-03',
          email: 'amina@example.com',
        ),
        throwsArgumentError,
      );
    });

    test('un départ antérieur à l’arrivée est refusé', () {
      expect(
        () => StayReservationGateway.buildReservationPayload(
          roomTypeId: 7,
          guestName: 'Amina',
          checkIn: '2026-12-03',
          checkOut: '2026-12-01',
          email: 'amina@example.com',
        ),
        throwsArgumentError,
      );
    });

    test('un séjour d’une nuit est refusé (after:check_in)', () {
      expect(
        () => StayReservationGateway.buildReservationPayload(
          roomTypeId: 7,
          guestName: 'Amina',
          checkIn: '2026-12-01',
          checkOut: '2026-12-01',
          email: 'amina@example.com',
        ),
        throwsArgumentError,
      );
    });
  });

  group('demande — StorePublicContactRequest', () {
    Map<String, dynamic> payload({
      String type = 'trade_company',
      String email = 'amina@example.com',
      String message = 'Bonjour, je souhaite un devis pour 200 unités.',
      String key = 'pub-12345678',
    }) {
      return ContactRequestGateway.buildPayload(
        targetType: type,
        targetSlug: 'atelier-nadia',
        name: 'Amina',
        email: email,
        message: message,
        idempotencyKey: key,
      );
    }

    test('le consentement est TOUJOURS explicite et à vrai', () {
      expect(payload()['consent'], isTrue);
    });

    test('le champ piège n’est jamais envoyé', () {
      expect(payload().containsKey('company_website'), isFalse);
    });

    test('un type sans resolver public est refusé avant l’appel', () {
      // `PublicProfileLocator::types()` ne contient pas `pharmacy` : la
      // requête serait un 422. L'app doit refuser plus tôt, avec un message.
      expect(() => payload(type: 'pharmacy'), throwsArgumentError);
      expect(() => payload(type: 'travel'), throwsArgumentError);
    });

    test('un message trop court est refusé (min 10)', () {
      expect(() => payload(message: 'Devis'), throwsArgumentError);
    });

    test('un slug public invalide est refusé', () {
      expect(
        () => ContactRequestGateway.buildPayload(
          targetType: 'restaurant',
          targetSlug: 'pas de slug ici',
          name: 'Amina',
          email: 'amina@example.com',
          message: 'Bonjour, une table pour deux ce soir ?',
          idempotencyKey: 'pub-12345678',
        ),
        throwsArgumentError,
      );
    });

    test('un e-mail sans arobase est refusé', () {
      expect(() => payload(email: 'amina.example.com'), throwsArgumentError);
    });

    test('une clé d’idempotence trop courte est refusée (min 8)', () {
      expect(() => payload(key: 'short'), throwsArgumentError);
    });
  });

  group('modèles', () {
    test('un produit sans identifiant est rejeté', () {
      expect(JourneyOffer.tryParse({'name': 'Café'}), isNull);
      expect(JourneyOffer.tryParse({'id': 3}), isNull);
      expect(JourneyOffer.tryParse(null), isNull);
    });

    test('un produit indisponible reste visible mais non commandable', () {
      final parsed = JourneyOffer.tryParse({
        'id': 3,
        'name': 'Café',
        'price_minor': 1200,
        'available': false,
      });

      expect(parsed, isNotNull);
      expect(parsed!.available, isFalse);
      expect(parsed.isPurchasable, isFalse);
    });

    test('un type de chambre parse les clés du contrat', () {
      final room = StayRoomOffer.tryParse({
        'room_type_id': 9,
        'code': 'DLX',
        'name': 'Deluxe',
        'available': 2,
        'base_price_minor': 45000,
        'currency': 'MAD',
      });

      expect(room, isNotNull);
      expect(room!.roomTypeId, 9);
      expect(room.available, 2);
      expect(room.isBookable, isTrue);
      expect(room.priceMinor, 45000);
    });

    test('un type de chambre complet n’est pas réservable', () {
      final room = StayRoomOffer.tryParse({
        'room_type_id': 9,
        'name': 'Deluxe',
        'available': 0,
        'base_price_minor': 45000,
      });

      expect(room!.isBookable, isFalse);
    });

    test('un suivi sans référence rend null (jamais une référence inventée)',
        () {
      expect(
        JourneyStatusReport.tryParse(
          {'status': 'confirmed'},
          fallbackReference: 'CMD-1',
        )?.reference,
        'CMD-1',
      );
      expect(JourneyStatusReport.tryParse('texte', fallbackReference: 'X'),
          isNull);
    });

    test('le suivi expose statut, vendeur, total et étapes', () {
      final report = JourneyStatusReport.tryParse(
        {
          'reference': 'CMD-9',
          'fulfillment_status': 'pending',
          'total_minor': 1250,
          'currency': 'MAD',
          'seller': {'name': 'Épicerie Nadia'},
          'payment': {'status': 'cash'},
          'timeline': {'placed_at': '2026-11-24T18:00:00Z'},
        },
        fallbackReference: 'CMD-9',
      );

      expect(report!.status, 'pending');
      expect(report.fields['seller'], 'Épicerie Nadia');
      expect(report.fields['total'], '12,50 MAD');
      expect(report.events, ['placed_at: 2026-11-24T18:00:00Z']);
    });

    test('formatMinorPrice n’arrondit jamais un montant envoyé au serveur', () {
      expect(formatMinorPrice(1250, 'MAD'), '12,50 MAD');
      expect(formatMinorPrice(5, 'MAD'), '0,05 MAD');
      expect(formatMinorPrice(-250, 'EUR'), '-2,50 EUR');
      expect(formatMinorPrice(100, null), '1,00');
    });

    test('isIsoDate refuse un format libre et une date impossible', () {
      expect(isIsoDate('2026-11-24'), isTrue);
      expect(isIsoDate('24/11/2026'), isFalse);
      expect(isIsoDate('2026-13-01'), isFalse);
      expect(isIsoDate('2026-02-30'), isFalse);
      expect(isIsoDate(''), isFalse);
    });
  });
}
