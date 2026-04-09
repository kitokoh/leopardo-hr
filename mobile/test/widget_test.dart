import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/models/employee.dart';

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
    });

    expect(employee.companyId, 'company-10');
    expect(employee.role, 'manager');
    expect(employee.firstName, 'Leila');
  });
}
