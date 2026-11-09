import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_pos_restaurant/core/models/pos_models.dart';

/// Tests des ajouts de ce lot cote Restaurant : quantite directe, code-barres
/// et champs de recherche sur le produit.
void main() {
  const bissap = PosProduct(
    id: 1,
    name: 'Bissap',
    priceMinor: 1500,
    categoryId: 3,
    sku: 'BOIS-01',
    barcode: '6001234567890',
  );
  const the = PosProduct(id: 2, name: 'The a la menthe', priceMinor: 700, categoryId: 3);

  group('Cart.setQuantity', () {
    test('fixe la quantite d une ligne existante', () {
      final cart = const Cart().addProduct(bissap).setQuantity(1, 12);

      expect(cart.lines.single.quantity, 12);
      expect(cart.subtotalMinor, 18000);
    });

    test('retire la ligne quand la quantite tombe a zero', () {
      final cart = const Cart().addProduct(bissap).setQuantity(1, 0);

      expect(cart.isEmpty, isTrue);
    });

    test('retire la ligne pour une quantite negative', () {
      final cart = const Cart().addProduct(bissap).setQuantity(1, -3);

      expect(cart.isEmpty, isTrue);
    });

    test('ne change rien pour un produit absent', () {
      final cart = const Cart().addProduct(bissap).setQuantity(999, 5);

      expect(cart.lines.single.quantity, 1);
    });

    test('ne touche pas les autres lignes', () {
      final cart = const Cart()
          .addProduct(bissap)
          .addProduct(the)
          .setQuantity(1, 4);

      expect(cart.lines.first.quantity, 4);
      expect(cart.lines.last.quantity, 1);
    });
  });

  group('PosProduct', () {
    test('expose SKU et code-barres', () {
      expect(bissap.sku, 'BOIS-01');
      expect(bissap.barcode, '6001234567890');
    });

    test('SKU et code-barres sont optionnels', () {
      expect(the.sku, isNull);
      expect(the.barcode, isNull);
    });

    test('survit a un aller-retour JSON avec SKU et code-barres', () {
      final restored = PosProduct.fromJson(bissap.toJson());

      expect(restored.sku, 'BOIS-01');
      expect(restored.barcode, '6001234567890');
      expect(restored.name, 'Bissap');
    });

    test('un produit sans SKU ni code-barres survit au JSON', () {
      final restored = PosProduct.fromJson(the.toJson());

      expect(restored.sku, isNull);
      expect(restored.barcode, isNull);
    });
  });
}
