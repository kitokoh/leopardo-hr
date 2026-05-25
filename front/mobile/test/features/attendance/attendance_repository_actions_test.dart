import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/features/attendance/data/attendance_repository.dart';

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
}
