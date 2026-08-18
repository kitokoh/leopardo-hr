// ============================================================
// SyncService tests — issue #1296.
//
// Covers syncNow(): the offline/skipped short-circuits, marking pending
// queue items synced on a successful push, marking them failed (for
// retry) on a transient push failure, and applying an employees delta
// from the pull response.
// ============================================================

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/offline/database/edge_database.dart';
import 'package:leopardo_core/offline/services/sync_service.dart';

import 'support/fake_http_adapter.dart';

void main() {
  late EdgeDatabase db;
  late Dio dio;
  late FakeHttpAdapter adapter;
  late SyncService service;

  setUp(() {
    db = EdgeDatabase.forTesting(NativeDatabase.memory());
    dio = Dio();
    adapter = FakeHttpAdapter();
    dio.httpClientAdapter = adapter;
    service = SyncService(
      db: db,
      dio: dio,
      edgeBaseUrl: 'http://edge.local',
      cloudBaseUrl: 'https://cloud.example',
      edgeNodeId: 'node-1',
      edgeToken: 'token-1',
    );
  });

  tearDown(() async {
    await db.close();
  });

  test('defaults: Dio injected without options gets connect/receive timeouts', () async {
    // Issue #4406 : les providers passent `Dio()` nu — SyncService doit
    // garantir des bornes par défaut pour qu'une requête pendante ne bloque
    // jamais la sync (le ping garde son propre timeout court par requête).
    expect(dio.options.connectTimeout, const Duration(seconds: 10));
    expect(dio.options.receiveTimeout, const Duration(seconds: 30));
  });

  test('syncNow() short-circuits while offline and never calls the API', () async {
    final result = await service.syncNow();

    expect(result.offline, isTrue);
    expect(result.success, isFalse);
    expect(adapter.requests, isEmpty);
  });

  test('syncNow() with nothing pending succeeds with zero sent/failed', () async {
    service.debugSetModeForTesting(SyncMode.cloud);
    // Pull delta call still happens; queue an empty successful response.
    adapter.queueSuccess(data: {'entities': <String, dynamic>{}});

    final result = await service.syncNow();

    expect(result.success, isTrue);
    expect(result.sent, 0);
    expect(result.failed, 0);
  });

  test('syncNow() pushes pending items and marks them synced on success', () async {
    service.debugSetModeForTesting(SyncMode.cloud);
    await db.insertAttendanceLog(
      LocalAttendanceLogsCompanion.insert(
        employeeId: 'emp-1',
        companyId: 'co-1',
        checkIn: DateTime.now(),
      ),
    );

    // First response = push, second response = pull.
    adapter.queueSuccess();
    adapter.queueSuccess(data: {'entities': <String, dynamic>{}});

    final result = await service.syncNow();

    expect(result.success, isTrue);
    expect(result.sent, 1);
    expect(result.failed, 0);
    expect(adapter.requests, hasLength(2));
    expect(adapter.requests.first.path, contains('/edge-node/node-1/push'));
    expect(adapter.requests.last.path, contains('/edge-node/node-1/pull'));

    final pending = await db.getPendingItems();
    expect(pending, isEmpty);
  });

  test('syncNow() marks items failed for retry on a transient push error', () async {
    service.debugSetModeForTesting(SyncMode.cloud);
    await db.insertAttendanceLog(
      LocalAttendanceLogsCompanion.insert(
        employeeId: 'emp-2',
        companyId: 'co-1',
        checkIn: DateTime.now(),
      ),
    );

    adapter.queueFailure();
    // Pull still runs after the push branch, regardless of push outcome.
    adapter.queueSuccess(data: {'entities': <String, dynamic>{}});

    final result = await service.syncNow();

    expect(result.success, isTrue);
    expect(result.sent, 0);
    expect(result.failed, 1);

    // #4960 : l'item échoué est re-piégé au cycle suivant (retry policy) —
    // attemptCount incrémenté, status 'failed' mais < maxSyncAttempts. Ce
    // n'est plus un drop silencieux.
    final pending = await db.getPendingItems();
    expect(pending, hasLength(1));
    expect(pending.first.status, 'failed');
    expect(pending.first.attemptCount, 1);
  });

  test('syncNow() stops retrying an item after maxSyncAttempts failures', () async {
    service.debugSetModeForTesting(SyncMode.cloud);
    await db.insertAttendanceLog(
      LocalAttendanceLogsCompanion.insert(
        employeeId: 'emp-2',
        companyId: 'co-1',
        checkIn: DateTime.now(),
      ),
    );

    // Simule 5 échecs de push successifs (statut terminal 'failed').
    for (var i = 0; i < EdgeDatabase.maxSyncAttempts; i++) {
      adapter.queueFailure();
      adapter.queueSuccess(data: {'entities': <String, dynamic>{}});
      await service.syncNow();
    }

    final pending = await db.getPendingItems();
    expect(pending, isEmpty, reason: 'item terminal — plus de retry');

    final item = await (db.select(db.localSyncQueue)).getSingle();
    expect(item.status, 'failed');
    expect(item.attemptCount, EdgeDatabase.maxSyncAttempts);
  });

  test('syncNow() applies an employees delta from the pull response', () async {
    service.debugSetModeForTesting(SyncMode.cloud);
    adapter.queueSuccess(data: {
      'entities': {
        'employees': [
          {
            'id': 'emp-9',
            'company_id': 'co-1',
            'first_name': 'Ada',
            'last_name': 'Lovelace',
            'email': 'ada@example.com',
          },
        ],
      },
    });

    final result = await service.syncNow();

    expect(result.success, isTrue);
    final employee = await db.findEmployee('emp-9');
    expect(employee, isNotNull);
    expect(employee!.firstName, 'Ada');
    expect(employee.email, 'ada@example.com');
  });

  test('apiBaseUrl reflects the current mode', () {
    expect(service.apiBaseUrl, 'http://edge.local/api'); // offline default

    service.debugSetModeForTesting(SyncMode.cloud);
    expect(service.apiBaseUrl, 'https://cloud.example/api');

    service.debugSetModeForTesting(SyncMode.edge);
    expect(service.apiBaseUrl, 'http://edge.local/api');
  });
}
