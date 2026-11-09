import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_esc_pos.dart';
import 'package:leopardo_core/core/pos/pos_history.dart';
import 'package:leopardo_core/core/pos/pos_printer.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

/// Tests de l'**historique des ventes** et de la **reimpression** (#9268).
///
/// Le cas qui compte : le client revient avec un ticket illisible. Sans
/// historique, le vendeur n'a aucun moyen de retrouver la vente.
void main() {
  Widget wrap(Widget child) => MaterialApp(home: child);

  PosReceipt receipt(
          {int quantity = 2, int unitPrice = 1500, int discount = 0}) =>
      PosReceipt(
        lines: [
          PosReceiptLine(
            name: 'Cafe',
            quantity: quantity,
            unitPriceMinor: unitPrice,
          ),
        ],
        discountMinor: discount,
      );

  PosSaleRecord record({
    String id = 'sale-1',
    int quantity = 2,
    int unitPrice = 1500,
    int discount = 0,
    String? paymentLabel = 'Especes',
    int? orderId = 42,
    bool queued = false,
    DateTime? paidAt,
  }) =>
      PosSaleRecord(
        id: id,
        receipt: receipt(
            quantity: quantity, unitPrice: unitPrice, discount: discount),
        paidAt: paidAt ?? DateTime(2026, 11, 9, 14, 30),
        orderId: orderId,
        paymentLabel: paymentLabel,
        queued: queued,
      );

  group('PosSalesHistory', () {
    test('ajoute la vente la plus recente en tete', () {
      final history =
          const PosSalesHistory().add(record(id: 'a')).add(record(id: 'b'));

      expect(history.records.first.id, 'b');
      expect(history.length, 2);
    });

    test('remplace une vente de meme identifiant au lieu de la dupliquer', () {
      final history = const PosSalesHistory()
          .add(record(id: 'a', quantity: 1))
          .add(record(id: 'a', quantity: 5));

      expect(history.length, 1);
      expect(history.records.first.itemCount, 5);
    });

    test('tronque au plafond en gardant les plus recentes', () {
      var history = const PosSalesHistory();

      for (var i = 0; i < PosSalesHistory.maxRecords + 10; i++) {
        history = history.add(record(id: 'sale-$i'));
      }

      expect(history.length, PosSalesHistory.maxRecords);
      expect(
          history.records.first.id, 'sale-${PosSalesHistory.maxRecords + 9}');
    });

    test('retrouve une vente par identifiant', () {
      final history = const PosSalesHistory().add(record(id: 'a'));

      expect(history.byId('a')?.id, 'a');
      expect(history.byId('inconnu'), isNull);
    });

    test('retrouve les ventes par montant — le client se souvient du prix', () {
      final history = const PosSalesHistory()
          .add(record(id: 'a', quantity: 2, unitPrice: 1500))
          .add(record(id: 'b', quantity: 1, unitPrice: 1500));

      final matches = history.byTotal(3000);

      expect(matches.length, 1);
      expect(matches.first.id, 'a');
    });

    test('calcule le total encaisse d une journee', () {
      final history = const PosSalesHistory()
          .add(record(
              id: 'a',
              quantity: 2,
              unitPrice: 1500,
              paidAt: DateTime(2026, 11, 9, 9)))
          .add(record(
              id: 'b',
              quantity: 1,
              unitPrice: 1000,
              paidAt: DateTime(2026, 11, 9, 18)))
          .add(record(
              id: 'c',
              quantity: 1,
              unitPrice: 9999,
              paidAt: DateTime(2026, 11, 8, 12)));

      expect(history.onDay(DateTime(2026, 11, 9)).length, 2);
      expect(history.totalOnDay(DateTime(2026, 11, 9)), 4000);
    });

    test('survit a un aller-retour JSON', () {
      final history =
          const PosSalesHistory().add(record(id: 'a', queued: true));
      final restored = PosSalesHistory.fromJson(history.toJson());

      expect(restored.length, 1);
      expect(restored.records.first.id, 'a');
      expect(restored.records.first.queued, isTrue);
      expect(restored.records.first.receipt.totalMinor, 3000);
    });

    test('ignore une entree corrompue sans perdre le reste', () {
      final restored = PosSalesHistory.fromJson({
        'records': [
          {
            'id': 'bon',
            'receipt': receipt().toJson(),
            'paidAt': '2026-11-09T10:00:00.000'
          },
          'pas un objet',
        ],
      });

      expect(restored.length, 1);
      expect(restored.records.first.id, 'bon');
    });
  });

  group('PosSalesHistoryScreen', () {
    testWidgets('affiche un etat vide utile quand aucune vente',
        (tester) async {
      await tester.pumpWidget(
        wrap(const PosSalesHistoryScreen(history: PosSalesHistory())),
      );
      await tester.pumpAndSettle();

      expect(find.text('Aucune vente aujourd\u2019hui'), findsOneWidget);
      expect(find.textContaining('reimprimer'), findsOneWidget);
    });

    testWidgets('liste les ventes avec montant et moyen de paiement',
        (tester) async {
      final history = const PosSalesHistory().add(record(id: 'a'));

      await tester.pumpWidget(wrap(PosSalesHistoryScreen(history: history)));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('pos-history-a')), findsOneWidget);
      expect(find.text('30,00'), findsOneWidget);
      expect(find.textContaining('Especes'), findsOneWidget);
    });

    testWidgets('signale une vente encore en attente de reseau',
        (tester) async {
      final history =
          const PosSalesHistory().add(record(id: 'a', queued: true));

      await tester.pumpWidget(wrap(PosSalesHistoryScreen(history: history)));
      await tester.pumpAndSettle();

      expect(find.textContaining('en attente de reseau'), findsOneWidget);
    });

    testWidgets('filtre par montant saisi', (tester) async {
      final history = const PosSalesHistory()
          .add(record(id: 'a', quantity: 2, unitPrice: 1500))
          .add(record(id: 'b', quantity: 1, unitPrice: 1000));

      await tester.pumpWidget(wrap(PosSalesHistoryScreen(history: history)));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.byKey(const Key('pos-history-search')), '1000');
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('pos-history-b')), findsOneWidget);
      expect(find.byKey(const Key('pos-history-a')), findsNothing);
    });

    testWidgets('affiche un etat vide quand le filtre ne trouve rien',
        (tester) async {
      final history = const PosSalesHistory().add(record(id: 'a'));

      await tester.pumpWidget(wrap(PosSalesHistoryScreen(history: history)));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.byKey(const Key('pos-history-search')), '999999');
      await tester.pumpAndSettle();

      expect(find.text('Aucun resultat'), findsOneWidget);
    });

    testWidgets('desactive la reimpression sans imprimante configuree',
        (tester) async {
      final history = const PosSalesHistory().add(record(id: 'a'));

      await tester.pumpWidget(wrap(PosSalesHistoryScreen(history: history)));
      await tester.pumpAndSettle();

      final button = tester.widget<IconButton>(
        find.byKey(const Key('pos-history-reprint-a')),
      );

      expect(button.onPressed, isNull);
      expect(button.tooltip, 'Aucune imprimante configuree');
    });

    testWidgets('reimprime le ticket avec la mention REIMPRESSION',
        (tester) async {
      final service = FakePosPrinterService(
        printers: const [PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1')],
      );
      await service
          .select(const PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1'));
      await service.connect();

      final history = const PosSalesHistory().add(record(id: 'a'));
      var reprinted = 0;

      await tester.pumpWidget(
        wrap(PosSalesHistoryScreen(
          history: history,
          printer: service,
          onReprint: (_) => reprinted++,
        )),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('pos-history-reprint-a')));
      await tester.pumpAndSettle();

      expect(service.printedTickets.length, 1);
      expect(reprinted, 1);

      // Le duplicata doit se distinguer de l'original sur le papier.
      final text = String.fromCharCodes(service.printedTickets.first);
      expect(text, contains('REIMPRESSION'));
    });

    testWidgets('signale un echec d impression sans perdre l ecran',
        (tester) async {
      final service = FakePosPrinterService(
        printers: const [PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1')],
        failOnPrint: true,
        failMessage: 'Papier manquant.',
      );
      await service
          .select(const PosPrinterDevice(id: 'AA:BB', name: 'Caisse 1'));

      final history = const PosSalesHistory().add(record(id: 'a'));

      await tester.pumpWidget(
        wrap(PosSalesHistoryScreen(history: history, printer: service)),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('pos-history-reprint-a')));
      await tester.pumpAndSettle();

      expect(find.text('Papier manquant.'), findsOneWidget);
      expect(find.byKey(const Key('pos-history-a')), findsOneWidget);
    });
  });
}
