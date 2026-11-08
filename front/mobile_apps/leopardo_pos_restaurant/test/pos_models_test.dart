import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_pos_restaurant/core/models/pos_models.dart';
import 'package:leopardo_pos_restaurant/core/offline/pos_offline_queue.dart';

/// Tests du cœur métier du POS Restaurant tablette (#9214).
///
/// Ces tests couvrent les règles qui, si elles cassent, font perdre de
/// l'argent ou bloquent un service : totaux du panier, obligation de table
/// pour une commande sur place, et stabilité de la clé d'idempotence.
void main() {
  const bissap = PosProduct(id: 1, name: 'Bissap', priceMinor: 1500);
  const the = PosProduct(id: 2, name: 'Thé', priceMinor: 700);

  group('Cart', () {
    test('ajoute un produit et incrémente la ligne existante', () {
      var cart = const Cart().addProduct(bissap).addProduct(bissap).addProduct(the);

      expect(cart.lines.length, 2);
      expect(cart.lines.first.quantity, 2);
      expect(cart.itemCount, 3);
    });

    test('calcule le sous-total en unités mineures', () {
      final cart = const Cart().addProduct(bissap).addProduct(bissap).addProduct(the);

      // 2 * 1500 + 1 * 700 = 3700
      expect(cart.subtotalMinor, 3700);
    });

    test('retire une ligne quand la quantité tombe à zéro', () {
      final cart = const Cart().addProduct(bissap).removeProduct(bissap.id);

      expect(cart.isEmpty, isTrue);
      expect(cart.subtotalMinor, 0);
    });

    test('décrémente sans supprimer tant que la quantité reste positive', () {
      final cart = const Cart()
          .addProduct(bissap)
          .addProduct(bissap)
          .removeProduct(bissap.id);

      expect(cart.lines.single.quantity, 1);
    });

    test('un panier vide n\'est pas valide', () {
      expect(const Cart().isValid, isFalse);
    });

    test('dine_in sans table n\'est pas valide', () {
      final cart = const Cart().addProduct(bissap);

      expect(cart.orderType, OrderType.dineIn);
      expect(cart.isValid, isFalse);
    });

    test('dine_in avec table est valide', () {
      final cart = const Cart().addProduct(bissap).copyWith(tableId: 7);

      expect(cart.isValid, isTrue);
    });

    test('takeaway est valide sans table', () {
      final cart = const Cart().addProduct(bissap).copyWith(orderType: OrderType.takeaway);

      expect(cart.isValid, isTrue);
    });

    test('survit à un aller-retour JSON', () {
      final cart = const Cart()
          .addProduct(bissap)
          .addProduct(the)
          .copyWith(orderType: OrderType.delivery, covers: 3, note: 'Sans sucre');

      final restored = Cart.fromJson(cart.toJson());

      expect(restored.lines.length, 2);
      expect(restored.subtotalMinor, cart.subtotalMinor);
      expect(restored.orderType, OrderType.delivery);
      expect(restored.covers, 3);
      expect(restored.note, 'Sans sucre');
    });
  });

  group('PosOfflineQueue.paymentKey', () {
    test('est stable pour les mêmes arguments', () {
      final first = PosOfflineQueue.paymentKey(orderId: 42, amountMinor: 3700, method: 'cash');
      final second = PosOfflineQueue.paymentKey(orderId: 42, amountMinor: 3700, method: 'cash');

      expect(first, second);
    });

    test('diffère quand le montant ou le moyen change', () {
      final base = PosOfflineQueue.paymentKey(orderId: 42, amountMinor: 3700, method: 'cash');

      expect(
        PosOfflineQueue.paymentKey(orderId: 42, amountMinor: 3800, method: 'cash'),
        isNot(base),
      );
      expect(
        PosOfflineQueue.paymentKey(orderId: 42, amountMinor: 3700, method: 'card'),
        isNot(base),
      );
    });
  });

  group('OrderType', () {
    test('expose les valeurs de contrat du backend', () {
      expect(OrderType.dineIn.wireValue, 'dine_in');
      expect(OrderType.takeaway.wireValue, 'takeaway');
      expect(OrderType.delivery.wireValue, 'delivery');
    });
  });
}
