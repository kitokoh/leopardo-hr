import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/pos/pos_search.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

import '../../core/api/retail_api_client.dart';
import '../../core/models/retail_models.dart';

/// Adapte un [RetailProduct] a l'interface de recherche partagee.
///
/// On ne fait pas heriter `RetailProduct` de `PosSearchable` : le modele
/// metier reste pur et ne depend pas du noyau UI. L'adaptateur est la seule
/// couture entre les deux.
class RetailSearchable implements PosSearchable {
  const RetailSearchable(this.product);

  final RetailProduct product;

  @override
  int get id => product.id;

  @override
  String get name => product.name;

  @override
  String? get sku => product.sku;

  @override
  String? get barcode => product.barcode;

  @override
  int? get categoryId => product.categoryId;
}

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
    this.affinity = const PosAffinityRegistry(),
    this.parked = const PosParkedTickets(),
    this.voidedLines = const [],
    this.sync = const PosSyncProgress(),
    this.showFavouritesOnly = false,
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

  /// Favoris + recents (persistes localement).
  final PosAffinityRegistry affinity;

  /// Commandes mises de cote.
  final PosParkedTickets parked;

  /// Lignes annulees avec motif, conservees pour la trace.
  final List<PosVoidedLine> voidedLines;

  /// Progression de la synchronisation hors ligne.
  final PosSyncProgress sync;

  /// Filtre « favoris seulement ».
  final bool showFavouritesOnly;

  bool get sessionOpen => session != null;

  /// Produits visibles : rayon + favoris + recherche **classee par
  /// pertinence**, favoris et recents remontes en tete.
  ///
  /// C'est le cœur de l'ergonomie de comptoir : le vendeur tape 3 lettres et
  /// l'article est en premiere position, au lieu de faire defiler une grille.
  List<RetailProduct> get visibleProducts {
    var pool = products;

    if (selectedCategoryId != null) {
      pool = pool.where((product) => product.categoryId == selectedCategoryId).toList();
    }

    if (showFavouritesOnly) {
      pool = pool.where((product) => affinity.isFavourite(product.id)).toList();
    }

    // 1. Ordre d'affinite (favoris, puis usage, puis recence).
    final ordered = affinity.order([for (final product in pool) RetailSearchable(product)]);

    // 2. Classement par pertinence pour la saisie courante.
    final ranked = rankPosItems(items: ordered, query: searchQuery);

    return [for (final item in ranked) item.product];
  }

  /// Les articles recemment vendus, dans l'ordre, resolus en produits.
  List<RetailProduct> get recentProducts {
    final byId = {for (final product in products) product.id: product};

    return [
      for (final id in affinity.recentItemIds(limit: 8))
        if (byId[id] != null) byId[id]!,
    ];
  }

  /// Le ticket pret a etre montre au client.
  ///
  /// Le total est calcule par le noyau partage : c'est exactement celui qui
  /// sera encaisse, pas un recalcul approximatif de l'ecran.
  PosReceipt get receipt => PosReceipt(
        lines: [
          for (final line in cart.lines)
            PosReceiptLine(
              name: line.product.name,
              quantity: line.quantity,
              unitPriceMinor: line.product.priceMinor,
            ),
        ],
        discountMinor: cart.discountMinor,
        voidedLines: voidedLines,
        note: cart.note,
      );

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
    PosAffinityRegistry? affinity,
    PosParkedTickets? parked,
    List<PosVoidedLine>? voidedLines,
    PosSyncProgress? sync,
    bool? showFavouritesOnly,
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
        affinity: affinity ?? this.affinity,
        parked: parked ?? this.parked,
        voidedLines: voidedLines ?? this.voidedLines,
        sync: sync ?? this.sync,
        showFavouritesOnly: showFavouritesOnly ?? this.showFavouritesOnly,
      );
}

/// Controleur de la caisse : charge le catalogue, gere le panier, encaisse.
///
/// Riverpod 3 : `Notifier` remplace `StateNotifier` (retire de l'API publique
/// en 3.x). Le comportement est identique, l'API est celle du framework.
class RetailPosController extends Notifier<RetailPosState> {
  RetailApiClient get _api => ref.read(retailApiClientProvider);

  @override
  RetailPosState build() => const RetailPosState();

  /// Charge catalogue + session locale (reseau, puis cache).
  Future<void> bootstrap({required int locationId}) async {
    state = state.copyWith(loading: true, locationId: locationId, clearError: true);

    final catalog = await _api.loadCatalog(locationId: locationId);
    final cachedSession = _api.store.readSession();
    final cachedCart = _api.store.readCart();
    final affinity = _api.store.readAffinity();
    final parked = _api.store.readParkedTickets();

    state = state.copyWith(
      categories: catalog.categories,
      products: catalog.products,
      session: cachedSession,
      cart: cachedCart ?? const RetailCart(),
      offline: catalog.fromCache,
      pendingOperations: _api.queue.length,
      affinity: affinity,
      parked: parked,
      sync: PosSyncProgress(total: _api.queue.length),
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

  /// Efface le message d'erreur affiche (bandeau ferme par le vendeur).
  void clearError() {
    state = state.copyWith(clearError: true);
  }

  void setSearchQuery(String query) {
    state = state.copyWith(searchQuery: query);
  }

  void selectCategory(int? categoryId) {
    state = categoryId == null
        ? state.copyWith(clearCategory: true)
        : state.copyWith(selectedCategoryId: categoryId);
  }

  void toggleFavouritesOnly() {
    state = state.copyWith(showFavouritesOnly: !state.showFavouritesOnly);
  }

  /// Epingle / depingle un article en favori (appui long sur la tuile).
  void toggleFavourite(int productId) {
    final affinity = state.affinity.toggleFavourite(productId);

    state = state.copyWith(affinity: affinity);
    _api.store.saveAffinity(affinity).catchError((Object _) {});
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
    state = state.copyWith(cart: const RetailCart(), voidedLines: const []);
    _persistCart(const RetailCart());
  }

  /// Annule une ligne **avec motif** : la ligne sort du panier mais reste
  /// tracee dans le ticket et dans l'etat.
  ///
  /// Retourne `false` si le motif est invalide (un motif « Autre » sans
  /// commentaire ne justifie rien).
  bool voidLine({
    required int productId,
    required PosVoidReason reason,
    String? comment,
  }) {
    final index = state.cart.lines.indexWhere((line) => line.product.id == productId);

    if (index == -1) {
      state = state.copyWith(error: 'Cette ligne n\'est plus dans le panier.');

      return false;
    }

    final line = state.cart.lines[index];
    final voided = PosVoidedLine(
      itemId: line.product.id,
      itemName: line.product.name,
      quantity: line.quantity,
      amountMinor: line.lineTotalMinor,
      reason: reason,
      comment: comment,
      voidedAt: DateTime.now(),
    );

    if (!voided.isValid) {
      state = state.copyWith(error: 'Un motif « Autre » exige une precision.');

      return false;
    }

    final lines = [...state.cart.lines]..removeAt(index);
    final cart = state.cart.copyWith(lines: lines);

    state = state.copyWith(
      cart: cart,
      voidedLines: [...state.voidedLines, voided],
      clearError: true,
    );
    _persistCart(cart);

    return true;
  }

  /// Met la commande en cours de cote et vide le panier.
  ///
  /// Retourne `false` si le panier est vide ou si la file est pleine.
  bool parkCurrentTicket(String label) {
    if (state.cart.isEmpty) {
      state = state.copyWith(error: 'Le panier est vide : rien a mettre en attente.');

      return false;
    }

    if (state.parked.isFull) {
      state = state.copyWith(
        error: 'Trop de commandes en attente (${PosParkedTickets.maxTickets}). '
            'Rappelez-en une avant d\'en mettre une autre de cote.',
      );

      return false;
    }

    final ticket = PosParkedTicket(
      id: 'retail-${DateTime.now().microsecondsSinceEpoch}',
      label: label.trim().isEmpty ? 'Commande' : label.trim(),
      payload: state.cart.toJson(),
      parkedAt: DateTime.now(),
      itemCount: state.cart.itemCount,
      totalMinor: state.cart.totalMinor,
    );

    final parked = state.parked.park(ticket);

    state = state.copyWith(
      parked: parked,
      cart: const RetailCart(),
      voidedLines: const [],
      clearError: true,
    );
    _persistCart(const RetailCart());
    _api.store.saveParkedTickets(parked).catchError((Object _) {});

    return true;
  }

  /// Rappelle une commande mise de cote et restaure son panier.
  bool recallTicket(String id) {
    final result = state.parked.recall(id);

    if (result.ticket == null) {
      state = state.copyWith(error: 'Cette commande n\'est plus en attente.');

      return false;
    }

    final cart = RetailCart.fromJson(result.ticket!.payload);

    state = state.copyWith(
      parked: result.remaining,
      cart: cart,
      voidedLines: const [],
      clearError: true,
    );
    _persistCart(cart);
    _api.store.saveParkedTickets(result.remaining).catchError((Object _) {});

    return true;
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

      // Enregistre l'usage de chaque article : c'est ce qui fait remonter les
      // articles habituels en tete de grille au service suivant.
      var affinity = state.affinity;

      for (final line in state.cart.lines) {
        affinity = affinity.recordUse(line.product.id);
      }

      state = state.copyWith(
        cart: const RetailCart(),
        voidedLines: const [],
        loading: false,
        offline: outcome.queued,
        pendingOperations: _api.queue.length,
        lastOrderId: orderId,
        affinity: affinity,
        sync: state.sync.copyWith(total: _api.queue.length),
      );
      _persistCart(const RetailCart());
      _api.store.saveAffinity(affinity).catchError((Object _) {});

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

  /// Rejoue la file d'attente hors ligne, avec progression visible.
  ///
  /// Le moteur partage decide quoi rejouer et quoi declarer en conflit : un
  /// rejet metier (4xx) n'est plus rejoue indefiniment, il est remonte a
  /// l'operateur.
  Future<void> flushQueue() async {
    final pending = _api.queue.pending();

    if (pending.isEmpty) {
      state = state.copyWith(
        sync: const PosSyncProgress(),
        pendingOperations: 0,
        offline: false,
      );

      return;
    }

    state = state.copyWith(
      sync: PosSyncProgress(total: pending.length, running: true),
      clearError: true,
    );

    final progress = await _api.flushQueueWithProgress(
      onProgress: (synced, total) {
        state = state.copyWith(
          sync: state.sync.copyWith(total: total, synced: synced, running: true),
        );
      },
    );

    state = state.copyWith(
      pendingOperations: _api.queue.length,
      offline: _api.queue.length > 0,
      sync: progress,
      error: progress.hasConflicts ? progress.message : null,
    );
  }

  void _persistCart(RetailCart cart) {
    // Persistance best-effort : le panier doit survivre a un passage en
    // arriere-plan, mais un echec d'ecriture ne doit pas bloquer la vente.
    _api.store.saveCart(cart).catchError((Object _) {});
  }
}

final retailPosControllerProvider =
    NotifierProvider<RetailPosController, RetailPosState>(RetailPosController.new);
