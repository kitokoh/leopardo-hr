import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/pos_api_client.dart';
import '../../core/models/pos_models.dart';

/// État complet de la caisse tablette (#9214).
class PosState {
  const PosState({
    this.categories = const [],
    this.products = const [],
    this.tables = const [],
    this.cart = const Cart(),
    this.sessionId,
    this.branchId,
    this.offline = false,
    this.pendingOperations = 0,
    this.loading = false,
    this.error,
  });

  final List<PosCategory> categories;
  final List<PosProduct> products;
  final List<PosTable> tables;
  final Cart cart;
  final int? sessionId;
  final int? branchId;
  final bool offline;
  final int pendingOperations;
  final bool loading;
  final String? error;

  bool get sessionOpen => sessionId != null;

  List<PosProduct> productsFor(int? categoryId) => categoryId == null
      ? products
      : products.where((product) => product.categoryId == categoryId).toList();

  PosState copyWith({
    List<PosCategory>? categories,
    List<PosProduct>? products,
    List<PosTable>? tables,
    Cart? cart,
    int? sessionId,
    int? branchId,
    bool? offline,
    int? pendingOperations,
    bool? loading,
    String? error,
    bool clearError = false,
  }) =>
      PosState(
        categories: categories ?? this.categories,
        products: products ?? this.products,
        tables: tables ?? this.tables,
        cart: cart ?? this.cart,
        sessionId: sessionId ?? this.sessionId,
        branchId: branchId ?? this.branchId,
        offline: offline ?? this.offline,
        pendingOperations: pendingOperations ?? this.pendingOperations,
        loading: loading ?? this.loading,
        error: clearError ? null : (error ?? this.error),
      );
}

/// Contrôleur de la caisse : charge le catalogue, gère le panier, encaisse.
///
/// Riverpod 3 : `Notifier` remplace `StateNotifier` (retiré de l'API publique
/// en 3.x). Le comportement est identique, l'API est celle du framework.
class PosController extends Notifier<PosState> {
  PosApiClient get _api => ref.read(posApiClientProvider);

  @override
  PosState build() => const PosState();

  /// Charge catalogue + plan de salle (réseau, puis cache).
  Future<void> bootstrap({required int branchId}) async {
    state = state.copyWith(loading: true, branchId: branchId, clearError: true);

    final catalog = await _api.loadCatalog(branchId: branchId);
    final tables = await _api.loadTables(branchId: branchId);

    state = state.copyWith(
      categories: catalog.categories,
      products: catalog.products,
      tables: tables,
      offline: catalog.fromCache,
      pendingOperations: _api.queue.length,
      loading: false,
    );
  }

  /// Ouvre la session de caisse (en ligne uniquement).
  Future<bool> openSession({required int openingCashMinor}) async {
    final branchId = state.branchId;

    if (branchId == null) {
      state = state.copyWith(error: 'Aucune succursale sélectionnée.');

      return false;
    }

    state = state.copyWith(loading: true, clearError: true);

    try {
      final sessionId = await _api.openSession(
        branchId: branchId,
        openingCashMinor: openingCashMinor,
      );

      state = state.copyWith(sessionId: sessionId, loading: false);

      return true;
    } on PosOfflineException catch (error) {
      state = state.copyWith(loading: false, error: error.message);

      return false;
    }
  }

  void addProduct(PosProduct product) {
    state = state.copyWith(cart: state.cart.addProduct(product));
  }

  void removeProduct(int productId) {
    state = state.copyWith(cart: state.cart.removeProduct(productId));
  }

  void setOrderType(OrderType type) {
    state = state.copyWith(cart: state.cart.copyWith(orderType: type));
  }

  void selectTable(int tableId) {
    state = state.copyWith(cart: state.cart.copyWith(tableId: tableId));
  }

  void setCovers(int covers) {
    state = state.copyWith(cart: state.cart.copyWith(covers: covers.clamp(1, 999)));
  }

  /// Applique une remise (unites mineures, bornee a zero).
  ///
  /// La validite (remise <= sous-total) est verifiee par `Cart.isValid` : on
  /// ne bloque pas la saisie, on empeche seulement l'encaissement.
  void setDiscount(int discountMinor) {
    state = state.copyWith(
      cart: state.cart.copyWith(discountMinor: discountMinor < 0 ? 0 : discountMinor),
    );
  }

  /// Applique un pourboire (unites mineures, borne a zero).
  void setTip(int tipMinor) {
    state = state.copyWith(
      cart: state.cart.copyWith(tipMinor: tipMinor < 0 ? 0 : tipMinor),
    );
  }

  /// Efface le message d'erreur affiche.
  void clearError() {
    state = state.copyWith(clearError: true);
  }

  void clearCart() {
    state = state.copyWith(cart: const Cart());
  }

  /// Envoie la commande puis encaisse.
  ///
  /// Retourne `true` si l'encaissement est passé (immédiat ou mis en file).
  Future<bool> submitAndPay({required String method}) async {
    final sessionId = state.sessionId;
    final branchId = state.branchId;

    if (sessionId == null || branchId == null) {
      state = state.copyWith(error: 'La caisse doit être ouverte avant d\'encaisser.');

      return false;
    }

    if (!state.cart.isValid) {
      state = state.copyWith(
        error: state.cart.isEmpty
            ? 'Le panier est vide.'
            : 'Sélectionnez une table pour une commande sur place.',
      );

      return false;
    }

    state = state.copyWith(loading: true, clearError: true);

    try {
      final orderId = await _api.createOrder(
        branchId: branchId,
        sessionId: sessionId,
        cart: state.cart,
      );

      final outcome = await _api.pay(
        orderId: orderId,
        amountMinor: state.cart.totalMinor,
        method: method,
      );

      state = state.copyWith(
        cart: const Cart(),
        loading: false,
        offline: outcome.queued,
        pendingOperations: _api.queue.length,
      );

      return true;
    } on PosOfflineException catch (error) {
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
      error: succeeded > 0 ? '$succeeded opération(s) synchronisée(s).' : null,
    );
  }
}

final posControllerProvider = NotifierProvider<PosController, PosState>(PosController.new);
