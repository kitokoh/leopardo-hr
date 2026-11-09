import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_options.dart';
import 'package:leopardo_pos_restaurant/core/models/pos_models.dart';

/// Tests des variantes et options de plat cote Restaurant (#9265).
void main() {
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

  const poulet = PosProduct(
    id: 1,
    name: 'Poulet braise',
    priceMinor: 4500,
    options: options,
  );

  group('CartLine avec options', () {
    test('le prix unitaire inclut les options', () {
      const line = CartLine(
        product: poulet,
        quantity: 1,
        selections: [
          PosOptionSelection(groupId: 1, optionId: 11),
          PosOptionSelection(groupId: 2, optionId: 21),
        ],
      );

      expect(line.unitPriceMinor, 5200); // 4500 + 700
      expect(line.lineTotalMinor, 5200);
    });

    test('la quantite multiplie le prix options comprises', () {
      const line = CartLine(
        product: poulet,
        quantity: 3,
        selections: [
          PosOptionSelection(groupId: 1, optionId: 12),
          PosOptionSelection(groupId: 2, optionId: 22),
        ],
      );

      expect(line.unitPriceMinor, 5400); // 4500 + 900
      expect(line.lineTotalMinor, 16200);
    });

    test('une option a quantite multiplie son prix', () {
      const line = CartLine(
        product: poulet,
        quantity: 1,
        selections: [
          PosOptionSelection(groupId: 1, optionId: 11),
          PosOptionSelection(groupId: 2, optionId: 21, quantity: 2),
        ],
      );

      expect(line.unitPriceMinor, 5900); // 4500 + 700*2
    });

    test('sans option le prix reste celui du plat', () {
      const line = CartLine(product: poulet, quantity: 2);

      expect(line.unitPriceMinor, 4500);
      expect(line.lineTotalMinor, 9000);
      expect(line.optionLabels, isEmpty);
    });

    test('les libelles d options sont lisibles', () {
      const line = CartLine(
        product: poulet,
        quantity: 1,
        selections: [
          PosOptionSelection(groupId: 1, optionId: 11),
          PosOptionSelection(groupId: 2, optionId: 21, quantity: 2),
        ],
      );

      expect(line.optionLabels, ['Cuisson : Saignant', 'Supplements : Fromage x2']);
    });

    test('withSelections conserve la quantite', () {
      const line = CartLine(product: poulet, quantity: 4);
      final updated = line.withSelections(
        const [PosOptionSelection(groupId: 1, optionId: 12)],
      );

      expect(updated.quantity, 4);
      expect(updated.selections.length, 1);
    });

    test('survit a un aller-retour JSON avec options', () {
      const line = CartLine(
        product: poulet,
        quantity: 2,
        selections: [
          PosOptionSelection(groupId: 1, optionId: 11),
          PosOptionSelection(groupId: 2, optionId: 22, quantity: 3),
        ],
      );

      final restored = CartLine.fromJson(line.toJson());

      expect(restored.quantity, 2);
      expect(restored.selections.length, 2);
      expect(restored.unitPriceMinor, line.unitPriceMinor);
      expect(restored.product.options.groups.length, 2);
    });
  });

  group('PosProduct avec options', () {
    test('un plat sans options reste valide', () {
      const simple = PosProduct(id: 2, name: 'Eau', priceMinor: 200);

      expect(simple.options.isEmpty, isTrue);
      expect(simple.toJson().containsKey('options'), isFalse);
    });

    test('les options survivent au JSON du produit', () {
      final restored = PosProduct.fromJson(poulet.toJson());

      expect(restored.options.groups.length, 2);
      expect(restored.options.groupById(1)?.required, isTrue);
      expect(restored.options.groupById(2)?.optionById(21)?.priceMinor, 700);
    });
  });
}
