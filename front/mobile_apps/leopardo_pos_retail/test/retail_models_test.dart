import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_pos_retail/core/models/retail_models.dart';
import 'package:leopardo_pos_retail/core/offline/retail_offline_queue.dart';

/// Tests du coeur metier du POS Retail tablette (#9215).
///
/// Ces tests couvrent les regles qui, si elles cassent, font perdre de
/// l'argent ou bloquent une vente : totaux du panier, remise bornee, attendu
/// de caisse, validite d'un remboursement, stabilite de la cle d'idempotence,
/// recherche produit et correspondance code-barres.
void main() {
  const bissap = RetailProduct(
    id: 1,
    name: 'Jus de bissap 50cl',
    priceMinor: 1500,
    costMinor: 900,
    sku: 'SKU-BISSAP-50',
    barcode: '6001234567890',
  );
  const the = RetailProduct(
    id: 2,
    name: 'The a la menthe',
    priceMinor: 700,
    costMinor: 300,
    sku: 'SKU-THE-01',
  );

  group('RetailCart', () {
    test('ajoute un produit et incremente la ligne existante', () {
      final cart = const RetailCart().addProduct(bissap).addProduct(bissap).addProduct(the);

      expect(cart.lines.length, 2);
      expect(cart.lines.first.quantity, 2);
      expect(cart.itemCount, 3);
    });

    test('calcule le sous-total en unites mineures', () {
      final cart = const RetailCart().addProduct(bissap).addProduct(bissap).addProduct(the);

      // 2 * 1500 + 1 * 700 = 3700
      expect(cart.subtotalMinor, 3700);
    });

    test('applique la remise au total', () {
      final cart = const RetailCart()
          .addProduct(bissap)
          .addProduct(the)
          .copyWith(discountMinor: 200);

      // 1500 + 700 - 200 = 2000
      expect(cart.totalMinor, 2000);
    });

    test('ne rend jamais un total negatif', () {
      final cart = const RetailCart().addProduct(the).copyWith(discountMinor: 5000);

      expect(cart.totalMinor, 0);
    });

    test('refuse une remise superieure au sous-total', () {
      final cart = const RetailCart().addProduct(the).copyWith(discountMinor: 5000);

      expect(cart.isValid, isFalse);
    });

    test('retire une ligne quand la quantite tombe a zero', () {
      final cart = const RetailCart().addProduct(bissap).removeProduct(bissap.id);

      expect(cart.isEmpty, isTrue);
      expect(cart.subtotalMinor, 0);
    });

    test('setQuantity fixe la quantite saisie au pave numerique', () {
      final cart = const RetailCart().addProduct(bissap).setQuantity(bissap.id, 12);

      expect(cart.lines.single.quantity, 12);
      expect(cart.subtotalMinor, 18000);
    });

    test('setQuantity a zero retire la ligne', () {
      final cart = const RetailCart().addProduct(bissap).setQuantity(bissap.id, 0);

      expect(cart.isEmpty, isTrue);
    });

    test('un panier vide n\'est pas valide', () {
      expect(const RetailCart().isValid, isFalse);
    });

    test('survit a un aller-retour JSON', () {
      final cart = const RetailCart()
          .addProduct(bissap)
          .addProduct(the)
          .copyWith(discountMinor: 100, note: 'Client fidele');

      final restored = RetailCart.fromJson(cart.toJson());

      expect(restored.lines.length, 2);
      expect(restored.totalMinor, cart.totalMinor);
      expect(restored.discountMinor, 100);
      expect(restored.note, 'Client fidele');
    });
  });

  group('RetailProduct', () {
    test('matche par nom, insensible a la casse', () {
      expect(bissap.matches('bissap'), isTrue);
      expect(bissap.matches('BISSAP'), isTrue);
    });

    test('matche par SKU', () {
      expect(bissap.matches('SKU-BISSAP'), isTrue);
    });

    test('matche un code-barres exact', () {
      expect(bissap.matches('6001234567890'), isTrue);
    });

    test('ne matche pas un code-barres partiel', () {
      // Un code partiel ne doit pas ramener le mauvais article en caisse.
      expect(bissap.matches('600123'), isFalse);
    });

    test('une recherche vide matche tout', () {
      expect(bissap.matches(''), isTrue);
    });

    test('calcule la marge unitaire sans jamais la rendre negative', () {
      expect(bissap.marginMinor, 600);

      const loss = RetailProduct(id: 3, name: 'Solde', priceMinor: 100, costMinor: 500);

      expect(loss.marginMinor, 0);
    });

    test('isScannable reflete la presence d\'un code-barres', () {
      expect(bissap.isScannable, isTrue);
      expect(the.isScannable, isFalse);
    });
  });

  group('RetailSession', () {
    test('calcule l\'attendu de caisse', () {
      // 10000 + 3000 + 2000 - 500 - 5000 - 1000 = 8500
      final expected = RetailSession.expectedCash(
        openingCashMinor: 10000,
        cashSalesMinor: 3000,
        cashInMinor: 2000,
        cashOutMinor: 500,
        dropsMinor: 5000,
        cashRefundsMinor: 1000,
      );

      expect(expected, 8500);
    });

    test('ne rend jamais un attendu negatif', () {
      final expected = RetailSession.expectedCash(
        openingCashMinor: 0,
        cashSalesMinor: 0,
        cashInMinor: 0,
        cashOutMinor: 0,
        dropsMinor: 5000,
        cashRefundsMinor: 0,
      );

      expect(expected, 0);
    });

    test('calcule l\'ecart de comptage', () {
      expect(RetailSession.variance(countedCashMinor: 8500, expectedCashMinor: 8500), 0);
      expect(RetailSession.variance(countedCashMinor: 8400, expectedCashMinor: 8500), -100);
      expect(RetailSession.variance(countedCashMinor: 8600, expectedCashMinor: 8500), 100);
    });
  });

  group('RetailCashMovement', () {
    test('exige un montant positif et un motif', () {
      const valid = RetailCashMovement(
        type: RetailCashMovementType.cashIn,
        amountMinor: 2000,
        reason: 'Appoint de monnaie',
      );
      const noReason = RetailCashMovement(
        type: RetailCashMovementType.cashIn,
        amountMinor: 2000,
        reason: '   ',
      );
      const noAmount = RetailCashMovement(
        type: RetailCashMovementType.cashIn,
        amountMinor: 0,
        reason: 'Appoint',
      );

      expect(valid.isValid, isTrue);
      expect(noReason.isValid, isFalse);
      expect(noAmount.isValid, isFalse);
    });

    test('signe l\'effet sur l\'attendu de caisse', () {
      const cashIn = RetailCashMovement(
        type: RetailCashMovementType.cashIn,
        amountMinor: 2000,
        reason: 'Appoint',
      );
      const drop = RetailCashMovement(
        type: RetailCashMovementType.drop,
        amountMinor: 5000,
        reason: 'Banque',
      );

      expect(cashIn.signedEffectMinor, 2000);
      expect(drop.signedEffectMinor, -5000);
    });

    test('expose les valeurs de contrat du backend', () {
      expect(RetailCashMovementType.cashIn.wireValue, 'in');
      expect(RetailCashMovementType.cashOut.wireValue, 'out');
      expect(RetailCashMovementType.drop.wireValue, 'drop');
    });
  });

  group('RetailRefund', () {
    test('exige un montant positif et un motif', () {
      const valid = RetailRefund(
        amountMinor: 1000,
        reason: 'Article rapporte',
        method: RetailPaymentMethod.cash,
      );
      const noReason = RetailRefund(
        amountMinor: 1000,
        reason: '',
        method: RetailPaymentMethod.cash,
      );

      expect(valid.isValid, isTrue);
      expect(noReason.isValid, isFalse);
    });

    test('refuse un cumul superieur au total de la commande', () {
      const refund = RetailRefund(
        amountMinor: 2000,
        reason: 'Retour',
        method: RetailPaymentMethod.cash,
      );

      // 2000 deja rembourse + 2000 > 3000 de commande.
      expect(refund.fitsWithin(3000, 2000), isFalse);
      expect(refund.fitsWithin(3000, 0), isTrue);
    });
  });

  group('RetailOfflineQueue', () {
    test('la cle d\'encaissement est stable pour les memes arguments', () {
      final first = RetailOfflineQueue.paymentKey(orderId: 42, amountMinor: 3700, method: 'cash');
      final second = RetailOfflineQueue.paymentKey(orderId: 42, amountMinor: 3700, method: 'cash');

      expect(first, second);
    });

    test('la cle d\'encaissement differe quand le montant ou le moyen change', () {
      final base = RetailOfflineQueue.paymentKey(orderId: 42, amountMinor: 3700, method: 'cash');

      expect(
        RetailOfflineQueue.paymentKey(orderId: 42, amountMinor: 3800, method: 'cash'),
        isNot(base),
      );
      expect(
        RetailOfflineQueue.paymentKey(orderId: 42, amountMinor: 3700, method: 'card'),
        isNot(base),
      );
    });

    test('la cle de remboursement est distincte de celle d\'encaissement', () {
      final pay = RetailOfflineQueue.paymentKey(orderId: 42, amountMinor: 1000, method: 'cash');
      final refund = RetailOfflineQueue.refundKey(orderId: 42, amountMinor: 1000, method: 'cash');

      expect(pay, isNot(refund));
    });
  });

  group('RetailPaymentMethod', () {
    test('expose les valeurs de contrat du backend', () {
      expect(RetailPaymentMethod.cash.wireValue, 'cash');
      expect(RetailPaymentMethod.card.wireValue, 'card');
      expect(RetailPaymentMethod.mobileMoney.wireValue, 'mobile_money');
    });
  });
}
