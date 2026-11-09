import 'package:flutter/material.dart';
import 'package:leopardo_core/core/pos/pos_numeric_keypad.dart';

import '../../core/models/pos_models.dart';
import '../../core/theme/pos_theme.dart';

/// Feuille de saisie d'une **remise** ou d'un **pourboire** (#9214).
///
/// Les deux se saisissent au pave numerique tactile, pas au clavier systeme :
/// le serveur est debout, la tablette sur le comptoir, et le clavier virtuel
/// masquerait l'addition qu'il est en train d'annoncer au client.
///
/// La remise est bornee au sous-total ; le pourboire n'a pas de plafond mais
/// reste un entier positif.
class AmountEntrySheet extends StatefulWidget {
  const AmountEntrySheet({
    super.key,
    required this.title,
    required this.label,
    required this.initialMinor,
    this.maxMinor,
    this.helperText,
  });

  final String title;
  final String label;
  final int initialMinor;

  /// Plafond optionnel (le sous-total pour une remise).
  final int? maxMinor;

  final String? helperText;

  @override
  State<AmountEntrySheet> createState() => _AmountEntrySheetState();
}

class _AmountEntrySheetState extends State<AmountEntrySheet> {
  late String _input = widget.initialMinor > 0 ? '${widget.initialMinor}' : '';

  int get _value => int.tryParse(_input) ?? 0;

  bool get _exceeds => widget.maxMinor != null && _value > widget.maxMinor!;

  @override
  Widget build(BuildContext context) {
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
          Text(widget.title, style: Theme.of(context).textTheme.titleLarge),
          if (widget.helperText != null) ...[
            const SizedBox(height: 4),
            Text(widget.helperText!, style: Theme.of(context).textTheme.bodyMedium),
          ],
          const SizedBox(height: 16),
          InputDecorator(
            decoration: InputDecoration(
              labelText: widget.label,
              errorText: _exceeds ? 'Le montant depasse le maximum autorise.' : null,
            ),
            child: Text(
              _input.isEmpty ? '0' : _input,
              style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w800),
            ),
          ),
          const SizedBox(height: 12),
          PosNumericKeypad(
            onDigit: (digit) => setState(() {
              if (_input.length < 9 && !(_input.isEmpty && digit == '0')) {
                _input = '$_input$digit';
              }
            }),
            onBackspace: () => setState(() {
              if (_input.isNotEmpty) {
                _input = _input.substring(0, _input.length - 1);
              }
            }),
            onClear: () => setState(() => _input = ''),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _exceeds ? null : () => Navigator.of(context).pop(_value),
            child: const Text('Valider'),
          ),
        ],
      ),
    );
  }
}


class SplitPaymentSheet extends StatefulWidget {
  const SplitPaymentSheet({super.key, required this.totalMinor});

  final int totalMinor;

  @override
  State<SplitPaymentSheet> createState() => _SplitPaymentSheetState();
}

class _SplitPaymentSheetState extends State<SplitPaymentSheet> {
  SplitPayment _split = const SplitPayment();
  PosPaymentMethod _method = PosPaymentMethod.cash;
  String _input = '';

  int get _remaining => _split.remainingMinor(widget.totalMinor);

  void _addPart() {
    final amount = int.tryParse(_input) ?? 0;

    if (amount <= 0 || amount > _remaining) {
      return;
    }

    setState(() {
      _split = _split.add(PaymentSplit(method: _method, amountMinor: amount));
      _input = '';
    });
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final covers = _split.covers(widget.totalMinor);

    return Padding(
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Paiement partage', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 4),
            Text(
              'Total : ${_formatMinor(widget.totalMinor)} — reste ${_formatMinor(_remaining)}',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            if (_split.splits.isNotEmpty) ...[
              for (var index = 0; index < _split.splits.length; index++)
                ListTile(
                  dense: true,
                  leading: Icon(
                    _split.splits[index].method == PosPaymentMethod.cash
                        ? Icons.payments
                        : (_split.splits[index].method == PosPaymentMethod.card
                            ? Icons.credit_card
                            : Icons.phone_android),
                  ),
                  title: Text(_split.splits[index].method.label),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _formatMinor(_split.splits[index].amountMinor),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      IconButton(
                        tooltip: 'Retirer cette part',
                        onPressed: () => setState(() {
                          _split = _split.removeAt(index);
                        }),
                        icon: const Icon(Icons.close),
                      ),
                    ],
                  ),
                ),
              const Divider(),
            ],
            SegmentedButton<PosPaymentMethod>(
              segments: [
                for (final method in PosPaymentMethod.values)
                  ButtonSegment(value: method, label: Text(method.label)),
              ],
              selected: {_method},
              onSelectionChanged: (selection) =>
                  setState(() => _method = selection.first),
              showSelectedIcon: false,
            ),
            const SizedBox(height: 12),
            InputDecorator(
              decoration: const InputDecoration(labelText: 'Montant de la part'),
              child: Text(
                _input.isEmpty ? '0' : _input,
                style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
              ),
            ),
            const SizedBox(height: 8),
            PosNumericKeypad(
              onDigit: (digit) => setState(() {
                if (_input.length < 9 && !(_input.isEmpty && digit == '0')) {
                  _input = '$_input$digit';
                }
              }),
              onBackspace: () => setState(() {
                if (_input.isNotEmpty) {
                  _input = _input.substring(0, _input.length - 1);
                }
              }),
              onClear: () => setState(() => _input = ''),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _remaining > 0 ? _addPart : null,
              icon: const Icon(Icons.add),
              label: const Text('Ajouter la part'),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _remaining > 0
                  ? () => setState(() {
                        _split = _split.add(
                          PaymentSplit(method: _method, amountMinor: _remaining),
                        );
                      })
                  : null,
              icon: const Icon(Icons.done_all),
              label: Text('Solder le reste (${_formatMinor(_remaining)})'),
            ),
            const SizedBox(height: 12),
            if (!covers && _split.splits.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  'Les parts doivent couvrir exactement le total.',
                  style: TextStyle(color: scheme.error, fontSize: 15),
                ),
              ),
            FilledButton(
              onPressed: covers ? () => Navigator.of(context).pop(_split) : null,
              child: const Text('Encaisser les parts'),
            ),
          ],
        ),
      ),
    );
  }
}

String _formatMinor(int minor) {
  final negative = minor < 0;
  final absolute = minor.abs();
  final units = absolute ~/ 100;
  final cents = (absolute % 100).toString().padLeft(2, '0');

  return '${negative ? '-' : ''}$units,$cents';
}

/// Cible tactile minimale du POS Restaurant, exposee pour les tests.
const double restaurantMinTouchTarget = PosTheme.minTouchTarget;
