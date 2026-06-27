import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/models/onboarding_step.dart';

void main() {
  group('OnboardingStep model', () {
    test('fromJson maps completed step', () {
      final step = OnboardingStep.fromJson({
        'id': 1,
        'key': 'add_employees',
        'title': 'Ajouter vos employes',
        'description': 'Importez ou creez manuellement vos fiches employes',
        'completed': true,
        'skipped': false,
      });

      expect(step.id, 1);
      expect(step.key, 'add_employees');
      expect(step.title, 'Ajouter vos employes');
      expect(step.description, contains('employes'));
      expect(step.completed, true);
      expect(step.skipped, false);
    });

    test('fromJson maps skipped step', () {
      final step = OnboardingStep.fromJson({
        'id': 2,
        'key': 'configure_payroll',
        'title': 'Configurer la paie',
        'description': null,
        'completed': false,
        'skipped': true,
      });

      expect(step.completed, false);
      expect(step.skipped, true);
      expect(step.description, isNull);
    });

    test('fromJson defaults to incomplete and not skipped', () {
      final step = OnboardingStep.fromJson({
        'id': 3,
        'key': 'setup_leaves',
        'title': 'Configurer les conges',
      });

      expect(step.completed, false);
      expect(step.skipped, false);
      expect(step.key, 'setup_leaves');
    });
  });
}
