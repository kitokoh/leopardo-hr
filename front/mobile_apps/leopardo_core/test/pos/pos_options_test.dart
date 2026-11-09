import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_options.dart';

/// Tests des variantes et options de plat (#9265).
void main() {
  const cuisson = PosOptionGroup(
    id: 1,
    name: 'Cuisson',
    required: true,
    options: [
      PosOption(id: 11, name: 'Saignant'),
      PosOption(id: 12, name: 'A point'),
      PosOption(id: 13, name: 'Bien cuit'),
    ],
  );

  const accompagnement = PosOptionGroup(
    id: 2,
    name: 'Accompagnement',
    options: [
      PosOption(id: 21, name: 'Frites', priceMinor: 500),
      PosOption(id: 22, name: 'Riz', priceMinor: 300),
    ],
  );

  const supplements = PosOptionGroup(
    id: 3,
    name: 'Supplements',
    maxSelections: 2,
    options: [
      PosOption(id: 31, name: 'Fromage', priceMinor: 700),
      PosOption(id: 32, name: 'Bacon', priceMinor: 900),
      PosOption(id: 33, name: 'Oeuf', priceMinor: 400),
    ],
  );

  const options =
      PosProductOptions(groups: [cuisson, accompagnement, supplements]);

  group('PosOptionsPricing.additionalMinor', () {
    test('additionne les prix des options selectionnees', () {
      final price = PosOptionsPricing.additionalMinor(
        productOptions: options,
        selections: const [
          PosOptionSelection(groupId: 2, optionId: 21), // +500
          PosOptionSelection(groupId: 3, optionId: 31, quantity: 2), // +1400
        ],
      );

      expect(price, 1900);
    });

    test('ignore les options inconnues', () {
      final price = PosOptionsPricing.additionalMinor(
        productOptions: options,
        selections: const [
          PosOptionSelection(groupId: 2, optionId: 99), // inconnue
          PosOptionSelection(groupId: 99, optionId: 21), // groupe inconnu
          PosOptionSelection(groupId: 1, optionId: 11), // +0
        ],
      );

      expect(price, 0);
    });
  });

  group('PosOptionsPricing.validate', () {
    test('valide une selection complete', () {
      final valid = PosOptionsPricing.validate(
        productOptions: options,
        selections: const [
          PosOptionSelection(groupId: 1, optionId: 12),
          PosOptionSelection(groupId: 3, optionId: 33),
        ],
      );

      expect(valid, isTrue);
    });

    test('refuse un groupe obligatoire non couvert', () {
      final valid = PosOptionsPricing.validate(
        productOptions: options,
        selections: const [
          PosOptionSelection(groupId: 2, optionId: 21),
        ],
      );

      expect(valid, isFalse); // cuisson manque
    });

    test('refuse une selection vers un groupe inconnu', () {
      final valid = PosOptionsPricing.validate(
        productOptions: options,
        selections: const [
          PosOptionSelection(groupId: 1, optionId: 12),
          PosOptionSelection(groupId: 99, optionId: 11),
        ],
      );

      expect(valid, isFalse);
    });

    test('refuse de depasser maxSelections', () {
      final valid = PosOptionsPricing.validate(
        productOptions: options,
        selections: const [
          PosOptionSelection(groupId: 1, optionId: 12),
          PosOptionSelection(groupId: 3, optionId: 31),
          PosOptionSelection(groupId: 3, optionId: 32),
          PosOptionSelection(groupId: 3, optionId: 33), // 3 > maxSelections 2
        ],
      );

      expect(valid, isFalse);
    });
  });

  group('PosOptionGroup.isSatisfiedBy', () {
    test('un groupe facultatif est satisfait sans selection', () {
      expect(accompagnement.isSatisfiedBy(const []), isTrue);
    });

    test('un groupe obligatoire exige une selection', () {
      expect(cuisson.isSatisfiedBy(const []), isFalse);
      expect(
        cuisson.isSatisfiedBy(
            const [PosOptionSelection(groupId: 1, optionId: 11)]),
        isTrue,
      );
    });

    test('une option indisponible est refusee', () {
      const limited = PosOptionGroup(
        id: 4,
        name: 'Sauce',
        options: [PosOption(id: 41, name: 'Piment', available: false)],
      );

      expect(
        limited.isSatisfiedBy(
            const [PosOptionSelection(groupId: 4, optionId: 41)]),
        isFalse,
      );
    });
  });

  group('PosProductOptions', () {
    test('tri par groupe par id', () {
      expect(options.groupById(2)?.name, 'Accompagnement');
      expect(options.groupById(99), isNull);
    });

    test('survit a un aller-retour JSON', () {
      final restored = PosProductOptions.fromJson(options.toJson());

      expect(restored.groups.length, 3);
      expect(restored.groupById(3)?.maxSelections, 2);
      expect(restored.groupById(1)?.required, isTrue);
      expect(restored.groupById(2)?.optionById(21)?.priceMinor, 500);
    });

    test('JSON null ou vide donne un produit sans options', () {
      expect(PosProductOptions.fromJson(null).isEmpty, isTrue);
      expect(const PosProductOptions().isEmpty, isTrue);
    });
  });
}
