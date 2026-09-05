import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/features/restaurant/data/restaurant_repository.dart';
import 'package:leopardo_manager/features/restaurant/services/restaurant_offline_queue.dart';

import '../../helpers/mobile_test_harness.dart';

void main() {
  late Directory tempDir;

  setUpAll(() async {
    tempDir = await Directory.systemTemp.createTemp('hive_resto_offline_test');
    Hive.init(tempDir.path);
  });

  tearDownAll(() async {
    await Hive.deleteFromDisk();
    tempDir.deleteSync(recursive: true);
  });

  ApiClient clientWithSyncResponse(List<Map<String, dynamic>> statuses) {
    final client = ApiClient(FakeSecureStorage(), FakeAppPreferences());
    client.dio.interceptors.insert(
      0,
      InterceptorsWrapper(
        onRequest: (options, handler) {
          // Le serveur répond idempotemment : il reprend les CLÉS envoyées par
          // le client (mobile-<ts>-<n>) et associe un statut à chacune, dans
          // l'ordre de la liste fournie par le test.
          final body = (options.data as Map<String, dynamic>);
          final ops = (body['operations'] as List<dynamic>? ?? const []);
          final data = <Map<String, dynamic>>[];
          for (var i = 0; i < ops.length; i++) {
            final op = ops[i] as Map<String, dynamic>;
            final status =
                i < statuses.length ? statuses[i]['status'] : 'error';
            data.add({
              'idempotency_key': op['idempotency_key'],
              'status': status,
            });
          }
          handler.resolve(
            Response(
              requestOptions: options,
              statusCode: 200,
              data: {'data': data},
            ),
          );
        },
      ),
    );
    return client;
  }

  test('enqueue stores operations with client idempotency keys', () {
    final queue = RestaurantOfflineQueue(
      RestaurantRepository(clientWithSyncResponse(const [])),
    );

    queue.enqueue(type: 'order.pay', payload: const {'order_id': 12});
    queue.enqueue(
      type: 'order.add_item',
      payload: const {'order_id': 12, 'product_id': 3},
    );

    expect(queue.hasPending, isTrue);
    expect(queue.pending, hasLength(2));
    expect(queue.pending[0].idempotencyKey, isNotEmpty);
    expect(
      queue.pending[1].idempotencyKey,
      isNot(queue.pending[0].idempotencyKey),
    );
  });

  test(
    'flush removes created and duplicate operations, keeps errors',
    () async {
      final queue = RestaurantOfflineQueue(
        RestaurantRepository(
          clientWithSyncResponse(const [
            {'status': 'created'},
            {'status': 'duplicate'},
            {'status': 'error', 'message': 'CONFLICT'},
          ]),
        ),
      );

      queue.enqueue(type: 'order.pay', payload: const {'order_id': 1});
      queue.enqueue(type: 'order.pay', payload: const {'order_id': 2});
      queue.enqueue(type: 'order.pay', payload: const {'order_id': 3});

      final results = await queue.flush();

      expect(results, hasLength(3));
      // created + duplicate sont retirés ; error reste pour retry.
      expect(queue.hasPending, isTrue);
      expect(queue.pending, hasLength(1));
      expect(queue.pending.single.idempotencyKey, results.last.idempotencyKey);
      expect(queue.history, hasLength(3));
    },
  );

  test(
    'replay never creates duplicates — server answers duplicate on rejeu',
    () async {
      // Premier rejeu : la clé est rejouée, le serveur répond duplicate.
      final queue = RestaurantOfflineQueue(
        RestaurantRepository(
          clientWithSyncResponse(const [
            {'status': 'duplicate'},
          ]),
        ),
      );

      queue.enqueue(type: 'order.pay', payload: const {'order_id': 12});
      final first = await queue.flush();
      final second = await queue.flush();

      // Après un duplicate, l'opération est retirée : le rejeu ne crée rien.
      expect(first.single.status, 'duplicate');
      expect(second, isEmpty);
      expect(queue.hasPending, isFalse);
    },
  );

  test('flush with empty queue returns no result', () async {
    final queue = RestaurantOfflineQueue(
      RestaurantRepository(clientWithSyncResponse(const [])),
    );

    final results = await queue.flush();

    expect(results, isEmpty);
  });
}
