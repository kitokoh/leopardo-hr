import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/models/payroll.dart';

void main() {
  group('Payroll.fromJson — Issue #2143', () {
    test('parses the real /me/pay-slips contract (period + compliance)', () {
      final payroll = Payroll.fromJson({
        'id': 42,
        'employee_id': 7,
        'period_start': '2026-05-01',
        'period_end': '2026-05-31',
        'period': '2026-05',
        'country_code': 'CI',
        'gross_salary': 400000,
        'net_salary': 360200,
        'currency': 'XOF',
        'status': 'validated',
        'compliance': {
          'level': 'pilot',
          'warning': 'Règles pilotes pour CI',
          'warning_key': 'payroll.compliance_warning_pilot',
          'source': 'docs/payroll/CI_COMPLIANCE.md',
          'verification_date': null,
        },
      });

      expect(payroll.month, 5);
      expect(payroll.year, 2026);
      expect(payroll.countryCode, 'CI');
      expect(payroll.compliance, isNotNull);
      expect(payroll.compliance!.level, 'pilot');
      expect(
          payroll.compliance!.warningKey, 'payroll.compliance_warning_pilot');
      expect(payroll.compliance!.source, 'docs/payroll/CI_COMPLIANCE.md');
      expect(payroll.compliance!.verificationDate, isNull);
      expect(payroll.compliance!.isPlaceholderOrUnknown, isFalse);
    });

    test('parses compliance from period_start when period is absent', () {
      final payroll = Payroll.fromJson({
        'id': 43,
        'employee_id': 8,
        'period_start': '2026-05-01',
        'period_end': '2026-05-31',
        'gross_salary': 100000,
        'net_salary': 96800,
        'status': 'validated',
        'compliance': {'level': 'placeholder', 'warning': 'Maquette'},
      });

      expect(payroll.month, 5);
      expect(payroll.year, 2026);
      expect(payroll.compliance!.level, 'placeholder');
      expect(payroll.compliance!.isPlaceholderOrUnknown, isTrue);
    });

    test('legacy period_month/period_year payload still parses', () {
      final payroll = Payroll.fromJson({
        'id': 44,
        'employee_id': 9,
        'period_month': 3,
        'period_year': 2026,
        'gross_salary': 1000,
        'net_salary': 800,
        'status': 'validated',
      });

      expect(payroll.month, 3);
      expect(payroll.year, 2026);
      expect(payroll.compliance, isNull);
    });

    test('payload without compliance is retro-compatible (no error, null)', () {
      final payroll = Payroll.fromJson({
        'id': 45,
        'employee_id': 10,
        'period': '2026-04',
        'gross_salary': 1000,
        'net_salary': 800,
        'status': 'sent',
      });

      expect(payroll.compliance, isNull);
      expect(payroll.month, 4);
      expect(payroll.year, 2026);
    });

    test('PayrollCompliance.fromJson defaults unknown level', () {
      final compliance = PayrollCompliance.fromJson(const {});

      expect(compliance.level, 'unknown');
      expect(compliance.isPlaceholderOrUnknown, isTrue);
    });
  });
}
