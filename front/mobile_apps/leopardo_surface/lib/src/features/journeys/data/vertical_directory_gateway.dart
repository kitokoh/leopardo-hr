import '../../discovery/data/public_discovery_repository.dart';
import '../domain/vertical_directory_models.dart';
import 'journey_http.dart';

/// Annuaire d'une verticale grand public — #9948.
///
/// ## Un seul contrat pour cinq verticales
///
/// `restaurant`, `retail`, `hospitality`, `trade` et `vtc` sont tous servis par
/// `GET /public/discover?type=…`. Le web a cinq dossiers parce que chaque
/// vitrine a sa mise en page ; côté mobile la lecture est identique. Une
/// passerelle paramétrée par `type` évite cinq copies de la même logique — et
/// donc cinq occasions d'oublier la distinction panne / annuaire vide.
///
/// ## Ce que cette passerelle ne fait pas
///
/// Elle ne filtre pas `type` côté client en aveugle : elle le fait quand même,
/// parce qu'un agrégateur qui ignorerait le paramètre ferait apparaître une
/// boutique dans l'annuaire des restaurants. C'est une garde, pas une
/// duplication de la règle serveur.
class VerticalDirectoryGateway {
  VerticalDirectoryGateway(this._discovery);

  final PublicDiscoveryRepository _discovery;

  /// Annuaire d'une verticale.
  ///
  /// Une panne API rend `available: false` plutôt qu'une exception : l'écran
  /// doit distinguer « rien de publié » de « service indisponible ».
  Future<VerticalDirectory> directory({
    required String type,
    String? query,
    String? city,
    String? near,
    double? radiusKm,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final result = await _discovery.discover(
        query: query,
        city: city,
        type: type,
        near: near,
        radiusKm: radiusKm,
        page: page,
        perPage: perPage,
      );

      final entries = <VerticalEntry>[];
      for (final item in result.items) {
        if (item.type != type) continue;
        final entry = VerticalEntry.tryParse({
          'type': item.type,
          'slug': item.slug,
          'name': item.name,
          'category': item.category,
          'city': item.city,
          'country': item.country,
          'description': item.description,
          'image_url': item.imageUrl,
          'distance_km': item.distanceKm,
          'rating_avg': item.ratingAvg,
          'reviews_count': item.reviewsCount,
        });
        if (entry != null) entries.add(entry);
      }

      return VerticalDirectory(
        available: true,
        entries: entries,
        lastPage: result.lastPage,
      );
    } on PublicApiFailure {
      return VerticalDirectory.unavailable;
    }
  }

  /// Fiche d'une entrée, résolue par filtrage de l'annuaire.
  ///
  /// `null` = introuvable **ou** non publiée. Les deux cas sont indiscernables
  /// côté public, et c'est voulu : révéler qu'un profil existe mais n'est pas
  /// publié serait une fuite d'information commerciale.
  Future<VerticalEntry?> bySlug({
    required String type,
    required String slug,
  }) async {
    final needle = slug.trim();
    if (needle.isEmpty) return null;

    final result = await directory(type: type, query: needle, perPage: 50);
    if (!result.available) return null;

    for (final entry in result.entries) {
      if (entry.slug == needle) return entry;
    }
    return null;
  }
}

/// Parcours de la verticale **Mobilité** (vtc) — courses invitées.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// - `GET  /public/vtc/marketplace/operators` — annuaire des opérateurs opt-in ;
/// - `GET  /public/vtc/marketplace/operators/{slug}` — fiche ;
/// - `POST /public/vtc/marketplace/estimate` — devis (tenant résolu par slug) ;
/// - `POST /public/vtc/marketplace/rides` — course invitée, idempotente ;
/// - `GET  /public/vtc/marketplace/rides/{reference}?code=` — suivi.
///
/// Aucune de ces routes ne demande de compte : la course se demande puis se
/// suit par référence + code, exactement comme sur la vitrine web `/operateurs`.
///
/// ## Ce que la passerelle ne fait pas
///
/// Elle ne géocode pas. Le contrat exige `pickup.lat/lng` ET `dropoff.lat/lng` :
/// sans coordonnées, un devis serait géographiquement faux. L'écran demande
/// donc des coordonnées explicites (ou la position de l'appareil, avec
/// consentement) plutôt que d'inventer un point.
class VtcGateway {
  VtcGateway(this._api);

  final JourneyApi _api;

  static const String marketplace = '/public/vtc/marketplace';

  /// Corps EXACT de `POST …/estimate`.
  static Map<String, dynamic> buildEstimatePayload({
    required String operator,
    required GeoPoint pickup,
    required GeoPoint dropoff,
  }) {
    final slug = operator.trim();
    if (slug.isEmpty) {
      throw ArgumentError.value(operator, 'operator', 'Opérateur requis');
    }
    if (!pickup.isValid) {
      throw ArgumentError.value(pickup, 'pickup', 'Point de départ invalide');
    }
    if (!dropoff.isValid) {
      throw ArgumentError.value(dropoff, 'dropoff', 'Point d’arrivée invalide');
    }

    return {
      'operator': slug,
      'pickup': pickup.toPayload(),
      'dropoff': dropoff.toPayload(),
    };
  }

  /// Corps EXACT de `POST …/rides`.
  ///
  /// Les adresses vides sont OMISES : envoyer `""` ferait échouer une
  /// validation `string|min:1` alors que le champ est facultatif.
  static Map<String, dynamic> buildRidePayload({
    required String operator,
    required String idempotencyKey,
    required GeoPoint pickup,
    required GeoPoint dropoff,
    required String passengerName,
    required String passengerPhone,
    String? pickupAddress,
    String? dropoffAddress,
  }) {
    final payload = buildEstimatePayload(
      operator: operator,
      pickup: pickup,
      dropoff: dropoff,
    );

    final key = idempotencyKey.trim();
    if (key.isEmpty || key.length > 255) {
      throw ArgumentError.value(
        idempotencyKey,
        'idempotencyKey',
        'Clé 1..255 caractères',
      );
    }

    final name = passengerName.trim();
    if (name.isEmpty) {
      throw ArgumentError.value(passengerName, 'passengerName', 'Nom requis');
    }

    final phone = passengerPhone.trim();
    if (phone.isEmpty) {
      throw ArgumentError.value(
        passengerPhone,
        'passengerPhone',
        'Téléphone requis',
      );
    }

    final from = pickupAddress?.trim() ?? '';
    final to = dropoffAddress?.trim() ?? '';

    return {
      ...payload,
      'idempotency_key': key,
      if (from.isNotEmpty) 'pickup_address': from,
      if (to.isNotEmpty) 'dropoff_address': to,
      'passenger_name': name,
      'passenger_phone': phone,
    };
  }

  /// Devis public d'une course.
  ///
  /// Lève sur échec : un devis silencieusement nul ferait afficher « prix à
  /// confirmer » alors que l'opérateur a peut-être refusé la demande.
  Future<RideEstimate> estimate({
    required String operator,
    required GeoPoint pickup,
    required GeoPoint dropoff,
  }) async {
    final json = await _api.post(
      '$marketplace/estimate',
      buildEstimatePayload(
        operator: operator,
        pickup: pickup,
        dropoff: dropoff,
      ),
    );

    final estimate = RideEstimate.tryParse(JourneyApi.dataOf(json)) ??
        RideEstimate.tryParse(json);
    if (estimate == null) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Estimate without payload',
      );
    }
    return estimate;
  }

  /// Crée une course invitée.
  ///
  /// L'idempotence est portée par la clé générée côté client et **conservée
  /// jusqu'au succès** : un double tap ou un retry réseau renvoie la même
  /// course (200, sans re-présenter le code), jamais un doublon.
  Future<GuestRide> createRide({
    required String operator,
    required String idempotencyKey,
    required GeoPoint pickup,
    required GeoPoint dropoff,
    required String passengerName,
    required String passengerPhone,
    String? pickupAddress,
    String? dropoffAddress,
  }) async {
    final json = await _api.post(
      '$marketplace/rides',
      buildRidePayload(
        operator: operator,
        idempotencyKey: idempotencyKey,
        pickup: pickup,
        dropoff: dropoff,
        passengerName: passengerName,
        passengerPhone: passengerPhone,
        pickupAddress: pickupAddress,
        dropoffAddress: dropoffAddress,
      ),
    );

    final ride =
        GuestRide.tryParse(JourneyApi.dataOf(json)) ?? GuestRide.tryParse(json);
    if (ride == null) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Ride without reference',
      );
    }
    return ride;
  }

  /// Suit une course par référence + code.
  ///
  /// Le serveur répond un 404 uniforme pour une référence inconnue **et** pour
  /// un code faux (anti-énumération) : l'écran affiche « introuvable », jamais
  /// « panne ».
  Future<GuestRide> track({
    required String reference,
    required String code,
  }) async {
    final json = await _api.get(
      '$marketplace/rides/$reference',
      query: {'code': code},
    );

    final ride =
        GuestRide.tryParse(JourneyApi.dataOf(json)) ?? GuestRide.tryParse(json);
    if (ride == null) {
      throw const PublicApiFailure(
        PublicFailureKind.notFound,
        message: 'Ride not found',
      );
    }
    return ride;
  }
}
