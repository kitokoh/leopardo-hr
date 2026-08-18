import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_manager/features/auth/screens/access_denied_screen.dart';
import 'package:leopardo_core/features/auth/screens/welcome_screen.dart';
import 'package:leopardo_manager/features/home/screens/home_screen.dart';

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

  testWidgets('authenticated manager is redirected away from public login', (
    tester,
  ) async {
    await tester.pumpWidget(
      appRouterHarness(
        overrides: [
          authOverride(
            testEmployee(role: 'manager', managerRole: 'principal'),
          ),
        ],
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(HomeScreen), findsOneWidget);
  });

  testWidgets('authenticated non-manager sees access denied (no redirect loop)', (
    tester,
  ) async {
    await tester.pumpWidget(
      appRouterHarness(overrides: [authOverride(testEmployee())]),
    );
    await tester.pumpAndSettle();

    // T116 : plus de boucle /welcome <-> / — l'utilisateur sans le rôle
    // Manager reçoit un écran explicite.
    expect(find.byType(AccessDeniedScreen), findsOneWidget);
  });
}
