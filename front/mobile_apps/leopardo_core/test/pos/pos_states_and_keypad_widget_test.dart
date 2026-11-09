import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_numeric_keypad.dart';
import 'package:leopardo_core/core/pos/pos_settings_screen.dart';
import 'package:leopardo_core/core/pos/pos_states.dart';

/// Tests des **etats d'interface partages** (#9266) et du **pave numerique**
/// (#9267), desormais dans `leopardo_core` et utilises par les deux caisses.
void main() {
  Widget wrap(Widget child) => MaterialApp(home: Scaffold(body: child));

  group('PosEmptyState', () {
    testWidgets('affiche titre, message et action', (tester) async {
      var tapped = 0;

      await tester.pumpWidget(
        wrap(PosEmptyState(
          icon: Icons.inventory_2_outlined,
          title: 'Aucun article',
          message: 'Ce rayon ne contient aucun article disponible.',
          action: OutlinedButton(
            onPressed: () => tapped++,
            child: const Text('Voir tous les articles'),
          ),
        )),
      );

      expect(find.text('Aucun article'), findsOneWidget);
      expect(find.textContaining('aucun article disponible'), findsOneWidget);

      await tester.tap(find.text('Voir tous les articles'));
      expect(tapped, 1);
    });

    testWidgets('reste lisible sans message ni action', (tester) async {
      await tester.pumpWidget(
        wrap(const PosEmptyState(
            icon: Icons.star_border, title: 'Aucun favori')),
      );

      expect(find.text('Aucun favori'), findsOneWidget);
    });
  });

  group('PosOfflineBanner', () {
    testWidgets('rassure quand rien n attend le reseau', (tester) async {
      await tester
          .pumpWidget(wrap(const PosOfflineBanner(pendingOperations: 0)));

      expect(find.textContaining('la vente continue'), findsOneWidget);
    });

    testWidgets('annonce le nombre d operations en attente', (tester) async {
      await tester
          .pumpWidget(wrap(const PosOfflineBanner(pendingOperations: 3)));

      expect(find.textContaining('3 operation(s) en attente'), findsOneWidget);
      expect(find.textContaining("Rien n'est perdu"), findsOneWidget);
    });

    testWidgets('propose une synchronisation manuelle', (tester) async {
      var synced = 0;

      await tester.pumpWidget(
        wrap(PosOfflineBanner(pendingOperations: 2, onSync: () => synced++)),
      );

      await tester.tap(find.text('Synchroniser'));
      expect(synced, 1);
    });

    testWidgets('desactive le bouton pendant une synchronisation',
        (tester) async {
      await tester.pumpWidget(
        wrap(PosOfflineBanner(
          pendingOperations: 2,
          onSync: () {},
          syncing: true,
        )),
      );

      final button = tester.widget<TextButton>(find.byType(TextButton));
      expect(button.onPressed, isNull);
    });
  });

  group('PosErrorBanner', () {
    testWidgets('affiche le message et permet de le fermer', (tester) async {
      var dismissed = 0;

      await tester.pumpWidget(
        wrap(PosErrorBanner(
          message: 'La remise depasse le sous-total.',
          onDismiss: () => dismissed++,
        )),
      );

      expect(find.text('La remise depasse le sous-total.'), findsOneWidget);

      await tester.tap(find.byTooltip('Fermer'));
      expect(dismissed, 1);
    });
  });

  group('PosConnectionChip', () {
    testWidgets('affiche En ligne quand tout va bien', (tester) async {
      await tester.pumpWidget(wrap(const PosConnectionChip(offline: false)));

      expect(find.text('En ligne'), findsOneWidget);
    });

    testWidgets('affiche Hors ligne avec le nombre d operations',
        (tester) async {
      await tester.pumpWidget(
        wrap(const PosConnectionChip(offline: true, pending: 4)),
      );

      expect(find.text('Hors ligne (4)'), findsOneWidget);
    });

    testWidgets('passe en degrade des qu une operation attend', (tester) async {
      await tester.pumpWidget(
        wrap(const PosConnectionChip(offline: false, pending: 1)),
      );

      expect(find.text('Hors ligne (1)'), findsOneWidget);
    });
  });

  group('PosNumericKeypad', () {
    testWidgets('saisit des chiffres et efface', (tester) async {
      var value = '';
      var cleared = 0;

      await tester.pumpWidget(
        wrap(StatefulBuilder(
          builder: (context, setState) => Column(
            children: [
              Text(value, key: const Key('value')),
              PosNumericKeypad(
                onDigit: (digit) =>
                    setState(() => value = applyNumericKey(value, digit)),
                onBackspace: () =>
                    setState(() => value = applyBackspace(value)),
                onClear: () => setState(() {
                  value = '';
                  cleared++;
                }),
              ),
            ],
          ),
        )),
      );

      await tester.tap(find.text('1'));
      await tester.tap(find.text('2'));
      await tester.pump();

      expect(value, '12');

      await tester.tap(find.byIcon(Icons.keyboard_backspace));
      await tester.pump();

      expect(value, '1');

      await tester.tap(find.byIcon(Icons.backspace_outlined));
      await tester.pump();

      expect(value, '');
      expect(cleared, 1);
    });

    testWidgets('expose un bouton de validation optionnel', (tester) async {
      var validated = 0;

      await tester.pumpWidget(
        wrap(PosNumericKeypad(
          onDigit: (_) {},
          onBackspace: () {},
          onClear: () {},
          onValidate: () => validated++,
        )),
      );

      await tester.tap(find.byKey(const Key('pos-keypad-validate')));
      expect(validated, 1);
    });

    testWidgets('desactive la validation quand elle est interdite',
        (tester) async {
      await tester.pumpWidget(
        wrap(PosNumericKeypad(
          onDigit: (_) {},
          onBackspace: () {},
          onClear: () {},
          onValidate: () {},
          validateEnabled: false,
        )),
      );

      final button = tester.widget<FilledButton>(
        find.byKey(const Key('pos-keypad-validate')),
      );

      expect(button.onPressed, isNull);
    });

    testWidgets('les touches respectent la cible tactile de 48 dp',
        (tester) async {
      await tester.pumpWidget(
        wrap(PosNumericKeypad(
          onDigit: (_) {},
          onBackspace: () {},
          onClear: () {},
        )),
      );

      // La cible est la zone tapable, pas le glyphe : on mesure l'InkWell
      // qui porte le geste, sinon on ne teste que la hauteur du chiffre.
      final size = tester.getSize(
        find.ancestor(of: find.text('5'), matching: find.byType(InkWell)).first,
      );

      expect(size.height, greaterThanOrEqualTo(posMinTouchTarget));
    });
  });

  group('PosNumericField', () {
    testWidgets('affiche 0 quand la saisie est vide', (tester) async {
      await tester.pumpWidget(
        wrap(const PosNumericField(value: '', label: 'Quantite')),
      );

      expect(find.text('0'), findsOneWidget);
    });

    testWidgets('affiche la valeur saisie et son suffixe', (tester) async {
      await tester.pumpWidget(
        wrap(const PosNumericField(
            value: '1500', label: 'Montant', suffix: 'FCFA')),
      );

      expect(find.text('1500'), findsOneWidget);
      expect(find.text('FCFA'), findsOneWidget);
    });
  });

  group('PosSettingsScreen — acces imprimante (#9266)', () {
    Future<void> unlock(WidgetTester tester) async {
      await tester.enterText(find.byKey(const Key('pos-settings-pin')), '0000');
      await tester.tap(find.byKey(const Key('pos-settings-unlock')));
      await tester.pumpAndSettle();
    }

    testWidgets('annonce l absence d imprimante et propose d en choisir une',
        (tester) async {
      var opened = 0;

      await tester.pumpWidget(
        wrap(PosSettingsScreen(
          initialConfig:
              const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/api/v1'),
          onSave: (_) async {},
          probe: (_) async => const PosHealthResult(status: PosHealthStatus.ok),
          onOpenPrinter: () async => opened++,
        )),
      );

      await unlock(tester);

      expect(
          find.textContaining('Aucune imprimante configuree'), findsOneWidget);

      await tester.tap(find.byKey(const Key('pos-settings-printer')));
      await tester.pumpAndSettle();

      expect(opened, 1);
    });

    testWidgets('affiche l imprimante configuree', (tester) async {
      await tester.pumpWidget(
        wrap(PosSettingsScreen(
          initialConfig:
              const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/api/v1'),
          onSave: (_) async {},
          probe: (_) async => const PosHealthResult(status: PosHealthStatus.ok),
          printerName: 'Imprimante Caisse 1',
          onOpenPrinter: () async {},
        )),
      );

      await unlock(tester);

      expect(find.textContaining('Imprimante Caisse 1'), findsOneWidget);
      expect(find.text('Changer d\u2019imprimante'), findsOneWidget);
    });

    testWidgets('desactive l acces imprimante quand il n est pas branche',
        (tester) async {
      await tester.pumpWidget(
        wrap(PosSettingsScreen(
          initialConfig:
              const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/api/v1'),
          onSave: (_) async {},
          probe: (_) async => const PosHealthResult(status: PosHealthStatus.ok),
        )),
      );

      await unlock(tester);

      final button = tester.widget<OutlinedButton>(
        find.byKey(const Key('pos-settings-printer')),
      );

      expect(button.onPressed, isNull);
    });
  });
}
