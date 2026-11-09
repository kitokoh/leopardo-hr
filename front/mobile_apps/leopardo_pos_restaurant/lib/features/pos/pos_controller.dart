import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/pos/pos_history.dart';
import 'package:leopardo_core/core/pos/pos_search.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

import '../../core/api/pos_api_client.dart';
import 'package:leopardo_core/core/pos/pos_options.dart';

import '../../core/models/pos_models.dart';

/// Adapte un [PosProduct] a l'interface de recherche partagee.
///
/// Le modele metier reste pur : l'adaptateur est la seule couture entre le
/// domaine et le noyau UI partage.
class PosSearchableProduct implements PosSearchable {
  const PosSearchableProduct(this.product);

  final PosProduct product;

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
    this.searchQuery = '',
    this.affinity = const PosAffinityRegistry(),
    this.parked = const PosParkedTickets(),
    this.voidedLines = const [],
    this.sync = const PosSyncProgress(),
    this.showFavouritesOnly = false,
    this.history = const PosSalesHistory(),
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

  /// Saisie de recherche courante.
  final String searchQuery;

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

  /// Historique des ventes de la tablette (#9268), pour la reimpression.
  final PosSalesHistory history;

  bool get sessionOpen => sessionId != null;

  List<PosProduct> productsFor(int? categoryId) => categoryId == null
      ? products
      : products.where((product) => product.categoryId == categoryId).toList();

  /// Plats visibles : rayon + favoris + recherche **classee par pertinence**,
  /// favoris et recents remontes en tete.
  ///
  /// C'est le cœur de l'ergonomie de salle : le serveur tape 3 lettres et le
  /// plat est en premiere position, au lieu de faire defiler un menu.
  List<PosProduct> get visibleProducts {
    var pool = products;

    if (showFavouritesOnly) {
      pool = pool.where((product) => affinity.isFavourite(product.id)).toList();
    }

    final ordered = affinity.order([for (final product in pool) PosSearchableProduct(product)]);
    final ranked = rankPosItems(items: ordered, query: searchQuery);

    return [for (final item in ranked) item.product];
  }

  /// Les plats recemment servis, dans l'ordre, resolus en produits.
  List<PosProduct> get recentProducts {
    final byId = {for (final product in products) product.id: product};

    return [
      for (final id in affinity.recentItemIds(limit: 8))
        if (byId[id] != null) byId[id]!,
    ];
  }

  /// Le produit correspondant exactement a un code-barres scanne.
  PosProduct? productForBarcode(String barcode) {
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

  /// La table selectionnee, si elle existe encore dans le plan de salle.
  PosTable? get selectedTable {
    for (final table in tables) {
      if (table.id == cart.tableId) {
        return table;
      }
    }

    return null;
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
              // Prix unitaire options comprises : c'est ce que le client a
              // accepte, et ce que l'apercu et l'imprimante doivent montrer.
              unitPriceMinor: line.unitPriceMinor,
              options: line.optionLabels,
            ),
        ],
        discountMinor: cart.discountMinor,
        tipMinor: cart.tipMinor,
        voidedLines: voidedLines,
        tableLabel: selectedTable?.label,
        covers: cart.covers,
        orderTypeLabel: cart.orderType.label,
        note: cart.note,
      );

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
    String? searchQuery,
    PosAffinityRegistry? affinity,
    PosParkedTickets? parked,
    List<PosVoidedLine>? voidedLines,
    PosSyncProgress? sync,
    bool? showFavouritesOnly,
    PosSalesHistory? history,
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
        searchQuery: searchQuery ?? this.searchQuery,
        affinity: affinity ?? this.affinity,
        parked: parked ?? this.parked,
        voidedLines: voidedLines ?? this.voidedLines,
        sync: sync ?? this.sync,
        showFavouritesOnly: showFavouritesOnly ?? this.showFavouritesOnly,
        history: history ?? this.history,
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
    final cachedCart = _api.store.readCart();
    final affinity = _api.store.readAffinity();
    final parked = _api.store.readParkedTickets();

    state = state.copyWith(
      categories: catalog.categories,
      products: catalog.products,
      tables: tables,
      cart: cachedCart ?? const Cart(),
      offline: catalog.fromCache,
      pendingOperations: _api.queue.length,
      affinity: affinity,
      parked: parked,
      history: _api.store.readSalesHistory(),
      sync: PosSyncProgress(total: _api.queue.length),
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

  void setSearchQuery(String query) {
    state = state.copyWith(searchQuery: query);
  }

  void toggleFavouritesOnly() {
    state = state.copyWith(showFavouritesOnly: !state.showFavouritesOnly);
  }

  /// Épingle / dépingle un plat en favori (appui long sur la tuile).
  void toggleFavourite(int productId) {
    final affinity = state.affinity.toggleFavourite(productId);

    state = state.copyWith(affinity: affinity);
    _api.store.saveAffinity(affinity).catchError((Object _) {});
  }

  void addProduct(PosProduct product) {
    final cart = state.cart.addProduct(product);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  /// Ajoute un plat avec ses options choisies (#9265).
  ///
  /// Deux lignes du meme plat avec des options differentes restent **deux
  /// lignes distinctes** : un « Poulet saignant + frites » et un
  /// « Poulet bien cuit + riz » ne peuvent pas fusionner.
  void addProductWithOptions(PosProduct product, List<PosOptionSelection> selections) {
    if (selections.isEmpty) {
      addProduct(product);

      return;
    }

    final cart = state.cart.copyWith(
      lines: [
        ...state.cart.lines,
        CartLine(product: product, quantity: 1, selections: selections),
      ],
    );

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  /// Ajoute le plat correspondant a un code-barres scanne.
  ///
  /// Retourne `false` si aucun plat ne porte ce code : l'interface doit le
  /// dire au serveur plutot que d'ignorer silencieusement le scan.
  bool scanBarcode(String barcode) {
    final product = state.productForBarcode(barcode);

    if (product == null) {
      state = state.copyWith(error: 'Aucun article pour le code $barcode.');

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

  void setOrderType(OrderType type) {
    final cart = state.cart.copyWith(orderType: type);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  void selectTable(int tableId) {
    final cart = state.cart.copyWith(tableId: tableId);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  void setCovers(int covers) {
    final cart = state.cart.copyWith(covers: covers.clamp(1, 999));

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  /// Applique une remise (unites mineures, bornee a zero).
  ///
  /// La validite (remise <= sous-total) est verifiee par `Cart.isValid` : on
  /// ne bloque pas la saisie, on empeche seulement l'encaissement.
  void setDiscount(int discountMinor) {
    final cart = state.cart.copyWith(discountMinor: discountMinor < 0 ? 0 : discountMinor);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  /// Applique un pourboire (unites mineures, borne a zero).
  void setTip(int tipMinor) {
    final cart = state.cart.copyWith(tipMinor: tipMinor < 0 ? 0 : tipMinor);

    state = state.copyWith(cart: cart);
    _persistCart(cart);
  }

  /// Efface le message d'erreur affiche.
  void clearError() {
    state = state.copyWith(clearError: true);
  }

  void clearCart() {
    state = state.copyWith(cart: const Cart(), voidedLines: const []);
    _persistCart(const Cart());
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
      id: 'restaurant-${DateTime.now().microsecondsSinceEpoch}',
      label: label.trim().isEmpty ? 'Commande' : label.trim(),
      payload: state.cart.toJson(),
      parkedAt: DateTime.now(),
      itemCount: state.cart.itemCount,
      totalMinor: state.cart.totalMinor,
      tableLabel: state.selectedTable?.label,
    );

    final parked = state.parked.park(ticket);

    state = state.copyWith(
      parked: parked,
      cart: const Cart(),
      voidedLines: const [],
      clearError: true,
    );
    _persistCart(const Cart());
    _api.store.saveParkedTickets(parked).catchError((Object _) {});

    return true;
  }

  /// Rappelle une commande mise de cote et restaure son panier complet
  /// (lignes, type de commande, table, couverts, remise, pourboire).
  bool recallTicket(String id) {
    final result = state.parked.recall(id);

    if (result.ticket == null) {
      state = state.copyWith(error: 'Cette commande n\'est plus en attente.');

      return false;
    }

    final cart = Cart.fromJson(result.ticket!.payload);

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

      // Enregistre l'usage de chaque plat : c'est ce qui fait remonter les
      // plats habituels en tete de grille au service suivant.
      var affinity = state.affinity;

      for (final line in state.cart.lines) {
        affinity = affinity.recordUse(line.product.id);
      }

      // Historique local (#9268) : ecrit avant de vider le panier, y compris
      // quand la vente part en file hors ligne.
      final history = state.history.add(
        PosSaleRecord(
          id: 'restaurant-sale-${DateTime.now().microsecondsSinceEpoch}',
          receipt: state.receipt,
          paidAt: DateTime.now(),
          orderId: orderId,
          paymentLabel: method,
          queued: outcome.queued,
        ),
      );

      state = state.copyWith(
        cart: const Cart(),
        voidedLines: const [],
        loading: false,
        offline: outcome.queued,
        pendingOperations: _api.queue.length,
        affinity: affinity,
        history: history,
        sync: state.sync.copyWith(total: _api.queue.length),
      );
      _persistCart(const Cart());
      _api.store.saveAffinity(affinity).catchError((Object _) {});
      _api.store.saveSalesHistory(history).catchError((Object _) {});

      return true;
    } on PosOfflineException catch (error) {
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

  void _persistCart(Cart cart) {
    // Persistance best-effort : le panier doit survivre a un passage en
    // arriere-plan, mais un echec d'ecriture ne doit pas bloquer le service.
    _api.store.saveCart(cart).catchError((Object _) {});
  }
}

final posControllerProvider = NotifierProvider<PosController, PosState>(PosController.new);
