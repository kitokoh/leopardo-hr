import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/features/auth/screens/welcome_screen.dart';
import 'package:leopardo_rh/l10n/l10n.dart';

void main() {
  testWidgets('WelcomeScreen renders brand, feature slide and CTAs', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        locale: Locale('fr'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: WelcomeScreen(),
      ),
    );

    // Brand header.
    expect(find.text('Leopardo RH'), findsOneWidget);
    expect(
      find.text('Votre journee commence ici, pas dans un back-office.'),
      findsOneWidget,
    );

    // First feature slide is visible.
    expect(
      find.text('Une home qui vous parle avant de vous noyer'),
      findsOneWidget,
    );

    // Both CTAs are present.
    expect(find.widgetWithText(ElevatedButton, 'Se connecter'), findsOneWidget);
    expect(
      find.widgetWithText(OutlinedButton, 'Acces employe (invitation)'),
      findsOneWidget,
    );
  });
}
