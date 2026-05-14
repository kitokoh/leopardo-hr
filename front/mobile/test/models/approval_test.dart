import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/models/approval.dart';

void main() {
  group('Approval model', () {
    test('fromJson maps all fields correctly', () {
      final approval = Approval.fromJson({
        'id': 1,
        'type': 'leave',
        'requester_name': 'Ahmed Benali',
        'summary': 'Demande de conge annuel du 01/06 au 05/06',
        'created_at': '2026-05-13T10:30:00Z',
        'status': 'pending',
      });

      expect(approval.id, 1);
      expect(approval.type, 'leave');
      expect(approval.requesterName, 'Ahmed Benali');
      expect(approval.summary, contains('conge annuel'));
      expect(approval.createdAt, '2026-05-13T10:30:00Z');
      expect(approval.status, 'pending');
    });

    test('fromJson handles expense approval', () {
      final approval = Approval.fromJson({
        'id': 2,
        'type': 'expense',
        'requester_name': 'Fatima Zahra',
        'summary': 'Note de frais transport 1500 DZD',
        'created_at': '2026-05-12T14:00:00Z',
        'status': 'approved',
      });

      expect(approval.type, 'expense');
      expect(approval.status, 'approved');
    });

    test('fromJson defaults missing optional fields', () {
      final approval = Approval.fromJson({
        'id': 3,
      });

      expect(approval.type, '');
      expect(approval.requesterName, '');
      expect(approval.summary, '');
      expect(approval.createdAt, '');
      expect(approval.status, 'pending');
    });
  });
}
