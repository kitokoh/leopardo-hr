import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/features/auth/screens/welcome_screen.dart';
import 'package:leopardo_rh/features/home/screens/home_screen.dart';

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
      appRouterHarness(overrides: [authOverride(testEmployee())]),
    );
    await tester.pumpAndSettle();

    expect(find.byType(HomeScreen), findsOneWidget);
  });
}
