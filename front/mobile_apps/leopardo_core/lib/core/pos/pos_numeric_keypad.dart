/// **Pave numerique tactile** de caisse, partage par les deux apps POS.
///
/// Une caisse se pilote au doigt, pas au clavier systeme : ouvrir le clavier
/// virtuel d'Android masque la moitie de l'ecran et fait perdre le panier de
/// vue. Ce pave reste dans l'interface, avec des touches de 64 dp.
///
/// Il sert a deux choses, qui partagent la meme ergonomie :
///  - saisir une **quantite** (le vendeur tape `12` puis valide) ;
///  - saisir un **montant** (fonds de caisse, remboursement, mouvement).
///
/// Il vivait en double : une version complete dans `leopardo_pos_retail`, une
/// version reduite (`_Keypad`) dans `leopardo_pos_restaurant`. Les deux apps
/// utilisent desormais celle-ci.
library;

import 'package:flutter/material.dart';

/// **Pave numerique tactile** de caisse.
class PosNumericKeypad extends StatelessWidget {
  const PosNumericKeypad({
    super.key,
    required this.onDigit,
    required this.onBackspace,
    required this.onClear,
    this.onValidate,
    this.validateLabel = 'Valider',
    this.validateEnabled = true,
  });

  /// Une touche chiffre a ete pressee.
  final ValueChanged<String> onDigit;

  /// Efface le dernier caractere.
  final VoidCallback onBackspace;

  /// Efface toute la saisie.
  final VoidCallback onClear;

  /// Valide la saisie (optionnel : un pave de quantite peut valider au fil de
  /// l'eau, un pave de montant a besoin d'un bouton explicite).
  final VoidCallback? onValidate;

  final String validateLabel;
  final bool validateEnabled;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final row in const [
          ['1', '2', '3'],
          ['4', '5', '6'],
          ['7', '8', '9'],
        ])
          Row(
            children: [
              for (final digit in row)
                Expanded(
                  child: _KeypadKey(
                    label: digit,
                    onTap: () => onDigit(digit),
                  ),
                ),
            ],
          ),
        Row(
          children: [
            Expanded(
              child: _KeypadKey(
                label: 'C',
                icon: Icons.backspace_outlined,
                onTap: onClear,
                semanticLabel: 'Effacer la saisie',
              ),
            ),
            Expanded(
              child: _KeypadKey(
                label: '0',
                onTap: () => onDigit('0'),
              ),
            ),
            Expanded(
              child: _KeypadKey(
                label: '<',
                icon: Icons.keyboard_backspace,
                onTap: onBackspace,
                semanticLabel: 'Effacer le dernier chiffre',
              ),
            ),
          ],
        ),
        if (onValidate != null) ...[
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              key: const Key('pos-keypad-validate'),
              onPressed: validateEnabled ? onValidate : null,
              child: Text(validateLabel),
            ),
          ),
        ],
      ],
    );
  }
}

/// Une touche du pave : cible large, retour visuel net.
class _KeypadKey extends StatelessWidget {
  const _KeypadKey({
    required this.label,
    required this.onTap,
    this.icon,
    this.semanticLabel,
  });

  final String label;
  final VoidCallback onTap;
  final IconData? icon;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.all(4),
      child: Semantics(
        button: true,
        label: semanticLabel ?? label,
        child: Material(
          color: scheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(12),
            child: Container(
              // 64 dp : au-dessus du minimum d'accessibilite de 48 dp, parce
              // qu'on tape vite et souvent sans regarder ses doigts.
              constraints: const BoxConstraints(minHeight: 64),
              alignment: Alignment.center,
              child: icon != null
                  ? Icon(icon, size: 26, color: scheme.onSurface)
                  : Text(
                      label,
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w700,
                        color: scheme.onSurface,
                      ),
                    ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Champ de saisie numerique pilote par [PosNumericKeypad].
///
/// Le champ reste **en lecture seule** : toute la saisie passe par le pave,
/// ce qui garantit une seule source de verite et evite le clavier systeme.
class PosNumericField extends StatelessWidget {
  const PosNumericField({
    super.key,
    required this.value,
    required this.label,
    this.suffix,
    this.errorText,
  });

  final String value;
  final String label;
  final String? suffix;
  final String? errorText;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        errorText: errorText,
        suffixText: suffix,
      ),
      child: Text(
        value.isEmpty ? '0' : value,
        style: TextStyle(
          fontSize: 30,
          fontWeight: FontWeight.w800,
          color: scheme.onSurface,
        ),
      ),
    );
  }
}

/// Applique une touche a une saisie numerique en cours.
///
/// Regles de caisse : pas de zero de tete inutile, longueur bornee (un montant
/// de caisse tient largement en 9 chiffres), et une saisie vide reste vide
/// plutot que de devenir `0` (sinon l'utilisateur ne peut plus effacer).
String applyNumericKey(String current, String digit, {int maxLength = 9}) {
  if (current.length >= maxLength) {
    return current;
  }

  if (current.isEmpty && digit == '0') {
    return current;
  }

  return '$current$digit';
}

/// Retire le dernier caractere d'une saisie numerique.
String applyBackspace(String current) =>
    current.isEmpty ? current : current.substring(0, current.length - 1);

/// Convertit une saisie en unites mineures entieres.
///
/// La caisse travaille en **unites mineures** (centimes) : le vendeur tape des
/// unites, on convertit une seule fois, ici. Jamais de flottant.
int parseMinorUnits(String input) {
  final trimmed = input.trim();

  if (trimmed.isEmpty) {
    return 0;
  }

  final value = int.tryParse(trimmed) ?? 0;

  return value < 0 ? 0 : value;
}
