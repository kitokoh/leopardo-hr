import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/models/contract.dart';

void main() {
  group('Contract model', () {
    test('fromJson maps all required fields', () {
      final contract = Contract.fromJson({
        'id': 1,
        'employee_id': 42,
        'reference': 'CTR-2026-001',
        'type': 'cdi',
        'start_date': '2026-01-15',
        'end_date': null,
        'base_salary': 85000.50,
        'currency': 'DZD',
        'status': 'active',
      });

      expect(contract.id, 1);
      expect(contract.employeeId, 42);
      expect(contract.reference, 'CTR-2026-001');
      expect(contract.type, 'cdi');
      expect(contract.startDate, '2026-01-15');
      expect(contract.endDate, isNull);
      expect(contract.baseSalary, 85000.50);
      expect(contract.currency, 'DZD');
      expect(contract.status, 'active');
    });

    test('fromJson handles missing optional fields with defaults', () {
      final contract = Contract.fromJson({
        'id': 2,
        'employee_id': 10,
        'start_date': '2026-03-01',
      });

      expect(contract.id, 2);
      expect(contract.reference, '');
      expect(contract.type, 'cdi');
      expect(contract.endDate, isNull);
      expect(contract.baseSalary, 0);
      expect(contract.currency, 'DZD');
      expect(contract.status, 'active');
    });

    test('fromJson handles CDD contract with end date', () {
      final contract = Contract.fromJson({
        'id': 3,
        'employee_id': 15,
        'reference': 'CDD-2026-005',
        'type': 'cdd',
        'start_date': '2026-06-01',
        'end_date': '2026-12-31',
        'base_salary': 45000,
        'currency': 'MAD',
        'status': 'active',
      });

      expect(contract.type, 'cdd');
      expect(contract.endDate, '2026-12-31');
      expect(contract.currency, 'MAD');
    });
  });
}
