import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/retail_api_client.dart';
import '../../core/models/retail_models.dart';

/// Etat complet de la caisse tablette Retail (#9215).
class RetailPosState {
  const RetailPosState({
    this.categories = const [],
    this.products = const [],
    this.cart = const RetailCart(),
    this.session,
    this.locationId,
    this.searchQuery = '',
    this.selectedCategoryId,
    this.offline = false,
    this.pendingOperations = 0,
    this.loading = false,
    this.error,
    this.lastOrderId,
  });

  final List<RetailCategory> categories;
  final List<RetailProduct> products;
  final RetailCart cart;
  final RetailSession? session;
  final int? locationId;
  final String searchQuery;
  final int? selectedCategoryId;
  final bool offline;
  final int pendingOperations;
  final bool loading;
  final String? error;
  final int? lastOrderId;

  bool get sessionOpen => session != null;

  /// Produits visibles : filtre rayon + recherche libre / code-barres.
  ///
  /// La recherche est un geste de caisse : le vendeur tape un nom ou passe un
  /// code-barres. Les deux passent par le meme champ.
  List<RetailProduct> get visibleProducts {
    final byCategory = selectedCategoryId == null
        ? products
        : products.where((product) => product.categoryId == selectedCategoryId).toList();

    if (searchQuery.trim().isEmpty) {
      return byCategory;
    }

    return byCategory.where((product) => product.matches(searchQuery)).toList();
  }

  /// Le produit correspondant exactement a un code-barres scanne, s'il existe.
  RetailProduct? productForBarcode(String barcode) {
    final needle = barcode.trim().toLowerCase();

    if (needle.isEmpty) {
      return null;
    }

    for (final product in products) {
      if ((product.barcode ?? '').toLowerCase() == needle) {
        return product;
      }
    }

    return null;
  }

  RetailPosState copyWith({
    List<RetailCategory>? categories,
    List<RetailProduct>? products,
    RetailCart? cart,
    RetailSession? session,
    int? locationId,
    String? searchQuery,
    int? selectedCategoryId,
    bool clearCategory = false,
    bool? offline,
    int? pendingOperations,
    bool? loading,
    String? error,
    bool clearError = false,
    int? lastOrderId,
  }) =>
      RetailPosState(
        categories: categories ?? this.categories,
        products: products ?? this.products,
        cart: cart ?? this.cart,
        session: session ?? this.session,
        locationId: locationId ?? this.locationId,
        searchQuery: searchQuery ?? this.searchQuery,
        selectedCategoryId:
            clearCategory ? null : (selectedCategoryId ?? this.selectedCategoryId),
        offline: offline ?? this.offline,
        pendingOperations: pendingOperations ?? this.pendingOperations,
        loading: loading ?? this.loading,
        error: clearError ? null : (error ?? this.error),
        lastOrderId: lastOrderId ?? this.lastOrderId,
      );
}

/// Controleur de la caisse : charge le catalogue, gere le panier, encaisse.
class RetailPosController extends StateNotifier<RetailPosState> {
  RetailPosController(this._api) : super(const RetailPosState());

  final RetailApiClient _api;

  /// Charge catalogue + session locale (reseau, puis cache).
  Future<void> bootstrap({required int locationId}) async {
    state = state.copyWith(loading: true, locationId: locationId, clearError: true);

    final catalog = await _api.loadCatalog(locationId: locationId);
    final cachedSession = _api.store.readSession();
    final cachedCart = _api.store.readCart();

    state = state.copyWith(
      categories: catalog.categories,
      products: catalog.products,
      session: cachedSession,
      cart: cachedCart ?? const RetailCart(),
      offline: catalog.fromCache,
      pendingOperations: _api.queue.length,
      loading: false,
    );
  }

  /// Ouvre la session de caisse (en ligne uniquement).
  Future<bool> openSession({required int openingCashMinor}) async {
    final locationId = state.locationId;

    if (locationId == null) {
      state = state.copyWith(error: 'Aucun emplacement selectionne.');

      return false;
    }

    state = state.copyWith(loading: true, clearError: true);

    try {
      final session = await _api.openSession(
        locationId: locationId,
        openingCashMinor: openingCashMinor,
      );

      state = state.copyWith(session: session, loading: false);

      return true;
    } on RetailOfflineException catch (error) {
      state = state.copyWith(loading: false, error: error.message);

      return false;
    }
  }

  /// Cloture la caisse avec le comptage physique.
  Future<RetailSession?> closeSession({
    required int countedCashMinor,
    String? varianceReason,
  }) async {
    final session = state.session;

    if (session == null) {
      state = state.copyWith(error: 'Aucune caisse ouverte.');

      return null;
    }

    state = state.copyWith(loading: true, clearError: true);

    try {
      final closed = await _api.closeSession(
        sessionId: session.id,
        countedCashMinor: countedCashMinor,
        varianceReason: varianceReason,
      );

      state = state.copyWith(session: null, loading: false);

      return closed;
    } on RetailOfflineException catch (error) {
      state = state.copyWith(loading: false, error: error.message);

      return null;
    }
  }

  /// Enregistre un mouvement de tiroir-caisse.
  Future<bool> recordCashMovement(RetailCashMovement movement) async {
    final session = state.session;

    if (session == null) {
      state = state.copyWith(error: 'Aucune caisse ouverte.');

      return false;
    }

    if (!movement.isValid) {
      state = state.copyWith(error: 'Un mouvement exige un montant et un motif.');

      return false;
    }

    try {
      await _api.recordCashMovement(sessionId: session.id, movement: movement);

      return true;
    } on RetailOfflineException catch (error) {
      state = state.copyWith(error: error.message);

      return false;
    }
  }

  void setSearchQuery(String query) {
    state = state.copyWith(searchQuery: query);
  }

  void selectCategory(int? categoryId) {
    state = categoryId == null
        ? state.copyWith(clearCategory: true)
        : state.copyWith(selectedCategoryId: categoryId);
  }

  void addProduct(RetailProduct product, {int quantity = 1}) {
    final cart = state.cart.addProduct(product, quantity: quantity);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  /// Ajoute le produit correspondant a un code-barres scanne.
  ///
  /// Retourne `false` si aucun produit ne porte ce code : l'interface doit le
  /// dire au vendeur plutot que d'ignorer silencieusement le scan.
  bool scanBarcode(String barcode) {
    final product = state.productForBarcode(barcode);

    if (product == null) {
      state = state.copyWith(error: 'Aucun produit pour le code $barcode.');

      return false;
    }

    addProduct(product);

    return true;
  }

  void removeProduct(int productId) {
    final cart = state.cart.removeProduct(productId);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  void setQuantity(int productId, int quantity) {
    final cart = state.cart.setQuantity(productId, quantity);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  void setDiscount(int discountMinor) {
    final cart = state.cart.copyWith(discountMinor: discountMinor < 0 ? 0 : discountMinor);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  void clearCart() {
    state = state.copyWith(cart: const RetailCart());
    _persistCart(const RetailCart());
  }

  /// Envoie la commande puis encaisse.
  ///
  /// Retourne `true` si l'encaissement est passe (immediat ou mis en file).
  Future<bool> submitAndPay({required RetailPaymentMethod method}) async {
    final session = state.session;

    if (session == null) {
      state = state.copyWith(error: 'La caisse doit etre ouverte avant d\'encaisser.');

      return false;
    }

    if (!state.cart.isValid) {
      state = state.copyWith(
        error: state.cart.isEmpty
            ? 'Le panier est vide.'
            : 'La remise depasse le sous-total.',
      );

      return false;
    }

    state = state.copyWith(loading: true, clearError: true);

    try {
      final orderId = await _api.createOrder(sessionId: session.id, cart: state.cart);

      final outcome = await _api.pay(
        orderId: orderId,
        amountMinor: state.cart.totalMinor,
        method: method,
      );

      state = state.copyWith(
        cart: const RetailCart(),
        loading: false,
        offline: outcome.queued,
        pendingOperations: _api.queue.length,
        lastOrderId: orderId,
      );
      _persistCart(const RetailCart());

      return true;
    } on RetailOfflineException catch (error) {
      state = state.copyWith(loading: false, error: error.message);

      return false;
    }
  }

  /// Rembourse la derniere commande encaissee (#9208).
  Future<bool> refundLastOrder(RetailRefund refund) async {
    final orderId = state.lastOrderId;

    if (orderId == null) {
      state = state.copyWith(error: 'Aucune commande a rembourser.');

      return false;
    }

    if (!refund.isValid) {
      state = state.copyWith(error: 'Un remboursement exige un montant et un motif.');

      return false;
    }

    state = state.copyWith(loading: true, clearError: true);

    try {
      final outcome = await _api.refund(orderId: orderId, refund: refund);

      state = state.copyWith(
        loading: false,
        offline: outcome.queued,
        pendingOperations: _api.queue.length,
      );

      return true;
    } on RetailOfflineException catch (error) {
      state = state.copyWith(loading: false, error: error.message);

      return false;
    }
  }

  /// Rejoue la file d'attente hors ligne.
  Future<void> flushQueue() async {
    final succeeded = await _api.flushQueue();

    state = state.copyWith(
      pendingOperations: _api.queue.length,
      offline: _api.queue.length > 0,
      error: succeeded > 0 ? '$succeeded operation(s) synchronisee(s).' : null,
    );
  }

  void _persistCart(RetailCart cart) {
    // Persistance best-effort : le panier doit survivre a un passage en
    // arriere-plan, mais un echec d'ecriture ne doit pas bloquer la vente.
    _api.store.saveCart(cart).catchError((Object _) {});
  }
}

final retailPosControllerProvider =
    StateNotifierProvider<RetailPosController, RetailPosState>((ref) {
  return RetailPosController(ref.watch(retailApiClientProvider));
});
