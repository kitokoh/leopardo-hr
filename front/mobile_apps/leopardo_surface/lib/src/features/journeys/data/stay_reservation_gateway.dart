import '../../discovery/data/public_discovery_repository.dart';
import '../domain/journey_models.dart';
import 'journey_http.dart';

/// Parcours de réservation d'un séjour (`hospitality_property`).
///
/// ## Le contrat, tel qu'il existe réellement
///
/// - `GET  /public/hospitality/properties/{slug}/availability?from&to&adults`
/// - `POST /public/hospitality/properties/{slug}/reservations`
/// - `GET  /public/hospitality/reservations/{reference}?code={tracking_code}`
///
/// La disponibilité est calculée par le serveur (capacité opérationnelle moins
/// les chambres immobilisées/pending expirées) : l'app ne la recalcule jamais.
/// Un type de chambre à `available: 0` reste affiché « complet », il n'est
/// simplement pas sélectionnable — le cacher laisserait croire qu'il n'existe
/// pas.
class StayReservationGateway {
  StayReservationGateway(this._api);

  final JourneyApi _api;

  String propertyPath(String slug) => '/public/hospitality/properties/$slug';

  String reservationPath(String reference) =>
      '/public/hospitality/reservations/$reference';

  Future<List<StayRoomOffer>> availability({
    required String slug,
    required String from,
    required String to,
    int adults = 1,
  }) async {
    _assertPeriod(from: from, to: to);

    final json = await _api.get(
      '${propertyPath(slug)}/availability',
      query: {'from': from, 'to': to, 'adults': adults.clamp(1, 30)},
    );

    final rooms = <StayRoomOffer>[];
    for (final raw in JourneyApi.listOf(json)) {
      final room = StayRoomOffer.tryParse(raw);
      if (room != null) rooms.add(room);
    }
    return rooms;
  }

  /// Corps EXACT de `StoreHospitalityPublicReservationRequest`.
  static Map<String, dynamic> buildReservationPayload({
    required int roomTypeId,
    required String guestName,
    required String checkIn,
    required String checkOut,
    String? email,
    String? phone,
    int adults = 1,
    int children = 0,
    String? idempotencyKey,
  }) {
    if (roomTypeId < 1) {
      throw ArgumentError.value(
          roomTypeId, 'roomTypeId', 'Type de chambre requis');
    }

    if (guestName.trim().isEmpty || guestName.trim().length > 150) {
      throw ArgumentError.value(
          guestName, 'guestName', 'Nom 1..150 caractères');
    }

    _assertPeriod(from: checkIn, to: checkOut);

    // Le serveur accepte un contact vide, mais une réservation sans AUCUN
    // moyen de contact est impossible à confirmer par l'établissement : on
    // l'exige côté app, sans l'inventer.
    final hasEmail = email != null && email.trim().isNotEmpty;
    final hasPhone = phone != null && phone.trim().isNotEmpty;
    if (!hasEmail && !hasPhone) {
      throw ArgumentError('Au moins un e-mail ou un téléphone est requis');
    }

    return {
      'room_type_id': roomTypeId,
      'guest_name': guestName.trim(),
      if (hasEmail) 'contact_email': email.trim(),
      if (hasPhone) 'contact_phone': phone.trim(),
      'check_in': checkIn,
      'check_out': checkOut,
      'adults': adults.clamp(1, 30),
      'children': children.clamp(0, 30),
      if (idempotencyKey != null && idempotencyKey.trim().isNotEmpty)
        'idempotency_key': idempotencyKey.trim(),
    };
  }

  Future<JourneyConfirmation> reserve({
    required String slug,
    required int roomTypeId,
    required String guestName,
    required String checkIn,
    required String checkOut,
    String? email,
    String? phone,
    int adults = 1,
    int children = 0,
    String? idempotencyKey,
  }) async {
    final json = await _api.post(
      '${propertyPath(slug)}/reservations',
      buildReservationPayload(
        roomTypeId: roomTypeId,
        guestName: guestName,
        checkIn: checkIn,
        checkOut: checkOut,
        email: email,
        phone: phone,
        adults: adults,
        children: children,
        idempotencyKey: idempotencyKey,
      ),
    );

    final confirmation = JourneyConfirmation.tryParse(
      JourneyApi.dataOf(json),
    );
    if (confirmation == null) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Reservation without reference',
      );
    }
    return confirmation;
  }

  Future<JourneyStatusReport> track({
    required String reference,
    required String code,
  }) async {
    final json = await _api.get(
      reservationPath(reference),
      query: {'code': code},
    );

    final report = JourneyStatusReport.tryParse(
      JourneyApi.dataOf(json),
      fallbackReference: reference,
    );
    if (report == null) {
      throw const PublicApiFailure(
        PublicFailureKind.notFound,
        message: 'Reservation not found',
      );
    }
    return report;
  }

  static void _assertPeriod({required String from, required String to}) {
    if (!isIsoDate(from) || !isIsoDate(to)) {
      throw ArgumentError('Dates attendues au format AAAA-MM-JJ');
    }
    // Comparaison de chaînes valide sur ce format ISO (ordre lexicographique
    // = ordre chronologique) : pas de fuseau, pas de conversion.
    if (to.compareTo(from) <= 0) {
      throw ArgumentError('La date de départ doit suivre la date d’arrivée');
    }
  }
}
