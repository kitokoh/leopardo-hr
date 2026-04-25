import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/features/auth/screens/welcome_screen.dart';

void main() {
  testWidgets('WelcomeScreen renders brand, feature slide and CTAs',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: WelcomeScreen(),
      ),
    );

    // Brand header.
    expect(find.text('Leopardo RH'), findsOneWidget);
    expect(find.text('Votre carriere, a portee de main'), findsOneWidget);

    // First feature slide is visible (pointage).
    expect(find.text('Pointez en un geste'), findsOneWidget);

    // Both CTAs are present.
    expect(find.widgetWithText(ElevatedButton, 'Se connecter'), findsOneWidget);
    expect(
        find.widgetWithText(OutlinedButton, 'Creer un compte'), findsOneWidget);
  });
}
