/// Generation de ticket d'imprimante thermique **ESC/POS** (#9264).
///
/// Une imprimante thermique de caisse parle ESC/POS : une suite d'octets de
/// controle (`ESC a` = alignement, `GS !` = taille, `GS V` = decoupe...) entre
/// lesquels on place le texte. Ce module construit ces octets a partir d'un
/// ticket deja calcule par la caisse — le meme [PosReceipt] que l'apercu a
/// l'ecran, pour que l'imprime et l'affiche ne divergent pas.
///
/// **Pur** : aucune dependance Flutter ni Bluetooth. La generation est
/// testable en `flutter test` sans peripherique ; l'impression (BLE) est un
/// autre module qui consomme ces octets.
library;

import 'pos_ticket.dart';

/// Largeur de ligne d'un ticket 58 mm (32 chars) ou 80 mm (48 chars).
///
/// Les imprimantes de caisse coupent les lignes trop longues ; le ticket est
/// plus lisible si c'est **nous** qui coupons joliment (avec retour a la
/// ligne et indentation) plutot que l'imprimante (milieu de mot, sans
/// alinea).
enum PosReceiptWidth {
  width58(32),
  width80(48);

  const PosReceiptWidth(this.columns);

  final int columns;
}

/// Une ligne du ticket, alignee.
class PosReceiptRow {
  const PosReceiptRow(this.text, {this.align = PosReceiptAlign.left});

  final String text;
  final PosReceiptAlign align;
}

/// Alignement d'une ligne de ticket.
enum PosReceiptAlign {
  left(0),
  center(1),
  right(2);

  const PosReceiptAlign(this.escValue);

  /// Valeur du parametre `ESC a n` d'ESC/POS.
  final int escValue;
}

/// Caracteres haut de gamme de l'encodage [Latin-1](https://fr.wikipedia.org/wiki/ISO/CEI_8859-1).
///
/// Les imprimantes thermiques courantes embarquent cette page de codes ; les
/// caracteres hors repertoire (emojis, accents exotiques) sont remplaces par
/// une approximation ASCII pour ne jamais produire d'octet illisible.
class PosEscPosEncoding {
  const PosEscPosEncoding._();

  /// Encode un caractere pour une imprimante ESC/POS (page de codes 437/Latin-1).
  ///
  /// Retourne un seul octet, ou un point d'interrogation pour les caracteres
  /// inconnus (mieux vaut un `?` qu'un octet parasite qui derange la ligne).
  static int encodeChar(int rune) {
    if (rune == 0x00E9) {
      return 0x82; // é
    }
    if (rune == 0x00E8) {
      return 0x8A; // è
    }
    if (rune == 0x00EA) {
      return 0x88; // ê
    }
    if (rune == 0x00E0) {
      return 0x85; // à
    }
    if (rune == 0x00FB) {
      return 0x93; // û
    }
    if (rune == 0x00F9) {
      return 0x97; // ù
    }
    if (rune == 0x00E7) {
      return 0x87; // ç
    }
    if (rune == 0x00EE) {
      return 0x8C; // î
    }
    if (rune == 0x00EF) {
      return 0x8D; // ï
    }
    if (rune == 0x00F4) {
      return 0x94; // ô
    }
    if (rune == 0x00B0) {
      return 0xF8; // °
    }
    if (rune == 0x00B7) {
      return 0xFA; // ·
    }
    if (rune == 0x20AC) {
      return 0x80; // € (page 437)
    }
    if (rune == 0x00AB) {
      return 0xAE; // «
    }
    if (rune == 0x00BB) {
      return 0xAF; // »
    }
    if (rune == 0x2019) {
      return 0x27; // ’ -> '
    }

    // Majuscules accentuees : le nom du commerce est imprime en capitales,
    // donc ce sont ces formes-la qui apparaissent reellement sur le ticket.
    if (rune == 0x00C9) {
      return 0x90; // É
    }
    if (rune == 0x00C8) {
      return 0xD4; // È
    }
    if (rune == 0x00CA) {
      return 0xD2; // Ê
    }
    if (rune == 0x00C0) {
      return 0xB7; // À
    }
    if (rune == 0x00C7) {
      return 0x80; // Ç
    }
    if (rune == 0x00CE) {
      return 0xD7; // Î
    }
    if (rune == 0x00CF) {
      return 0xD8; // Ï
    }
    if (rune == 0x00D4) {
      return 0xE2; // Ô
    }
    if (rune == 0x00DB) {
      return 0xEA; // Û
    }
    if (rune == 0x00D9) {
      return 0xEB; // Ù
    }

    // ASCII de base, inchange.
    if (rune >= 0x20 && rune <= 0x7E) {
      return rune;
    }

    // Hors repertoire : approximation.
    return 0x3F; // '?'
  }

  /// Encode une chaine en octets imprimables, decoupe si besoin.
  static List<int> encodeLine(String text) {
    const replacement = 0x3F;

    final bytes = <int>[];

    for (final rune in text.runes) {
      // Les caractères de contrôle du texte (tabulations, retours) sont
      // encodes en espace pour ne pas deranger la mise en page.
      if (rune < 0x20) {
        bytes.add(0x20);
      } else {
        bytes.add(encodeChar(rune));
      }

      // Garde-fou : une ligne ne depasse jamais la largeur du ticket.
      if (bytes.length >= 255) {
        break;
      }
    }

    return bytes;
  }
}

/// Generateur de ticket ESC/POS.
///
/// Usage :
/// ```dart
/// final bytes = const PosEscPosGenerator().render(receipt: receipt, header: 'Chez Mamie');
/// await printer.write(bytes);
/// ```
class PosEscPosGenerator {
  const PosEscPosGenerator({
    this.width = PosReceiptWidth.width58,
    this.bold = true,
    this.feedLines = 3,
    this.cut = true,
  });

  /// Largeur du papier (58 mm par defaut : les caisses mobiles).
  final PosReceiptWidth width;

  /// Le nom du commerce est-il imprime en gras ?
  final bool bold;

  /// Nombre de lignes vierges avant la decoupe.
  final int feedLines;

  /// Decoupe automatique en fin de ticket (GS V 1).
  final bool cut;

  int get _columns => width.columns;

  /// Rend le ticket complet en octets ESC/POS.
  List<int> render({
    required PosReceipt receipt,
    required String shopName,
    String? shopAddress,
    String? ticketNumber,
    DateTime? printedAt,
    String? operatorName,
  }) {
    final bytes = <int>[];

    // Init + densite (ESC @ puis ESC 7).
    bytes.addAll(const [0x1B, 0x40]);
    bytes.addAll(const [0x1B, 0x37, 0x02]);

    _appendWrapped(bytes, shopName.toUpperCase(), align: PosReceiptAlign.center, bold: bold);

    if (shopAddress != null && shopAddress.trim().isNotEmpty) {
      _appendWrapped(bytes, shopAddress, align: PosReceiptAlign.center);
    }

    bytes.add(0x0A);
    _appendSeparator(bytes);

    final printed = printedAt ?? DateTime.now();

    if (ticketNumber != null && ticketNumber.isNotEmpty) {
      _appendRow(bytes, 'Ticket n\u00b0$ticketNumber', align: PosReceiptAlign.left);
    }

    _appendRow(
      bytes,
      _formatDateTime(printed),
      align: PosReceiptAlign.left,
    );

    if (operatorName != null && operatorName.trim().isNotEmpty) {
      _appendRow(bytes, 'Vendeur : ${operatorName.trim()}', align: PosReceiptAlign.left);
    }

    _appendSeparator(bytes);

    // Lignes du ticket.
    for (final line in receipt.lines) {
      final name = line.name.length > _columns - 4
          ? line.name.substring(0, _columns - 4)
          : line.name;
      _appendRow(bytes, name, align: PosReceiptAlign.left);
      _appendRightAmount(bytes,
          '${line.quantity} x ${_formatAmount(line.unitPriceMinor)}',
          _formatAmount(line.totalMinor));

      // Options du plat : imprimees en retrait, sous la ligne, pour que le
      // client retrouve exactement ce qu'il a commande (cuisson, supplements).
      for (final option in line.options) {
        _appendRow(bytes, '  + $option', align: PosReceiptAlign.left);
      }
    }

    if (receipt.discountMinor > 0) {
      _appendSeparator(bytes);
      _appendRightAmount(bytes, 'Remise', '-${_formatAmount(receipt.discountMinor)}');
    }

    if (receipt.tipMinor > 0) {
      _appendRightAmount(bytes, 'Pourboire', _formatAmount(receipt.tipMinor));
    }

    _appendSeparator(bytes);

    _appendRow(bytes, 'TOTAL', align: PosReceiptAlign.left, bold: true);
    _appendRightAmount(bytes, '', _formatAmount(receipt.totalMinor), bold: true);

    if (receipt.voidedLines.isNotEmpty) {
      _appendSeparator(bytes);
      _appendRow(bytes, 'ANNULE :', align: PosReceiptAlign.left);

      for (final voided in receipt.voidedLines) {
        _appendRow(bytes, '  ${voided.itemName} (${voided.reason.label})',
            align: PosReceiptAlign.left);
      }
    }

    if (receipt.note != null && receipt.note!.trim().isNotEmpty) {
      _appendSeparator(bytes);
      _appendWrapped(bytes, receipt.note!, align: PosReceiptAlign.left);
    }

    _appendSeparator(bytes);

    // Pied de ticket.
    _appendWrapped(bytes, 'Merci de votre visite !', align: PosReceiptAlign.center);

    // Blancs avant decoupe.
    for (var i = 0; i < feedLines; i++) {
      bytes.add(0x0A);
    }

    if (cut) {
      // GS V 1 : decoupe partielle.
      bytes.addAll(const [0x1D, 0x56, 0x01]);
    }

    return bytes;
  }

  /// Imprime un texte en le coupant proprement sur [width.columns].
  void _appendWrapped(List<int> out, String text,
      {required PosReceiptAlign align, bool bold = false}) {
    final words = text.split(RegExp(r'\s+')).where((word) => word.isNotEmpty).toList();

    if (words.isEmpty) {
      out.add(0x0A);
      return;
    }

    var current = '';

    for (final word in words) {
      // Un mot plus long que la ligne est coupe brutalement.
      var piece = word;

      while (piece.length > _columns) {
        if (current.isNotEmpty) {
          _appendRow(out, current.trim(), align: align, bold: bold);
          current = '';
        }

        _appendRow(out, piece.substring(0, _columns), align: align, bold: bold);
        piece = piece.substring(_columns);
      }

      final candidate = current.isEmpty ? piece : '$current $piece';

      if (candidate.length > _columns && current.isNotEmpty) {
        _appendRow(out, current.trim(), align: align, bold: bold);
        current = piece;
      } else {
        current = candidate;
      }
    }

    if (current.trim().isNotEmpty) {
      _appendRow(out, current.trim(), align: align, bold: bold);
    }
  }

  /// Ligne pleine, avec alignement.
  void _appendRow(List<int> out, String text,
      {required PosReceiptAlign align, bool bold = false}) {
    out.addAll(alignBytes(align));

    if (bold) {
      out.addAll(const [0x1B, 0x45, 0x01]); // ESC E 1 : gras ON
    }

    out.addAll(PosEscPosEncoding.encodeLine(text));
    out.add(0x0A);

    if (bold) {
      out.addAll(const [0x1B, 0x45, 0x00]); // gras OFF
    }
  }

  /// Ligne « libelle à gauche, montant à droite » (le classique des tickets).
  void _appendRightAmount(List<int> out, String label, String amount,
      {bool bold = false}) {
    final available = _columns - amount.length - 1;

    if (available <= 0) {
      _appendRow(out, '$label $amount', align: PosReceiptAlign.left, bold: bold);
      return;
    }

    final fitted = label.length > available ? label.substring(0, available) : label;
    final padding = _columns - fitted.length - amount.length;

    _appendRow(out, fitted + (' ' * padding) + amount, align: PosReceiptAlign.left,
        bold: bold);
  }

  /// Separatrice `- - - - - -` pleine largeur.
  void _appendSeparator(List<int> out) =>
      _appendRow(out, '-' * _columns, align: PosReceiptAlign.left);

  /// Octets d'alignement ESC/POS : `ESC a n`.
  List<int> alignBytes(PosReceiptAlign align) => [0x1B, 0x61, align.escValue];

  /// Formate un montant en unites mineures (centimes) en texte lisible.
  /// `1500` -> `15,00` (groupeur d'espaces fines: `1 234,50`).
  static String _formatAmount(int minor) {
    final euros = minor ~/ 100;
    final cents = (minor % 100).toString().padLeft(2, '0');
    final grouped = _groupDigits(euros);

    return '$grouped,$cents';
  }

  static String _groupDigits(int value) {
    final digits = value.toString();

    if (digits.length <= 3) {
      return digits;
    }

    final buffer = StringBuffer();

    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && (digits.length - i) % 3 == 0) {
        buffer.write(' ');
      }

      buffer.write(digits[i]);
    }

    return buffer.toString();
  }

  /// `2026-11-09 19:45` — le format compact, sans secondes ni fuseau.
  static String _formatDateTime(DateTime when) {
    String two(int value) => value.toString().padLeft(2, '0');

    return '${when.year}-${two(when.month)}-${two(when.day)} ${two(when.hour)}:${two(when.minute)}';
  }
}

/// Etat d'une imprimante Bluetooth, vu par l'operateur.
enum PosPrinterStatus {
  disconnected('Non connectee'),
  connecting('Connexion...'),
  connected('Connectee'),
  printing('Impression...'),
  error('Erreur');

  const PosPrinterStatus(this.label);

  final String label;

  bool get isConnected => this == PosPrinterStatus.connected || this == PosPrinterStatus.printing;
}

/// Resultat d'une impression : le message a afficher a l'operateur.
class PosPrintOutcome {
  const PosPrintOutcome.success() : success = true, message = 'Ticket imprime.';
  const PosPrintOutcome.failure(this.message) : success = false;

  final bool success;
  final String message;
}

/// Une imprimante decouverte, affichable telle quelle.
class PosPrinterDevice {
  const PosPrinterDevice({required this.id, required this.name});

  final String id;
  final String name;

  bool get hasName => name.trim().isNotEmpty;

  @override
  bool operator ==(Object other) =>
      other is PosPrinterDevice && other.id == id;

  @override
  int get hashCode => id.hashCode;
}
