import 'package:flutter/material.dart';

import '../../core/models/retail_models.dart';

/// Feuille de saisie d'un mouvement de tiroir-caisse (#9212).
///
/// Un mouvement est une piece de justification comptable : le motif est
/// **obligatoire**, et le formulaire refuse de valider sans lui.
class CashMovementSheet extends StatefulWidget {
  const CashMovementSheet({super.key});

  @override
  State<CashMovementSheet> createState() => _CashMovementSheetState();
}

class _CashMovementSheetState extends State<CashMovementSheet> {
  final _amountController = TextEditingController();
  final _reasonController = TextEditingController();

  RetailCashMovementType _type = RetailCashMovementType.cashIn;

  @override
  void dispose() {
    _amountController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final amount = int.tryParse(_amountController.text.trim()) ?? 0;
    final movement = RetailCashMovement(
      type: _type,
      amountMinor: amount,
      reason: _reasonController.text,
    );

    return Padding(
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Mouvement de caisse', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          SegmentedButton<RetailCashMovementType>(
            segments: [
              for (final type in RetailCashMovementType.values)
                ButtonSegment<RetailCashMovementType>(
                  value: type,
                  label: Text(type.label),
                ),
            ],
            selected: {_type},
            onSelectionChanged: (selection) => setState(() => _type = selection.first),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(labelText: 'Montant (unites mineures)'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _reasonController,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(labelText: 'Motif (obligatoire)'),
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: movement.isValid
                ? () => Navigator.of(context).pop(movement)
                : null,
            child: const Text('Enregistrer'),
          ),
        ],
      ),
    );
  }
}
