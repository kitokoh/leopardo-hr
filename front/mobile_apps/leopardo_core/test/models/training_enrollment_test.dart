import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/models/training_enrollment.dart';

void main() {
  group('TrainingEnrollment model', () {
    test('fromJson maps all fields', () {
      final enrollment = TrainingEnrollment.fromJson({
        'id': 1,
        'course_title': 'Securite au travail',
        'session_date': '2026-06-15',
        'progress': 75,
        'status': 'in_progress',
      });

      expect(enrollment.id, 1);
      expect(enrollment.courseTitle, 'Securite au travail');
      expect(enrollment.sessionDate, '2026-06-15');
      expect(enrollment.progress, 75);
      expect(enrollment.status, 'in_progress');
    });

    test('fromJson handles completed enrollment', () {
      final enrollment = TrainingEnrollment.fromJson({
        'id': 2,
        'course_title': 'Gestion de projet',
        'session_date': '2026-04-01',
        'progress': 100,
        'status': 'completed',
      });

      expect(enrollment.progress, 100);
      expect(enrollment.status, 'completed');
    });

    test('fromJson defaults missing fields', () {
      final enrollment = TrainingEnrollment.fromJson({
        'id': 3,
      });

      expect(enrollment.courseTitle, '');
      expect(enrollment.sessionDate, isNull);
      expect(enrollment.progress, 0);
      expect(enrollment.status, 'enrolled');
    });
  });
}
