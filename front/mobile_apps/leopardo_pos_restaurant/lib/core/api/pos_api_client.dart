import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/pos/pos_sync.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

import '../models/pos_models.dart';
import '../offline/pos_offline_queue.dart';
import '../storage/pos_local_store.dart';

/// Client API du POS Restaurant tablette (#9214).
///
/// Contrat **offline-first** : chaque lecture tente le réseau, retombe sur le
/// cache local, et signale à l'appelant d'où vient la donnée (`fromCache`).
/// L'interface peut ainsi afficher honnêtement « hors ligne » au lieu de
/// laisser croire que le menu est à jour.
class PosApiClient {
  PosApiClient({required this.dio, required this.store, required this.queue});

  final Dio dio;
  final PosLocalStore store;
  final PosOfflineQueue queue;

  /// Résultat d'une lecture, avec sa provenance.
  Future<CatalogResult> loadCatalog({int? branchId}) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/restaurant/catalog',
        queryParameters: branchId == null ? null : {'branch_id': branchId},
      );

      final data = response.data?['data'] as Map<String, dynamic>? ?? const {};

      final categories = ((data['categories'] as List<dynamic>?) ?? [])
          .map((raw) => PosCategory.fromJson(raw as Map<String, dynamic>))
          .toList();

      final products = ((data['products'] as List<dynamic>?) ?? [])
          .map((raw) => PosProduct.fromJson(raw as Map<String, dynamic>))
          .toList();

      await store.saveCatalog(categories: categories, products: products);

      return CatalogResult(categories: categories, products: products, fromCache: false);
    } on DioException {
      return CatalogResult(
        categories: store.readCategories(),
        products: store.readProducts(),
        fromCache: true,
      );
    }
  }

  /// Charge le plan de salle (réseau, puis cache).
  Future<List<PosTable>> loadTables({int? branchId}) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/restaurant/tables',
        queryParameters: branchId == null ? null : {'branch_id': branchId},
      );

      final tables = ((response.data?['data'] as List<dynamic>?) ?? [])
          .map((raw) => PosTable.fromJson(raw as Map<String, dynamic>))
          .toList();

      await store.saveTables(tables);

      return tables;
    } on DioException {
      return store.readTables();
    }
  }

  /// Ouvre une session de caisse.
  ///
  /// **En ligne uniquement** : une session a besoin d'un identifiant serveur
  /// (elle porte le fonds de caisse et l'attendu de clôture). Hors ligne,
  /// l'appelant reçoit une [PosOfflineException] et l'interface le dit.
  Future<int> openSession({required int branchId, required int openingCashMinor}) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/restaurant/pos/sessions',
        data: {'branch_id': branchId, 'opening_cash_minor': openingCashMinor},
      );

      return ((response.data?['data'] as Map<String, dynamic>?)?['id'] as num).toInt();
    } on DioException catch (error) {
      throw PosOfflineException('Ouverture de caisse impossible hors ligne.', error);
    }
  }

  /// Crée une commande (en ligne uniquement — même raison que la session).
  Future<int> createOrder({
    required int branchId,
    required int sessionId,
    required Cart cart,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/restaurant/orders',
        data: {
          'branch_id': branchId,
          'pos_session_id': sessionId,
          'order_type': cart.orderType.wireValue,
          'table_id': cart.tableId,
          'covers': cart.covers,
          'note': cart.note,
          'lines': cart.lines
              .map(
                (line) => {
                  'product_id': line.product.id,
                  'quantity': line.quantity,
                },
              )
              .toList(),
        },
      );

      return ((response.data?['data'] as Map<String, dynamic>?)?['id'] as num).toInt();
    } on DioException catch (error) {
      throw PosOfflineException('Création de commande impossible hors ligne.', error);
    }
  }

  /// Encaisse une commande.
  ///
  /// **Fonctionne hors ligne** : l'opération est mise en file avec une clé
  /// d'idempotence stable. Le rejeu après reconnexion ne double pas
  /// l'encaissement.
  Future<PaymentOutcome> pay({
    required int orderId,
    required int amountMinor,
    required String method,
  }) async {
    final key = PosOfflineQueue.paymentKey(
      orderId: orderId,
      amountMinor: amountMinor,
      method: method,
    );

    try {
      await dio.post<Map<String, dynamic>>(
        '/restaurant/orders/$orderId/payments',
        data: {
          'method': method,
          'amount_minor': amountMinor,
          'idempotency_key': key,
        },
      );

      return const PaymentOutcome(queued: false);
    } on DioException {
      await queue.enqueue(
        QueuedOperation(
          idempotencyKey: key,
          endpoint: '/restaurant/orders/$orderId/payments',
          payload: {
            'method': method,
            'amount_minor': amountMinor,
            'idempotency_key': key,
          },
          createdAt: DateTime.now(),
        ),
      );

      return const PaymentOutcome(queued: true);
    }
  }

  /// Rejoue la file d'attente hors ligne, avec progression et gestion des
  /// conflits.
  ///
  /// Le moteur partage (`PosSyncEngine`) decide du sort de chaque operation :
  ///  - **2xx** : retiree de la file ;
  ///  - **echec reseau / 5xx / 429** : conservee, retentee avec un delai
  ///    croissant ;
  ///  - **4xx metier** : conservee mais **plus rejouee**, et remontee a
  ///    l'operateur.
  Future<PosSyncProgress> flushQueueWithProgress({
    void Function(int synced, int total)? onProgress,
  }) async {
    const engine = PosSyncEngine();
    final pending = queue.pending();
    final total = pending.length;

    if (total == 0) {
      return const PosSyncProgress();
    }

    final operations = <PosSyncOperation>[];
    var synced = 0;

    for (final operation in pending) {
      final current = PosSyncOperation(
        idempotencyKey: operation.idempotencyKey,
        attempts: operation.attempts,
        lastAttemptAt: operation.createdAt,
        lastError: operation.lastError,
      );

      if (current.state == PosSyncOpState.conflict) {
        operations.add(current);
        continue;
      }

      try {
        await dio.post<Map<String, dynamic>>(operation.endpoint, data: operation.payload);
        await queue.remove(operation.idempotencyKey);
        synced++;
        operations.add(
          engine.applyOutcome(current, statusCode: 200, at: DateTime.now()),
        );
      } on DioException catch (error) {
        final statusCode = error.response?.statusCode;
        final message = error.message ?? 'network';

        await queue.recordFailure(operation.idempotencyKey, message);
        operations.add(
          engine.applyOutcome(
            current,
            statusCode: statusCode,
            error: message,
            at: DateTime.now(),
          ),
        );
      }

      onProgress?.call(synced, total);
    }

    return engine.summarize(operations).progress;
  }

  /// Rejoue la file d'attente hors ligne (compatibilite).
  Future<int> flushQueue() async {
    final progress = await flushQueueWithProgress();

    return progress.synced;
  }
}

/// Résultat d'un chargement de catalogue, avec sa provenance.
class CatalogResult {
  const CatalogResult({
    required this.categories,
    required this.products,
    required this.fromCache,
  });

  final List<PosCategory> categories;
  final List<PosProduct> products;
  final bool fromCache;
}

/// Résultat d'un encaissement : immédiat ou mis en file.
class PaymentOutcome {
  const PaymentOutcome({required this.queued});

  final bool queued;
}

/// Levée quand une opération exige le réseau et qu'il est absent.
class PosOfflineException implements Exception {
  PosOfflineException(this.message, this.cause);

  final String message;
  final Object cause;

  @override
  String toString() => message;
}

/// Providers Riverpod du POS Restaurant tablette.
final posLocalStoreProvider = Provider<PosLocalStore>((ref) {
  throw UnimplementedError('PosLocalStore doit être surchargé au bootstrap.');
});

final posOfflineQueueProvider = Provider<PosOfflineQueue>((ref) {
  throw UnimplementedError('PosOfflineQueue doit être surchargé au bootstrap.');
});

final posApiClientProvider = Provider<PosApiClient>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: const String.fromEnvironment('API_BASE_URL', defaultValue: 'https://api.leopardo.app/api/v1'),
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 20),
    ),
  );

  return PosApiClient(
    dio: dio,
    store: ref.watch(posLocalStoreProvider),
    queue: ref.watch(posOfflineQueueProvider),
  );
});
