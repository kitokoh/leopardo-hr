import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/core_providers.dart';
import 'package:leopardo_core/features/restaurant/data/restaurant_repository.dart';
import 'package:leopardo_core/models/restaurant_delivery.dart';
import 'package:leopardo_core/models/restaurant_kpis.dart';
import 'package:leopardo_core/models/restaurant_order.dart';
import 'package:leopardo_core/models/restaurant_pos_session.dart';
import 'package:leopardo_core/models/restaurant_stock_alert.dart';
import 'package:leopardo_core/models/restaurant_table.dart';
import 'package:leopardo_manager/features/restaurant/services/restaurant_offline_queue.dart';

final restaurantRepositoryProvider = Provider<RestaurantRepository>((ref) {
  return RestaurantRepository(ref.watch(apiClientProvider));
});

final restaurantOfflineQueueProvider = Provider<RestaurantOfflineQueue>((ref) {
  return RestaurantOfflineQueue(ref.watch(restaurantRepositoryProvider));
});

// ── Serveur (RESTO-801/#6222) ────────────────────────────────────────────

final restaurantServerOrdersProvider =
    FutureProvider.autoDispose<List<RestaurantOrder>>((ref) {
  return ref.watch(restaurantRepositoryProvider).serverOrders();
});

final restaurantServerTablesProvider =
    FutureProvider.autoDispose<List<RestaurantTable>>((ref) {
  return ref.watch(restaurantRepositoryProvider).serverTables();
});

// ── Livreur (RESTO-802/#6223) ────────────────────────────────────────────

final restaurantRiderDeliveriesProvider =
    FutureProvider.autoDispose<List<RestaurantDelivery>>((ref) {
  return ref.watch(restaurantRepositoryProvider).riderDeliveries();
});

// ── Gérant (RESTO-803/#6224) ─────────────────────────────────────────────

final restaurantManagerKpisProvider =
    FutureProvider.autoDispose<RestaurantKpis>((ref) {
  return ref.watch(restaurantRepositoryProvider).managerKpis();
});

final restaurantManagerStockAlertsProvider =
    FutureProvider.autoDispose<List<RestaurantStockAlert>>((ref) {
  return ref.watch(restaurantRepositoryProvider).managerStockAlerts();
});

final restaurantManagerPosSessionProvider =
    FutureProvider.autoDispose<RestaurantPosSession?>((ref) {
  return ref.watch(restaurantRepositoryProvider).currentPosSession();
});
