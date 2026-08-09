// ============================================================
// AttendanceOfflineService tests — issue #1296.
//
// There was previously no Dart test coverage under lib/offline/ at all.
// These tests exercise the three real branches of checkIn()/checkOut():
//   1. Fully offline (SyncMode.offline)              -> always local.
//   2. Online + API succeeds (SyncMode.cloud/edge)    -> remote write.
//   3. Online + API call fails (DioException)         -> local fallback.
// ============================================================

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/offline/database/edge_database.dart';
import 'package:leopardo_core/offline/services/attendance_offline_service.dart';
import 'package:leopardo_core/offline/services/sync_service.dart';

import 'support/fake_http_adapter.dart';

void main() {
  late EdgeDatabase db;
  late Dio dio;
  late FakeHttpAdapter adapter;
  late SyncService syncService;
  late AttendanceOfflineService service;

  setUp(() {
    db = EdgeDatabase.forTesting(NativeDatabase.memory());
    dio = Dio();
    adapter = FakeHttpAdapter();
    dio.httpClientAdapter = adapter;
    syncService = SyncService(
      db: db,
      dio: dio,
      edgeBaseUrl: 'http://edge.local',
      cloudBaseUrl: 'https://cloud.example',
      edgeNodeId: 'node-1',
      edgeToken: 'token-1',
    );
    service = AttendanceOfflineService(
      db: db,
      syncService: syncService,
      dio: dio,
    );
  });

  tearDown(() async {
    await db.close();
  });

  group('checkIn', () {
    test('offline mode always saves locally without calling the API', () async {
      // currentMode defaults to SyncMode.offline until _detectMode() runs
      // (start() is never called here), which is exactly the offline path.
      final result = await service.checkIn(
        employeeId: 'emp-1',
        companyId: 'co-1',
        gpsLat: 1.23,
        gpsLng: 4.56,
      );

      expect(result.savedLocally, isTrue);
      expect(result.synced, isFalse);
      expect(result.fallback, isFalse);
      expect(adapter.requests, isEmpty);

      final logs = await db.getAttendanceLogs('emp-1');
      expect(logs, hasLength(1));
      expect(logs.single.companyId, 'co-1');
      expect(logs.single.syncStatus, 'pending');

      final pending = await db.getPendingItems();
      expect(pending, hasLength(1));
      expect(pending.single.entityType, 'attendance_logs');
      expect(pending.single.operation, 'create');
    });

    test(
      'online mode with a successful API call does not touch local DB',
      () async {
        syncServiceSetMode(syncService, SyncMode.cloud);
        adapter.queueSuccess(
          data: {
            'data': {'id': 'remote-log-1'},
          },
        );

        final result = await service.checkIn(
          employeeId: 'emp-2',
          companyId: 'co-1',
        );

        expect(result.id, 'remote-log-1');
        expect(result.savedLocally, isFalse);
        expect(result.synced, isTrue);
        expect(adapter.requests, hasLength(1));
        expect(
          adapter.requests.single.path,
          contains('/v1/attendance/check-in'),
        );

        final logs = await db.getAttendanceLogs('emp-2');
        expect(logs, isEmpty);
      },
    );

    test(
      'online mode with a failing API call falls back to local storage',
      () async {
        syncServiceSetMode(syncService, SyncMode.cloud);
        adapter.queueFailure();

        final result = await service.checkIn(
          employeeId: 'emp-3',
          companyId: 'co-1',
        );

        expect(result.savedLocally, isTrue);
        expect(result.synced, isFalse);
        expect(result.fallback, isTrue);

        final logs = await db.getAttendanceLogs('emp-3');
        expect(logs, hasLength(1));
      },
    );
  });

  group('checkOut', () {
    test('offline mode checks out the local record directly', () async {
      final id = await db.insertAttendanceLog(
        LocalAttendanceLogsCompanion.insert(
          employeeId: 'emp-4',
          companyId: 'co-1',
          checkIn: DateTime.now(),
        ),
      );

      await service.checkOut(logId: id);

      final logs = await db.getAttendanceLogs('emp-4');
      expect(logs.single.checkOut, isNotNull);
      expect(adapter.requests, isEmpty);
    });

    test('isLocalId forces a local checkout even in online mode', () async {
      syncServiceSetMode(syncService, SyncMode.cloud);
      final id = await db.insertAttendanceLog(
        LocalAttendanceLogsCompanion.insert(
          employeeId: 'emp-5',
          companyId: 'co-1',
          checkIn: DateTime.now(),
        ),
      );

      await service.checkOut(logId: id, isLocalId: true);

      final logs = await db.getAttendanceLogs('emp-5');
      expect(logs.single.checkOut, isNotNull);
      expect(adapter.requests, isEmpty);
    });

    test(
      'online mode with a failing API call falls back to local checkout',
      () async {
        syncServiceSetMode(syncService, SyncMode.cloud);
        final id = await db.insertAttendanceLog(
          LocalAttendanceLogsCompanion.insert(
            employeeId: 'emp-6',
            companyId: 'co-1',
            checkIn: DateTime.now(),
          ),
        );
        adapter.queueFailure();

        await service.checkOut(logId: id);

        expect(adapter.requests, hasLength(1));
        final logs = await db.getAttendanceLogs('emp-6');
        expect(logs.single.checkOut, isNotNull);
      },
    );
  });
}

/// SyncMode is normally only mutated internally via real connectivity/health
/// probes, which aren't relevant to these tests, so this helper just forwards
/// to the `@visibleForTesting` hook on [SyncService].
void syncServiceSetMode(SyncService service, SyncMode mode) {
  service.debugSetModeForTesting(mode);
}
