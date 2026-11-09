import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_pos_retail/features/pos/numeric_keypad.dart';
import 'package:leopardo_pos_retail/features/pos/retail_pos_screen.dart';

/// Tests du **pave numerique tactile** et du formatage monetaire (#9215).
///
/// Ces regles sont celles qui font une caisse utilisable au doigt : une
/// saisie qui ne produit pas de zero de tete, un montant qui ne depasse pas
/// la longueur d'un ticket, et un affichage qui montre le signe d'une remise.
void main() {
  group('applyNumericKey', () {
    test('ajoute un chiffre a la suite', () {
      expect(applyNumericKey('1', '2'), '12');
      expect(applyNumericKey('12', '3'), '123');
    });

    test('ignore un zero de tete', () {
      // Sinon le vendeur ne peut plus effacer sa saisie : '0' puis '5' = '05'.
      expect(applyNumericKey('', '0'), '');
    });

    test('accepte un zero apres un chiffre', () {
      expect(applyNumericKey('1', '0'), '10');
    });

    test('borne la longueur de la saisie', () {
      expect(applyNumericKey('123456789', '0'), '123456789');
      expect(applyNumericKey('123456789', '0', maxLength: 9), '123456789');
    });

    test('respecte une longueur maximale personnalisee', () {
      expect(applyNumericKey('12', '3', maxLength: 2), '12');
    });
  });

  group('applyBackspace', () {
    test('retire le dernier chiffre', () {
      expect(applyBackspace('123'), '12');
    });

    test('laisse une saisie vide inchangee', () {
      expect(applyBackspace(''), '');
    });

    test('vide une saisie d un seul chiffre', () {
      expect(applyBackspace('7'), '');
    });
  });

  group('parseMinorUnits', () {
    test('convertit une saisie en entier', () {
      expect(parseMinorUnits('1500'), 1500);
    });

    test('rend zero pour une saisie vide', () {
      expect(parseMinorUnits(''), 0);
      expect(parseMinorUnits('   '), 0);
    });

    test('rend zero pour une saisie non numerique', () {
      expect(parseMinorUnits('abc'), 0);
    });

    test('ne rend jamais un montant negatif', () {
      expect(parseMinorUnits('-500'), 0);
    });
  });

  group('formatMinor', () {
    test('formate un montant en unites et centimes', () {
      expect(formatMinor(1500), '15,00');
      expect(formatMinor(1550), '15,50');
      expect(formatMinor(5), '0,05');
    });

    test('formate zero', () {
      expect(formatMinor(0), '0,00');
    });

    test('affiche le signe d une remise', () {
      expect(formatMinor(-150), '-1,50');
    });

    test('gere les grands montants', () {
      expect(formatMinor(123456789), '1234567,89');
    });
  });

  group('cible tactile', () {
    test('le minimum tactile du POS respecte 48 dp', () {
      expect(posMinTouchTarget, greaterThanOrEqualTo(48));
    });
  });
}
