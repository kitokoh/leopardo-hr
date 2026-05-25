import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_manager/features/attendance/data/attendance_repository.dart';

import '../../helpers/mobile_test_harness.dart';

void main() {
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

  test(
    'updateAttendanceLog sends correction payload and maps response',
    () async {
      RequestOptions? captured;
      final repo = AttendanceRepository(
        clientWithHandler((options, handler) {
          captured = options;
          handler.resolve(
            Response(
              requestOptions: options,
              statusCode: 200,
              data: {
                'data': {
                  'id': 7,
                  'employee_id': 12,
                  'date': '2026-05-22',
                  'check_in': '2026-05-22T08:05:00Z',
                  'check_out': '2026-05-22T17:00:00Z',
                  'hours_worked': 8.92,
                  'late_minutes': 5,
                  'status': 'manual',
                },
              },
            ),
          );
        }),
      );

      final log = await repo.updateAttendanceLog(
        logId: 7,
        checkIn: DateTime.utc(2026, 5, 22, 8, 5),
        checkOut: DateTime.utc(2026, 5, 22, 17),
        notes: 'Correction RH',
      );

      expect(captured?.method, 'PUT');
      expect(captured?.path, '/attendance/7');
      expect((captured?.data as Map)['notes'], 'Correction RH');
      expect(log.id, 7);
      expect(log.status, 'manual');
      expect(log.lateMinutes, 5);
    },
  );

  test('requestCorrection posts employee request to attendance API', () async {
    RequestOptions? captured;
    final repo = AttendanceRepository(
      clientWithHandler((options, handler) {
        captured = options;
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 201,
            data: {
              'data': {'id': 9001, 'status': 'pending'},
              'message': 'Demande de modification transmise au RH.',
            },
          ),
        );
      }),
    );

    await repo.requestCorrection(
      logId: 7,
      date: DateTime(2026, 5, 22),
      checkIn: DateTime.utc(2026, 5, 22, 8, 10),
      reason: 'Oubli de pointage',
    );

    final payload = (captured?.data as Map).cast<String, dynamic>();
    expect(captured?.method, 'POST');
    expect(captured?.path, '/attendance/corrections');
    expect(payload['attendance_log_id'], 7);
    expect(payload['date'], '2026-05-22');
    expect(payload['reason'], 'Oubli de pointage');
    expect(payload.containsKey('requested_check_out'), isFalse);
  });

  test('checkIn and checkOut use resilient attendance actions', () async {
    final paths = <String>[];
    final repo = AttendanceRepository(
      clientWithHandler((options, handler) {
        paths.add('${options.method} ${options.path}');
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 200,
            data: {
              'data': {
                'item': {
                  'id': paths.length,
                  'employee_id': 12,
                  'date': '2026-05-25',
                  'check_in': '2026-05-25T08:00:00Z',
                  'check_out':
                      options.path.endsWith('check-out')
                          ? '2026-05-25T17:00:00Z'
                          : null,
                  'hours_worked': 8,
                  'status': 'present',
                },
              },
            },
          ),
        );
      }),
    );

    final checkIn = await repo.checkIn();
    final checkOut = await repo.checkOut();

    expect(paths, ['POST /attendance/check-in', 'POST /attendance/check-out']);
    expect(checkIn.id, 1);
    expect(checkIn.checkOut, isNull);
    expect(checkOut.id, 2);
    expect(checkOut.checkOut, isNotNull);
  });

  test('getHistory maps paginated attendance list with date filters', () async {
    RequestOptions? captured;
    final repo = AttendanceRepository(
      clientWithHandler((options, handler) {
        captured = options;
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: 200,
            data: {
              'data': [
                {
                  'id': 41,
                  'employee_id': 12,
                  'date': '2026-05-01',
                  'check_in': '2026-05-01T08:00:00Z',
                  'check_out': '2026-05-01T17:00:00Z',
                  'hours_worked': 8,
                  'status': 'present',
                },
              ],
            },
          ),
        );
      }),
    );

    final history = await repo.getHistory(2026, 5);

    expect(captured?.path, '/attendance');
    expect(captured?.queryParameters['date_from'], '2026-05-01');
    expect(captured?.queryParameters['date_to'], '2026-05-31');
    expect(captured?.queryParameters['per_page'], 50);
    expect(history.single.id, 41);
  });

  test('decodeTodayResponse supports item, empty and collection payloads', () {
    final empty = AttendanceRepository.decodeTodayResponse({'data': null});
    expect(empty['log'], isNull);

    final collection = AttendanceRepository.decodeTodayResponse({
      'data': {
        'items': [
          {'id': 1},
        ],
        'mode': 'team',
      },
      'meta': {'total': 1},
    });
    expect(collection['log'], isNull);
    expect(collection['context']['mode'], 'team');
    expect(collection['context']['items'], isA<List>());

    final today = AttendanceRepository.decodeTodayResponse({
      'data': {
        'item': {
          'id': '77',
          'employee_id': '12',
          'check_in_time': '08:12',
          'check_out_time': '17:03',
          'hours_worked': '8.85',
          'overtime_hours': '0.25',
          'late_minutes': '12',
          'status': 'present',
          'name': 'Samia RH',
          'photo_path': '/profiles/samia.png',
        },
        'context': {'timezone': 'Africa/Algiers'},
      },
    });

    expect(today['log'].id, 77);
    expect(today['log'].employeeName, 'Samia RH');
    expect(today['log'].employeePhotoUrl, '/profiles/samia.png');
    expect(today['context']['timezone'], 'Africa/Algiers');
  });
}
