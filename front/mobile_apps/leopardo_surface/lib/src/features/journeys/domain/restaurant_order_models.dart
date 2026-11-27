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

/// Un jour d'ouverture du restaurant.
///
/// ## La convention, et pourquoi elle est écrite ici
///
/// RESTO-805 : `day_of_week` vaut **0 = lundi … 6 = dimanche**. Ce n'est PAS
/// la convention de `DateTime.weekday` (1 = lundi … 7 = dimanche) ni celle de
/// `DateTime.weekday % 7` lue à l'envers. Une erreur d'un cran décalerait
/// toute la semaine : le restaurant afficherait les horaires du mardi le lundi,
/// et « ouvert maintenant » mentirait un jour sur sept.
class RestaurantHour {
  const RestaurantHour({
    required this.dayOfWeek,
    this.opensAt,
    this.closesAt,
    this.isClosed = false,
  });

  /// 0 = lundi … 6 = dimanche (convention RESTO-805).
  final int dayOfWeek;

  /// Heure d'ouverture `HH:MM`, ou `null` si non renseignée.
  final String? opensAt;

  /// Heure de fermeture `HH:MM`, ou `null` si non renseignée.
  final String? closesAt;

  final bool isClosed;

  /// Vrai si le jour est réellement servi (ouvert ET bornes présentes).
  bool get isServed => !isClosed && opensAt != null && closesAt != null;

  /// Vrai si [now] tombe dans la plage de ce jour.
  ///
  /// Gère le service qui passe minuit (`22:00` → `02:00`) : la plage est
  /// alors considérée comme « ouverte » avant minuit OU après l'ouverture,
  /// ce qui est le seul sens possible pour un restaurant de nuit.
  bool isOpenAt(DateTime now) {
    if (!isServed) return false;

    final open = _minutesOf(opensAt!);
    final close = _minutesOf(closesAt!);
    if (open == null || close == null) return false;

    final current = now.hour * 60 + now.minute;

    if (close > open) {
      return current >= open && current < close;
    }

    // Service à cheval sur minuit.
    return current >= open || current < close;
  }

  static RestaurantHour? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final day = _int(raw['day_of_week']);
    if (day == null) return null;

    return RestaurantHour(
      dayOfWeek: ((day % 7) + 7) % 7,
      opensAt: _str(raw['opens_at']),
      closesAt: _str(raw['closes_at']),
      isClosed: raw['is_closed'] == true,
    );
  }
}

/// `HH:MM` → minutes depuis minuit, ou `null` si la valeur est inexploitable.
int? _minutesOf(String value) {
  final parts = value.split(':');
  if (parts.length < 2) return null;

  final hours = int.tryParse(parts[0]);
  final minutes = int.tryParse(parts[1]);
  if (hours == null || minutes == null) return null;
  if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;

  return hours * 60 + minutes;
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
    this.cuisineTypes = const [],
    this.hours = const [],
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

  /// Cuisines servies (`cuisine_types`), telles que publiées.
  final List<String> cuisineTypes;

  /// Horaires, un élément par jour renseigné.
  final List<RestaurantHour> hours;

  final List<RestaurantMenuCategory> menu;

  /// Vrai si le restaurant est ouvert à [now].
  ///
  /// `null` quand aucun horaire n'est publié : on ne prétend pas connaître
  /// l'état d'ouverture d'un établissement qui ne l'a pas déclaré.
  bool? isOpenAt(DateTime now) {
    if (hours.isEmpty) return null;

    final today = (now.weekday - 1) % 7;
    for (final hour in hours) {
      if (hour.dayOfWeek == today) return hour.isOpenAt(now);
    }

    // Aucune ligne pour aujourd'hui : le jour n'est pas servi.
    return false;
  }

  /// Horaires triés du lundi au dimanche.
  List<RestaurantHour> get sortedHours {
    final copy = [...hours]..sort((a, b) => a.dayOfWeek.compareTo(b.dayOfWeek));
    return copy;
  }

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

    final cuisines = <String>[];
    final rawCuisines = raw['cuisine_types'];
    if (rawCuisines is List) {
      for (final entry in rawCuisines) {
        final value = _str(entry);
        if (value != null) cuisines.add(value);
      }
    }

    final hours = <RestaurantHour>[];
    final rawHours = raw['hours'];
    if (rawHours is List) {
      for (final entry in rawHours) {
        final hour = RestaurantHour.tryParse(entry);
        if (hour != null) hours.add(hour);
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
      cuisineTypes: cuisines,
      hours: hours,
      menu: menu,
    );
  }
}

/// Un avis publié (`GET /public/restaurants/{slug}/reviews`).
///
/// ## Ce que le DTO public contient, et ce qu'il ne contient pas
///
/// Le contrôleur ne rend QUE `author_name`, `rating`, `comment`, `reply`,
/// `replied_at`, `date` — jamais `order_reference` (le secret du client), ni
/// d'identifiant interne, ni de PII. Ce modèle ne cherche donc pas ces champs :
/// les inventer côté client reviendrait à afficher une donnée que le serveur
/// refuse de publier.
class RestaurantReview {
  const RestaurantReview({
    required this.authorName,
    required this.rating,
    this.comment,
    this.reply,
    this.repliedAt,
    this.date,
  });

  final String authorName;

  /// Note 1..5.
  final int rating;

  final String? comment;

  /// Réponse publique du restaurateur (#9713).
  final String? reply;

  final String? repliedAt;

  /// Date de publication (`YYYY-MM-DD`).
  final String? date;

  bool get hasReply => reply != null && reply!.trim().isNotEmpty;

  static RestaurantReview? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final rating = _int(raw['rating']);
    if (rating == null) return null;

    return RestaurantReview(
      authorName: _str(raw['author_name']) ?? '',
      rating: rating.clamp(1, 5),
      comment: _str(raw['comment']),
      reply: _str(raw['reply']),
      repliedAt: _str(raw['replied_at']),
      date: _str(raw['date']),
    );
  }
}

/// Page d'avis publiés.
class RestaurantReviewPage {
  const RestaurantReviewPage({
    required this.items,
    this.total = 0,
    this.currentPage = 1,
    this.perPage = 20,
  });

  final List<RestaurantReview> items;
  final int total;
  final int currentPage;
  final int perPage;

  bool get isEmpty => items.isEmpty;

  /// Vrai s'il reste des avis au-delà de cette page.
  bool get hasMore => currentPage * perPage < total;

  static RestaurantReviewPage? tryParse(Map<String, dynamic> json) {
    final rawItems = json['data'];
    if (rawItems is! List) return null;

    final items = <RestaurantReview>[];
    for (final entry in rawItems) {
      final review = RestaurantReview.tryParse(entry);
      if (review != null) items.add(review);
    }

    final meta = json['meta'];
    final metaMap = meta is Map ? meta : const {};

    return RestaurantReviewPage(
      items: items,
      total: _int(metaMap['total']) ?? items.length,
      currentPage: _int(metaMap['current_page']) ?? 1,
      perPage: _int(metaMap['per_page']) ?? 20,
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

/// Statuts terminaux d'une commande : au-delà, le suivi n'a plus à interroger
/// le serveur.
///
/// La liste est celle du workflow `OrderStateMachine` côté serveur. Un statut
/// inconnu est traité comme NON terminal : mieux vaut une interrogation de trop
/// qu'un suivi qui s'arrête sur une commande encore en cuisine.
const Set<String> restaurantTerminalStatuses = {
  'served',
  'paid',
  'closed',
  'cancelled',
  'refunded',
};

/// Vrai si [status] est terminal (comparaison insensible à la casse).
bool isRestaurantTerminalStatus(String? status) {
  if (status == null) return false;
  return restaurantTerminalStatuses.contains(status.trim().toLowerCase());
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
