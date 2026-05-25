import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/features/absences/providers/absence_provider.dart';
import 'package:leopardo_rh/features/absences/screens/absence_list_screen.dart';
import 'package:leopardo_rh/features/salary_advances/providers/salary_advance_provider.dart';
import 'package:leopardo_rh/features/salary_advances/screens/salary_advance_list_screen.dart';
import 'package:leopardo_rh/models/absence.dart';
import 'package:leopardo_rh/models/salary_advance.dart';

import '../helpers/mobile_test_harness.dart';

void main() {
  final pendingAbsence = Absence(
    id: 33,
    employeeId: 44,
    absenceTypeId: 2,
    absenceTypeName: 'Conge annuel',
    startDate: DateTime(2026, 5, 26),
    endDate: DateTime(2026, 5, 27),
    daysCount: 2,
    status: 'pending',
    reason: 'Rendez-vous familial',
  );

  const pendingAdvance = SalaryAdvance(
    id: 12,
    employeeId: 44,
    status: 'pending',
    amount: 30000,
    reason: 'Besoin familial',
    repaymentMonths: 3,
  );

  testWidgets('manager demo can see HR decisions for team requests', (
    tester,
  ) async {
    final manager = testEmployee(
      id: 7,
      firstName: 'Fatima',
      lastName: 'RH',
      role: 'manager',
      managerRole: 'rh',
    );
    final overrides = [
      authOverride(manager),
      absencesProvider.overrideWith((ref) async => [pendingAbsence]),
      salaryAdvancesProvider.overrideWith((ref) async => [pendingAdvance]),
    ];

    await pumpMobile(
      tester,
      const AbsenceListScreen(),
      overrides: overrides,
      surfaceSize: const Size(430, 1000),
    );
    expect(find.text('Mes Absences'), findsOneWidget);
    expect(find.text('Conge annuel'), findsOneWidget);
    expect(find.text('Approuver'), findsOneWidget);
    expect(find.text('Refuser'), findsOneWidget);

    await pumpMobile(
      tester,
      const SalaryAdvanceListScreen(),
      overrides: overrides,
      surfaceSize: const Size(430, 1000),
    );
    expect(find.text('Avances'), findsOneWidget);
    expect(find.text('30000 DZD'), findsOneWidget);
    expect(find.text('Approuver'), findsOneWidget);
    expect(find.text('Refuser'), findsOneWidget);
  });

  testWidgets(
    'employee demo keeps self-service cancellation instead of approval',
    (tester) async {
      final employee = testEmployee(id: 44, firstName: 'Karim');
      final overrides = [
        authOverride(employee),
        absencesProvider.overrideWith((ref) async => [pendingAbsence]),
        salaryAdvancesProvider.overrideWith((ref) async => [pendingAdvance]),
      ];

      await pumpMobile(
        tester,
        const AbsenceListScreen(),
        overrides: overrides,
        surfaceSize: const Size(430, 1000),
      );
      expect(find.text('Annuler la demande'), findsOneWidget);
      expect(find.text('Approuver'), findsNothing);
      expect(find.text('Refuser'), findsNothing);

      await pumpMobile(
        tester,
        const SalaryAdvanceListScreen(),
        overrides: overrides,
        surfaceSize: const Size(430, 1000),
      );
      expect(find.text('Annuler la demande'), findsOneWidget);
      expect(find.text('Approuver'), findsNothing);
      expect(find.text('Refuser'), findsNothing);
    },
  );
}
