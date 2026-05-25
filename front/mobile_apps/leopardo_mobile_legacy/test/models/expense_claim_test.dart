import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/models/expense_claim.dart';

void main() {
  group('ExpenseClaim model', () {
    test('fromJson maps all fields correctly', () {
      final claim = ExpenseClaim.fromJson({
        'id': 1,
        'reference': 'EXP-2026-042',
        'category': 'transport',
        'amount': 1500.75,
        'currency': 'DZD',
        'date': '2026-05-10',
        'description': 'Taxi aeroport',
        'status': 'pending',
      });

      expect(claim.id, 1);
      expect(claim.reference, 'EXP-2026-042');
      expect(claim.category, 'transport');
      expect(claim.amount, 1500.75);
      expect(claim.currency, 'DZD');
      expect(claim.date, '2026-05-10');
      expect(claim.description, 'Taxi aeroport');
      expect(claim.status, 'pending');
    });

    test('fromJson handles null description', () {
      final claim = ExpenseClaim.fromJson({
        'id': 2,
        'reference': 'EXP-2026-043',
        'category': 'repas',
        'amount': 800,
        'currency': 'MAD',
        'date': '2026-05-11',
        'description': null,
        'status': 'approved',
      });

      expect(claim.description, isNull);
      expect(claim.status, 'approved');
    });

    test('fromJson defaults missing fields', () {
      final claim = ExpenseClaim.fromJson({
        'id': 3,
      });

      expect(claim.reference, '');
      expect(claim.category, '');
      expect(claim.amount, 0);
      expect(claim.currency, 'DZD');
      expect(claim.date, '');
      expect(claim.status, 'pending');
    });
  });
}
