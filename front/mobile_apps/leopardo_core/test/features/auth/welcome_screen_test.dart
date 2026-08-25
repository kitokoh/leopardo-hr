import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/features/auth/screens/welcome_screen.dart';
import 'package:leopardo_core/l10n/l10n.dart';

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

    // Hero title is localized via the ARB catalog (fr).
    expect(find.text(AppLocalizations.of(tester.element(find.byType(WelcomeScreen))).welcomeHeroTitle), findsOneWidget);

    // The first current capability is visible.
    expect(find.text('Mon équipe'), findsOneWidget);

    // Both CTAs are present.
    expect(find.widgetWithText(ElevatedButton, 'Se connecter'), findsOneWidget);
    expect(
      find.widgetWithText(OutlinedButton, 'Acces employe (invitation)'),
      findsOneWidget,
    );
  });
}
