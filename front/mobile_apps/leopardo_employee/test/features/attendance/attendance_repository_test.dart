import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_employee/features/attendance/data/attendance_repository.dart';

import '../../helpers/mobile_test_harness.dart';

/// Intercepteur qui enregistre les requêtes et répond avec un JSON d'log.
class _AttendanceInterceptor extends Interceptor {
  _AttendanceInterceptor({this.failWithNetworkError = false});

  final bool failWithNetworkError;
  final requests = <RequestOptions>[];

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    requests.add(options);
    if (failWithNetworkError) {
      handler.reject(
        DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
          message: 'connection refused',
        ),
      );
      return;
    }
    handler.resolve(
      Response(
        requestOptions: options,
        statusCode: 200,
        data: {
          'data': {
            'id': 42,
            'employee_id': 7,
            'date': '2026-08-09',
            'check_in': '2026-08-09T08:02:00Z',
            'check_out': '2026-08-09T16:03:00Z',
            'status': 'present',
            'work_type': 'normal',
            'punch_note': null,
          },
        },
      ),
    );
  }
}
Map<String, dynamic> _payloadOf(RequestOptions options) =>
    (options.data as Map).cast<String, dynamic>();

/// Tests critiques (issue #1560) — repository pointage : check-in / check-out,
/// payload GPS + note, mode hors-ligne (file Hive), corrections.
void main() {
  late Directory tempDir;
  late FakeSecureStorage storage;
  late FakeAppPreferences preferences;
  late ApiClient client;

  setUpAll(() async {
    tempDir = await Directory.systemTemp.createTemp('hive_test');
    Hive.init(tempDir.path);
  });

  tearDownAll(() async {
    await Hive.deleteFromDisk();
    tempDir.deleteSync(recursive: true);
  });

  setUp(() async {
    storage = FakeSecureStorage();
    preferences = FakeAppPreferences();
    client = ApiClient(storage, preferences);
  });

  test('checkIn envoie work_type + device_timezone et parse la réponse', () async {
    final interceptor = _AttendanceInterceptor();
    client.dio.interceptors.add(interceptor);

    final repository = AttendanceRepository(client);
    final log = await repository.checkIn(workType: 'normal');

    final request = interceptor.requests.single;
    expect(request.method, 'POST');
    expect(request.path, '/attendance/check-in');
    final payload = _payloadOf(request);
    expect(payload['work_type'], 'normal');
    expect(payload.containsKey('device_timezone'), isTrue);

    expect(log.id, 42);
    expect(log.employeeId, 7);
    expect(log.status, 'present');
    expect(log.workType, 'normal');
  });

  test('checkIn inclut GPS + note uniquement quand fournis', () async {
    final interceptor = _AttendanceInterceptor();
    client.dio.interceptors.add(interceptor);

    final repository = AttendanceRepository(client);
    await repository.checkIn(
      punchNote: '  Mission client  ',
      gpsLat: 36.7538,
      gpsLng: 3.0588,
      gpsAccuracy: 12.5,
    );

    final payload = _payloadOf(interceptor.requests.single);
    expect(payload['gps_lat'], 36.7538);
    expect(payload['gps_lng'], 3.0588);
    expect(payload['gps_accuracy'], 12.5);
    expect(payload['punch_note'], 'Mission client'); // trim
  });

  test('checkIn sans note ne met pas punch_note au payload', () async {
    final interceptor = _AttendanceInterceptor();
    client.dio.interceptors.add(interceptor);

    final repository = AttendanceRepository(client);
    await repository.checkIn();

    final payload = _payloadOf(interceptor.requests.single);
    expect(payload.containsKey('punch_note'), isFalse);
    expect(payload.containsKey('gps_lat'), isFalse);
  });

  test('checkOut appelle /attendance/check-out', () async {
    final interceptor = _AttendanceInterceptor();
    client.dio.interceptors.add(interceptor);

    final repository = AttendanceRepository(client);
    final log = await repository.checkOut();

    expect(interceptor.requests.single.method, 'POST');
    expect(interceptor.requests.single.path, '/attendance/check-out');
    expect(log.status, 'present');
  });

  test('checkIn hors-ligne → file Hive offline_punches + statut offline', () async {
    final interceptor = _AttendanceInterceptor(failWithNetworkError: true);
    client.dio.interceptors.add(interceptor);

    final repository = AttendanceRepository(client);
    final log = await repository.checkIn(workType: 'normal');

    expect(log.status, 'offline_sync_pending');
    expect(log.id, 0);

    final box = await Hive.openBox<Map<dynamic, dynamic>>('offline_punches');
    expect(box.length, 1);
    final entry = box.getAt(0)!;
    expect(entry['type'], 'check-in');
    expect((entry['payload'] as Map)['work_type'], 'normal');
    await box.clear();
  });

  test('checkOut hors-ligne → file Hive offline_punches (type check-out)', () async {
    final interceptor = _AttendanceInterceptor(failWithNetworkError: true);
    client.dio.interceptors.add(interceptor);

    final repository = AttendanceRepository(client);
    final log = await repository.checkOut();

    expect(log.status, 'offline_sync_pending');

    final box = await Hive.openBox<Map<dynamic, dynamic>>('offline_punches');
    expect(box.length, 1);
    expect(box.getAt(0)!['type'], 'check-out');
    await box.clear();
  });

  test('une erreur non-réseau (500) est propagée, pas mise en file', () async {
    final failing = _ThrowingInterceptor();
    client.dio.interceptors.add(failing);

    final repository = AttendanceRepository(client);
    await expectLater(
      repository.checkIn(),
      throwsA(isA<Object>()), // ApiException (message serveur, pas hors-ligne)
    );

    final box = await Hive.openBox<Map<dynamic, dynamic>>('offline_punches');
    expect(box.length, 0);
  });

  test('updateAttendanceLog envoie check_in + notes et parse le log', () async {
    final interceptor = _AttendanceInterceptor();
    client.dio.interceptors.add(interceptor);

    final repository = AttendanceRepository(client);
    final log = await repository.updateAttendanceLog(
      logId: 42,
      checkIn: DateTime.utc(2026, 8, 9, 8, 0),
      checkOut: DateTime.utc(2026, 8, 9, 16, 0),
      notes: 'Correction RH',
    );

    final request = interceptor.requests.single;
    expect(request.method, 'PUT');
    expect(request.path, '/attendance/42');
    final payload = _payloadOf(request);
    expect(payload['check_in'], '2026-08-09T08:00:00.000Z');
    expect(payload['check_out'], '2026-08-09T16:00:00.000Z');
    expect(payload['notes'], 'Correction RH');
    expect(log.id, 42);
  });
}

class _ThrowingInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    handler.reject(
      DioException(
        requestOptions: options,
        response: Response(
          requestOptions: options,
          statusCode: 500,
          data: {'message': 'Erreur serveur'},
        ),
        type: DioExceptionType.badResponse,
      ),
    );
  }
}
