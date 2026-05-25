import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_rh/features/attendance/screens/attendance_screen.dart';

void main() {
  test('uses stable month key for attendance history refreshes', () {
    final firstTick = attendanceHistoryMonthKey(DateTime(2026, 5, 25, 8, 0, 1));
    final nextTick = attendanceHistoryMonthKey(DateTime(2026, 5, 25, 8, 0, 2));

    expect(firstTick, DateTime(2026, 5));
    expect(nextTick, firstTick);
  });

  test('decodes normalized today payload for a single employee', () {
    final decoded = AttendanceRepository.decodeTodayResponse({
      'data': {
        'mode': 'single',
        'item': {
          'employee_id': 101,
          'check_in_time': '08:15',
          'check_out_time': null,
          'hours_worked': '0.00',
          'status': 'ontime',
        },
      },
    });

    expect(decoded['log'], isNotNull);
    expect(decoded['log'].employeeId, 101);
    expect(decoded['log'].checkIn?.hour, 8);
    expect(decoded['log'].status, 'ontime');
  });

  test('keeps manager collection payload in context without crashing', () {
    final decoded = AttendanceRepository.decodeTodayResponse({
      'data': {
        'mode': 'collection',
        'items': [
          {'employee_id': 1, 'status': 'ontime'},
          {'employee_id': 2, 'status': 'absent'},
        ],
        'meta': {'total': 2},
      },
    });

    expect(decoded['log'], isNull);
    expect(decoded['context']['mode'], 'collection');
    expect((decoded['context']['items'] as List).length, 2);
  });
}
