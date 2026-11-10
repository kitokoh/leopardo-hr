import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_esc_pos.dart';
import 'package:leopardo_core/core/pos/pos_option_sheet.dart';
import 'package:leopardo_core/core/pos/pos_options.dart';
import 'package:leopardo_core/core/pos/pos_printer.dart';
import 'package:leopardo_core/core/pos/pos_printer_screen.dart';

/// Tests widget de la selection d'imprimante, du bouton d'impression et de la
/// feuille de variantes (#9264, #9265).
void main() {
  Widget wrap(Widget child) => MaterialApp(home: child);

  group('PosPrinterPickerScreen', () {
    testWidgets('liste les imprimantes decouvertes', (tester) async {
      final service = FakePosPrinterService(
        printers: const [
          PosPrinterDevice(id: 'AA:BB', name: 'Imprimante Caisse 1'),
          PosPrinterDevice(id: 'CC:DD', name: 'Imprimante Bar'),
        ],
      );

      await tester.pumpWidget(wrap(PosPrinterPickerScreen(service: service)));
      await tester.pumpAndSettle();

      expect(find.text('Imprimante Caisse 1'), findsOneWidget);
      expect(find.text('Imprimante Bar'), findsOneWidget);
    });

    testWidgets('affiche un message quand aucune imprimante n est trouvee',
        (tester) async {
      final service = FakePosPrinterService();

      await tester.pumpWidget(wrap(PosPrinterPickerScreen(service: service)));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('pos-printer-error')), findsOneWidget);
      expect(
        find.textContaining('Aucune imprimante detectee'),
        findsOneWidget,
      );
    });

    testWidgets('selectionne et connecte une imprimante', (tester) async {
      final service = FakePosPrinterService(
        printers: const [PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1')],
      );
      PosPrinterDevice? selected;

      await tester.pumpWidget(
        wrap(PosPrinterPickerScreen(
          service: service,
          onSelected: (device) async => selected = device,
        )),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('pos-printer-AA:BB')));
      await tester.pumpAndSettle();

      expect(selected?.id, 'AA:BB');
      expect(service.status, PosPrinterStatus.connected);
    });
  });

  group('PosPrintButton', () {
    testWidgets('imprime et confirme', (tester) async {
      final service = FakePosPrinterService(
        printers: const [PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1')],
      );
      await service
          .select(const PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1'));
      await service.connect();

      await tester.pumpWidget(
        wrap(Scaffold(
          body: PosPrintButton(
            service: service,
            buildBytes: () => const [0x1B, 0x40, 0x41],
          ),
        )),
      );

      await tester.tap(find.byKey(const Key('pos-print-button')));
      await tester.pumpAndSettle();

      expect(service.printedTickets.length, 1);
      expect(find.text('Ticket imprime.'), findsOneWidget);
    });

    testWidgets('signale une imprimante non connectee', (tester) async {
      final service = FakePosPrinterService(
        printers: const [PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1')],
      );

      await tester.pumpWidget(
        wrap(Scaffold(
          body: PosPrintButton(
            service: service,
            buildBytes: () => const [0x1B],
          ),
        )),
      );

      await tester.tap(find.byKey(const Key('pos-print-button')));
      await tester.pumpAndSettle();

      expect(service.printedTickets, isEmpty);
      expect(find.textContaining('non connectee'), findsOneWidget);
    });

    testWidgets('signale une erreur d impression (papier)', (tester) async {
      final service = FakePosPrinterService(
        printers: const [PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1')],
        failOnPrint: true,
        failMessage: 'Papier manquant.',
      );
      await service
          .select(const PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1'));
      await service.connect();

      await tester.pumpWidget(
        wrap(Scaffold(
          body: PosPrintButton(
            service: service,
            buildBytes: () => const [0x1B],
          ),
        )),
      );

      await tester.tap(find.byKey(const Key('pos-print-button')));
      await tester.pumpAndSettle();

      expect(find.text('Papier manquant.'), findsOneWidget);
    });
  });

  group('PosOptionSheet', () {
    const cuisson = PosOptionGroup(
      id: 1,
      name: 'Cuisson',
      required: true,
      options: [
        PosOption(id: 11, name: 'Saignant'),
        PosOption(id: 12, name: 'Bien cuit'),
      ],
    );

    const supplements = PosOptionGroup(
      id: 2,
      name: 'Supplements',
      maxSelections: 2,
      options: [
        PosOption(id: 21, name: 'Fromage', priceMinor: 700),
        PosOption(id: 22, name: 'Bacon', priceMinor: 900),
      ],
    );

    const options = PosProductOptions(groups: [cuisson, supplements]);

    testWidgets('bloque la validation tant que l obligatoire n est pas choisi',
        (tester) async {
      await tester.pumpWidget(
        wrap(const Scaffold(
          body: PosOptionSheet(
            productName: 'Poulet braise',
            basePriceMinor: 4500,
            productOptions: options,
          ),
        )),
      );
      await tester.pumpAndSettle();

      final button = tester.widget<FilledButton>(
        find.byKey(const Key('pos-option-confirm')),
      );

      expect(button.onPressed, isNull);
      expect(find.text('Completez les choix obligatoires'), findsOneWidget);
    });

    testWidgets('active la validation apres le choix obligatoire',
        (tester) async {
      await tester.pumpWidget(
        wrap(const Scaffold(
          body: PosOptionSheet(
            productName: 'Poulet braise',
            basePriceMinor: 4500,
            productOptions: options,
          ),
        )),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('pos-option-1-11')));
      await tester.pumpAndSettle();

      final button = tester.widget<FilledButton>(
        find.byKey(const Key('pos-option-confirm')),
      );

      expect(button.onPressed, isNotNull);
      expect(find.text('Ajouter au panier'), findsOneWidget);
    });

    testWidgets('met a jour le total avec les supplements', (tester) async {
      await tester.pumpWidget(
        wrap(const Scaffold(
          body: PosOptionSheet(
            productName: 'Poulet braise',
            basePriceMinor: 4500,
            productOptions: options,
          ),
        )),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('pos-option-1-11')));
      await tester.tap(find.byKey(const Key('pos-option-2-21')));
      await tester.pumpAndSettle();

      // 4500 + 700 = 5200 -> 52,00
      expect(find.text('52,00'), findsOneWidget);
    });

    testWidgets('renvoie la selection validee', (tester) async {
      PosOptionSelectionResult? result;

      await tester.pumpWidget(
        wrap(Scaffold(
          body: Builder(
            builder: (context) => ElevatedButton(
              onPressed: () async {
                result = await showPosOptionSheet(
                  context: context,
                  productName: 'Poulet braise',
                  basePriceMinor: 4500,
                  productOptions: options,
                );
              },
              child: const Text('ouvrir'),
            ),
          ),
        )),
      );

      await tester.tap(find.text('ouvrir'));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('pos-option-1-12')));
      await tester.tap(find.byKey(const Key('pos-option-2-22')));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('pos-option-confirm')));
      await tester.pumpAndSettle();

      expect(result, isNotNull);
      expect(result!.selections.length, 2);
      expect(result!.additionalMinor, 900);
    });
  });
}
