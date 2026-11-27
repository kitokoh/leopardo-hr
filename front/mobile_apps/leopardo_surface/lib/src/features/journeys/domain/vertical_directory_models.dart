/// Modèles des annuaires de verticales grand public — #9948.
///
/// ## Pourquoi un modèle commun à cinq verticales
///
/// `restaurant`, `retail`, `hospitality`, `trade` et `vtc` partagent le MÊME
/// contrat de lecture : `GET /public/discover?type=…`. Le web a cinq dossiers
/// parce que chaque vitrine a sa mise en page ; côté mobile, la liste, la
/// recherche et la fiche sont identiques, seuls le libellé et l'action
/// changent. Cinq copies de la même logique garantiraient que l'une d'elles
/// oublie un cas (panne vs annuaire vide) que les quatre autres gèrent.
///
/// ## Ce que ces modèles refusent d'inventer
///
/// - Un item sans `slug` n'est pas ouvrable : il est écarté au parsing.
/// - `distanceKm` reste `null` quand la recherche n'a pas été faite avec une
///   position : afficher « 0 km » ferait croire à une proximité mesurée.
/// - `ratingAvg` reste `null` sans avis : une note par défaut inventerait une
///   réputation.
library;

/// Une entrée d'annuaire, commune aux cinq verticales.
class VerticalEntry {
  const VerticalEntry({
    required this.type,
    required this.slug,
    required this.name,
    this.category,
    this.city,
    this.country,
    this.description,
    this.imageUrl,
    this.distanceKm,
    this.ratingAvg,
    this.reviewsCount,
  });

  /// Type de découverte (`restaurant`, `retail_shop`, …).
  final String type;

  /// Identifiant public, seule clé d'ouverture d'une fiche.
  final String slug;

  final String name;
  final String? category;
  final String? city;
  final String? country;
  final String? description;
  final String? imageUrl;

  /// Renseigné uniquement quand la recherche a été faite avec `near`.
  final double? distanceKm;

  final double? ratingAvg;
  final int? reviewsCount;

  bool get hasRating => ratingAvg != null && (reviewsCount ?? 0) > 0;

  /// Localisation lisible, ou `null` si rien n'est publié.
  String? get location {
    final parts = [city, country].whereType<String>().toList();
    if (parts.isEmpty) return null;
    return parts.join(', ');
  }

  static VerticalEntry? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final slug = _str(raw['slug']);
    if (slug == null) return null;

    return VerticalEntry(
      type: _str(raw['type']) ?? 'unknown',
      slug: slug,
      name: _str(raw['name']) ?? slug,
      category: _str(raw['category']),
      city: _str(raw['city']),
      country: _str(raw['country']),
      description: _str(raw['description']),
      imageUrl: _str(raw['image_url']),
      distanceKm: _dbl(raw['distance_km']),
      ratingAvg: _dbl(raw['rating_avg']),
      reviewsCount: _int(raw['reviews_count']),
    );
  }
}

/// Résultat d'un chargement d'annuaire.
///
/// ## Pourquoi `available` existe
///
/// « Aucun établissement publié » et « l'API n'a pas répondu » sont deux états
/// différents, et l'utilisateur mérite le bon message. Les confondre ferait
/// croire à une panne là où il n'y a simplement pas encore d'opt-in — ou
/// l'inverse, ce qui est pire : on annoncerait « rien à proximité » sur une
/// panne réseau.
class VerticalDirectory {
  const VerticalDirectory({
    required this.available,
    required this.entries,
    this.lastPage = 1,
  });

  /// `false` = panne API. `true` + liste vide = aucun établissement publié.
  final bool available;
  final List<VerticalEntry> entries;
  final int lastPage;

  bool get isEmpty => available && entries.isEmpty;

  static const VerticalDirectory unavailable = VerticalDirectory(
    available: false,
    entries: [],
  );
}

/// Point géographique — contrat `POST /public/vtc/marketplace/estimate|rides`.
class GeoPoint {
  const GeoPoint({required this.lat, required this.lng});

  final double lat;
  final double lng;

  /// Vrai si les coordonnées sont dans les bornes terrestres.
  ///
  /// Le serveur valide `lat` entre -90 et 90 et `lng` entre -180 et 180 : un
  /// point hors bornes serait refusé en 422, autant le refuser ici avec un
  /// message immédiat.
  bool get isValid =>
      lat.isFinite &&
      lng.isFinite &&
      lat >= -90 &&
      lat <= 90 &&
      lng >= -180 &&
      lng <= 180;

  Map<String, dynamic> toPayload() => {'lat': lat, 'lng': lng};

  /// Parse une saisie « lat,lng » ; `null` si le format est inattendu.
  ///
  /// La vitrine web accepte exactement cette forme
  /// (`parsePoint` dans `RideRequestSurface.tsx`) : la parité de saisie évite
  /// qu'un utilisateur habitué au web ne comprenne pas l'app.
  static GeoPoint? tryParseText(String value) {
    final parts = value.split(',');
    if (parts.length != 2) return null;

    final lat = double.tryParse(parts[0].trim());
    final lng = double.tryParse(parts[1].trim());
    if (lat == null || lng == null) return null;

    final point = GeoPoint(lat: lat, lng: lng);
    return point.isValid ? point : null;
  }
}

/// Devis public d'une course VTC (`POST …/estimate`).
class RideEstimate {
  const RideEstimate({
    this.distanceM,
    this.durationS,
    this.priceMinor,
    this.currency,
  });

  final int? distanceM;
  final int? durationS;
  final int? priceMinor;
  final String? currency;

  /// Un devis sans prix n'est pas un devis : l'écran ne doit pas afficher
  /// « 0 » ni « gratuit », mais dire que le prix sera confirmé.
  bool get isPriced => priceMinor != null;

  static RideEstimate? tryParse(Object? raw) {
    if (raw is! Map) return null;
    return RideEstimate(
      distanceM: _int(raw['distance_m']),
      durationS: _int(raw['duration_s']),
      priceMinor: _int(raw['price_minor']),
      currency: _str(raw['currency']),
    );
  }
}

/// Course invitée créée (`POST …/rides`).
///
/// ## Le code de suivi n'est montré qu'une fois
///
/// L'API ne le renvoie qu'à la création (201). Au rejeu idempotent (200), il
/// est absent : c'est voulu, et l'écran le dit plutôt que d'afficher un champ
/// vide. `trackingCode` est donc nullable, jamais une chaîne vide.
class GuestRide {
  const GuestRide({
    required this.reference,
    required this.status,
    this.pickupAddress,
    this.dropoffAddress,
    this.estimatedPriceMinor,
    this.currency,
    this.trackingCode,
  });

  final String reference;
  final String status;
  final String? pickupAddress;
  final String? dropoffAddress;
  final int? estimatedPriceMinor;
  final String? currency;
  final String? trackingCode;

  static GuestRide? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final reference = _str(raw['reference']);
    if (reference == null) return null;

    return GuestRide(
      reference: reference,
      status: _str(raw['status']) ?? 'requested',
      pickupAddress: _str(raw['pickup_address']),
      dropoffAddress: _str(raw['dropoff_address']),
      estimatedPriceMinor: _int(raw['estimated_price_minor']),
      currency: _str(raw['currency']),
      trackingCode: _str(raw['tracking_code']),
    );
  }
}

String? _str(Object? value) {
  if (value is String && value.trim().isNotEmpty) return value.trim();
  if (value is num) return value.toString();
  return null;
}

int? _int(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}

double? _dbl(Object? value) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value);
  return null;
}
