import 'package:dio/dio.dart';
import 'package:leopardo_core/features/stock/data/stock_repository.dart';
import 'package:leopardo_core/models/stock_models.dart';

import '../offline/retail_offline_queue.dart';

/// Source de stock du POS Retail tablette (#9274).
///
/// Le POS a son **propre** client Dio (base URL injectée au déploiement, file
/// offline Hive, cache local) : il ne peut pas passer par l'`ApiClient` de
/// `leopardo_core`. Cette classe implémente donc [StockDataSource] au-dessus de
/// ce Dio, ce qui permet à l'écran partagé `StockScreen` de servir la caisse
/// **sans en dupliquer une ligne**.
///
/// **Offline-first, comme les paiements** : une réception saisie sans réseau
/// est mise en file avec une clé d'idempotence **stable** (dérivée du contenu
/// de l'opération, pas d'un UUID aléatoire). Le rejeu après reconnexion ne
/// double donc pas la quantité — le backend Retail déduplique par
/// `(company_id, idempotency_key)` (#9270).
///
/// Une opération **refusée par le serveur (4xx)** n'est PAS mise en file : la
/// rejouer ne ferait que la refuser encore, en masquant le vrai problème. Le
/// refus remonte à l'écran, qui l'affiche tel quel.
class RetailPosStockDataSource implements StockDataSource {
  RetailPosStockDataSource({required this.dio, required this.queue});

  final Dio dio;
  final RetailOfflineQueue queue;

  static const _readTimeout = Duration(seconds: 8);

  /// Clé d'idempotence **stable** pour une réception / un ajustement.
  ///
  /// Dérivée de (emplacement, produit, delta, motif, note) : deux saisies
  /// identiques produisent la même clé, donc un rejeu est sans effet. Un
  /// horodatage n'entre PAS dans la clé — il rendrait chaque rejeu unique et
  /// donc inutile.
  static String movementKey({
    required int locationId,
    required int productId,
    required double quantityDelta,
    required String reasonCode,
    String? note,
  }) {
    final normalizedDelta = quantityDelta.toStringAsFixed(3);
    final normalizedNote = (note ?? '').trim().toLowerCase();

    return 'retail-stock-$locationId-$productId-$normalizedDelta-$reasonCode-$normalizedNote';
  }

  @override
  Future<List<StockLevel>> levels({
    int? locationId,
    int? productId,
    bool belowThresholdOnly = false,
    int perPage = 50,
  }) async {
    final response = await dio.get<Map<String, dynamic>>(
      '/retail/stock/levels',
      queryParameters: {
        if (locationId != null) 'location_id': locationId,
        if (productId != null) 'product_id': productId,
        if (belowThresholdOnly) 'below_threshold': true,
        'per_page': perPage,
      },
      options: Options(receiveTimeout: _readTimeout),
    );

    return _list(response.data).map((raw) => StockLevel.fromJson(raw)).toList();
  }

  @override
  Future<List<StockMovement>> movements({
    int? locationId,
    int? productId,
    String? reasonCode,
    int perPage = 50,
  }) async {
    final response = await dio.get<Map<String, dynamic>>(
      '/retail/stock/movements',
      queryParameters: {
        if (locationId != null) 'location_id': locationId,
        if (productId != null) 'product_id': productId,
        if (reasonCode != null) 'reason_code': reasonCode,
        'per_page': perPage,
      },
      options: Options(receiveTimeout: _readTimeout),
    );

    return _list(response.data)
        .map((raw) => StockMovement.fromJson(raw))
        .toList();
  }

  @override
  Future<List<StockAlert>> alerts({int? locationId, int perPage = 50}) async {
    final response = await dio.get<Map<String, dynamic>>(
      '/retail/stock/alerts',
      queryParameters: {
        if (locationId != null) 'location_id': locationId,
        'per_page': perPage,
      },
      options: Options(receiveTimeout: _readTimeout),
    );

    return _list(response.data).map((raw) => StockAlert.fromJson(raw)).toList();
  }

  @override
  Future<StockMovement> recordMovement({
    required int locationId,
    required int productId,
    required double quantityDelta,
    required StockMovementReason reason,
    int? unitCostMinor,
    String? note,
    String? idempotencyKey,
  }) async {
    final wireCode = reason.wireCode;

    if (wireCode == null) {
      throw StockWriteException(
        'Ce motif ne peut pas etre saisi manuellement.',
        statusCode: null,
      );
    }

    final key = idempotencyKey ??
        movementKey(
          locationId: locationId,
          productId: productId,
          quantityDelta: quantityDelta,
          reasonCode: wireCode,
          note: note,
        );

    final payload = <String, dynamic>{
      'location_id': locationId,
      'product_id': productId,
      'quantity_delta': quantityDelta,
      'reason_code': wireCode,
      if (unitCostMinor != null) 'unit_cost_minor': unitCostMinor,
      if (note != null && note.isNotEmpty) 'note': note,
      'idempotency_key': key,
    };

    try {
      final response = await dio.post<Map<String, dynamic>>(
        '/retail/stock/movements',
        data: payload,
      );

      final data = response.data?['data'];
      final movement = data is Map ? data['movement'] : null;

      if (movement is Map) {
        return StockMovement.fromJson(movement.cast<String, dynamic>());
      }

      if (data is Map) {
        return StockMovement.fromJson(data.cast<String, dynamic>());
      }

      return StockMovement.fromJson(const {});
    } on DioException catch (error) {
      final statusCode = error.response?.statusCode;

      // 4xx métier : refus définitif, on ne met PAS en file.
      if (statusCode != null && statusCode >= 400 && statusCode < 500) {
        throw StockWriteException(
          _serverMessage(error) ?? 'Le mouvement de stock a ete refuse.',
          statusCode: statusCode,
          cause: error,
        );
      }

      // Réseau / 5xx / 429 : mise en file avec la clé stable. Le rejeu ne
      // doublera pas la quantité (idempotence serveur).
      await queue.enqueue(
        QueuedRetailOperation(
          idempotencyKey: key,
          endpoint: '/retail/stock/movements',
          payload: payload,
          createdAt: DateTime.now(),
        ),
      );

      throw StockWriteException(
        'Hors ligne : mouvement mis en file, il sera envoye a la reconnexion.',
        statusCode: statusCode,
        cause: error,
      );
    }
  }

  /// Message serveur (422 « Stock insuffisant ») quand il existe.
  String? _serverMessage(DioException error) {
    final data = error.response?.data;

    if (data is Map) {
      final message = data['message'];
      if (message is String && message.trim().isNotEmpty) {
        return message;
      }

      final errors = data['errors'];
      if (errors is Map) {
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty && value.first is String) {
            return value.first as String;
          }
        }
      }
    }

    return null;
  }

  List<Map<String, dynamic>> _list(Map<String, dynamic>? payload) {
    if (payload == null) {
      return const [];
    }

    final data = payload['data'];

    if (data is List) {
      return data
          .whereType<Map<dynamic, dynamic>>()
          .map((e) => e.cast<String, dynamic>())
          .toList();
    }

    if (data is Map && data['data'] is List) {
      return (data['data'] as List)
          .whereType<Map<dynamic, dynamic>>()
          .map((e) => e.cast<String, dynamic>())
          .toList();
    }

    return const [];
  }
}
