import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_hr/features/auth/screens/access_denied_screen.dart';
import 'package:leopardo_core/features/auth/screens/welcome_screen.dart';
import 'package:leopardo_hr/features/home/screens/home_screen.dart';

import '../helpers/mobile_test_harness.dart';

void main() {
  testWidgets('unauthenticated users are redirected from protected home', (
    tester,
  ) async {
    await tester.pumpWidget(appRouterHarness(overrides: [authOverride(null)]));
    await tester.pumpAndSettle();

    expect(find.byType(WelcomeScreen), findsOneWidget);
    expect(find.text('Leopardo RH'), findsOneWidget);
  });

  testWidgets('authenticated users are redirected away from public login', (
    tester,
  ) async {
    await tester.pumpWidget(
      appRouterHarness(
        overrides: [
          authOverride(testEmployee(role: 'manager', managerRole: 'rh')),
        ],
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(HomeScreen), findsOneWidget);
  });

  testWidgets('manager principal (managerRole=principal) accède à l app RH', (
    tester,
  ) async {
    // #4960 : le guard n'acceptait que manager_role == 'rh' — le manager
    // principal (rôle attendu par le README et les écrans team/approvals)
    // était bloqué sur /access-denied, contrairement à l'app Manager.
    await tester.pumpWidget(
      appRouterHarness(
        overrides: [
          authOverride(testEmployee(role: 'manager', managerRole: 'principal')),
        ],
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(HomeScreen), findsOneWidget);
    expect(find.byType(AccessDeniedScreen), findsNothing);
  });

  testWidgets('employé simple est redirigé vers /access-denied', (
    tester,
  ) async {
    await tester.pumpWidget(
      appRouterHarness(
        overrides: [authOverride(testEmployee(role: 'employee'))],
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(AccessDeniedScreen), findsOneWidget);
  });
}
