import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/restaurant_delivery.dart';
import 'package:leopardo_core/models/restaurant_kpis.dart';
import 'package:leopardo_core/models/restaurant_order.dart';
import 'package:leopardo_core/models/restaurant_pos_session.dart';
import 'package:leopardo_core/models/restaurant_stock_alert.dart';
import 'package:leopardo_core/models/restaurant_sync.dart';
import 'package:leopardo_core/models/restaurant_table.dart';

/// Repository des surfaces mobiles RestaurantManager (RESTO-801..804,
/// issues #6222..#6225).
///
/// Tous les appels sont authentifiés Sanctum, tenant-scope, et suivent les
/// invariants serveur (machine à états, montants vérifiés, 404 sûr
/// cross-tenant). Contrats stables : `docs/mobile/RESTO_MOBILE.md`.
class RestaurantRepository {
  RestaurantRepository(this.apiClient);

  final ApiClient apiClient;

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);

  // ── Serveur (RESTO-801/#6222) ────────────────────────────────────────

  Future<List<RestaurantOrder>> serverOrders() async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/server/orders',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return extractDataList(response.data)
        .map((e) => RestaurantOrder.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<RestaurantTable>> serverTables() async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/server/tables',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return extractDataList(response.data)
        .map((e) => RestaurantTable.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<RestaurantOrder> serveOrder(int orderId) async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/server/orders/$orderId/serve',
      method: 'POST',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return RestaurantOrder.fromJson(extractDataMap(response.data));
  }

  Future<Map<String, dynamic>> payOrder(
    int orderId, {
    required int amountMinor,
    int? tipMinor,
    String? idempotencyKey,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/server/orders/$orderId/pay',
      method: 'POST',
      data: {
        'amount_minor': amountMinor,
        if (tipMinor != null) 'tip_minor': tipMinor,
        if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return extractDataMap(response.data);
  }

  // ── Livreur (RESTO-802/#6223) ────────────────────────────────────────

  Future<List<RestaurantDelivery>> riderDeliveries() async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/rider/deliveries',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return extractDataList(response.data)
        .map((e) => RestaurantDelivery.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<RestaurantDelivery> riderDelivery(int deliveryId) async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/rider/deliveries/$deliveryId',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return RestaurantDelivery.fromJson(extractDataMap(response.data));
  }

  Future<RestaurantDelivery> outForDelivery(int deliveryId) async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/rider/deliveries/$deliveryId/out-for-delivery',
      method: 'POST',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return RestaurantDelivery.fromJson(extractDataMap(response.data));
  }

  Future<RestaurantDelivery> deliver(int deliveryId) async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/rider/deliveries/$deliveryId/deliver',
      method: 'POST',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return RestaurantDelivery.fromJson(extractDataMap(response.data));
  }

  // ── Gérant (RESTO-803/#6224) ─────────────────────────────────────────

  Future<RestaurantKpis> managerKpis() async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/manager/kpis',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return RestaurantKpis.fromJson(extractDataMap(response.data));
  }

  Future<List<RestaurantStockAlert>> managerStockAlerts() async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/manager/stock-alerts',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return extractDataList(response.data)
        .map((e) => RestaurantStockAlert.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<RestaurantPosSession?> currentPosSession() async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/manager/pos-sessions/current',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final map = extractDataMap(response.data);
    if (map.isEmpty) return null;
    return RestaurantPosSession.fromJson(map);
  }

  Future<RestaurantPosSession> closePosSession(
    int sessionId, {
    required int countedCashMinor,
    String? varianceReason,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/manager/pos-sessions/$sessionId/close',
      method: 'POST',
      data: {
        'counted_cash_minor': countedCashMinor,
        if (varianceReason != null) 'variance_reason': varianceReason,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return RestaurantPosSession.fromJson(extractDataMap(response.data));
  }

  // ── Offline (RESTO-804/#6225) ────────────────────────────────────────

  /// Rejoue une file d'opérations hors ligne. Le serveur déduplique par
  /// `idempotency_key` : un rejeu ne crée jamais de doublon. Borné à 50
  /// opérations par appel (invariant serveur).
  Future<List<RestaurantSyncResult>> syncOffline(
    List<RestaurantSyncOperation> operations,
  ) async {
    if (operations.isEmpty) return const [];
    final response = await apiClient.requestWithRetry(
      '/restaurant/mobile/sync',
      method: 'POST',
      data: {'operations': operations.map((o) => o.toJson()).toList()},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return extractDataList(response.data)
        .map((e) => RestaurantSyncResult.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}
