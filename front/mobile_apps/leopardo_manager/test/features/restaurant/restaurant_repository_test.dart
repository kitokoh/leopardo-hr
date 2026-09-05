import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/features/restaurant/data/restaurant_repository.dart';
import 'package:leopardo_core/models/restaurant_sync.dart';

import '../../helpers/mobile_test_harness.dart';

void main() {
  late Directory tempDir;

  setUpAll(() async {
    tempDir = await Directory.systemTemp.createTemp('hive_resto_test');
    Hive.init(tempDir.path);
  });

  tearDownAll(() async {
    await Hive.deleteFromDisk();
    tempDir.deleteSync(recursive: true);
  });

  ApiClient clientWithHandler(
    void Function(RequestOptions options, RequestInterceptorHandler handler)
        onRequest,
  ) {
    final client = ApiClient(FakeSecureStorage(), FakeAppPreferences());
    client.dio.interceptors.insert(
      0,
      InterceptorsWrapper(onRequest: onRequest),
    );
    return client;
  }

  test('serverOrders maps the service queue payload', () async {
    RequestOptions? captured;
    final repo = RestaurantRepository(
      clientWithHandler((options, handler) {
        captured = options;
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 200,
            data: {
              'data': [
                {
                  'id': 12,
                  'reference': 'CMD-0007',
                  'status': 'ready',
                  'order_type': 'dine_in',
                  'table_name': 'T3',
                  'total_minor': 4500,
                  'currency': 'EUR',
                  'items_count': 3,
                },
              ],
            },
          ),
        );
      }),
    );

    final orders = await repo.serverOrders();

    expect(captured?.path, '/restaurant/mobile/server/orders');
    expect(orders, hasLength(1));
    expect(orders.first.id, 12);
    expect(orders.first.reference, 'CMD-0007');
    expect(orders.first.isReady, isTrue);
    expect(orders.first.totalMinor, 4500);
    expect(orders.first.currency, 'EUR');
  });

  test('payOrder sends amount/tip/idempotency and maps payment', () async {
    RequestOptions? captured;
    final repo = RestaurantRepository(
      clientWithHandler((options, handler) {
        captured = options;
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 200,
            data: {
              'data': {
                'payment_id': 99,
                'provider_code': 'cash',
                'status': 'captured',
                'amount_minor': 4500,
              },
            },
          ),
        );
      }),
    );

    final payment = await repo.payOrder(
      12,
      amountMinor: 4500,
      tipMinor: 200,
      idempotencyKey: 'mobile-1',
    );

    expect(captured?.method, 'POST');
    expect(captured?.path, '/restaurant/mobile/server/orders/12/pay');
    expect((captured?.data as Map)['amount_minor'], 4500);
    expect((captured?.data as Map)['tip_minor'], 200);
    expect((captured?.data as Map)['idempotency_key'], 'mobile-1');
    expect(payment['payment_id'], 99);
    expect(payment['status'], 'captured');
  });

  test('riderDeliveries maps assigned deliveries', () async {
    final repo = RestaurantRepository(
      clientWithHandler((options, handler) {
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 200,
            data: {
              'data': [
                {
                  'id': 3,
                  'reference': 'LIV-0001',
                  'status': 'assigned',
                  'customer_name': 'Yacine',
                  'customer_phone': '+213555',
                  'address': 'Rue 1',
                  'fee_minor': 500,
                  'order_total_minor': 3000,
                },
              ],
            },
          ),
        );
      }),
    );

    final deliveries = await repo.riderDeliveries();

    expect(deliveries, hasLength(1));
    expect(deliveries.first.isAssigned, isTrue);
    expect(deliveries.first.customerName, 'Yacine');
    expect(deliveries.first.feeMinor, 500);
  });

  test('deliver transition posts and maps status', () async {
    final repo = RestaurantRepository(
      clientWithHandler((options, handler) {
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 200,
            data: {
              'data': {
                'id': 3,
                'status': 'delivered',
                'delivered_at': '2026-08-31T10:00:00Z',
              },
            },
          ),
        );
      }),
    );

    final delivery = await repo.deliver(3);

    expect(delivery.status, 'delivered');
    expect(delivery.deliveredAt, isNotNull);
  });

  test('managerKpis maps server-side aggregates', () async {
    final repo = RestaurantRepository(
      clientWithHandler((options, handler) {
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 200,
            data: {
              'data': {
                'today_revenue_minor': 120000,
                'orders_count': 30,
                'avg_basket_minor': 4000,
                'tables_opened_today': 8,
                'currency': 'DZD',
              },
            },
          ),
        );
      }),
    );

    final kpis = await repo.managerKpis();

    expect(kpis.todayRevenueMinor, 120000);
    expect(kpis.ordersCount, 30);
    expect(kpis.avgBasketMinor, 4000);
    expect(kpis.tablesOpenedToday, 8);
    expect(kpis.currency, 'DZD');
  });

  test('syncOffline posts operations and maps per-op status', () async {
    RequestOptions? captured;
    final repo = RestaurantRepository(
      clientWithHandler((options, handler) {
        captured = options;
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 200,
            data: {
              'data': [
                {
                  'type': 'order.pay',
                  'idempotency_key': 'mobile-1',
                  'status': 'created',
                },
                {
                  'type': 'order.pay',
                  'idempotency_key': 'mobile-2',
                  'status': 'duplicate',
                },
              ],
            },
          ),
        );
      }),
    );

    final results = await repo.syncOffline(const [
      RestaurantSyncOperation(
        type: 'order.pay',
        idempotencyKey: 'mobile-1',
        payload: {'order_id': 12},
      ),
      RestaurantSyncOperation(
        type: 'order.pay',
        idempotencyKey: 'mobile-2',
        payload: {'order_id': 12},
      ),
    ]);

    expect(captured?.path, '/restaurant/mobile/sync');
    expect((captured?.data as Map)['operations'], isA<List>());
    expect(results, hasLength(2));
    expect(results.first.status, 'created');
    expect(results.last.isDuplicate, isTrue);
  });
}
