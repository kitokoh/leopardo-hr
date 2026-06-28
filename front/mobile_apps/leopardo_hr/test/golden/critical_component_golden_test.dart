import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_manager/features/absences/providers/absence_provider.dart';
import 'package:leopardo_manager/features/absences/screens/absence_list_screen.dart';
import 'package:leopardo_manager/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_manager/features/attendance/screens/monthly_summary_screen.dart';
import 'package:leopardo_core/models/monthly_summary.dart';

import '../helpers/mobile_test_harness.dart';

void main() {
  testWidgets('pay slip monthly summary keeps its critical baseline content', (
    tester,
  ) async {
    final employee = testEmployee();

    await pumpMobile(
      tester,
      const MonthlySummaryScreen(),
      overrides: [
        authOverride(employee),
        monthlySummaryProvider.overrideWith((ref, date) async {
          return MonthlySummary(
            employeeId: employee.id,
            name: employee.fullName,
            year: 2026,
            month: 5,
            periodFrom: DateTime(2026, 5),
            periodTo: DateTime(2026, 5, 31),
            workingDays: 22,
            daysPresent: 20,
            daysAbsent: 1,
            hours: 160,
            overtimeHours: 4,
            gross: 120000,
            deductions: 12000,
            net: 108000,
            currency: 'DA',
            breakdown: [
              MonthlyBreakdownEntry(
                date: DateTime(2026, 5, 4),
                hours: 8,
                overtimeHours: 1,
                baseGain: 6000,
                overtimeGain: 900,
                total: 6900,
              ),
            ],
            disclaimer: 'Baseline paie mobile',
          );
        }),
      ],
    );
    await tester.pumpAndSettle();

    expect(find.byType(MonthlySummaryScreen), findsOneWidget);
    expect(find.text('Mon mois'), findsOneWidget);
    expect(find.textContaining('108'), findsWidgets);
    expect(find.byIcon(Icons.paid), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('leave calendar empty state keeps its approval baseline', (
    tester,
  ) async {
    await pumpMobile(
      tester,
      const AbsenceListScreen(),
      overrides: [
        authOverride(testEmployee()),
        absencesProvider.overrideWith((ref) async => []),
      ],
    );
    await tester.pumpAndSettle();

    expect(find.byType(AbsenceListScreen), findsOneWidget);
    expect(find.text('Mes Absences'), findsOneWidget);
    expect(find.byIcon(Icons.calendar_today), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
