import '../../discovery/data/public_discovery_repository.dart';
import '../domain/restaurant_order_models.dart';
import 'journey_http.dart';

/// Commande publique **restaurant par slug** — #9959.
///
/// ## Pourquoi cette passerelle existe
///
/// Le registre mobile déclarait la verticale `restaurant` bloquée en `order`,
/// au motif que la boutique publique exige un jeton par tenant
/// (`X-Restaurant-Shop-Token`). Ce motif décrivait RESTO-805 (#6226) et il est
/// **périmé** : depuis RESTO-902 (#7747) une surface publique **par slug**
/// existe, sans aucun jeton, et c'est celle que consomme la vitrine web
/// `/restaurants/{slug}`.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// - `GET  /public/restaurants/{slug}` — profil + `menu` ;
/// - `POST /public/restaurants/{slug}/orders` — création, idempotente ;
/// - `GET  /public/restaurants/{slug}/orders/{ref}?secret=` — suivi ;
/// - `POST /public/restaurants/{slug}/orders/{ref}/pay` — paiement.
///
/// ## Ce que la passerelle ne fait pas
///
/// Elle n'envoie **aucun montant** : prix et TVA sont calculés serveur
/// (`CreateOnlineOrderAction`). Le total affiché avant envoi est une estimation
/// locale, et le montant qui fait foi est celui rendu à la création.
class RestaurantOrderGateway {
  RestaurantOrderGateway(this._api);

  final JourneyApi _api;

  static const String base = '/public/restaurants';

  String profilePath(String slug) => '$base/${Uri.encodeComponent(slug)}';

  String ordersPath(String slug) => '${profilePath(slug)}/orders';

  /// Profil public d'un restaurant, menu inclus.
  ///
  /// `null` = introuvable **ou** non publié. Les deux cas sont indiscernables
  /// côté public (404 fail-closed), et c'est voulu : révéler qu'un
  /// établissement existe mais n'est pas publié serait une fuite commerciale.
  Future<RestaurantPublicProfile?> profile(String slug) async {
    final needle = slug.trim();
    if (needle.isEmpty) return null;

    try {
      final json = await _api.get(profilePath(needle));
      return RestaurantPublicProfile.tryParse(JourneyApi.dataOf(json));
    } on PublicApiFailure catch (failure) {
      if (failure.kind == PublicFailureKind.notFound) return null;
      rethrow;
    }
  }

  /// Corps EXACT de `StoreRestaurantPublicSlugOrderRequest`.
  ///
  /// Les contrôles ici ne remplacent pas la validation serveur : ils évitent
  /// d'envoyer une requête qu'on sait déjà invalide (message immédiat) et
  /// rendent le contrat testable sans réseau.
  static Map<String, dynamic> buildOrderPayload({
    required String customerName,
    required String customerPhone,
    required List<RestaurantCartLine> lines,
    required String idempotencyKey,
    RestaurantOrderType orderType = RestaurantOrderType.pickup,
    String? note,
  }) {
    final name = customerName.trim();
    if (name.isEmpty) {
      throw ArgumentError.value(
        customerName,
        'customerName',
        'Nom du client requis',
      );
    }

    final phone = customerPhone.trim();
    if (phone.isEmpty) {
      throw ArgumentError.value(
        customerPhone,
        'customerPhone',
        'Téléphone requis',
      );
    }

    if (lines.isEmpty) {
      throw ArgumentError.value(lines, 'lines', 'Panier vide');
    }

    if (lines.length > restaurantMaxCartLines) {
      throw ArgumentError.value(
        lines.length,
        'lines',
        'Maximum $restaurantMaxCartLines lignes',
      );
    }

    for (final line in lines) {
      if (line.product.code.trim().isEmpty) {
        throw ArgumentError.value(
          line.product.code,
          'lines.product_code',
          'Code produit requis',
        );
      }
      if (line.quantity < 1 || line.quantity > restaurantMaxLineQuantity) {
        throw ArgumentError.value(
          line.quantity,
          'lines.quantity',
          'Quantité 1..$restaurantMaxLineQuantity',
        );
      }
    }

    final key = idempotencyKey.trim();
    if (key.isEmpty || key.length > 64) {
      throw ArgumentError.value(
        idempotencyKey,
        'idempotencyKey',
        'Clé 1..64 caractères',
      );
    }

    final trimmedNote = note?.trim() ?? '';

    return {
      'customer_name': name,
      'customer_phone': phone,
      'order_type': orderType.wire,
      if (trimmedNote.isNotEmpty) 'note': trimmedNote,
      'idempotency_key': key,
      'items': [for (final line in lines) line.toPayload()],
    };
  }

  /// Crée une commande invitée.
  ///
  /// L'idempotence est portée par la clé générée côté client et **conservée
  /// jusqu'au succès** : un double tap ou un retry réseau renvoie la même
  /// commande (200, `created: false`), jamais un doublon.
  Future<RestaurantOrderResult> createOrder({
    required String slug,
    required String customerName,
    required String customerPhone,
    required List<RestaurantCartLine> lines,
    required String idempotencyKey,
    RestaurantOrderType orderType = RestaurantOrderType.pickup,
    String? note,
  }) async {
    final json = await _api.post(
      ordersPath(slug),
      buildOrderPayload(
        customerName: customerName,
        customerPhone: customerPhone,
        lines: lines,
        idempotencyKey: idempotencyKey,
        orderType: orderType,
        note: note,
      ),
    );

    final result = RestaurantOrderResult.tryParse(JourneyApi.dataOf(json)) ??
        RestaurantOrderResult.tryParse(json);

    if (result == null) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Order without reference',
      );
    }
    return result;
  }

  /// Suit une commande par référence + secret.
  ///
  /// Le secret est rendu une seule fois à la création (BOS-050) : sans lui, le
  /// serveur répond 404 uniforme. L'écran doit donc le conserver, et dire
  /// pourquoi quand il ne l'a pas.
  Future<RestaurantOrderTracking> track({
    required String slug,
    required String reference,
    required String secret,
  }) async {
    final json = await _api.get(
      '${ordersPath(slug)}/${Uri.encodeComponent(reference)}',
      query: {'secret': secret},
    );

    final tracking = RestaurantOrderTracking.tryParse(
      JourneyApi.dataOf(json),
      fallbackReference: reference,
    );
    if (tracking == null) {
      throw const PublicApiFailure(
        PublicFailureKind.notFound,
        message: 'Order not found',
      );
    }
    return tracking;
  }

  /// Paie une commande.
  ///
  /// Lève sur échec : un `null` silencieux laisserait croire à un paiement
  /// engagé alors qu'aucune transaction n'existe.
  Future<void> pay({
    required String slug,
    required String reference,
    required RestaurantPaymentMethod method,
    required String idempotencyKey,
  }) async {
    await _api.post(
      '${ordersPath(slug)}/${Uri.encodeComponent(reference)}/pay',
      {
        'provider_code': method.wire,
        'idempotency_key': idempotencyKey.trim(),
      },
    );
  }

  /// Avis PUBLIÉS d'un restaurant (`GET …/reviews`).
  ///
  /// Le serveur ne rend que les avis modérés (`status = published`) : un avis
  /// en attente n'apparaît pas, et l'écran ne doit pas laisser croire qu'il a
  /// été publié immédiatement après l'envoi.
  Future<RestaurantReviewPage> reviews(
    String slug, {
    int page = 1,
    int perPage = 20,
  }) async {
    final json = await _api.get(
      '${profilePath(slug)}/reviews',
      query: {'page': page, 'per_page': perPage},
    );

    return RestaurantReviewPage.tryParse(json) ??
        const RestaurantReviewPage(items: []);
  }

  /// Corps EXACT de `StoreRestaurantPublicReviewRequest`.
  ///
  /// `order_ref` est la **preuve d'achat** : le serveur refuse (404) une
  /// référence qui n'appartient pas à cette branche, et (422) une commande qui
  /// n'est pas encore servie. Ces contrôles locaux évitent d'envoyer une
  /// requête qu'on sait déjà invalide.
  static Map<String, dynamic> buildReviewPayload({
    required String orderRef,
    required int rating,
    required String authorName,
    String? comment,
  }) {
    final reference = orderRef.trim();
    if (reference.isEmpty) {
      throw ArgumentError.value(orderRef, 'orderRef', 'Référence requise');
    }

    if (rating < 1 || rating > 5) {
      throw ArgumentError.value(rating, 'rating', 'Note 1..5');
    }

    final author = authorName.trim();
    if (author.isEmpty) {
      throw ArgumentError.value(authorName, 'authorName', 'Nom requis');
    }

    final trimmed = comment?.trim() ?? '';

    return {
      'order_ref': reference,
      'rating': rating,
      'author_name': author,
      if (trimmed.isNotEmpty) 'comment': trimmed,
    };
  }

  /// Dépose un avis après une commande servie.
  ///
  /// Les deux refus métier sont distingués par l'appelant :
  /// - **409** : un avis existe déjà pour cette commande ;
  /// - **422** : la commande n'est pas dans un statut terminal servi/livré.
  /// Les confondre afficherait « erreur réseau » sur une règle métier.
  Future<void> submitReview({
    required String slug,
    required String orderRef,
    required int rating,
    required String authorName,
    String? comment,
  }) async {
    await _api.post(
      '${profilePath(slug)}/reviews',
      buildReviewPayload(
        orderRef: orderRef,
        rating: rating,
        authorName: authorName,
        comment: comment,
      ),
    );
  }
}
