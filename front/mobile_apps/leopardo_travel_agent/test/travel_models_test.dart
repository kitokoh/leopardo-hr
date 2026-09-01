import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_booking.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_cash_session.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_trip.dart';

void main() {
  group('TravelTrip.fromJson (TRAVEL-308, GET /travel/trips/search)', () {
    test('parse le payload complet', () {
      final trip = TravelTrip.fromJson({
        'id': 12,
        'code': 'GV-2026-0001',
        'route_id': 3,
        'carrier_id': 1,
        'vehicle_id': 9,
        'departure_date': '2026-09-15',
        'departure_time': '08:30',
        'arrival_date': '2026-09-15',
        'arrival_time': '11:00',
        'means_of_transport': 'bus',
        'total_seats': 45,
        'status': 'scheduled',
        'prices': [
          {
            'id': 1,
            'trip_id': 12,
            'class_id': 2,
            'adult_price_minor': 25000,
            'child_price_minor': 15000,
            'currency': 'DZD',
          },
        ],
        'route': {
          'id': 3,
          'origin_city_id': 1,
          'destination_city_id': 2,
        },
      });

      expect(trip.id, 12);
      expect(trip.code, 'GV-2026-0001');
      expect(trip.departureDate, '2026-09-15');
      expect(trip.departureTime, '08:30');
      expect(trip.totalSeats, 45);
      expect(trip.prices, hasLength(1));
      expect(trip.firstAdultPriceMinor, 25000);
      expect(trip.currency, 'DZD');
      expect(trip.route?.originCityId, 1);
    });

    test('tolère les champs optionnels absents', () {
      final trip = TravelTrip.fromJson({'id': 1, 'code': 'X'});
      expect(trip.departureDate, '');
      expect(trip.prices, isEmpty);
      expect(trip.firstAdultPriceMinor, isNull);
      expect(trip.route, isNull);
    });
  });

  group('TravelBooking.fromJson (TRAVEL-312..316)', () {
    test('parse le payload complet avec passagers et billets', () {
      final booking = TravelBooking.fromJson({
        'id': 42,
        'reference': 'BK-2026-0042',
        'trip_id': 12,
        'status': 'confirmed',
        'passenger_count': 2,
        'total_amount_minor': 50000,
        'currency': 'DZD',
        'booking_source': 'office',
        'payment_status': 'confirmed',
        'expires_at': null,
        'contact_email': 'client@example.com',
        'notify_consent': true,
        'passengers': [
          {
            'id': 1,
            'booking_id': 42,
            'full_name': 'Alice',
            'age_category': 'adult',
            'class_id': 2,
            'seat_number': 5,
            'unit_price_minor': 25000,
            'has_document': true,
          },
        ],
        'tickets': [
          {
            'id': 7,
            'ticket_number': 'TKT-2026-0007',
            'booking_id': 42,
            'passenger_id': 1,
            'status': 'issued',
          },
        ],
      });

      expect(booking.id, 42);
      expect(booking.reference, 'BK-2026-0042');
      expect(booking.status, 'confirmed');
      expect(booking.paymentStatus, 'confirmed');
      expect(booking.totalAmountMinor, 50000);
      expect(booking.passengers, hasLength(1));
      expect(booking.passengers.first.fullName, 'Alice');
      expect(booking.passengers.first.hasDocument, isTrue);
      expect(booking.tickets, hasLength(1));
      expect(booking.tickets.first.ticketNumber, 'TKT-2026-0007');
    });

    test('tolère les payloads minimaux', () {
      final booking = TravelBooking.fromJson({'id': 1});
      expect(booking.reference, isNull);
      expect(booking.passengers, isEmpty);
      expect(booking.tickets, isEmpty);
      expect(booking.totalAmountMinor, isNull);
    });
  });

  group('TravelCashSession.fromJson (TRAVEL-810/#6100)', () {
    test('parse une session ouverte', () {
      final session = TravelCashSession.fromJson({
        'id': 3,
        'status': 'open',
        'opened_by_user_id': 11,
        'opened_at': '2026-08-31T08:00:00Z',
        'opening_balance_minor': 5000,
      });

      expect(session.id, 3);
      expect(session.isOpen, isTrue);
      expect(session.openingBalanceMinor, 5000);
    });

    test('parse une clôture avec écart serveur', () {
      final session = TravelCashSession.fromJson({
        'id': 3,
        'status': 'closed',
        'opening_balance_minor': 5000,
        'expected_balance_minor': 55000,
        'actual_balance_minor': 55200,
        'difference_minor': 200,
      });

      expect(session.isOpen, isFalse);
      expect(session.expectedBalanceMinor, 55000);
      expect(session.differenceMinor, 200);
    });
  });

  group('TravelReceipt.fromJson (GET /travel/pdv/receipts/{id})', () {
    test('parse le reçu d\'encaissement', () {
      final receipt = TravelReceipt.fromJson({
        'reference': 'BK-2026-0042',
        'trip_id': 12,
        'passenger_count': 1,
        'total_amount_minor': 25000,
        'currency': 'DZD',
        'payment_status': 'confirmed',
        'passengers': [
          {'full_name': 'Alice', 'seat_number': 5, 'unit_price_minor': 25000},
        ],
      });

      expect(receipt.reference, 'BK-2026-0042');
      expect(receipt.totalAmountMinor, 25000);
      expect(receipt.passengers, hasLength(1));
      expect(receipt.passengers.first.seatNumber, 5);
    });
  });
}
