import 'package:flutter/material.dart';

import '../../core/models/retail_models.dart';

/// Resultat de la feuille de cloture.
class CloseSessionResult {
  const CloseSessionResult({required this.countedCashMinor, this.varianceReason});

  final int countedCashMinor;
  final String? varianceReason;
}

/// Feuille de cloture de caisse (#9215).
///
/// Le vendeur compte le tiroir et saisit le montant physique. L'ecart est
/// calcule en direct et affiche : un ecart non nul exige un motif, sinon la
/// cloture serait un trou comptable sans explication.
class CloseSessionSheet extends StatefulWidget {
  const CloseSessionSheet({super.key, required this.session});

  final RetailSession session;

  @override
  State<CloseSessionSheet> createState() => _CloseSessionSheetState();
}

class _CloseSessionSheetState extends State<CloseSessionSheet> {
  final _countedController = TextEditingController();
  final _reasonController = TextEditingController();

  @override
  void dispose() {
    _countedController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final counted = int.tryParse(_countedController.text.trim()) ?? 0;
    final expected = widget.session.expectedCashMinor;
    final variance = RetailSession.variance(
      countedCashMinor: counted,
      expectedCashMinor: expected,
    );

    final needsReason = variance != 0 && _reasonController.text.trim().isEmpty;

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
          Text('Cloture de caisse', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Attendu', style: TextStyle(fontSize: 16)),
              Text('$expected', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _countedController,
            keyboardType: TextInputType.number,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(labelText: 'Especes comptees'),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Ecart', style: TextStyle(fontSize: 16)),
              Text(
                '$variance',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: variance == 0
                      ? Theme.of(context).colorScheme.primary
                      : Theme.of(context).colorScheme.error,
                ),
              ),
            ],
          ),
          if (variance != 0) ...[
            const SizedBox(height: 12),
            TextField(
              controller: _reasonController,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(labelText: 'Motif de l\'ecart (obligatoire)'),
            ),
          ],
          const SizedBox(height: 20),
          FilledButton(
            onPressed: needsReason
                ? null
                : () => Navigator.of(context).pop(
                      CloseSessionResult(
                        countedCashMinor: counted,
                        varianceReason: _reasonController.text.trim().isEmpty
                            ? null
                            : _reasonController.text.trim(),
                      ),
                    ),
            child: const Text('Cloturer la caisse'),
          ),
        ],
      ),
    );
  }
}
