import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_barcode_scanner.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';
import 'package:leopardo_core/core/pos/pos_widgets.dart';

/// Tests widget des **ecrans POS partages** (#9266) : scan, mise en attente et
/// rappel de ticket, apercu du ticket, gestes de ligne, bandeau de
/// synchronisation.
///
/// Ces widgets sont utilises par les deux caisses : un test ici couvre les
/// deux apps, ce qui est exactement l'interet de les avoir factorises.
void main() {
  Widget wrap(Widget child) => MaterialApp(home: Scaffold(body: child));

  /// Les feuilles de caisse sont hautes : sur la surface de test par defaut
  /// (800x600) elles debordent, ce qui masquerait le comportement teste.
  Future<void> withTabletSurface(WidgetTester tester, Future<void> Function() body) async {
    tester.view.physicalSize = const Size(1200, 2000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await body();
  }

  PosReceipt receipt({int quantity = 2, int unitPrice = 1500, int discount = 0, int tip = 0}) =>
      PosReceipt(
        lines: [
          PosReceiptLine(name: 'Cafe', quantity: quantity, unitPriceMinor: unitPrice),
        ],
        discountMinor: discount,
        tipMinor: tip,
      );

  group('PosBarcodeScannerScreen', () {
    testWidgets('affiche le titre, la consigne et le repli manuel', (tester) async {
      await tester.pumpWidget(
        wrap(const PosBarcodeScannerScreen(
          title: 'Scanner un article',
          hint: 'Visez le code-barres.',
        )),
      );
      await tester.pump();

      expect(find.text('Scanner un article'), findsOneWidget);
      expect(find.text('Visez le code-barres.'), findsOneWidget);
      // Le repli manuel est le point critique : une caisse bloquee sur un
      // ecran noir ne peut plus vendre.
      expect(find.text('Saisir le code a la main'), findsOneWidget);
    });

    testWidgets('propose lampe et bascule de camera', (tester) async {
      await tester.pumpWidget(wrap(const PosBarcodeScannerScreen()));
      await tester.pump();

      expect(find.byTooltip('Lampe'), findsOneWidget);
      expect(find.byTooltip('Changer de camera'), findsOneWidget);
    });

    testWidgets('le repli manuel ferme l ecran de scan', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: ElevatedButton(
                onPressed: () => Navigator.of(context).push<void>(
                  MaterialPageRoute(
                    builder: (_) => const PosBarcodeScannerScreen(),
                  ),
                ),
                child: const Text('Scanner'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Scanner'));
      await tester.pumpAndSettle();

      expect(find.text('Saisir le code a la main'), findsOneWidget);

      await tester.tap(find.text('Saisir le code a la main'));
      await tester.pumpAndSettle();

      expect(find.text('Scanner'), findsOneWidget);
    });
  });

  group('PosReceiptPreview', () {
    testWidgets('affiche les lignes et le total encaisse', (tester) async {
      await tester.pumpWidget(wrap(PosReceiptPreview(receipt: receipt())));

      expect(find.textContaining('Cafe'), findsOneWidget);
      // Le montant apparait sur la ligne, le sous-total et le total : on
      // verifie que le total encaisse est bien celui du panier.
      expect(find.text('30,00'), findsNWidgets(3));
    });

    testWidgets('affiche la remise en negatif et le pourboire a part', (tester) async {
      await tester.pumpWidget(
        wrap(PosReceiptPreview(receipt: receipt(discount: 500, tip: 200))),
      );

      expect(find.text('-5,00'), findsOneWidget);
      expect(find.text('2,00'), findsOneWidget);
      // Total = 30,00 - 5,00 + 2,00 : le pourboire n'est pas une remise.
      expect(find.text('27,00'), findsOneWidget);
    });

    testWidgets('affiche table, couverts et type de commande', (tester) async {
      await tester.pumpWidget(
        wrap(PosReceiptPreview(
          receipt: PosReceipt(
            lines: const [
              PosReceiptLine(name: 'Plat', quantity: 1, unitPriceMinor: 2000),
            ],
            tableLabel: 'Table 4',
            covers: 3,
            orderTypeLabel: 'Sur place',
          ),
        )),
      );

      expect(find.textContaining('Table 4'), findsOneWidget);
      expect(find.textContaining('3 couvert(s)'), findsOneWidget);
      expect(find.textContaining('Sur place'), findsOneWidget);
    });

    testWidgets('affiche les options choisies sur la ligne', (tester) async {
      await tester.pumpWidget(
        wrap(PosReceiptPreview(
          receipt: PosReceipt(
            lines: const [
              PosReceiptLine(
                name: 'Poulet',
                quantity: 1,
                unitPriceMinor: 3000,
                options: ['Cuisson : Saignant', 'Fromage'],
              ),
            ],
          ),
        )),
      );

      expect(find.textContaining('Cuisson : Saignant'), findsOneWidget);
      expect(find.textContaining('Fromage'), findsOneWidget);
    });

    testWidgets('declenche l encaissement et l impression', (tester) async {
      var confirmed = 0;
      var printed = 0;

      await tester.pumpWidget(
        wrap(PosReceiptPreview(
          receipt: receipt(),
          onConfirm: () => confirmed++,
          onPrint: () => printed++,
        )),
      );

      await tester.tap(find.text('Encaisser'));
      expect(confirmed, 1);

      await tester.tap(find.byKey(const Key('pos-receipt-print')));
      expect(printed, 1);
    });

    testWidgets('masque l impression quand elle n est pas branchee', (tester) async {
      await tester.pumpWidget(wrap(PosReceiptPreview(receipt: receipt())));

      expect(find.byKey(const Key('pos-receipt-print')), findsNothing);
    });
  });

  group('PosParkTicketSheet', () {
    testWidgets('propose des libelles et renvoie celui choisi', (tester) async {
      await withTabletSurface(tester, () async {
        String? parked;

        await tester.pumpWidget(
          wrap(Builder(
            builder: (context) => ElevatedButton(
              onPressed: () async {
                parked = await showModalBottomSheet<String>(
                  context: context,
                  builder: (_) => const PosParkTicketSheet(),
                );
              },
              child: const Text('Ouvrir'),
            ),
          )),
        );

        await tester.tap(find.text('Ouvrir'));
        await tester.pumpAndSettle();

        expect(find.text('Client au comptoir'), findsOneWidget);

        // Un libelle suggere remplit le champ ; c'est le bouton qui valide.
        await tester.tap(find.text('Client au comptoir'));
        await tester.pumpAndSettle();

        await tester.tap(find.widgetWithText(FilledButton, 'Mettre en attente'));
        await tester.pumpAndSettle();

        expect(parked, 'Client au comptoir');
      });
    });

    testWidgets('refuse un libelle vide', (tester) async {
      await withTabletSurface(tester, () async {
        await tester.pumpWidget(
          wrap(Builder(
            builder: (context) => ElevatedButton(
              onPressed: () => showModalBottomSheet<String>(
                context: context,
                builder: (_) => const PosParkTicketSheet(),
              ),
              child: const Text('Ouvrir'),
            ),
          )),
        );

        await tester.tap(find.text('Ouvrir'));
        await tester.pumpAndSettle();

        // Le bouton de validation reste desactive tant qu'aucun libelle n'est
        // saisi : une commande en attente sans nom est introuvable.
        final button = tester.widget<FilledButton>(
          find.widgetWithText(FilledButton, 'Mettre en attente'),
        );

        expect(button.onPressed, isNull);
      });
    });
  });

  group('PosRecallTicketSheet', () {
    PosParkedTicket ticket({String id = 't1', String label = 'Client au comptoir'}) =>
        PosParkedTicket(
          id: id,
          label: label,
          payload: const {},
          parkedAt: DateTime(2026, 11, 9, 12),
          itemCount: 3,
          totalMinor: 4500,
        );

    testWidgets('affiche un etat vide quand rien n est en attente', (tester) async {
      await withTabletSurface(tester, () async {
        await tester.pumpWidget(
          wrap(const PosRecallTicketSheet(tickets: PosParkedTickets())),
        );

        expect(find.text('Aucune commande en attente.'), findsOneWidget);
      });
    });

    testWidgets('liste les commandes en attente avec leur total', (tester) async {
      await withTabletSurface(tester, () async {
        await tester.pumpWidget(
          wrap(PosRecallTicketSheet(
            tickets: PosParkedTickets(tickets: [ticket()]),
            now: DateTime(2026, 11, 9, 12, 5),
          )),
        );

        expect(find.text('Client au comptoir'), findsOneWidget);
        expect(find.textContaining('45,00'), findsOneWidget);
      });
    });

    testWidgets('renvoie l identifiant de la commande choisie', (tester) async {
      await withTabletSurface(tester, () async {
        String? recalled;

        await tester.pumpWidget(
          wrap(Builder(
            builder: (context) => ElevatedButton(
              onPressed: () async {
                recalled = await showModalBottomSheet<String>(
                  context: context,
                  builder: (_) => PosRecallTicketSheet(
                    tickets: PosParkedTickets(tickets: [ticket()]),
                    now: DateTime(2026, 11, 9, 12, 5),
                  ),
                );
              },
              child: const Text('Ouvrir'),
            ),
          )),
        );

        await tester.tap(find.text('Ouvrir'));
        await tester.pumpAndSettle();

        await tester.tap(find.text('Client au comptoir'));
        await tester.pumpAndSettle();

        expect(recalled, 't1');
      });
    });
  });

  group('PosSwipeableLine', () {
    testWidgets('ouvre les actions sur appui long', (tester) async {
      PosLineAction? action;

      await tester.pumpWidget(
        wrap(PosSwipeableLine(
          onRemove: () {},
          onAction: (value) => action = value,
          child: const ListTile(title: Text('Cafe')),
        )),
      );

      await tester.longPress(find.text('Cafe'));
      await tester.pumpAndSettle();

      expect(find.text('Augmenter la quantite'), findsOneWidget);

      await tester.tap(find.text('Augmenter la quantite'));
      await tester.pumpAndSettle();

      expect(action, PosLineAction.increase);
    });

    testWidgets('retire la ligne par balayage', (tester) async {
      var removed = 0;

      await tester.pumpWidget(
        wrap(PosSwipeableLine(
          onRemove: () => removed++,
          onAction: (_) {},
          child: const ListTile(title: Text('Cafe')),
        )),
      );

      await tester.drag(find.text('Cafe'), const Offset(-500, 0));
      await tester.pumpAndSettle();

      expect(removed, 1);
    });
  });

  group('PosVoidReasonSheet', () {
    testWidgets('exige un motif et renvoie le choix', (tester) async {
      await withTabletSurface(tester, () async {
        ({PosVoidReason reason, String comment})? result;

        await tester.pumpWidget(
          wrap(Builder(
            builder: (context) => ElevatedButton(
              onPressed: () async {
                result = await showModalBottomSheet<({PosVoidReason reason, String comment})>(
                  context: context,
                  builder: (_) => const PosVoidReasonSheet(itemName: 'Cafe'),
                );
              },
              child: const Text('Ouvrir'),
            ),
          )),
        );

        await tester.tap(find.text('Ouvrir'));
        await tester.pumpAndSettle();

        expect(find.text('Cafe'), findsOneWidget);

        await tester.tap(find.text('Casse / perte'));
        await tester.pumpAndSettle();

        await tester.tap(find.widgetWithText(FilledButton, 'Annuler la ligne'));
        await tester.pumpAndSettle();

        expect(result?.reason, PosVoidReason.breakage);
      });
    });

    testWidgets('bloque la validation tant que le motif Autre n est pas precise',
        (tester) async {
      await withTabletSurface(tester, () async {
        await tester.pumpWidget(
          wrap(Builder(
            builder: (context) => ElevatedButton(
              onPressed: () => showModalBottomSheet<({PosVoidReason reason, String comment})>(
                context: context,
                builder: (_) => const PosVoidReasonSheet(itemName: 'Cafe'),
              ),
              child: const Text('Ouvrir'),
            ),
          )),
        );

        await tester.tap(find.text('Ouvrir'));
        await tester.pumpAndSettle();

        await tester.tap(find.text('Autre'));
        await tester.pumpAndSettle();

        // Sans precision, la trace ne dit rien : on refuse la validation.
        final button = tester.widget<FilledButton>(
          find.widgetWithText(FilledButton, 'Annuler la ligne'),
        );

        expect(button.onPressed, isNull);
        expect(find.textContaining('exige une precision'), findsOneWidget);
      });
    });
  });

  group('PosSyncBanner', () {
    testWidgets('affiche la progression et permet de synchroniser', (tester) async {
      var synced = 0;

      await tester.pumpWidget(
        wrap(PosSyncBanner(
          progress: const PosSyncProgress(total: 4, synced: 1),
          onSyncNow: () => synced++,
        )),
      );

      expect(find.textContaining('1/4'), findsOneWidget);
      expect(find.textContaining('en attente de reseau'), findsOneWidget);

      await tester.tap(find.text('Synchroniser'));
      expect(synced, 1);
    });

    testWidgets('signale les conflits et propose de les ecarter', (tester) async {
      var dismissed = 0;

      await tester.pumpWidget(
        wrap(PosSyncBanner(
          progress: const PosSyncProgress(total: 3, synced: 1, conflicts: 1),
          onDismissConflicts: () => dismissed++,
        )),
      );

      expect(find.textContaining('refusee(s) par le serveur'), findsOneWidget);

      await tester.tap(find.byTooltip('Masquer'));
      expect(dismissed, 1);
    });

    testWidgets('reste muet quand il n y a rien a dire', (tester) async {
      await tester.pumpWidget(
        wrap(const PosSyncBanner(progress: PosSyncProgress())),
      );

      expect(find.text('Tout est synchronise.'), findsOneWidget);
      expect(find.text('Synchroniser'), findsNothing);
    });
  });
}
