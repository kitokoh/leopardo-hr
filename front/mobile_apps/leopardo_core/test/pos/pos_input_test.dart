import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_input.dart';

/// Tests de la **douchette materielle** et des raccourcis clavier.
///
/// Ces regles evitent le bug classique d'une caisse : le code-barres atterrit
/// dans le champ de recherche au lieu d'ajouter l'article au panier.
void main() {
  group('PosHardwareScannerDetector', () {
    test('reconnait un code tape tres vite puis Entree', () {
      final detector = PosHardwareScannerDetector();
      final start = DateTime(2026, 11, 9, 10);
      String? scanned;

      for (var index = 0; index < 13; index++) {
        scanned = detector.feed(
          PosKeyStroke(
            character: '6001234567890'[index],
            at: start.add(Duration(milliseconds: index * 10)),
          ),
        );
      }

      expect(scanned, isNull);

      scanned = detector.feed(
        PosKeyStroke(
            character: '\n', at: start.add(const Duration(milliseconds: 140))),
      );

      expect(scanned, '6001234567890');
    });

    test('ignore une saisie humaine trop lente', () {
      final detector = PosHardwareScannerDetector();
      final start = DateTime(2026, 11, 9, 10);
      String? scanned;

      for (var index = 0; index < 13; index++) {
        scanned = detector.feed(
          PosKeyStroke(
            character: '6001234567890'[index],
            at: start.add(Duration(milliseconds: index * 300)),
          ),
        );
      }

      scanned = detector.feed(
        PosKeyStroke(
            character: '\n', at: start.add(const Duration(seconds: 5))),
      );

      // Les frappes lentes vident le tampon : seule la derniere reste, trop
      // courte pour etre un code-barres.
      expect(scanned, isNull);
    });

    test('ignore un code trop court', () {
      final detector = PosHardwareScannerDetector();
      final start = DateTime(2026, 11, 9, 10);

      detector.feed(PosKeyStroke(character: '1', at: start));
      final scanned = detector.feed(
        PosKeyStroke(
            character: '\n', at: start.add(const Duration(milliseconds: 10))),
      );

      expect(scanned, isNull);
    });

    test('vide le tampon apres un scan', () {
      final detector = PosHardwareScannerDetector();
      final start = DateTime(2026, 11, 9, 10);

      for (var index = 0; index < 8; index++) {
        detector.feed(
          PosKeyStroke(
            character: '12345678'[index],
            at: start.add(Duration(milliseconds: index * 5)),
          ),
        );
      }

      detector.feed(
        PosKeyStroke(
            character: '\n', at: start.add(const Duration(milliseconds: 50))),
      );

      expect(detector.buffer, isEmpty);
    });

    test('reset vide le tampon', () {
      final detector = PosHardwareScannerDetector();

      detector.feed(PosKeyStroke(character: '1', at: DateTime(2026, 11, 9)));
      detector.reset();

      expect(detector.buffer, isEmpty);
    });
  });

  group('posShortcutFor', () {
    test('mappe les touches de fonction', () {
      expect(posShortcutFor('F2'), PosShortcut.scan);
      expect(posShortcutFor('F4'), PosShortcut.pay);
      expect(posShortcutFor('F6'), PosShortcut.park);
      expect(posShortcutFor('F7'), PosShortcut.recall);
      expect(posShortcutFor('F8'), PosShortcut.receipt);
      expect(posShortcutFor('F9'), PosShortcut.discount);
      expect(posShortcutFor('F10'), PosShortcut.tip);
    });

    test('mappe Echap et Suppr', () {
      expect(posShortcutFor('Escape'), PosShortcut.dismiss);
      expect(posShortcutFor('Delete'), PosShortcut.removeLastLine);
    });

    test('mappe les signes de quantite', () {
      expect(posShortcutFor('+'), PosShortcut.incrementLastLine);
      expect(posShortcutFor('-'), PosShortcut.decrementLastLine);
    });

    test('renvoie null pour une touche non mappee', () {
      expect(posShortcutFor('a'), isNull);
    });
  });

  group('posShortcutLabel', () {
    test('chaque raccourci a un libelle non vide', () {
      for (final shortcut in PosShortcut.values) {
        expect(posShortcutLabel(shortcut), isNotEmpty);
      }
    });
  });
}
