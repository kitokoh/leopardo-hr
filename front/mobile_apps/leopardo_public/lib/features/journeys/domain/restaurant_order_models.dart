/// Modèles de la commande publique **restaurant par slug** — #9959.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// - `GET  /public/restaurants/{slug}` — profil public, `menu` inclus
///   (catégories + produits publiés) ;
/// - `POST /public/restaurants/{slug}/orders` — création, idempotente ;
/// - `GET  /public/restaurants/{slug}/orders/{ref}?secret=` — suivi ;
/// - `POST /public/restaurants/{slug}/orders/{ref}/pay` — paiement.
///
/// Aucune de ces routes ne demande de jeton : c'est la surface RESTO-902
/// (#7747), celle que consomme la vitrine web `/restaurants/{slug}`. Le
/// blocage `order` du registre mobile décrivait RESTO-805 (#6226), la boutique
/// à jeton `X-Restaurant-Shop-Token` — un autre contrat, périmé pour l'usage
/// grand public.
///
/// ## Ce que ces modèles refusent d'inventer
///
/// - Un produit sans `code` n'est pas commandable : le serveur désigne les
///   lignes par `product_code`, jamais par un identifiant interne. Il est
///   écarté au parsing, pas affiché puis refusé.
/// - Un prix absent reste `null` : afficher « 0 » ferait croire à la gratuité.
/// - Le `tracking_secret` n'est rendu qu'à la **création réelle** (201) : au
///   rejeu idempotent (200) il est absent, et c'est voulu. Il est donc
///   nullable, jamais une chaîne vide.
library;

/// Un produit publié du menu public.
class RestaurantMenuProduct {
  const RestaurantMenuProduct({
    required this.code,
    required this.name,
    this.description,
    this.priceMinor,
    this.currency,
  });

  /// Code métier — la seule clé acceptée par `POST …/orders`.
  final String code;
  final String name;
  final String? description;

  /// Prix unitaire, ou `null` si le serveur ne l'a pas publié.
  final int? priceMinor;
  final String? currency;

  static RestaurantMenuProduct? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final code = _str(raw['code']);
    if (code == null) return null;

    return RestaurantMenuProduct(
      code: code,
      name: _str(raw['name']) ?? code,
      description: _str(raw['description']),
      priceMinor: _int(raw['price_minor']),
      currency: _str(raw['currency']),
    );
  }
}

/// Une catégorie du menu public, avec ses produits.
class RestaurantMenuCategory {
  const RestaurantMenuCategory({required this.name, this.products = const []});

  final String name;
  final List<RestaurantMenuProduct> products;

  bool get isEmpty => products.isEmpty;

  static RestaurantMenuCategory? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final name = _str(raw['name']);
    if (name == null) return null;

    final products = <RestaurantMenuProduct>[];
    final rawProducts = raw['products'];
    if (rawProducts is List) {
      for (final entry in rawProducts) {
        final product = RestaurantMenuProduct.tryParse(entry);
        if (product != null) products.add(product);
      }
    }

    return RestaurantMenuCategory(name: name, products: products);
  }
}

/// Profil public d'un restaurant (`GET /public/restaurants/{slug}`).
class RestaurantPublicProfile {
  const RestaurantPublicProfile({
    required this.slug,
    required this.name,
    this.establishmentType,
    this.city,
    this.address,
    this.phone,
    this.description,
    this.coverImageUrl,
    this.currency,
    this.ratingAvg,
    this.reviewsCount,
    this.menu = const [],
  });

  final String slug;
  final String name;
  final String? establishmentType;
  final String? city;
  final String? address;
  final String? phone;
  final String? description;
  final String? coverImageUrl;
  final String? currency;
  final double? ratingAvg;
  final int? reviewsCount;
  final List<RestaurantMenuCategory> menu;

  /// Vrai si le menu contient au moins un produit commandable.
  ///
  /// Un profil sans menu n'est pas une erreur : la branche est publiée mais
  /// n'a rien mis en ligne. L'écran le dit au lieu d'afficher un panier vide.
  bool get hasMenu => menu.any((category) => category.products.isNotEmpty);

  /// Nombre de produits publiés, toutes catégories confondues.
  int get productCount =>
      menu.fold(0, (total, category) => total + category.products.length);

  static RestaurantPublicProfile? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final slug = _str(raw['slug']);
    if (slug == null) return null;

    final menu = <RestaurantMenuCategory>[];
    final rawMenu = raw['menu'];
    if (rawMenu is List) {
      for (final entry in rawMenu) {
        final category = RestaurantMenuCategory.tryParse(entry);
        if (category != null && !category.isEmpty) menu.add(category);
      }
    }

    return RestaurantPublicProfile(
      slug: slug,
      name: _str(raw['name']) ?? slug,
      establishmentType: _str(raw['establishment_type']),
      city: _str(raw['city']),
      address: _str(raw['address']),
      phone: _str(raw['phone']),
      description: _str(raw['description']),
      coverImageUrl: _str(raw['cover_image_url']),
      currency: _str(raw['currency']),
      ratingAvg: _dbl(raw['rating_avg']),
      reviewsCount: _int(raw['reviews_count']),
      menu: menu,
    );
  }
}

/// Une ligne de panier en cours de saisie.
class RestaurantCartLine {
  const RestaurantCartLine({
    required this.product,
    required this.quantity,
    this.note,
  });

  final RestaurantMenuProduct product;
  final int quantity;
  final String? note;

  /// Total de la ligne, ou `null` si le prix n'est pas publié.
  ///
  /// On ne remplace pas un prix manquant par 0 : le total affiché serait faux
  /// et l'utilisateur croirait à un montant que le serveur ne confirmera pas.
  int? get lineTotalMinor {
    final unit = product.priceMinor;
    if (unit == null) return null;
    return unit * quantity;
  }

  RestaurantCartLine copyWith({int? quantity, String? note}) =>
      RestaurantCartLine(
        product: product,
        quantity: quantity ?? this.quantity,
        note: note ?? this.note,
      );

  Map<String, dynamic> toPayload() => {
        'product_code': product.code,
        'quantity': quantity,
        if (note != null && note!.trim().isNotEmpty) 'note': note!.trim(),
      };
}

/// Types de commande acceptés par le contrat public.
///
/// `pickup` est l'alias public de `takeaway` interne : le serveur le traduit.
/// On envoie l'alias public, jamais la valeur interne.
enum RestaurantOrderType {
  pickup('pickup'),
  delivery('delivery');

  const RestaurantOrderType(this.wire);

  final String wire;
}

/// Commande créée (`POST …/orders`).
class RestaurantOrderResult {
  const RestaurantOrderResult({
    required this.reference,
    required this.status,
    required this.totalMinor,
    required this.currency,
    required this.created,
    this.trackingSecret,
  });

  final String reference;
  final String status;
  final int totalMinor;
  final String currency;

  /// `false` = rejeu idempotent : la commande existait déjà.
  final bool created;

  /// Secret de suivi, rendu **une seule fois** à la création réelle.
  ///
  /// `null` au rejeu : l'écran doit alors dire que le suivi se fait avec le
  /// secret déjà obtenu, pas afficher un champ vide.
  final String? trackingSecret;

  static RestaurantOrderResult? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final reference = _str(raw['reference']);
    if (reference == null) return null;

    return RestaurantOrderResult(
      reference: reference,
      status: _str(raw['status']) ?? 'pending',
      totalMinor: _int(raw['total_minor']) ?? 0,
      currency: _str(raw['currency']) ?? 'MAD',
      created: raw['created'] == true,
      trackingSecret: _str(raw['tracking_secret']),
    );
  }
}

/// Suivi d'une commande (`GET …/orders/{ref}?secret=`).
class RestaurantOrderTracking {
  const RestaurantOrderTracking({
    required this.reference,
    required this.status,
    required this.totalMinor,
    required this.currency,
    this.items = const [],
  });

  final String reference;
  final String status;
  final int totalMinor;
  final String currency;
  final List<RestaurantOrderLine> items;

  static RestaurantOrderTracking? tryParse(
    Object? raw, {
    required String fallbackReference,
  }) {
    if (raw is! Map) return null;

    final items = <RestaurantOrderLine>[];
    final rawItems = raw['items'];
    if (rawItems is List) {
      for (final entry in rawItems) {
        final line = RestaurantOrderLine.tryParse(entry);
        if (line != null) items.add(line);
      }
    }

    return RestaurantOrderTracking(
      reference: _str(raw['reference']) ?? fallbackReference,
      status: _str(raw['status']) ?? 'pending',
      totalMinor: _int(raw['total_minor']) ?? 0,
      currency: _str(raw['currency']) ?? 'MAD',
      items: items,
    );
  }
}

/// Une ligne d'une commande suivie.
class RestaurantOrderLine {
  const RestaurantOrderLine({
    required this.name,
    required this.quantity,
    this.lineTotalMinor,
  });

  final String name;
  final int quantity;
  final int? lineTotalMinor;

  static RestaurantOrderLine? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final name = _str(raw['name']);
    if (name == null) return null;

    return RestaurantOrderLine(
      name: name,
      quantity: _int(raw['quantity']) ?? 0,
      lineTotalMinor: _int(raw['line_total_minor']),
    );
  }
}

/// Moyens de paiement ouverts au public par le contrat par slug.
///
/// Le contrôleur n'ouvre que `cash` (alias public `cash_on_delivery`) et
/// `mobile_money` : la passerelle carte reste volontairement fermée côté
/// serveur (spec #5272 en attente). Proposer « carte » dans l'app enverrait
/// une valeur que le service refuserait.
enum RestaurantPaymentMethod {
  cashOnDelivery('cash_on_delivery'),
  mobileMoney('mobile_money');

  const RestaurantPaymentMethod(this.wire);

  final String wire;
}

/// Nombre maximal de lignes par commande (règle serveur `max:50`).
const int restaurantMaxCartLines = 50;

/// Quantité maximale par ligne (règle serveur `max:999`).
const int restaurantMaxLineQuantity = 999;

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
