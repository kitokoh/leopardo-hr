import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_accounting/features/accounting/models/accounting_document.dart';

void main() {
  group('AccountingDocument.fromJson (issue #5236)', () {
    test('parse le payload complet du endpoint documents', () {
      final document = AccountingDocument.fromJson({
        'id': 42,
        'company_id': '08e19dd0-10d9-429e-96a0-c2fa51d6a401',
        'type': 'invoice',
        'number': 'FAC-2026-0001',
        'status': 'sent',
        'contact_id': 7,
        'contact': {'id': 7, 'name': 'SARL Client Test'},
        'issue_date': '2026-08-01',
        'due_date': '2026-08-31',
        'currency': 'DZD',
        'tva_rate': 19,
        'subtotal_ht': 1900.0,
        'tax_amount': 361.0,
        'total_ttc': 2261.0,
        'paid_amount': 500.0,
        'notes': null,
        'lines': [
          {
            'description': 'Prestation conseil',
            'quantity': 2,
            'unit_price': 1000.0,
            'discount': 100.0,
          },
        ],
      });

      expect(document.id, 42);
      expect(document.type, 'invoice');
      expect(document.number, 'FAC-2026-0001');
      expect(document.status, 'sent');
      expect(document.contactName, 'SARL Client Test');
      expect(document.dueDate, '2026-08-31');
      expect(document.currency, 'DZD');
      expect(document.tvaRate, 19.0);
      expect(document.totalTtc, 2261.0);
      expect(document.paidAmount, 500.0);
      expect(document.remaining, closeTo(1761.0, 0.001));
      expect(document.isUnpaid, isTrue);
      expect(document.lines, hasLength(1));
      expect(document.lines.first.description, 'Prestation conseil');
      expect(document.lines.first.quantity, 2.0);
      expect(document.lines.first.unitPrice, 1000.0);
      expect(document.lines.first.discount, 100.0);
    });

    test('tolère les champs optionnels absents', () {
      final document = AccountingDocument.fromJson({
        'id': 1,
        'type': 'quote',
        'number': 'DEV-2026-0001',
        'status': 'draft',
        'total_ttc': 0,
      });

      expect(document.contactName, isNull);
      expect(document.currency, isNull);
      expect(document.dueDate, isNull);
      expect(document.lines, isEmpty);
      expect(document.remaining, 0.0);
      expect(document.isUnpaid, isFalse);
    });

    test('isUnpaid couvre sent et overdue uniquement', () {
      for (final status in ['sent', 'overdue']) {
        final document = AccountingDocument.fromJson({
          'id': 1,
          'type': 'invoice',
          'number': 'X',
          'status': status,
          'total_ttc': 100,
        });
        expect(document.isUnpaid, isTrue, reason: status);
      }
      for (final status in ['draft', 'partially_paid', 'paid', 'cancelled']) {
        final document = AccountingDocument.fromJson({
          'id': 1,
          'type': 'invoice',
          'number': 'X',
          'status': status,
          'total_ttc': 100,
        });
        expect(document.isUnpaid, isFalse, reason: status);
      }
    });

    test('toPayload d\'une ligne génère le contrat POST', () {
      const line = AccountingDocumentLine(
        description: 'Conseil',
        quantity: 2,
        unitPrice: 500,
        discount: 0,
      );
      expect(line.toPayload(), {
        'description': 'Conseil',
        'quantity': 2.0,
        'unit_price': 500.0,
        'discount': 0.0,
      });
    });
  });
}
