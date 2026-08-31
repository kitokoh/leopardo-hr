import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

import '../models/travel_booking.dart';
import '../models/travel_cash_session.dart';
import '../models/travel_trip.dart';

/// Accès aux endpoints TravelAgency depuis l'app agent (TRAVEL-701/#6088).
///
/// Le module backend est servi sous `/travel/*` (routes modules,
/// middleware `module.travelagency` + RBAC `travel.*`). Tous les montants
/// sont en unités mineures (jamais de flottant).
class TravelRepository {
  TravelRepository(this._apiClient);

  final ApiClient _apiClient;

  static const int _pageSize = 50;

  /// Recherche de trajets (GET /travel/trips/search).
  Future<List<TravelTrip>> searchTrips({
    int? originCityId,
    int? destinationCityId,
    String? departureDate,
    String? dateFrom,
    String? dateTo,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/trips/search',
      queryParameters: {
        'per_page': _pageSize,
        if (originCityId != null) 'origin_city_id': originCityId,
        if (destinationCityId != null) 'destination_city_id': destinationCityId,
        if (departureDate != null && departureDate.isNotEmpty)
          'departure_date': departureDate,
        if (dateFrom != null && dateFrom.isNotEmpty) 'date_from': dateFrom,
        if (dateTo != null && dateTo.isNotEmpty) 'date_to': dateTo,
      },
    );

    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((item) => TravelTrip.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  /// Détail d'un trajet (GET /travel/trips/{id}).
  Future<TravelTrip> getTrip(int tripId) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/trips/$tripId',
    );
    return TravelTrip.fromJson(extractDataMap(response.data));
  }

  /// Villes du référentiel (GET /travel/cities) — pour les filtres de
  /// recherche.
  Future<List<TravelCity>> listCities({String? search}) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/cities',
      queryParameters: {
        'per_page': _pageSize,
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );

    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((item) => TravelCity.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  /// Création d'une réservation guichet (POST /travel/bookings).
  /// `idempotency_key` obligatoire : un rejeu réseau ne crée jamais deux
  /// réservations (TRAVEL-312).
  Future<TravelBooking> createBooking({
    required int tripId,
    required String idempotencyKey,
    required List<BookingPassengerDraft> passengers,
    String? contactEmail,
    String? contactPhone,
    bool? notifyConsent,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/bookings',
      method: 'POST',
      data: {
        'trip_id': tripId,
        'booking_source': 'office',
        'idempotency_key': idempotencyKey,
        if (contactEmail != null && contactEmail.isNotEmpty)
          'contact_email': contactEmail,
        if (contactPhone != null && contactPhone.isNotEmpty)
          'contact_phone': contactPhone,
        if (notifyConsent != null) 'notify_consent': notifyConsent,
        'passengers': passengers.map((p) => p.toPayload()).toList(),
      },
    );

    return TravelBooking.fromJson(extractDataMap(response.data));
  }

  /// Confirmation + encaissement cash (POST /travel/bookings/{id}/confirm).
  Future<TravelBooking> confirmBooking(int bookingId) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/bookings/$bookingId/confirm',
      method: 'POST',
    );
    return TravelBooking.fromJson(extractDataMap(response.data));
  }

  /// Émission des billets (POST /travel/bookings/{id}/issue-ticket).
  Future<TravelBooking> issueTickets(int bookingId) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/bookings/$bookingId/issue-ticket',
      method: 'POST',
    );
    return TravelBooking.fromJson(extractDataMap(response.data));
  }

  /// Check-in d'un billet (POST /travel/tickets/{id}/check-in).
  Future<TravelTicket> checkInTicket(int ticketId) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/tickets/$ticketId/check-in',
      method: 'POST',
    );
    return TravelTicket.fromJson(extractDataMap(response.data));
  }

  /// Manifeste d'un trajet (GET /travel/trips/{id}/manifest) — liste des
  /// passagers triée par siège.
  Future<List<TravelPassenger>> manifest(int tripId) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/trips/$tripId/manifest',
    );

    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((item) => TravelPassenger.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  /// Réservations du guichet (GET /travel/bookings).
  Future<List<TravelBooking>> listBookings({String? status}) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/bookings',
      queryParameters: {
        'per_page': _pageSize,
        if (status != null && status.isNotEmpty) 'status': status,
      },
    );

    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((item) => TravelBooking.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  // ── PDV (TRAVEL-810/#6100) ────────────────────────────────────────────

  /// Ouverture d'une session de caisse (POST /travel/pdv/session/open).
  Future<TravelCashSession> openCashSession({int openingBalanceMinor = 0}) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/pdv/session/open',
      method: 'POST',
      data: {'opening_balance_minor': openingBalanceMinor},
    );
    return TravelCashSession.fromJson(extractDataMap(response.data));
  }

  /// Session en cours (GET /travel/pdv/session/current) — null si aucune.
  Future<TravelCashSession?> currentCashSession() async {
    final response = await _apiClient.requestWithRetry(
      '/travel/pdv/session/current',
    );
    final data = extractDataMap(response.data);
    if (data.isEmpty) {
      return null;
    }
    return TravelCashSession.fromJson(data);
  }

  /// Clôture avec le montant réel saisi — l'écart est calculé serveur
  /// (critère d'acceptation TRAVEL-810).
  Future<TravelCashSession> closeCashSession({
    required int actualBalanceMinor,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/pdv/session/close',
      method: 'POST',
      data: {'actual_balance_minor': actualBalanceMinor},
    );
    return TravelCashSession.fromJson(extractDataMap(response.data));
  }

  /// Reçu d'encaissement d'une réservation (GET /travel/pdv/receipts/{id}).
  Future<TravelReceipt> receipt(int bookingId) async {
    final response = await _apiClient.requestWithRetry(
      '/travel/pdv/receipts/$bookingId',
    );
    return TravelReceipt.fromJson(extractDataMap(response.data));
  }
}

/// Ville du référentiel (GET /travel/cities).
class TravelCity {
  const TravelCity({this.id, this.name, this.countryId});

  final int? id;
  final String? name;
  final int? countryId;

  factory TravelCity.fromJson(Map<String, dynamic> json) {
    return TravelCity(
      id: json['id'] as int?,
      name: json['name'] as String?,
      countryId: json['country_id'] as int?,
    );
  }

  @override
  String toString() => name ?? '';

  @override
  bool operator ==(Object other) => other is TravelCity && other.id == id;

  @override
  int get hashCode => id.hashCode;
}

/// Brouillon de passager pour la création de réservation.
class BookingPassengerDraft {
  const BookingPassengerDraft({
    required this.fullName,
    required this.ageCategory,
    required this.classId,
    this.seatNumber,
    this.birthDate,
  });

  final String fullName;
  final String ageCategory;
  final int classId;
  final int? seatNumber;
  final String? birthDate;

  Map<String, dynamic> toPayload() {
    return {
      'full_name': fullName,
      'age_category': ageCategory,
      'class_id': classId,
      if (seatNumber != null) 'seat_number': seatNumber,
      if (birthDate != null && birthDate.isNotEmpty) 'birth_date': birthDate,
    };
  }
}
