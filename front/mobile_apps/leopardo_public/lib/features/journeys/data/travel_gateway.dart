import '../../discovery/data/public_discovery_repository.dart';
import '../domain/journey_models.dart';
import '../domain/travel_models.dart';
import 'journey_http.dart';

/// Parcours de la verticale **Billets** (travel) — marketplace inter-agences.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// - `GET  /public/travel/marketplace/cities` — villes desservies ;
/// - `GET  /public/travel/marketplace/trips?origin_city_id&destination_city_id&date&per_page`
/// - `GET  /public/travel/marketplace/trips/{trip}` — détail + plan de sièges ;
/// - `POST /public/travel/marketplace/bookings` — réservation invitée,
///   idempotente sur `idempotency_key` ;
/// - `GET  /public/travel/marketplace/bookings/{reference}?code=` — suivi ;
/// - `POST /public/travel/marketplace/bookings/{reference}/cancel` — annulation.
///
/// Aucune de ces routes ne demande de compte ni de jeton d'agence : le tenant
/// est résolu **par trajet** (détail, réservation) ou **par référence**
/// (paiement, suivi) côté serveur. C'est ce qui rend le parcours complet
/// possible dans l'app, sans compte voyageur.
///
/// ## Ce que la passerelle ne fait pas
///
/// Elle ne calcule ni prix ni disponibilité : le serveur applique stock,
/// classes et promotions. Le total affiché avant envoi est une estimation
/// locale, et le montant qui fait foi est celui rendu à la création.
class TravelGateway {
  TravelGateway(this._api);

  final JourneyApi _api;

  static const String marketplace = '/public/travel/marketplace';

  String tripPath(int tripId) => '$marketplace/trips/$tripId';

  String bookingPath(String reference) => '$marketplace/bookings/$reference';

  /// Villes desservies par les agences opt-in (autocomplétion).
  Future<List<TravelCity>> cities() async {
    final json = await _api.get('$marketplace/cities');

    final cities = <TravelCity>[];
    for (final raw in JourneyApi.listOf(json)) {
      final city = TravelCity.tryParse(raw);
      if (city != null) cities.add(city);
    }
    return cities;
  }

  /// Recherche agrégée cross-tenant des trajets publiés.
  ///
  /// Les filtres sont des **identifiants de ville** (`origin_city_id`), pas des
  /// libellés : envoyer un nom ferait échouer la validation `integer` du
  /// serveur. L'écran résout donc la ville choisie en identifiant avant
  /// d'appeler cette méthode.
  Future<List<TravelTrip>> searchTrips({
    int? originCityId,
    int? destinationCityId,
    String? date,
    int perPage = 20,
  }) async {
    if (date != null && !isIsoDate(date)) {
      throw ArgumentError.value(date, 'date', 'Date attendue AAAA-MM-JJ');
    }

    final json = await _api.get(
      '$marketplace/trips',
      query: {
        if (originCityId != null) 'origin_city_id': originCityId,
        if (destinationCityId != null) 'destination_city_id': destinationCityId,
        if (date != null) 'date': date,
        'per_page': perPage.clamp(1, 50),
      },
    );

    final trips = <TravelTrip>[];
    for (final raw in JourneyApi.listOf(json)) {
      final trip = TravelTrip.tryParse(raw);
      if (trip != null) trips.add(trip);
    }
    return trips;
  }

  /// Détail d'un trajet, plan de sièges inclus.
  Future<TravelTrip> trip(int tripId) async {
    final json = await _api.get(tripPath(tripId));

    final trip = TravelTrip.tryParse(JourneyApi.dataOf(json));
    if (trip == null) {
      throw const PublicApiFailure(
        PublicFailureKind.notFound,
        message: 'Trip not found',
      );
    }
    return trip;
  }

  /// Corps EXACT de `StoreTravelMarketplaceBookingRequest`.
  ///
  /// Les contrôles ici ne remplacent pas la validation serveur : ils évitent
  /// d'envoyer une requête qu'on sait déjà invalide (message immédiat) et
  /// rendent le contrat testable sans réseau.
  static Map<String, dynamic> buildBookingPayload({
    required int tripId,
    required List<TravelPassengerDraft> passengers,
    required String idempotencyKey,
    String? contactEmail,
    String? contactPhone,
    bool notifyConsent = false,
  }) {
    if (tripId < 1) {
      throw ArgumentError.value(tripId, 'tripId', 'Trajet requis');
    }

    if (passengers.isEmpty) {
      throw ArgumentError.value(
        passengers,
        'passengers',
        'Au moins un passager',
      );
    }

    if (passengers.length > travelMaxPassengers) {
      throw ArgumentError.value(
        passengers.length,
        'passengers',
        'Maximum $travelMaxPassengers passagers',
      );
    }

    // Un billet sans nom n'est pas un billet.
    for (final passenger in passengers) {
      if (!passenger.isComplete) {
        throw ArgumentError.value(
          passenger.fullName,
          'passengers.full_name',
          'Nom du passager requis',
        );
      }
      if (passenger.classId < 1) {
        throw ArgumentError.value(
          passenger.classId,
          'passengers.class_id',
          'Classe requise',
        );
      }
    }

    final email = contactEmail?.trim() ?? '';
    final phone = contactPhone?.trim() ?? '';

    // Sans contact, l'agence ne peut pas prévenir d'un changement d'horaire.
    if (email.isEmpty && phone.isEmpty) {
      throw ArgumentError('Au moins un e-mail ou un téléphone est requis');
    }

    if (email.isNotEmpty && !email.contains('@')) {
      throw ArgumentError.value(email, 'contactEmail', 'E-mail invalide');
    }

    if (idempotencyKey.trim().isEmpty || idempotencyKey.length > 255) {
      throw ArgumentError.value(
        idempotencyKey,
        'idempotencyKey',
        'Clé 1..255 caractères',
      );
    }

    // Les coordonnées vides sont OMISES : envoyer `""` ferait échouer la
    // validation `email` de Laravel alors que le champ est facultatif.
    return {
      'trip_id': tripId,
      'idempotency_key': idempotencyKey.trim(),
      if (email.isNotEmpty) 'contact_email': email,
      if (phone.isNotEmpty) 'contact_phone': phone,
      'notify_consent': notifyConsent,
      'passengers': [for (final passenger in passengers) passenger.toPayload()],
    };
  }

  Future<TravelBookingConfirmation> book({
    required int tripId,
    required List<TravelPassengerDraft> passengers,
    required String idempotencyKey,
    String? contactEmail,
    String? contactPhone,
    bool notifyConsent = false,
  }) async {
    final json = await _api.post(
      '$marketplace/bookings',
      buildBookingPayload(
        tripId: tripId,
        passengers: passengers,
        idempotencyKey: idempotencyKey,
        contactEmail: contactEmail,
        contactPhone: contactPhone,
        notifyConsent: notifyConsent,
      ),
    );

    // La ressource est rendue à la racine (`data` + `agency`), pas sous `data`.
    final confirmation = TravelBookingConfirmation.tryParse(json) ??
        TravelBookingConfirmation.tryParse(JourneyApi.dataOf(json));

    if (confirmation == null) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Booking without reference',
      );
    }
    return confirmation;
  }

  /// Suivi par référence + code de validation.
  ///
  /// Le serveur répond un 404 uniforme pour une référence inconnue **et** pour
  /// un code faux (anti-énumération) : l'écran affiche « introuvable », jamais
  /// « panne ».
  Future<TravelBookingStatus> track({
    required String reference,
    required String code,
  }) async {
    final json = await _api.get(bookingPath(reference), query: {'code': code});

    final status = TravelBookingStatus.tryParse(
      JourneyApi.dataOf(json),
      fallbackReference: reference,
    );
    if (status == null) {
      throw const PublicApiFailure(
        PublicFailureKind.notFound,
        message: 'Booking not found',
      );
    }
    return status;
  }

  /// Annulation. Lève sur échec : une annulation refusée (délai dépassé, déjà
  /// annulée) doit être dite à l'utilisateur, pas avalée.
  Future<TravelBookingStatus> cancel({
    required String reference,
    required String code,
    required String reason,
  }) async {
    final json = await _api.post('${bookingPath(reference)}/cancel', {
      'code': code,
      'reason': reason,
    });

    final status = TravelBookingStatus.tryParse(
      JourneyApi.dataOf(json),
      fallbackReference: reference,
    );
    if (status == null) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Cancellation without status',
      );
    }
    return status;
  }
}
