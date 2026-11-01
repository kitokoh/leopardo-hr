/// Modèle d'un résultat de découverte publique.
///
/// ## Pourquoi ce modèle est « tolérant » plutôt que strict
///
/// La découverte agrège PLUSIEURS verticales (`restaurant`, `hospitality_property`,
/// `showcase`, `retail`, `trade`) via des providers indépendants. Le contrat
/// `PublicDiscoverableProvider` impose un socle (`type`, `slug`, `name`,
/// `url`) mais laisse les champs annexes optionnels — un showcase n'a pas de
/// latitude, un hôtel n'a pas de menu.
///
/// Un parsing strict (`as String`) ferait planter l'écran entier parce qu'une
/// verticale n'a pas renseigné `country`. On lit donc chaque champ
/// défensivement, et on ne rejette JAMAIS un item pour un champ accessoire :
/// un item sans `slug` est le seul cas réellement inutilisable (il ne peut pas
/// être ouvert).
///
/// ## `null` vs valeur par défaut
///
/// Les champs `city`, `description`, `imageUrl` restent nullables : l'UI doit
/// pouvoir distinguer « pas de photo » (afficher un motif) de « photo noire ».
class DiscoveryItem {
  const DiscoveryItem({
    required this.type,
    required this.slug,
    required this.name,
    this.category,
    this.city,
    this.country,
    this.description,
    this.imageUrl,
    this.latitude,
    this.longitude,
    this.distanceKm,
    this.url,
    this.ratingAvg,
    this.reviewsCount,
  });

  final String type;
  final String slug;
  final String name;
  final String? category;
  final String? city;
  final String? country;
  final String? description;
  final String? imageUrl;
  final double? latitude;
  final double? longitude;

  /// Renseigné uniquement quand la recherche a été faite avec `near`.
  final double? distanceKm;

  /// Destination navigateur fournie par le serveur (ex. `/restaurants/{slug}`).
  final String? url;

  final double? ratingAvg;
  final int? reviewsCount;

  /// Parse un item ; retourne null si l'item est inutilisable (pas de slug).
  static DiscoveryItem? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final slug = _str(raw['slug']);
    if (slug == null || slug.isEmpty) return null;

    return DiscoveryItem(
      type: _str(raw['type']) ?? 'unknown',
      slug: slug,
      name: _str(raw['name']) ?? slug,
      category: _str(raw['category']),
      city: _str(raw['city']),
      country: _str(raw['country']),
      description: _str(raw['description']),
      imageUrl: _str(raw['image_url']),
      latitude: _dbl(raw['latitude']),
      longitude: _dbl(raw['longitude']),
      distanceKm: _dbl(raw['distance_km']),
      url: _str(raw['url']),
      ratingAvg: _dbl(raw['rating_avg']),
      reviewsCount: _int(raw['reviews_count']),
    );
  }

  /// Identité stable d'un item : `type|slug` (le `slug` seul n'est unique que
  /// PAR verticale — deux verticales peuvent publier le même slug).
  String get key => '$type|$slug';

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
}

/// Réponse paginée de `GET /api/v1/public/discover`.
class DiscoveryPage {
  const DiscoveryPage({
    required this.items,
    required this.total,
    required this.page,
    required this.lastPage,
    required this.actions,
    required this.actionUrls,
  });

  final List<DiscoveryItem> items;
  final int total;
  final int page;
  final int lastPage;

  /// Capacités RÉELLES par type, telles que le serveur les déclare
  /// (`meta.actions`). C'est la source unique des boutons : le client ne
  /// décide jamais qu'un commerce « devrait » pouvoir réserver.
  final Map<String, List<String>> actions;

  /// Destinations navigateur par type et action (`meta.action_urls`).
  /// Une action absente ici n'est pas atteignable depuis l'app.
  final Map<String, Map<String, String?>> actionUrls;

  bool get hasMore => page < lastPage;

  static DiscoveryPage fromJson(Map<String, dynamic> json) {
    final data = json['data'];
    final meta = json['meta'];

    final items = <DiscoveryItem>[];
    if (data is List) {
      for (final raw in data) {
        final item = DiscoveryItem.tryParse(raw);
        if (item != null) items.add(item);
      }
    }

    final metaMap = meta is Map ? meta : const <String, dynamic>{};

    return DiscoveryPage(
      items: items,
      total: _int(metaMap['total']) ?? items.length,
      page: _int(metaMap['page']) ?? 1,
      lastPage: _int(metaMap['last_page']) ?? 1,
      actions: _actions(metaMap['actions']),
      actionUrls: _actionUrls(metaMap['action_urls']),
    );
  }

  /// Actions qu'un type donné sait réellement servir.
  List<String> actionsFor(String type) => actions[type] ?? const <String>[];

  /// Destination d'une action, ou null si elle n'est pas atteignable.
  String? actionUrlFor(String type, String action) => actionUrls[type]?[action];

  static Map<String, List<String>> _actions(Object? raw) {
    if (raw is! Map) return const {};
    final out = <String, List<String>>{};
    raw.forEach((key, value) {
      if (key is! String || value is! List) return;
      out[key] = value.whereType<String>().toList(growable: false);
    });
    return out;
  }

  static Map<String, Map<String, String?>> _actionUrls(Object? raw) {
    if (raw is! Map) return const {};
    final out = <String, Map<String, String?>>{};
    raw.forEach((typeKey, typeValue) {
      if (typeKey is! String || typeValue is! Map) return;
      final inner = <String, String?>{};
      typeValue.forEach((actionKey, urlValue) {
        if (actionKey is! String) return;
        inner[actionKey] = urlValue is String && urlValue.isNotEmpty ? urlValue : null;
      });
      out[typeKey] = inner;
    });
    return out;
  }

  static int? _int(Object? v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    if (v is String) return int.tryParse(v);
    return null;
  }
}
