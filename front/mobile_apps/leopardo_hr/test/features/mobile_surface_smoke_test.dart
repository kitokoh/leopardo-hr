import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_manager/features/absences/providers/absence_provider.dart';
import 'package:leopardo_manager/features/absences/screens/absence_list_screen.dart';
import 'package:leopardo_manager/features/auth/screens/login_screen.dart';
import 'package:leopardo_manager/features/auth/screens/register_screen.dart';
import 'package:leopardo_manager/features/auth/screens/welcome_screen.dart';
import 'package:leopardo_manager/features/home/screens/home_screen.dart';
import 'package:leopardo_manager/features/home/screens/modules_hub_screen.dart';
import 'package:leopardo_manager/features/notifications/providers/notification_provider.dart';
import 'package:leopardo_manager/features/notifications/screens/notification_list_screen.dart';
import 'package:leopardo_manager/features/payrolls/providers/payroll_provider.dart';
import 'package:leopardo_manager/features/payrolls/screens/payroll_list_screen.dart';
import 'package:leopardo_manager/features/team/providers/team_provider.dart';
import 'package:leopardo_manager/features/team/screens/team_screen.dart';
import 'package:leopardo_manager/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_manager/features/attendance/screens/history_screen.dart';
import 'package:leopardo_manager/features/attendance/screens/monthly_summary_screen.dart';
import 'package:leopardo_core/models/monthly_summary.dart';

import '../helpers/mobile_test_harness.dart';

void main() {
  final employee = testEmployee(role: 'manager', managerRole: 'rh');
  final baseOverrides = [
    authOverride(employee),
    absencesProvider.overrideWith((ref) async => []),
    payrollsProvider.overrideWith((ref) async => []),
    notificationsProvider.overrideWith((ref) async => []),
    teamListProvider.overrideWith((ref) async => []),
    invitationsListProvider.overrideWith((ref) async => []),
    historyProvider.overrideWith((ref, date) async => []),
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
        breakdown: const [],
        disclaimer: 'Test baseline',
      );
    }),
  ];

  testWidgets('main mobile surfaces render without backend calls', (
    tester,
  ) async {
    final cases = <Type, Widget>{
      WelcomeScreen: const WelcomeScreen(),
      LoginScreen: const LoginScreen(),
      RegisterScreen: const RegisterScreen(),
      HomeScreen: const HomeScreen(),
      ModulesHubScreen: const ModulesHubScreen(),
      AbsenceListScreen: const AbsenceListScreen(),
      PayrollListScreen: const PayrollListScreen(),
      NotificationListScreen: const NotificationListScreen(),
      TeamScreen: const TeamScreen(),
      HistoryScreen: const HistoryScreen(),
      MonthlySummaryScreen: const MonthlySummaryScreen(),
    };

    for (final entry in cases.entries) {
      await pumpMobile(
        tester,
        entry.value,
        overrides: baseOverrides,
        surfaceSize: const Size(430, 1200),
      );

      expect(find.byType(entry.key), findsOneWidget);
    }
  });
}
