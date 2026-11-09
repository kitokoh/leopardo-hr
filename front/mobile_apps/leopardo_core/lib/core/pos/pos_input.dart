/// **Douchette materielle** (USB / Bluetooth) — logique partagee.
///
/// Une douchette se comporte comme un clavier : elle « tape » le code puis
/// envoie Entree, tres vite. Sans traitement dedie, ce code atterrit dans le
/// champ de recherche et le vendeur voit `6001234567890` s'afficher au lieu de
/// l'article etre ajoute au panier.
///
/// Ce fichier ne contient **aucun widget** : il decide seulement si une
/// sequence de frappes est un scan ou une saisie humaine. C'est ce qui le rend
/// testable sans moteur graphique et partageable par les deux apps.
library;

/// Une frappe clavier, telle que vue par l'ecran de caisse.
class PosKeyStroke {
  const PosKeyStroke({required this.character, required this.at});

  /// Le caractere tape (`'1'`, `'a'`, `'\n'` pour Entree).
  final String character;

  final DateTime at;

  bool get isEnter => character == '\n' || character == '\r';

  bool get isDigit =>
      character.length == 1 &&
      character.codeUnitAt(0) >= 0x30 &&
      character.codeUnitAt(0) <= 0x39;
}

/// Detecteur de scan materiel.
///
/// Regle : une douchette emet ses caracteres a moins de [maxGap] l'un de
/// l'autre, puis Entree. Un humain qui tape depasse largement cet ecart. On
/// exige aussi une longueur minimale, sinon un `1` + Entree (quantite tapee
/// vite) serait pris pour un scan.
class PosHardwareScannerDetector {
  PosHardwareScannerDetector({
    this.maxGap = const Duration(milliseconds: 50),
    this.minLength = 6,
    this.maxLength = 64,
  });

  /// Ecart maximal entre deux frappes d'une meme douchette.
  final Duration maxGap;

  /// Longueur minimale d'un code-barres plausible.
  final int minLength;

  /// Longueur maximale : au-dela, ce n'est pas un code-barres.
  final int maxLength;

  final StringBuffer _buffer = StringBuffer();
  DateTime? _lastAt;

  /// Tampon courant (utile pour l'affichage de debogage et les tests).
  String get buffer => _buffer.toString();

  void reset() {
    _buffer.clear();
    _lastAt = null;
  }

  /// Traite une frappe.
  ///
  /// Retourne le code scanne quand la sequence se termine par Entree et
  /// ressemble a un code-barres ; `null` sinon (frappe humaine, sequence trop
  /// lente, ou code trop court).
  String? feed(PosKeyStroke stroke) {
    if (stroke.isEnter) {
      final code = _buffer.toString().trim();
      final looksLikeScan =
          code.length >= minLength && code.length <= maxLength;

      reset();

      return looksLikeScan ? code : null;
    }

    final previous = _lastAt;

    // Une frappe trop espacee signifie que l'humain a repris la main : on
    // repart de zero plutot que de coller deux saisies differentes.
    if (previous != null && stroke.at.difference(previous) > maxGap) {
      _buffer.clear();
    }

    if (_buffer.length >= maxLength) {
      _buffer.clear();
    }

    _buffer.write(stroke.character);
    _lastAt = stroke.at;

    return null;
  }
}

/// Raccourcis clavier de la caisse.
///
/// Une tablette de caisse a souvent un clavier USB : le vendeur enchaine au
/// clavier ce qu'il fait au doigt. Ces raccourcis sont volontairement peu
/// nombreux et sans modificateur, pour rester utilisables avec une douchette
/// qui occupe deja le clavier.
enum PosShortcut {
  /// F2 — ouvrir le scanner camera.
  scan,

  /// F4 — encaisser (moyen par defaut).
  pay,

  /// F6 — mettre la commande en attente.
  park,

  /// F7 — rappeler une commande en attente.
  recall,

  /// F8 — apercu du ticket.
  receipt,

  /// Echap — fermer la feuille / vider la recherche.
  dismiss,

  /// Suppr — retirer la derniere ligne.
  removeLastLine,

  /// + — incrementer la derniere ligne.
  incrementLastLine,

  /// - — decrementer la derniere ligne.
  decrementLastLine,

  /// F9 — appliquer une remise.
  discount,

  /// F10 — ajouter un pourboire.
  tip,
}

/// Traduit une touche logique en raccourci de caisse.
///
/// `key` est la valeur `LogicalKeyboardKey.keyLabel` (ou son equivalent) :
/// on reste sur des chaines pour ne pas dependre de `flutter/services` dans le
/// noyau pur.
PosShortcut? posShortcutFor(String key) {
  switch (key) {
    case 'F2':
      return PosShortcut.scan;
    case 'F4':
      return PosShortcut.pay;
    case 'F6':
      return PosShortcut.park;
    case 'F7':
      return PosShortcut.recall;
    case 'F8':
      return PosShortcut.receipt;
    case 'Escape':
      return PosShortcut.dismiss;
    case 'Delete':
    case 'Backspace':
      return PosShortcut.removeLastLine;
    case '+':
    case 'Add':
      return PosShortcut.incrementLastLine;
    case '-':
    case 'Subtract':
      return PosShortcut.decrementLastLine;
    case 'F9':
      return PosShortcut.discount;
    case 'F10':
      return PosShortcut.tip;
    default:
      return null;
  }
}

/// Libelle d'aide affiche dans la feuille « raccourcis ».
String posShortcutLabel(PosShortcut shortcut) {
  switch (shortcut) {
    case PosShortcut.scan:
      return 'F2 — Scanner un article';
    case PosShortcut.pay:
      return 'F4 — Encaisser';
    case PosShortcut.park:
      return 'F6 — Mettre la commande en attente';
    case PosShortcut.recall:
      return 'F7 — Rappeler une commande';
    case PosShortcut.receipt:
      return 'F8 — Apercu du ticket';
    case PosShortcut.dismiss:
      return 'Echap — Fermer / effacer la recherche';
    case PosShortcut.removeLastLine:
      return 'Suppr — Retirer la derniere ligne';
    case PosShortcut.incrementLastLine:
      return '+ — Augmenter la quantite';
    case PosShortcut.decrementLastLine:
      return '- — Diminuer la quantite';
    case PosShortcut.discount:
      return 'F9 — Appliquer une remise';
    case PosShortcut.tip:
      return 'F10 — Ajouter un pourboire';
  }
}
