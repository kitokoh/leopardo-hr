import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_settings_screen.dart';

/// Tests widget de l'ecran de configuration tablette (#9263).
void main() {
  Widget wrap(Widget child) => MaterialApp(home: child);

  PosSettingsScreen buildScreen({
    PosAppConfig initial = const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app'),
    PosHealthProbe? probe,
    PosConfigSaver? onSave,
    String? pinHash,
  }) =>
      PosSettingsScreen(
        initialConfig: initial,
        onSave: onSave ?? (_) async {},
        probe: probe ?? (_) async => const PosHealthResult(status: PosHealthStatus.ok),
        pinHash: pinHash,
      );

  group('porte PIN', () {
    testWidgets('affiche la demande de code au demarrage', (tester) async {
      await tester.pumpWidget(wrap(buildScreen()));

      expect(find.text('Acces protege'), findsOneWidget);
      expect(find.byKey(const Key('pos-settings-pin')), findsOneWidget);
      // Le formulaire n'est pas accessible avant deverrouillage.
      expect(find.byKey(const Key('pos-settings-url')), findsNothing);
    });

    testWidgets('refuse un code incorrect', (tester) async {
      await tester.pumpWidget(wrap(buildScreen()));

      await tester.enterText(find.byKey(const Key('pos-settings-pin')), '9999');
      await tester.tap(find.byKey(const Key('pos-settings-unlock')));
      await tester.pumpAndSettle();

      expect(find.text('Code incorrect.'), findsOneWidget);
      expect(find.byKey(const Key('pos-settings-url')), findsNothing);
    });

    testWidgets('refuse un code trop court', (tester) async {
      await tester.pumpWidget(wrap(buildScreen()));

      await tester.enterText(find.byKey(const Key('pos-settings-pin')), '12');
      await tester.tap(find.byKey(const Key('pos-settings-unlock')));
      await tester.pumpAndSettle();

      expect(find.text('Le code doit comporter 4 a 8 chiffres.'), findsOneWidget);
    });

    testWidgets('accepte le code d usine 0000', (tester) async {
      await tester.pumpWidget(wrap(buildScreen()));

      await tester.enterText(find.byKey(const Key('pos-settings-pin')), '0000');
      await tester.tap(find.byKey(const Key('pos-settings-unlock')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('pos-settings-url')), findsOneWidget);
    });

    testWidgets('accepte un code personnalise correct', (tester) async {
      final hash = PosPinCode.hash('4821', salt: 'leopardo-pos');

      await tester.pumpWidget(wrap(buildScreen(pinHash: hash)));

      await tester.enterText(find.byKey(const Key('pos-settings-pin')), '4821');
      await tester.tap(find.byKey(const Key('pos-settings-unlock')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('pos-settings-url')), findsOneWidget);
    });
  });

  group('formulaire', () {
    Future<void> unlock(WidgetTester tester) async {
      await tester.enterText(find.byKey(const Key('pos-settings-pin')), '0000');
      await tester.tap(find.byKey(const Key('pos-settings-unlock')));
      await tester.pumpAndSettle();
    }

    testWidgets('pre-remplit l URL et l emplacement', (tester) async {
      await tester.pumpWidget(
        wrap(buildScreen(
          initial: const PosAppConfig(
            apiBaseUrl: 'https://api.leopardo.app/api/v1',
            locationId: 7,
          ),
        )),
      );
      await unlock(tester);

      final urlField = tester.widget<TextField>(
        find.byKey(const Key('pos-settings-url')),
      );
      final locationField = tester.widget<TextField>(
        find.byKey(const Key('pos-settings-location')),
      );

      expect(urlField.controller?.text, 'https://api.leopardo.app/api/v1');
      expect(locationField.controller?.text, '7');
    });

    testWidgets('test de connexion reussi affiche le bandeau vert', (tester) async {
      await tester.pumpWidget(
        wrap(buildScreen(
          probe: (_) async => const PosHealthResult(status: PosHealthStatus.ok),
        )),
      );
      await unlock(tester);

      await tester.tap(find.byKey(const Key('pos-settings-test')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('pos-settings-health')), findsOneWidget);
      expect(find.text('Connexion reussie'), findsOneWidget);
    });

    testWidgets('test de connexion echoue affiche le motif', (tester) async {
      await tester.pumpWidget(
        wrap(buildScreen(
          probe: (_) async => const PosHealthResult(status: PosHealthStatus.unreachable),
        )),
      );
      await unlock(tester);

      await tester.tap(find.byKey(const Key('pos-settings-test')));
      await tester.pumpAndSettle();

      expect(
        find.text('Serveur injoignable — verifiez le reseau et l\u2019adresse'),
        findsOneWidget,
      );
    });

    testWidgets('refuse une URL vide a l enregistrement', (tester) async {
      await tester.pumpWidget(wrap(buildScreen()));
      await unlock(tester);

      await tester.enterText(find.byKey(const Key('pos-settings-url')), '');
      await tester.tap(find.byKey(const Key('pos-settings-save')));
      await tester.pumpAndSettle();

      expect(find.text('L\u2019adresse de l\u2019API est obligatoire.'), findsOneWidget);
    });

    testWidgets('refuse un emplacement non numerique', (tester) async {
      await tester.pumpWidget(
        wrap(buildScreen(
          initial: const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app', locationId: 1),
        )),
      );
      await unlock(tester);

      await tester.enterText(find.byKey(const Key('pos-settings-location')), 'abc');
      await tester.tap(find.byKey(const Key('pos-settings-save')));
      await tester.pumpAndSettle();

      expect(find.text('L\u2019identifiant doit etre un nombre.'), findsOneWidget);
    });

    testWidgets('enregistre une configuration valide et normalise l URL', (tester) async {
      PosAppConfig? saved;

      await tester.pumpWidget(
        wrap(buildScreen(
          initial: const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app', locationId: 1),
          onSave: (config) async => saved = config,
        )),
      );
      await unlock(tester);

      await tester.enterText(
        find.byKey(const Key('pos-settings-url')),
        'api.leopardo.app/api/v1/',
      );
      await tester.enterText(find.byKey(const Key('pos-settings-location')), '12');
      await tester.tap(find.byKey(const Key('pos-settings-save')));
      await tester.pumpAndSettle();

      expect(saved, isNotNull);
      expect(saved!.apiBaseUrl, 'https://api.leopardo.app/api/v1');
      expect(saved!.locationId, 12);
    });
  });
}
