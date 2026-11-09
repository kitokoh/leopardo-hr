import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/pos/pos_sync.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

import '../models/retail_models.dart';
import '../offline/retail_offline_queue.dart';
import '../storage/retail_local_store.dart';

/// Client API du POS Retail tablette (#9215).
///
/// Contrat **offline-first** : chaque lecture tente le reseau, retombe sur le
/// cache local, et signale a l'appelant d'ou vient la donnee (`fromCache`).
/// L'interface peut ainsi afficher honnetement « hors ligne » au lieu de
/// laisser croire que le catalogue est a jour.
class RetailApiClient {
  RetailApiClient({required this.dio, required this.store, required this.queue});

  final Dio dio;
  final RetailLocalStore store;
  final RetailOfflineQueue queue;

  /// Resultat d'une lecture, avec sa provenance.
  Future<RetailCatalogResult> loadCatalog({int? locationId}) async {
    try {
      final response = await dio.get<Map<String, dynamic>>(
        '/retail/products',
        queryParameters: locationId == null ? null : {'location_id': locationId},
      );

      final data = response.data?['data'] as Map<String, dynamic>? ?? const {};

      final categories = ((data['categories'] as List<dynamic>?) ?? [])
          .map((raw) => RetailCategory.fromJson(raw as Map<String, dynamic>))
          .toList();

      final products = ((data['products'] as List<dynamic>?) ?? [])
          .map((raw) => RetailProduct.fromJson(raw as Map<String, dynamic>))
          .toList();

      await store.saveCatalog(categories: categories, products: products);

      return RetailCatalogResult(
        categories: categories,
        products: products,
        fromCache: false,
      );
    } on DioException {
      return RetailCatalogResult(
        categories: store.readCategories(),
        products: store.readProducts(),
        fromCache: true,
      );
    }
  }

  /// Ouvre une session de caisse.
  ///
  /// **En ligne uniquement** : une session a besoin d'un identifiant serveur
  /// (elle porte le fonds de caisse et l'attendu de cloture). Hors ligne,
  /// l'appelant recoit une [RetailOfflineException] et l'interface le dit.
  Future<RetailSession> openSession({
    required int locationId,
    required int openingCashMinor,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/retail/pos/sessions',
        data: {'location_id': locationId, 'opening_cash_minor': openingCashMinor},
      );

      final data = response.data?['data'] as Map<String, dynamic>? ?? const {};
      final session = RetailSession.fromJson(data);

      await store.saveSession(session);

      return session;
    } on DioException catch (error) {
      throw RetailOfflineException('Ouverture de caisse impossible hors ligne.', error);
    }
  }

  /// Cloture la session avec le comptage physique.
  Future<RetailSession> closeSession({
    required int sessionId,
    required int countedCashMinor,
    String? varianceReason,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/retail/pos/sessions/$sessionId/close',
        data: {
          'counted_cash_minor': countedCashMinor,
          if (varianceReason != null) 'variance_reason': varianceReason,
        },
      );

      final data = response.data?['data'] as Map<String, dynamic>? ?? const {};
      final session = RetailSession.fromJson(data);

      await store.clearSession();

      return session;
    } on DioException catch (error) {
      throw RetailOfflineException('Cloture de caisse impossible hors ligne.', error);
    }
  }

  /// Enregistre un mouvement de tiroir-caisse (#9212).
  Future<void> recordCashMovement({
    required int sessionId,
    required RetailCashMovement movement,
  }) async {
    try {
      await dio.post<Map<String, dynamic>>(
        '/retail/pos/sessions/$sessionId/cash-movements',
        data: movement.toJson(),
      );
    } on DioException catch (error) {
      throw RetailOfflineException('Mouvement de caisse impossible hors ligne.', error);
    }
  }

  /// Cree une commande (en ligne uniquement — meme raison que la session).
  Future<int> createOrder({
    required int sessionId,
    required RetailCart cart,
  }) async {
    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/retail/pos/orders',
        data: {
          'pos_session_id': sessionId,
          'note': cart.note,
          'discount_minor': cart.discountMinor,
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
      throw RetailOfflineException('Creation de commande impossible hors ligne.', error);
    }
  }

  /// Encaisse une commande.
  ///
  /// **Fonctionne hors ligne** : l'operation est mise en file avec une cle
  /// d'idempotence stable. Le rejeu apres reconnexion ne double pas
  /// l'encaissement.
  Future<RetailPaymentOutcome> pay({
    required int orderId,
    required int amountMinor,
    required RetailPaymentMethod method,
  }) async {
    final key = RetailOfflineQueue.paymentKey(
      orderId: orderId,
      amountMinor: amountMinor,
      method: method.wireValue,
    );

    final payload = {
      'method': method.wireValue,
      'amount_minor': amountMinor,
      'idempotency_key': key,
    };

    try {
      await dio.post<Map<String, dynamic>>(
        '/retail/pos/orders/$orderId/payments',
        data: payload,
      );

      return const RetailPaymentOutcome(queued: false);
    } on DioException {
      await queue.enqueue(
        QueuedRetailOperation(
          idempotencyKey: key,
          endpoint: '/retail/pos/orders/$orderId/payments',
          payload: payload,
          createdAt: DateTime.now(),
        ),
      );

      return const RetailPaymentOutcome(queued: true);
    }
  }

  /// Rembourse une commande encaissee (#9208).
  ///
  /// **Fonctionne hors ligne** : meme contrat d'idempotence que l'encaissement.
  Future<RetailPaymentOutcome> refund({
    required int orderId,
    required RetailRefund refund,
  }) async {
    final key = RetailOfflineQueue.refundKey(
      orderId: orderId,
      amountMinor: refund.amountMinor,
      method: refund.method.wireValue,
    );

    final payload = {
      ...refund.toJson(),
      'idempotency_key': key,
    };

    try {
      await dio.post<Map<String, dynamic>>(
        '/retail/pos/orders/$orderId/refund',
        data: payload,
      );

      return const RetailPaymentOutcome(queued: false);
    } on DioException {
      await queue.enqueue(
        QueuedRetailOperation(
          idempotencyKey: key,
          endpoint: '/retail/pos/orders/$orderId/refund',
          payload: payload,
          createdAt: DateTime.now(),
        ),
      );

      return const RetailPaymentOutcome(queued: true);
    }
  }

  /// Rejoue la file d'attente hors ligne, avec progression et gestion des
  /// conflits.
  ///
  /// Le moteur partage (`PosSyncEngine`) decide du sort de chaque operation :
  ///  - **2xx** : retiree de la file ;
  ///  - **echec reseau / 5xx / 429** : conservee, retentee avec un delai
  ///    croissant (on ne martele pas le serveur pendant une coupure) ;
  ///  - **4xx metier** : conservee mais **plus rejouee**, et remontee a
  ///    l'operateur — reessayer un remboursement refuse ne fera que le refuser
  ///    encore, en masquant le vrai probleme.
  ///
  /// [onProgress] est appele apres chaque operation pour alimenter le bandeau.
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

      // Une operation deja classee en conflit n'est plus rejouee.
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

/// Resultat d'un chargement de catalogue, avec sa provenance.
class RetailCatalogResult {
  const RetailCatalogResult({
    required this.categories,
    required this.products,
    required this.fromCache,
  });

  final List<RetailCategory> categories;
  final List<RetailProduct> products;
  final bool fromCache;
}

/// Resultat d'un encaissement : immediat ou mis en file.
class RetailPaymentOutcome {
  const RetailPaymentOutcome({required this.queued});

  final bool queued;
}

/// Levee quand une operation exige le reseau et qu'il est absent.
class RetailOfflineException implements Exception {
  RetailOfflineException(this.message, this.cause);

  final String message;
  final Object cause;

  @override
  String toString() => message;
}

/// Providers Riverpod du POS Retail tablette.
final retailLocalStoreProvider = Provider<RetailLocalStore>((ref) {
  throw UnimplementedError('RetailLocalStore doit etre surcharge au bootstrap.');
});

final retailOfflineQueueProvider = Provider<RetailOfflineQueue>((ref) {
  throw UnimplementedError('RetailOfflineQueue doit etre surcharge au bootstrap.');
});

final retailApiClientProvider = Provider<RetailApiClient>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: const String.fromEnvironment(
        'API_BASE_URL',
        defaultValue: 'https://api.leopardo.app/api/v1',
      ),
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 20),
    ),
  );

  return RetailApiClient(
    dio: dio,
    store: ref.watch(retailLocalStoreProvider),
    queue: ref.watch(retailOfflineQueueProvider),
  );
});
