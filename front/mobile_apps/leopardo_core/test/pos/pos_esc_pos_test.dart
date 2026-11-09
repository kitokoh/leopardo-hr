import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_esc_pos.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

/// Tests du generateur ESC/POS (#9264).
void main() {
  PosReceipt buildReceipt() => const PosReceipt(
        lines: [
          PosReceiptLine(
              name: 'Jus de bissap', quantity: 2, unitPriceMinor: 1500),
          PosReceiptLine(
            name: 'Poulet braise',
            quantity: 1,
            unitPriceMinor: 4500,
            options: ['Cuisson : Saignant', '+ Frites'],
          ),
        ],
        discountMinor: 500,
        tipMinor: 1000,
        tableLabel: 'Table 4',
      );

  group('PosEscPosGenerator', () {
    test('produit des octets non vides commencant par l init', () {
      final bytes = const PosEscPosGenerator().render(
        receipt: buildReceipt(),
        shopName: 'Chez Mamie',
      );

      expect(bytes, isNotEmpty);
      expect(bytes.take(2), [0x1B, 0x40]); // ESC @
    });

    test('contient le nom du commerce en majuscules', () {
      final bytes = const PosEscPosGenerator().render(
        receipt: buildReceipt(),
        shopName: 'Chez Mamie',
      );

      expect(_hasAscii(bytes, 'CHEZ MAMIE'), isTrue);
    });

    test('contient le total attendu (1500*2 + 4500 - 500 + 1000)', () {
      final bytes = const PosEscPosGenerator().render(
        receipt: buildReceipt(),
        shopName: 'Chez Mamie',
      );

      // 3000 + 4500 = 7500 ; -500 remise = 7000 ; +1000 pourboire = 8000.
      expect(_hasAscii(bytes, '80,00'), isTrue);
      expect(_hasAscii(bytes, '15,00'), isTrue);
    });

    test('contient les options de la ligne', () {
      final bytes = const PosEscPosGenerator().render(
        receipt: buildReceipt(),
        shopName: 'Chez Mamie',
      );

      expect(_hasAscii(bytes, '+ Cuisson : Saignant'), isTrue);
      expect(_hasAscii(bytes, '+ Frites'), isTrue);
    });

    test('encode les accents vers la page de codes de l imprimante', () {
      final bytes = const PosEscPosGenerator().render(
        receipt: buildReceipt(),
        shopName: 'Crêperie Éléonore',
      );

      // Les accents ne sont pas translitteres en ASCII : ils partent en page
      // de codes. Le nom etant imprime en capitales, ce sont les formes
      // majuscules qui apparaissent (É = 0x90, Ê = 0xD2).
      expect(bytes.contains(0x90), isTrue); // É
      expect(bytes.contains(0xD2), isTrue); // Ê
      expect(_hasAscii(bytes, 'CR'), isTrue);
    });

    test('decoupe a la fin quand demandee', () {
      final bytes = const PosEscPosGenerator(cut: true).render(
        receipt: buildReceipt(),
        shopName: 'Chez Mamie',
      );

      expect(bytes.sublist(bytes.length - 3), [0x1D, 0x56, 0x01]);
    });

    test('pas de decoupe quand demandee', () {
      final bytes = const PosEscPosGenerator(cut: false).render(
        receipt: buildReceipt(),
        shopName: 'Chez Mamie',
      );

      var hasCut = false;

      for (var i = 0; i + 2 < bytes.length; i++) {
        if (bytes[i] == 0x1D && bytes[i + 1] == 0x56) {
          hasCut = true;
        }
      }

      expect(hasCut, isFalse);
    });

    test('separes les lignes par des retours a la ligne', () {
      final bytes = const PosEscPosGenerator().render(
        receipt: buildReceipt(),
        shopName: 'Chez Mamie',
      );

      expect(bytes.where((byte) => byte == 0x0A).length, greaterThan(5));
    });

    test('une remise apparait en negatif sur le ticket', () {
      final bytes = const PosEscPosGenerator().render(
        receipt: buildReceipt(),
        shopName: 'Chez Mamie',
      );

      expect(_hasAscii(bytes, '-5,00'), isTrue);
    });
  });

  group('PosEscPosEncoding', () {
    test('encode les caracteres ASCII tels quels', () {
      expect(PosEscPosEncoding.encodeChar(0x41), 0x41);
      expect(PosEscPosEncoding.encodeChar(0x7A), 0x7A);
    });

    test('encode les accents vers la page de codes', () {
      expect(PosEscPosEncoding.encodeChar(0xE9), 0x82); // é
      expect(PosEscPosEncoding.encodeChar(0xE8), 0x8A); // è
      expect(PosEscPosEncoding.encodeChar(0xE7), 0x87); // ç
    });

    test('remplace un caractere inconnu par un point d interrogation', () {
      expect(PosEscPosEncoding.encodeChar(0x1F600), 0x3F); // emoji
      expect(PosEscPosEncoding.encodeChar(0x10FFFF), 0x3F);
    });

    test('encode une ligne sans depasser 255 octets', () {
      final bytes = PosEscPosEncoding.encodeLine('a' * 1000);

      expect(bytes.length, lessThanOrEqualTo(255));
    });
  });
}

/// Cherche une chaine ASCII dans les octets (les caracteres non-ASCII sont
/// encode en page de codes, d ou la comparaison octet par octet).
bool _hasAscii(List<int> bytes, String needle) {
  final target = needle.codeUnits;
  final buffer = <int>[];

  for (final byte in bytes) {
    buffer.add(byte);

    if (buffer.length > target.length) {
      buffer.removeAt(0);
    }

    if (buffer.length == target.length) {
      var equal = true;

      for (var i = 0; i < target.length; i++) {
        if (buffer[i] != target[i]) {
          equal = false;
          break;
        }
      }

      if (equal) {
        return true;
      }
    }
  }

  return false;
}
