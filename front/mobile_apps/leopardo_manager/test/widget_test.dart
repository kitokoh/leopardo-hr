import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/models/attendance_log.dart';
import 'package:leopardo_core/models/employee.dart';

void main() {
  test('employee model maps optional beta fields', () {
    final employee = Employee.fromJson({
      'id': 10,
      'company_id': 'company-10',
      'first_name': 'Leila',
      'last_name': 'Ait',
      'email': 'leila@test.dev',
      'role': 'manager',
      'status': 'active',
      'photo_url': 'https://example.com/photo.jpg',
      'hire_date': '2023-01-15',
    });

    expect(employee.companyId, 'company-10');
    expect(employee.role, 'manager');
    expect(employee.firstName, 'Leila');
    expect(employee.photoUrl, 'https://example.com/photo.jpg');
    expect(employee.hireDate, DateTime(2023, 1, 15));
  });

  test('attendance log maps contract fields', () {
    final log = AttendanceLog.fromJson({
      'id': 1,
      'employee_id': 42,
      'date': '2026-04-10',
      'status': 'late',
      'late_minutes': 15,
      'overtime_hours': 1.5,
    });

    expect(log.lateMinutes, 15);
    expect(log.overtimeHours, 1.5);
  });
}
