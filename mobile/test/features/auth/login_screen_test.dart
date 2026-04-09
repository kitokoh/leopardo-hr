import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';
import 'package:leopardo_rh/models/employee.dart';

void main() {
  test('extracts token from root API payload', () {
    final payload = {
      'data': {
        'id': 1,
        'first_name': 'Hamid',
        'last_name': 'Djebari',
        'email': 'hamid@test.dev',
        'role': 'employee',
        'status': 'active',
      },
      'token': 'root-token',
    };

    expect(AuthRepository.extractToken(payload), 'root-token');
  });

  test('keeps compatibility with legacy mock payloads', () {
    final payload = {
      'data': {
        'token': 'nested-token',
        'user': {
          'id': 1,
          'company_id': 'company-1',
          'first_name': 'Hamid',
          'last_name': 'Djebari',
          'email': 'hamid@test.dev',
          'role': 'employee',
          'status': 'active',
        },
      },
    };

    final employee = Employee.fromJson(AuthRepository.extractEmployeeJson(payload));

    expect(AuthRepository.extractToken(payload), 'nested-token');
    expect(employee.companyId, 'company-1');
    expect(employee.role, 'employee');
  });
}
