import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/models/payroll.dart';
import 'package:leopardo_core/models/salary_advance.dart';

void main() {
  test('salary advance reads currency from direct payload', () {
    final advance = SalaryAdvance.fromJson({
      'id': 7,
      'employee_id': 12,
      'status': 'pending',
      'amount': '15000',
      'currency': 'XOF',
    });

    expect(advance.currency, 'XOF');
  });

  test('salary advance can inherit currency from nested company payload', () {
    final advance = SalaryAdvance.fromJson({
      'id': 8,
      'employee_id': 13,
      'status': 'approved',
      'amount': 25000,
      'company': {'currency': 'XAF'},
    });

    expect(advance.currency, 'XAF');
  });

  test('payroll reads currency from API payload', () {
    final payroll = Payroll.fromJson({
      'id': 1,
      'employee_id': 2,
      'period_month': 6,
      'period_year': 2026,
      'gross_salary': 120000,
      'net_salary': 98000,
      'currency': 'MAD',
      'status': 'validated',
    });

    expect(payroll.currency, 'MAD');
  });
}
