import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_pos_restaurant/core/models/pos_models.dart';
import 'package:leopardo_pos_restaurant/features/pos/payment_sheets.dart';

/// Tests des regles de caisse Restaurant ajoutees (#9214) : remise, pourboire
/// et paiement partage.
///
/// Ces regles touchent a l'argent : une remise qui depasse l'addition ou un
/// partage qui ne couvre pas le total doit etre refuse, pas arrondi.
void main() {
  const plat = PosProduct(id: 1, name: 'Poulet braise', priceMinor: 5000);
  const boisson = PosProduct(id: 2, name: 'Bissap', priceMinor: 1000);

  group('Cart — remise et pourboire', () {
    test('le sous-total ignore remise et pourboire', () {
      final cart = const Cart()
          .addProduct(plat)
          .addProduct(boisson)
          .copyWith(discountMinor: 500, tipMinor: 300);

      expect(cart.subtotalMinor, 6000);
    });

    test('le net deduit la remise', () {
      final cart = const Cart().addProduct(plat).copyWith(discountMinor: 1000);

      expect(cart.netMinor, 4000);
    });

    test('le total ajoute le pourboire au net', () {
      final cart = const Cart()
          .addProduct(plat)
          .copyWith(discountMinor: 1000, tipMinor: 500);

      // 5000 - 1000 + 500
      expect(cart.totalMinor, 4500);
    });

    test('le pourboire ne reduit jamais l addition', () {
      final withoutTip = const Cart().addProduct(plat);
      final withTip = withoutTip.copyWith(tipMinor: 700);

      expect(withTip.totalMinor, greaterThan(withoutTip.totalMinor));
    });

    test('le net ne devient jamais negatif', () {
      final cart = const Cart().addProduct(boisson).copyWith(discountMinor: 9000);

      expect(cart.netMinor, 0);
    });

    test('une remise superieure au sous-total invalide le panier', () {
      final cart = const Cart().addProduct(boisson).copyWith(discountMinor: 9000);

      expect(cart.discountValid, isFalse);
      expect(cart.isValid, isFalse);
    });

    test('une remise egale au sous-total reste valide', () {
      // `dine_in` exige une table : on teste la remise sur une commande a
      // emporter pour isoler la regle de remise.
      final cart = const Cart()
          .addProduct(boisson)
          .copyWith(discountMinor: 1000, orderType: OrderType.takeaway);

      expect(cart.discountValid, isTrue);
      expect(cart.isValid, isTrue);
    });

    test('survit a un aller-retour JSON avec remise et pourboire', () {
      final cart = const Cart()
          .addProduct(plat)
          .copyWith(discountMinor: 250, tipMinor: 400);

      final restored = Cart.fromJson(cart.toJson());

      expect(restored.discountMinor, 250);
      expect(restored.tipMinor, 400);
      expect(restored.totalMinor, cart.totalMinor);
    });
  });

  group('SplitPayment', () {
    test('couvre exactement le total', () {
      final split = const SplitPayment()
          .add(const PaymentSplit(method: PosPaymentMethod.cash, amountMinor: 2000))
          .add(const PaymentSplit(method: PosPaymentMethod.card, amountMinor: 3000));

      expect(split.paidMinor, 5000);
      expect(split.covers(5000), isTrue);
      expect(split.remainingMinor(5000), 0);
    });

    test('ne couvre pas un total superieur', () {
      final split = const SplitPayment()
          .add(const PaymentSplit(method: PosPaymentMethod.cash, amountMinor: 2000));

      expect(split.covers(5000), isFalse);
      expect(split.remainingMinor(5000), 3000);
    });

    test('signale un excedent', () {
      final split = const SplitPayment()
          .add(const PaymentSplit(method: PosPaymentMethod.cash, amountMinor: 6000));

      expect(split.exceeds(5000), isTrue);
      expect(split.covers(5000), isFalse);
      expect(split.remainingMinor(5000), 0);
    });

    test('un partage vide ne couvre rien', () {
      expect(const SplitPayment().covers(0), isFalse);
      expect(const SplitPayment().covers(5000), isFalse);
    });

    test('retire une part', () {
      final split = const SplitPayment()
          .add(const PaymentSplit(method: PosPaymentMethod.cash, amountMinor: 2000))
          .add(const PaymentSplit(method: PosPaymentMethod.card, amountMinor: 3000))
          .removeAt(0);

      expect(split.splits.length, 1);
      expect(split.paidMinor, 3000);
    });

    test('retirer un index hors bornes ne change rien', () {
      final split = const SplitPayment()
          .add(const PaymentSplit(method: PosPaymentMethod.cash, amountMinor: 2000));

      expect(split.removeAt(5).paidMinor, 2000);
      expect(split.removeAt(-1).paidMinor, 2000);
    });

    test('repartit en parts egales sans perdre de centime', () {
      final split = SplitPayment.equalParts(totalMinor: 100, parts: 3);

      expect(split.splits.length, 3);
      expect(split.paidMinor, 100);
      expect(split.covers(100), isTrue);
      // Le reste va a la premiere part : 34, 33, 33.
      expect(split.splits.first.amountMinor, 34);
      expect(split.splits[1].amountMinor, 33);
    });

    test('repartit un total divisible exactement', () {
      final split = SplitPayment.equalParts(totalMinor: 9000, parts: 3);

      expect(split.splits.every((part) => part.amountMinor == 3000), isTrue);
      expect(split.covers(9000), isTrue);
    });

    test('un nombre de parts invalide rend un partage vide', () {
      expect(SplitPayment.equalParts(totalMinor: 1000, parts: 0).splits, isEmpty);
      expect(SplitPayment.equalParts(totalMinor: 0, parts: 3).splits, isEmpty);
    });

    test('survit a un aller-retour JSON', () {
      final split = const SplitPayment()
          .add(const PaymentSplit(method: PosPaymentMethod.mobileMoney, amountMinor: 1500));

      final restored = SplitPayment.fromJson(split.toJson());

      expect(restored.splits.single.method, PosPaymentMethod.mobileMoney);
      expect(restored.paidMinor, 1500);
    });
  });

  group('PosPaymentMethod', () {
    test('expose les valeurs de contrat du backend', () {
      expect(PosPaymentMethod.cash.wireValue, 'cash');
      expect(PosPaymentMethod.card.wireValue, 'card');
      expect(PosPaymentMethod.mobileMoney.wireValue, 'mobile_money');
    });
  });

  group('cible tactile', () {
    test('le minimum tactile du POS Restaurant respecte 48 dp', () {
      expect(restaurantMinTouchTarget, greaterThanOrEqualTo(48));
    });
  });
}
