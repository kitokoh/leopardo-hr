import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/models/attendance_log.dart';

void main() {
  test('parses API attendance log timestamps and hours', () {
    final log = AttendanceLog.fromJson({
      'id': 5432,
      'employee_id': 101,
      'date': '2026-04-15',
      'check_in': '2026-04-15T07:55:12Z',
      'check_out': '2026-04-15T17:05:00Z',
      'hours_worked': 9.17,
      'overtime_hours': 1.17,
      'status': 'ontime',
    });

    expect(log.id, 5432);
    expect(log.employeeId, 101);
    expect(log.checkIn, isNotNull);
    expect(log.checkOut, isNotNull);
    expect(log.workedHours, 9.17);
    expect(log.overtimeHours, 1.17);
  });
}
