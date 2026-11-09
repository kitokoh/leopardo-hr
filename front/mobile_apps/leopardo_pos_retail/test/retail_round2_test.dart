import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_pos_retail/core/models/retail_models.dart';

/// Tests des ajouts de ce lot cote Retail : quantite directe et champs de
/// recherche sur le produit.
void main() {
  const bissap = RetailProduct(
    id: 1,
    name: 'Jus de bissap 50cl',
    priceMinor: 1500,
    categoryId: 3,
    sku: 'SKU-BISSAP-50',
    barcode: '6001234567890',
  );
  const the = RetailProduct(id: 2, name: 'The a la menthe', priceMinor: 700, categoryId: 3);

  group('RetailCart.setQuantity', () {
    test('fixe la quantite d une ligne existante', () {
      final cart = const RetailCart().addProduct(bissap).setQuantity(1, 12);

      expect(cart.lines.single.quantity, 12);
      expect(cart.subtotalMinor, 18000);
    });

    test('retire la ligne quand la quantite tombe a zero', () {
      final cart = const RetailCart().addProduct(bissap).setQuantity(1, 0);

      expect(cart.isEmpty, isTrue);
    });

    test('ne change rien pour un produit absent', () {
      final cart = const RetailCart().addProduct(bissap).setQuantity(999, 5);

      expect(cart.lines.single.quantity, 1);
    });

    test('ne touche pas les autres lignes', () {
      final cart = const RetailCart()
          .addProduct(bissap)
          .addProduct(the)
          .setQuantity(1, 4);

      expect(cart.lines.first.quantity, 4);
      expect(cart.lines.last.quantity, 1);
    });
  });

  group('RetailProduct', () {
    test('expose SKU et code-barres', () {
      expect(bissap.sku, 'SKU-BISSAP-50');
      expect(bissap.barcode, '6001234567890');
    });

    test('SKU et code-barres sont optionnels', () {
      expect(the.sku, isNull);
      expect(the.barcode, isNull);
    });

    test('survit a un aller-retour JSON avec SKU et code-barres', () {
      final restored = RetailProduct.fromJson(bissap.toJson());

      expect(restored.sku, 'SKU-BISSAP-50');
      expect(restored.barcode, '6001234567890');
      expect(restored.name, 'Jus de bissap 50cl');
    });
  });
}
