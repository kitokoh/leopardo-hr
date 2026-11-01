/// Modèle de la fiche publique d'un établissement.
///
/// ## Deux formats, un seul modèle
///
/// Selon la verticale, la fiche vient de `GET /public/restaurants/{slug}`
/// (`restaurant`) ou des données déjà chargées par la découverte
/// (`showcase`, `hospitality_property`). Ce modèle accepte donc les DEUX
/// formes et normalise :
///
///  - `establishment_type` (restaurant) ou `category` (découverte) ;
///  - `cover_image_url` ou `image_url` ;
///  - `menu` présent seulement pour la restauration.
///
/// Refuser l'une des deux formes obligerait à deux écrans quasi identiques —
/// exactement la duplication que la règle n°1 interdit.
class BusinessProfile {
  const BusinessProfile({
    required this.slug,
    required this.name,
    required this.type,
    this.category,
    this.city,
    this.address,
    this.description,
    this.imageUrl,
    this.latitude,
    this.longitude,
    this.phone,
    this.ratingAvg,
    this.reviewsCount,
    this.hours = const [],
    this.menu = const [],
    this.currency,
    this.verified = false,
    this.supportedActions = const [],
    this.actionUrls = const {},
  });

  final String slug;
  final String name;
  final String type;
  final String? category;
  final String? city;
  final String? address;
  final String? description;
  final String? imageUrl;
  final double? latitude;
  final double? longitude;

  /// Téléphone du COMMERCE (jamais celui du client).
  final String? phone;

  final double? ratingAvg;
  final int? reviewsCount;
  final List<BusinessHour> hours;
  final List<BusinessMenuSection> menu;
  final String? currency;

  /// Badge « vérifié » : la fiche ne l'invente pas, il vient du serveur.
  final bool verified;

  /// Capacités réelles, venant de `supportedActions()` du provider (via
  /// `meta.actions`). C'est la SEULE source des boutons Contacter / Commander /
  /// Réserver : un bouton sans capacité n'existe pas.
  final List<String> supportedActions;

  /// Destinations navigateur par action ; une action absente ici n'est pas
  /// atteignable depuis l'app → l'écran affiche « indisponible » explicitement.
  final Map<String, String?> actionUrls;

  bool supports(String action) => supportedActions.contains(action);

  /// Vrai si l'établissement est ouvert à l'instant [now] selon ses horaires.
  ///
  /// Retourne `null` quand les horaires sont inconnus — l'écran affiche alors
  /// l'état sans mentir, plutôt qu'un « Fermé » faux.
  bool? isOpenAt(DateTime now) {
    if (hours.isEmpty) return null;

    final today = hours.where((h) => h.dayOfWeek == now.weekday % 7);
    if (today.isEmpty) return null;

    final minutes = now.hour * 60 + now.minute;

    for (final hour in today) {
      final open = hour.opensMinutes;
      final close = hour.closesMinutes;
      if (open == null || close == null) continue;

      // Plage normale (09:00–18:00)
      if (close >= open && minutes >= open && minutes < close) return true;
      // Plage qui passe minuit (20:00–02:00) : la partie « après minuit » est
      // portée par le jour suivant chez la plupart des commerces, mais un
      // établissement qui déclare 20:00–02:00 le même jour doit rester ouvert
      // jusque 02:00 — sans cette branche, il paraîtrait fermé dès 00:00.
      if (close < open && (minutes >= open || minutes < close)) return true;
    }

    return false;
  }

  static BusinessProfile? tryParse(String slug, Object? raw) {
    final map = _asMap(raw);
    if (map == null) return null;

    final actions = map['supported_actions'];
    final actionUrls = map['action_urls'];

    return BusinessProfile(
      slug: _str(map['slug']) ?? slug,
      name: _str(map['name']) ?? slug,
      type: _str(map['type']) ?? 'restaurant',
      category: _str(map['category']) ?? _str(map['establishment_type']),
      city: _str(map['city']),
      address: _str(map['address']),
      description: _str(map['description']) ?? _str(map['public_description']),
      imageUrl: _str(map['cover_image_url']) ?? _str(map['image_url']),
      latitude: _dbl(map['latitude']),
      longitude: _dbl(map['longitude']),
      phone: _str(map['phone']),
      ratingAvg: _dbl(map['rating_avg']),
      reviewsCount: _int(map['reviews_count']),
      hours: _hours(map['hours']),
      menu: _menu(map['menu']),
      currency: _str(map['currency']),
      verified: map['verified'] == true || map['is_verified'] == true,
      supportedActions:
          actions is List ? actions.whereType<String>().toList(growable: false) : const [],
      actionUrls: _actionUrls(actionUrls),
    );
  }

  /// Construit une fiche depuis un item de découverte (pas d'appel réseau
  /// supplémentaire) : la liste transmet ce qu'elle sait déjà.
  factory BusinessProfile.fromDiscoveryItem(
    Object? rawItem, {
    List<String> supportedActions = const [],
    Map<String, String?> actionUrls = const {},
  }) {
    final map = _asMap(rawItem) ?? const <String, dynamic>{};
    return BusinessProfile(
      slug: _str(map['slug']) ?? '',
      name: _str(map['name']) ?? '',
      type: _str(map['type']) ?? 'unknown',
      category: _str(map['category']),
      city: _str(map['city']),
      description: _str(map['description']),
      imageUrl: _str(map['image_url']),
      latitude: _dbl(map['latitude']),
      longitude: _dbl(map['longitude']),
      ratingAvg: _dbl(map['rating_avg']),
      reviewsCount: _int(map['reviews_count']),
      supportedActions: supportedActions,
      actionUrls: actionUrls,
    );
  }

  static Map<String, dynamic>? _asMap(Object? raw) {
    if (raw is Map<String, dynamic>) return raw;
    if (raw is Map) return Map<String, dynamic>.from(raw);
    return null;
  }

  static String? _str(Object? v) => v is String && v.trim().isNotEmpty ? v.trim() : null;

  static double? _dbl(Object? v) {
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v);
    return null;
  }

  static int? _int(Object? v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    if (v is String) return int.tryParse(v);
    return null;
  }

  static List<BusinessHour> _hours(Object? raw) {
    if (raw is! List) return const [];
    final out = <BusinessHour>[];
    for (final entry in raw) {
      final hour = BusinessHour.tryParse(entry);
      if (hour != null) out.add(hour);
    }
    return out;
  }

  static List<BusinessMenuSection> _menu(Object? raw) {
    if (raw is! List) return const [];
    final out = <BusinessMenuSection>[];
    for (final entry in raw) {
      final section = BusinessMenuSection.tryParse(entry);
      if (section != null) out.add(section);
    }
    return out;
  }

  static Map<String, String?> _actionUrls(Object? raw) {
    if (raw is! Map) return const {};
    final out = <String, String?>{};
    raw.forEach((k, v) {
      if (k is! String) return;
      out[k] = v is String && v.isNotEmpty ? v : null;
    });
    return out;
  }
}

/// Un créneau d'ouverture. `day_of_week` suit la convention serveur (0 = dimanche).
class BusinessHour {
  const BusinessHour({
    required this.dayOfWeek,
    this.opensAt,
    this.closesAt,
  });

  final int dayOfWeek;
  final String? opensAt;
  final String? closesAt;

  int? get opensMinutes => _minutes(opensAt);
  int? get closesMinutes => _minutes(closesAt);

  /// `"09:30:00"` ou `"09:30"` → 570. Null si non parsable.
  static int? _minutes(String? raw) {
    if (raw == null) return null;
    final parts = raw.split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null) return null;
    if (h < 0 || h > 24 || m < 0 || m > 59) return null;
    return h * 60 + m;
  }

  static BusinessHour? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final day = raw['day_of_week'];
    final dayInt = day is num ? day.toInt() : int.tryParse('$day');
    if (dayInt == null) return null;
    return BusinessHour(
      dayOfWeek: dayInt,
      opensAt: raw['opens_at'] is String ? raw['opens_at'] as String : null,
      closesAt: raw['closes_at'] is String ? raw['closes_at'] as String : null,
    );
  }
}

/// Une section de menu (entrées, plats, boissons).
class BusinessMenuSection {
  const BusinessMenuSection({required this.name, this.items = const []});

  final String name;
  final List<BusinessMenuItem> items;

  static BusinessMenuSection? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final name = raw['name'] is String ? raw['name'] as String : null;
    if (name == null || name.isEmpty) return null;

    final items = <BusinessMenuItem>[];
    final rawItems = raw['items'] ?? raw['products'];
    if (rawItems is List) {
      for (final entry in rawItems) {
        final item = BusinessMenuItem.tryParse(entry);
        if (item != null) items.add(item);
      }
    }

    return BusinessMenuSection(name: name, items: items);
  }
}

class BusinessMenuItem {
  const BusinessMenuItem({
    required this.name,
    this.description,
    this.price,
    this.imageUrl,
  });

  final String name;
  final String? description;
  final double? price;
  final String? imageUrl;

  static BusinessMenuItem? tryParse(Object? raw) {
    if (raw is! Map) return null;
    final name = raw['name'] is String ? raw['name'] as String : null;
    if (name == null || name.isEmpty) return null;

    final price = raw['price'] ?? raw['unit_price'];

    return BusinessMenuItem(
      name: name,
      description: raw['description'] is String ? raw['description'] as String : null,
      price: price is num
          ? price.toDouble()
          : (price is String ? double.tryParse(price) : null),
      imageUrl: raw['image_url'] is String ? raw['image_url'] as String : null,
    );
  }
}
