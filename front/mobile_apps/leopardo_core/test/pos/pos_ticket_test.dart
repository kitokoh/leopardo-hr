import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

/// Tests des actions de commande partagees : mise en attente, annulation
/// motivee, apercu du ticket, et progression de synchronisation.
void main() {
  group('PosVoidReason', () {
    test('expose les valeurs de contrat backend', () {
      expect(PosVoidReason.entryError.wireValue, 'entry_error');
      expect(PosVoidReason.breakage.wireValue, 'breakage');
      expect(PosVoidReason.offered.wireValue, 'offered');
      expect(PosVoidReason.other.wireValue, 'other');
    });

    test('seul « Autre » exige un commentaire', () {
      expect(PosVoidReason.other.requiresComment, isTrue);
      expect(PosVoidReason.entryError.requiresComment, isFalse);
    });

    test('retombe sur « Autre » pour une valeur inconnue', () {
      expect(PosVoidReason.fromWire('nope'), PosVoidReason.other);
      expect(PosVoidReason.fromWire(null), PosVoidReason.other);
    });
  });

  group('PosVoidedLine', () {
    test('un motif ferme sans commentaire est valide', () {
      final line = PosVoidedLine(
        itemId: 1,
        itemName: 'Bissap',
        quantity: 1,
        amountMinor: 1500,
        reason: PosVoidReason.breakage,
        voidedAt: DateTime(2026, 11, 9),
      );

      expect(line.isValid, isTrue);
    });

    test('« Autre » sans commentaire n est pas valide', () {
      final line = PosVoidedLine(
        itemId: 1,
        itemName: 'Bissap',
        quantity: 1,
        amountMinor: 1500,
        reason: PosVoidReason.other,
        voidedAt: DateTime(2026, 11, 9),
      );

      expect(line.isValid, isFalse);
    });

    test('« Autre » avec commentaire est valide', () {
      final line = PosVoidedLine(
        itemId: 1,
        itemName: 'Bissap',
        quantity: 1,
        amountMinor: 1500,
        reason: PosVoidReason.other,
        comment: 'Plat renvoye en cuisine',
        voidedAt: DateTime(2026, 11, 9),
      );

      expect(line.isValid, isTrue);
    });

    test('un montant nul n est pas valide', () {
      final line = PosVoidedLine(
        itemId: 1,
        itemName: 'Bissap',
        quantity: 1,
        amountMinor: 0,
        reason: PosVoidReason.offered,
        voidedAt: DateTime(2026, 11, 9),
      );

      expect(line.isValid, isFalse);
    });

    test('survit a un aller-retour JSON', () {
      final line = PosVoidedLine(
        itemId: 7,
        itemName: 'The',
        quantity: 2,
        amountMinor: 1400,
        reason: PosVoidReason.customerChanged,
        voidedAt: DateTime(2026, 11, 9, 12, 30),
      );

      final restored = PosVoidedLine.fromJson(line.toJson());

      expect(restored.itemId, 7);
      expect(restored.quantity, 2);
      expect(restored.reason, PosVoidReason.customerChanged);
      expect(restored.voidedAt, DateTime(2026, 11, 9, 12, 30));
    });
  });

  group('PosParkedTickets', () {
    PosParkedTicket ticket(String id, DateTime at, {int total = 1000}) =>
        PosParkedTicket(
          id: id,
          label: 'Commande $id',
          payload: const {'lines': []},
          parkedAt: at,
          itemCount: 2,
          totalMinor: total,
        );

    test('met en attente et trie du plus recent au plus ancien', () {
      final parked = const PosParkedTickets()
          .park(ticket('a', DateTime(2026, 11, 9, 10)))
          .park(ticket('b', DateTime(2026, 11, 9, 12)));

      expect(parked.tickets.map((t) => t.id), ['b', 'a']);
    });

    test('rappelle un ticket et le retire de la file', () {
      final parked =
          const PosParkedTickets().park(ticket('a', DateTime(2026, 11, 9, 10)));
      final result = parked.recall('a');

      expect(result.ticket!.id, 'a');
      expect(result.remaining.isEmpty, isTrue);
    });

    test('rappeler un ticket inconnu ne change rien', () {
      final parked =
          const PosParkedTickets().park(ticket('a', DateTime(2026, 11, 9, 10)));
      final result = parked.recall('zzz');

      expect(result.ticket, isNull);
      expect(result.remaining.length, 1);
    });

    test('refuse une mise en attente au-dela du plafond', () {
      var parked = const PosParkedTickets();

      for (var index = 0; index < PosParkedTickets.maxTickets; index++) {
        parked = parked.park(ticket('t$index', DateTime(2026, 11, 9, 10)));
      }

      expect(parked.isFull, isTrue);
      expect(parked.park(ticket('extra', DateTime(2026, 11, 9, 11))).length,
          PosParkedTickets.maxTickets);
    });

    test('signale les tickets oublies depuis plus de 2 h', () {
      final parked = const PosParkedTickets()
          .park(ticket('vieux', DateTime(2026, 11, 9, 8)))
          .park(ticket('recent', DateTime(2026, 11, 9, 11, 30)));

      final stale = parked.staleAt(DateTime(2026, 11, 9, 12));

      expect(stale.map((t) => t.id), ['vieux']);
    });

    test('survit a un aller-retour JSON', () {
      final parked = const PosParkedTickets()
          .park(ticket('a', DateTime(2026, 11, 9, 10)))
          .park(ticket('b', DateTime(2026, 11, 9, 12)));

      final restored = PosParkedTickets.fromJson(parked.toJson());

      expect(restored.tickets.map((t) => t.id), ['b', 'a']);
      expect(restored.tickets.first.totalMinor, 1000);
    });
  });

  group('PosReceipt', () {
    const lines = [
      PosReceiptLine(name: 'Bissap', quantity: 2, unitPriceMinor: 1500),
      PosReceiptLine(name: 'The', quantity: 1, unitPriceMinor: 700),
    ];

    test('calcule le sous-total', () {
      const receipt = PosReceipt(lines: lines);

      expect(receipt.subtotalMinor, 3700);
      expect(receipt.itemCount, 3);
    });

    test('le pourboire s ajoute au total et n est pas une remise', () {
      const receipt =
          PosReceipt(lines: lines, discountMinor: 200, tipMinor: 500);

      expect(receipt.netMinor, 3500);
      expect(receipt.totalMinor, 4000);
    });

    test('ne rend jamais un net negatif', () {
      const receipt = PosReceipt(lines: lines, discountMinor: 9999);

      expect(receipt.netMinor, 0);
      expect(receipt.isValid, isFalse);
    });

    test('totalise les lignes annulees', () {
      final receipt = PosReceipt(
        lines: lines,
        voidedLines: [
          PosVoidedLine(
            itemId: 9,
            itemName: 'Cafe',
            quantity: 1,
            amountMinor: 800,
            reason: PosVoidReason.breakage,
            voidedAt: DateTime(2026, 11, 9),
          ),
        ],
      );

      expect(receipt.voidedMinor, 800);
      expect(receipt.isValid, isTrue);
    });

    test('un ticket vide n est pas valide', () {
      expect(const PosReceipt(lines: []).isValid, isFalse);
    });
  });

  group('PosSyncProgress', () {
    test('calcule le pourcentage traite', () {
      const progress = PosSyncProgress(total: 4, synced: 2, conflicts: 1);

      expect(progress.percent, 75);
      expect(progress.remaining, 1);
    });

    test('un total nul est considere complet', () {
      const progress = PosSyncProgress();

      expect(progress.percent, 100);
      expect(progress.isComplete, isFalse);
      expect(progress.message, 'Tout est synchronise.');
    });

    test('signale les conflits en priorite', () {
      const progress = PosSyncProgress(total: 3, synced: 1, conflicts: 1);

      expect(progress.hasConflicts, isTrue);
      expect(progress.message, contains('refusee'));
    });

    test('signale les echecs transitoires', () {
      const progress = PosSyncProgress(total: 3, synced: 1, failed: 1);

      expect(progress.message, contains('nouvel essai'));
    });

    test('borne le pourcentage entre 0 et 100', () {
      const progress = PosSyncProgress(total: 2, synced: 5);

      expect(progress.percent, 100);
    });
  });

  group('posRetryDelay', () {
    test('aucun delai a la premiere tentative', () {
      expect(posRetryDelay(0), Duration.zero);
    });

    test('croissance exponentielle', () {
      expect(posRetryDelay(1), const Duration(seconds: 2));
      expect(posRetryDelay(2), const Duration(seconds: 4));
      expect(posRetryDelay(3), const Duration(seconds: 8));
    });

    test('plafonne a 60 s', () {
      expect(posRetryDelay(20), const Duration(seconds: 60));
    });
  });

  group('posShouldRetry', () {
    test('reessaie un echec reseau (pas de statut)', () {
      expect(posShouldRetry(statusCode: null, attempts: 1), isTrue);
    });

    test('reessaie un 5xx et un 429', () {
      expect(posShouldRetry(statusCode: 503, attempts: 1), isTrue);
      expect(posShouldRetry(statusCode: 429, attempts: 1), isTrue);
    });

    test('ne reessaie pas un 4xx metier', () {
      expect(posShouldRetry(statusCode: 422, attempts: 1), isFalse);
      expect(posShouldRetry(statusCode: 409, attempts: 1), isFalse);
    });

    test('abandonne au-dela du plafond de tentatives', () {
      expect(posShouldRetry(statusCode: null, attempts: 8), isFalse);
    });
  });

  group('posSyncStatusFor', () {
    test('un 2xx est synchronise', () {
      expect(
          posSyncStatusFor(statusCode: 201, attempts: 1), PosSyncStatus.synced);
    });

    test('un 4xx est un conflit', () {
      expect(posSyncStatusFor(statusCode: 409, attempts: 1),
          PosSyncStatus.conflict);
    });

    test('un echec reseau est un nouvel essai', () {
      expect(posSyncStatusFor(statusCode: null, attempts: 1),
          PosSyncStatus.retrying);
    });
  });
}
