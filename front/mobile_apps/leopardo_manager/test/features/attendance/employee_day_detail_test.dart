import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_manager/features/attendance/data/attendance_repository.dart';

import '../../helpers/mobile_test_harness.dart';

/// PA2-ATT-005: manager day-detail drill-down.
///
/// Regression coverage for `AttendanceRepository.getEmployeeDayDetail`,
/// which powers the manager "tap an employee to see their full day" sheet.
/// It must call the same tenant-scoped `/attendance/today?employee_id=`
/// contract already guarded server-side by
/// `AttendancePolicy::viewForEmployee`, and correctly decode the
/// `item` + `sessions` + `summary` envelope into an [EmployeeDayDetail].
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
    'getEmployeeDayDetail requests /attendance/today with employee_id and decodes sessions',
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
                  'mode': 'single',
                  'item': {
                    'employee_id': 42,
                    'matricule': 'EMP-042',
                    'name': 'Nadia Cherif',
                    'checked_in': true,
                    'status': 'ontime',
                    'hours_worked': 6.5,
                    'overtime_hours': 0.5,
                    'late_minutes': 5,
                    'base_gain': 3900.0,
                    'overtime_gain': 400.0,
                    'total_estimated': 4300.0,
                    'currency': 'DA',
                  },
                  'sessions': [
                    {
                      'id': 1,
                      'employee_id': 42,
                      'date': '2026-07-25',
                      'session_number': 1,
                      'check_in': '2026-07-25T08:00:00Z',
                      'check_out': '2026-07-25T12:00:00Z',
                      'status': 'ontime',
                      'work_type': 'normal',
                      'hours_worked': 4.0,
                    },
                    {
                      'id': 2,
                      'employee_id': 42,
                      'date': '2026-07-25',
                      'session_number': 2,
                      'check_in': '2026-07-25T13:00:00Z',
                      'check_out': null,
                      'status': 'incomplete',
                      'work_type': 'normal',
                      'hours_worked': 2.5,
                    },
                  ],
                  'summary': {
                    'sessions_count': 2,
                    'is_working': true,
                    'break_minutes': 60,
                  },
                },
              },
            ),
          );
        }),
      );

      final detail = await repo.getEmployeeDayDetail(42);

      expect(captured?.method, 'GET');
      expect(captured?.path, '/attendance/today');
      expect(captured?.queryParameters['employee_id'], 42);

      expect(detail.employeeId, 42);
      expect(detail.employeeName, 'Nadia Cherif');
      expect(detail.matricule, 'EMP-042');
      expect(detail.status, 'ontime');
      expect(detail.isWorking, isTrue);
      expect(detail.sessionsCount, 2);
      expect(detail.hoursWorked, 6.5);
      expect(detail.overtimeHours, 0.5);
      expect(detail.lateMinutes, 5);
      expect(detail.breakMinutes, 60);
      expect(detail.totalEstimated, 4300.0);
      expect(detail.currency, 'DA');
      expect(detail.sessions, hasLength(2));
      expect(detail.sessions.first.checkOut, isNotNull);
      expect(detail.sessions.last.checkOut, isNull);
    },
  );

  test(
    'getEmployeeDayDetail tolerates an employee with no punches today',
    () async {
      final repo = AttendanceRepository(
        clientWithHandler((options, handler) {
          handler.resolve(
            Response(
              requestOptions: options,
              statusCode: 200,
              data: {
                'data': {
                  'mode': 'single',
                  'item': {
                    'employee_id': 7,
                    'name': 'Yacine Boudiaf',
                    'checked_in': false,
                    'status': 'absent',
                    'hours_worked': 0,
                    'overtime_hours': 0,
                    'late_minutes': 0,
                    'base_gain': 0,
                    'overtime_gain': 0,
                    'total_estimated': 0,
                    'currency': 'DA',
                  },
                  'sessions': [],
                  'summary': {
                    'sessions_count': 0,
                    'is_working': false,
                    'break_minutes': 0,
                  },
                },
              },
            ),
          );
        }),
      );

      final detail = await repo.getEmployeeDayDetail(7);

      expect(detail.employeeId, 7);
      expect(detail.status, 'absent');
      expect(detail.isWorking, isFalse);
      expect(detail.sessions, isEmpty);
      expect(detail.sessionsCount, 0);
    },
  );
}
