/// Feuille de **selection des variantes et options** d'un plat (#9265).
///
/// Le serveur choisit la cuisson, l'accompagnement, les supplements. La
/// feuille ne laisse pas valider tant que les groupes obligatoires ne sont
/// pas couverts, et affiche en permanence le prix effectif de la ligne —
/// le serveur annonce le bon montant au client sans calculer de tete.
library;

import 'package:flutter/material.dart';

import 'pos_options.dart';

/// Resultat d'une selection validee.
class PosOptionSelectionResult {
  const PosOptionSelectionResult({
    required this.selections,
    required this.additionalMinor,
  });

  final List<PosOptionSelection> selections;
  final int additionalMinor;
}

/// Ouvre la feuille de selection et renvoie le choix (null si annule).
Future<PosOptionSelectionResult?> showPosOptionSheet({
  required BuildContext context,
  required String productName,
  required int basePriceMinor,
  required PosProductOptions productOptions,
  List<PosOptionSelection> initial = const [],
}) {
  return showModalBottomSheet<PosOptionSelectionResult>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => PosOptionSheet(
      productName: productName,
      basePriceMinor: basePriceMinor,
      productOptions: productOptions,
      initial: initial,
    ),
  );
}

/// Feuille de selection des options.
class PosOptionSheet extends StatefulWidget {
  const PosOptionSheet({
    super.key,
    required this.productName,
    required this.basePriceMinor,
    required this.productOptions,
    this.initial = const [],
  });

  final String productName;
  final int basePriceMinor;
  final PosProductOptions productOptions;
  final List<PosOptionSelection> initial;

  @override
  State<PosOptionSheet> createState() => _PosOptionSheetState();
}

class _PosOptionSheetState extends State<PosOptionSheet> {
  late List<PosOptionSelection> _selections;

  @override
  void initState() {
    super.initState();
    _selections = List<PosOptionSelection>.from(widget.initial);
  }

  int get _additionalMinor => PosOptionsPricing.additionalMinor(
        productOptions: widget.productOptions,
        selections: _selections,
      );

  bool get _isValid => PosOptionsPricing.validate(
        productOptions: widget.productOptions,
        selections: _selections,
      );

  /// Bascule une option : choix unique (remplace) ou multiple (ajoute/retire).
  void _toggle(PosOptionGroup group, PosOption option) {
    setState(() {
      final existingIndex = _selections.indexWhere(
        (selection) =>
            selection.groupId == group.id && selection.optionId == option.id,
      );

      if (existingIndex != -1) {
        _selections.removeAt(existingIndex);

        return;
      }

      if (group.maxSelections <= 1) {
        // Choix unique : la nouvelle option remplace l'ancienne du groupe.
        _selections.removeWhere((selection) => selection.groupId == group.id);
        _selections
            .add(PosOptionSelection(groupId: group.id, optionId: option.id));

        return;
      }

      final currentInGroup = _selections
          .where((selection) => selection.groupId == group.id)
          .length;

      if (currentInGroup >= group.maxSelections) {
        // Plafond atteint : on remplace la plus ancienne du groupe plutot que
        // d'ignorer le geste (un tap sans effet laisse croire a un bug).
        final oldest = _selections
            .indexWhere((selection) => selection.groupId == group.id);

        if (oldest != -1) {
          _selections.removeAt(oldest);
        }
      }

      _selections
          .add(PosOptionSelection(groupId: group.id, optionId: option.id));
    });
  }

  /// Ajuste la quantite d'une option deja selectionnee.
  void _setQuantity(PosOptionGroup group, PosOption option, int quantity) {
    setState(() {
      final index = _selections.indexWhere(
        (selection) =>
            selection.groupId == group.id && selection.optionId == option.id,
      );

      if (index == -1) {
        return;
      }

      if (quantity <= 0) {
        _selections.removeAt(index);

        return;
      }

      _selections[index] = PosOptionSelection(
        groupId: group.id,
        optionId: option.id,
        quantity: quantity,
      );
    });
  }

  bool _isSelected(PosOptionGroup group, PosOption option) => _selections.any(
        (selection) =>
            selection.groupId == group.id && selection.optionId == option.id,
      );

  int _quantityOf(PosOptionGroup group, PosOption option) {
    for (final selection in _selections) {
      if (selection.groupId == group.id && selection.optionId == option.id) {
        return selection.quantity;
      }
    }

    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final total = widget.basePriceMinor + _additionalMinor;

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.85,
      maxChildSize: 0.95,
      minChildSize: 0.5,
      builder: (context, scrollController) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.productName,
                        style: TextStyle(
                          fontSize: 21,
                          fontWeight: FontWeight.w700,
                          color: scheme.onSurface,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Choisissez les options',
                        style: TextStyle(
                            fontSize: 15, color: scheme.onSurfaceVariant),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close),
                  tooltip: 'Fermer',
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              controller: scrollController,
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
              children: [
                for (final group in widget.productOptions.groups)
                  _buildGroup(context, group, scheme),
              ],
            ),
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Total de la ligne',
                      style: TextStyle(
                          fontSize: 16, color: scheme.onSurfaceVariant),
                    ),
                    Text(
                      _formatAmount(total),
                      key: const Key('pos-option-total'),
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                        color: scheme.onSurface,
                      ),
                    ),
                  ],
                ),
                if (_additionalMinor > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        'dont ${_formatAmount(_additionalMinor)} d\u2019options',
                        style: TextStyle(
                            fontSize: 15, color: scheme.onSurfaceVariant),
                      ),
                    ),
                  ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  height: 60,
                  child: FilledButton.icon(
                    key: const Key('pos-option-confirm'),
                    onPressed: _isValid
                        ? () => Navigator.of(context).pop(
                              PosOptionSelectionResult(
                                selections:
                                    List<PosOptionSelection>.from(_selections),
                                additionalMinor: _additionalMinor,
                              ),
                            )
                        : null,
                    icon: const Icon(Icons.check),
                    label: Text(
                      _isValid
                          ? 'Ajouter au panier'
                          : 'Completez les choix obligatoires',
                      style: const TextStyle(
                          fontSize: 17, fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGroup(
      BuildContext context, PosOptionGroup group, ColorScheme scheme) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                group.name,
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: scheme.onSurface,
                ),
              ),
              const SizedBox(width: 8),
              if (group.required)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: scheme.errorContainer,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    'Obligatoire',
                    style: TextStyle(
                      // 14 px reste lisible et tient dans la pastille ; en
                      // dessous, le mot devient illisible a bout de bras.
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: scheme.onErrorContainer,
                    ),
                  ),
                ),
              if (group.maxSelections > 1) ...[
                const SizedBox(width: 8),
                Text(
                  'jusqu\u2019a ${group.maxSelections}',
                  style:
                      TextStyle(fontSize: 15, color: scheme.onSurfaceVariant),
                ),
              ],
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              for (final option in group.options)
                _buildOptionChip(context, group, option, scheme),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildOptionChip(
    BuildContext context,
    PosOptionGroup group,
    PosOption option,
    ColorScheme scheme,
  ) {
    final selected = _isSelected(group, option);
    final quantity = _quantityOf(group, option);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        FilterChip(
          key: Key('pos-option-${group.id}-${option.id}'),
          selected: selected,
          onSelected: option.available ? (_) => _toggle(group, option) : null,
          labelPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          label: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                option.name,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  color: option.available ? null : scheme.outline,
                ),
              ),
              if (option.hasPrice) ...[
                const SizedBox(width: 6),
                Text(
                  '+${_formatAmount(option.priceMinor)}',
                  style: TextStyle(
                    fontSize: 14,
                    color: selected ? scheme.primary : scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ],
          ),
        ),
        if (selected && group.maxSelections > 1)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  key: Key('pos-option-minus-${group.id}-${option.id}'),
                  iconSize: 20,
                  visualDensity: VisualDensity.compact,
                  tooltip: 'Retirer un',
                  onPressed: () => _setQuantity(group, option, quantity - 1),
                  icon: const Icon(Icons.remove_circle_outline),
                ),
                Text(
                  '$quantity',
                  style: const TextStyle(
                      fontSize: 16, fontWeight: FontWeight.w600),
                ),
                IconButton(
                  key: Key('pos-option-plus-${group.id}-${option.id}'),
                  iconSize: 20,
                  visualDensity: VisualDensity.compact,
                  tooltip: 'Ajouter un',
                  onPressed: () => _setQuantity(group, option, quantity + 1),
                  icon: const Icon(Icons.add_circle_outline),
                ),
              ],
            ),
          ),
      ],
    );
  }

  /// `1500` -> `15,00`.
  static String _formatAmount(int minor) {
    final units = minor ~/ 100;
    final cents = (minor % 100).toString().padLeft(2, '0');

    return '$units,$cents';
  }
}
