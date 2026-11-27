import '../../discovery/data/public_discovery_repository.dart';
import '../domain/journey_models.dart';
import 'journey_http.dart';

/// Parcours de commande d'une boutique (`retail_shop`).
///
/// ## Le contrat, tel qu'il existe réellement
///
/// - `GET  /public/market/products?seller={slug}` — catalogue du vendeur ;
/// - `POST /public/market/orders` — checkout invité (idempotent sur
///   `idempotency_key`) ;
/// - `GET  /public/market/orders/{reference}?token={tracking_token}` — suivi.
///
/// Aucune de ces routes ne demande de compte ni de jeton tenant : c'est ce qui
/// rend le parcours complet possible dans l'app. Le jeton de suivi est rendu
/// UNE SEULE FOIS à la création (le serveur n'en stocke qu'une empreinte) —
/// l'écran doit donc le présenter comme un code à conserver.
class RetailOrderGateway {
  RetailOrderGateway(this._api);

  final JourneyApi _api;

  static const String productsPath = '/public/market/products';
  static const String ordersPath = '/public/market/orders';

  String trackPath(String reference) => '$ordersPath/$reference';

  /// Catalogue publié du vendeur. Un produit `available: false` est conservé
  /// (il est visible) mais n'est pas commandable.
  Future<List<JourneyOffer>> products(String sellerSlug) async {
    final json = await _api.get(
      productsPath,
      query: {'seller': sellerSlug, 'per_page': 50},
    );

    final offers = <JourneyOffer>[];
    for (final raw in JourneyApi.listOf(json)) {
      final offer = JourneyOffer.tryParse(raw);
      if (offer != null) offers.add(offer);
    }
    return offers;
  }

  /// Corps EXACT de `StoreMarketOrderRequest` (règles validées côté serveur).
  ///
  /// Les contrôles ici ne remplacent pas la validation serveur : ils évitent
  /// d'envoyer une requête qu'on sait déjà invalide (message immédiat) et
  /// rendent le contrat testable sans réseau.
  static Map<String, dynamic> buildOrderPayload({
    required String sellerSlug,
    required List<JourneyCartLine> lines,
    required JourneyCustomer customer,
    required JourneyAddress delivery,
    required String paymentMethod,
    required String idempotencyKey,
  }) {
    if (sellerSlug.trim().isEmpty) {
      throw ArgumentError.value(sellerSlug, 'sellerSlug', 'Vendeur requis');
    }

    final billable = lines.where((line) => line.quantity > 0).toList();
    if (billable.isEmpty) {
      throw ArgumentError.value(lines, 'lines', 'Panier vide');
    }

    if (!customer.isComplete) {
      throw ArgumentError.value(
          customer.name, 'customer', 'Nom et téléphone requis');
    }

    if (!delivery.isComplete) {
      throw ArgumentError.value(
        delivery.address,
        'delivery',
        'Adresse et ville requises',
      );
    }

    if (paymentMethod != 'cash' && paymentMethod != 'online') {
      throw ArgumentError.value(
          paymentMethod, 'paymentMethod', 'cash | online');
    }

    if (idempotencyKey.trim().isEmpty || idempotencyKey.length > 64) {
      throw ArgumentError.value(
        idempotencyKey,
        'idempotencyKey',
        'Clé 1..64 caractères',
      );
    }

    // Les quantités sont fusionnées par produit : deux lignes du même produit
    // produiraient sinon deux entrées pour une seule commande.
    final quantities = <String, int>{};
    for (final line in billable) {
      final id = line.offer.id;
      quantities[id] = (quantities[id] ?? 0) + line.boundedQuantity;
    }

    return {
      'seller': sellerSlug.trim(),
      'items': [
        for (final entry in quantities.entries)
          {
            'product_id': int.tryParse(entry.key) ??
                (throw ArgumentError.value(
                  entry.key,
                  'items.product_id',
                  'Identifiant produit numérique attendu',
                )),
            'quantity': entry.value.clamp(1, 999),
          },
      ],
      'customer': customer.toPayload(),
      'delivery': delivery.toPayload(),
      'payment_method': paymentMethod,
      'idempotency_key': idempotencyKey.trim(),
    };
  }

  Future<JourneyConfirmation> placeOrder({
    required String sellerSlug,
    required List<JourneyCartLine> lines,
    required JourneyCustomer customer,
    required JourneyAddress delivery,
    required String paymentMethod,
    required String idempotencyKey,
  }) async {
    final json = await _api.post(
      ordersPath,
      buildOrderPayload(
        sellerSlug: sellerSlug,
        lines: lines,
        customer: customer,
        delivery: delivery,
        paymentMethod: paymentMethod,
        idempotencyKey: idempotencyKey,
      ),
    );

    final data = JourneyApi.dataOf(json);
    final reference = data['reference'];
    if (reference is! String || reference.isEmpty) {
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Order without reference',
      );
    }

    final payment = data['payment'];
    return JourneyConfirmation(
      reference: reference,
      trackingCode: data['tracking_token'] is String
          ? data['tracking_token'] as String
          : null,
      status: payment is Map && payment['status'] is String
          ? payment['status'] as String
          : null,
      totalMinor:
          data['total_minor'] is int ? data['total_minor'] as int : null,
      currency: data['currency'] is String ? data['currency'] as String : null,
      checkoutUrl: payment is Map && payment['checkout_url'] is String
          ? payment['checkout_url'] as String
          : null,
    );
  }

  /// Suivi par référence + jeton. Un jeton absent ou faux rend 404 côté
  /// serveur (anti-énumération) : l'écran affiche « introuvable », pas
  /// « panne ».
  Future<JourneyStatusReport> track({
    required String reference,
    required String token,
  }) async {
    final json = await _api.get(
      trackPath(reference),
      query: {'token': token},
    );

    final report = JourneyStatusReport.tryParse(
      JourneyApi.dataOf(json),
      fallbackReference: reference,
    );
    if (report == null) {
      throw const PublicApiFailure(
        PublicFailureKind.notFound,
        message: 'Order not found',
      );
    }
    return report;
  }
}
