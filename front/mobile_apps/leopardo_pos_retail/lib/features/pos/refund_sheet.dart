import 'package:flutter/material.dart';

import '../../core/models/retail_models.dart';

/// Feuille de remboursement en caisse (#9208).
///
/// Le motif est obligatoire et le montant doit etre strictement positif :
/// c'est la meme regle que `RefundRetailOrderRequest` cote serveur.
class RefundSheet extends StatefulWidget {
  const RefundSheet({super.key});

  @override
  State<RefundSheet> createState() => _RefundSheetState();
}

class _RefundSheetState extends State<RefundSheet> {
  final _amountController = TextEditingController();
  final _reasonController = TextEditingController();

  RetailPaymentMethod _method = RetailPaymentMethod.cash;

  @override
  void dispose() {
    _amountController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final amount = int.tryParse(_amountController.text.trim()) ?? 0;
    final refund = RetailRefund(
      amountMinor: amount,
      reason: _reasonController.text,
      method: _method,
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
          Text('Remboursement', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(labelText: 'Montant a rembourser'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _reasonController,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(labelText: 'Motif (obligatoire)'),
          ),
          const SizedBox(height: 12),
          SegmentedButton<RetailPaymentMethod>(
            segments: [
              for (final method in RetailPaymentMethod.values)
                ButtonSegment<RetailPaymentMethod>(value: method, label: Text(method.label)),
            ],
            selected: {_method},
            onSelectionChanged: (selection) => setState(() => _method = selection.first),
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: refund.isValid ? () => Navigator.of(context).pop(refund) : null,
            child: const Text('Rembourser'),
          ),
        ],
      ),
    );
  }
}
