import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_accounting/core/i18n/app_strings.dart';
import 'package:leopardo_accounting/core/providers/core_providers.dart';
import 'package:leopardo_accounting/features/accounting/models/accounting_document.dart';
import 'package:leopardo_accounting/features/accounting/providers/accounting_providers.dart';

/// Création rapide d'une facture (issue #5236) : contact (optionnel), dates,
/// TVA, notes et lignes dynamiques. POST /accounting/documents.
class CreateInvoiceScreen extends ConsumerStatefulWidget {
  const CreateInvoiceScreen({super.key});

  @override
  ConsumerState<CreateInvoiceScreen> createState() =>
      _CreateInvoiceScreenState();
}

class _CreateInvoiceScreenState extends ConsumerState<CreateInvoiceScreen> {
  final _formKey = GlobalKey<FormState>();
  final _contactController = TextEditingController();
  final _tvaController = TextEditingController(text: '19');
  final _notesController = TextEditingController();
  final List<TextEditingController> _descriptionControllers = [
    TextEditingController(),
  ];
  final List<TextEditingController> _quantityControllers = [
    TextEditingController(text: '1'),
  ];
  final List<TextEditingController> _priceControllers = [
    TextEditingController(),
  ];
  DateTime? _issueDate;
  DateTime? _dueDate;
  bool _submitting = false;

  @override
  void dispose() {
    _contactController.dispose();
    _tvaController.dispose();
    _notesController.dispose();
    for (final controller in _descriptionControllers) {
      controller.dispose();
    }
    for (final controller in _quantityControllers) {
      controller.dispose();
    }
    for (final controller in _priceControllers) {
      controller.dispose();
    }
    super.dispose();
  }

  void _addLine() {
    setState(() {
      _descriptionControllers.add(TextEditingController());
      _quantityControllers.add(TextEditingController(text: '1'));
      _priceControllers.add(TextEditingController());
    });
  }

  void _removeLine(int index) {
    setState(() {
      _descriptionControllers.removeAt(index).dispose();
      _quantityControllers.removeAt(index).dispose();
      _priceControllers.removeAt(index).dispose();
    });
  }

  Future<void> _pickIssueDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _issueDate ?? now,
      firstDate: now.subtract(const Duration(days: 365)),
      lastDate: now.add(const Duration(days: 365 * 2)),
    );
    if (picked != null) {
      setState(() => _issueDate = picked);
    }
  }

  Future<void> _pickDueDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _dueDate ?? now.add(const Duration(days: 30)),
      firstDate: now,
      lastDate: now.add(const Duration(days: 365 * 3)),
    );
    if (picked != null) {
      setState(() => _dueDate = picked);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final lines = <AccountingDocumentLine>[
      for (var i = 0; i < _descriptionControllers.length; i++)
        AccountingDocumentLine(
          description: _descriptionControllers[i].text.trim(),
          quantity: double.tryParse(_quantityControllers[i].text) ?? 1,
          unitPrice: double.tryParse(_priceControllers[i].text) ?? 0,
        ),
    ];
    lines.removeWhere((line) => line.description.isEmpty);

    if (lines.isEmpty) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(_l10n().t('fillRequired'))));
      return;
    }

    setState(() => _submitting = true);
    try {
      await ref.read(
        createInvoiceProvider({
          'contact_id': _contactController.text.trim().isEmpty
              ? null
              : _contactController.text.trim(),
          'issue_date': _issueDate,
          'due_date': _dueDate,
          'tva_rate': double.tryParse(_tvaController.text),
          'notes': _notesController.text.trim().isEmpty
              ? null
              : _notesController.text.trim(),
          'lines': lines,
        }).future,
      );
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_l10n().t('createSuccess'))));
        context.pop();
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_l10n().t('createError'))));
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  AppStrings _l10n() =>
      AppStrings.of(ref.read(appPreferencesProvider).preferredLanguage);

  @override
  Widget build(BuildContext context) {
    final l10n = _l10n();

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('createInvoice'))),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _contactController,
              decoration: InputDecoration(
                labelText: l10n.t('contact'),
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pickIssueDate,
                    icon: const Icon(Icons.event_outlined),
                    label: Text(
                      _issueDate == null
                          ? l10n.t('issueDate')
                          : _issueDate!.toIso8601String().substring(0, 10),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pickDueDate,
                    icon: const Icon(Icons.event_outlined),
                    label: Text(
                      _dueDate == null
                          ? l10n.t('dueDate')
                          : _dueDate!.toIso8601String().substring(0, 10),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _tvaController,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: l10n.t('tvaRate'),
                border: const OutlineInputBorder(),
              ),
              validator: (value) {
                final rate = double.tryParse(value ?? '');
                if (value == null || value.isEmpty || rate == null) {
                  return l10n.t('fillRequired');
                }
                if (rate < 0 || rate > 100) {
                  return l10n.t('fillRequired');
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _notesController,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: l10n.t('notes'),
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              l10n.t('addLine'),
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            for (var i = 0; i < _descriptionControllers.length; i++)
              _LineEditor(
                descriptionController: _descriptionControllers[i],
                quantityController: _quantityControllers[i],
                priceController: _priceControllers[i],
                l10n: l10n,
                canRemove: _descriptionControllers.length > 1,
                onRemove: () => _removeLine(i),
              ),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: _addLine,
                icon: const Icon(Icons.add),
                label: Text(l10n.t('addLine')),
              ),
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: _submitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(l10n.t('submit')),
            ),
          ],
        ),
      ),
    );
  }
}

class _LineEditor extends StatelessWidget {
  const _LineEditor({
    required this.descriptionController,
    required this.quantityController,
    required this.priceController,
    required this.l10n,
    required this.canRemove,
    required this.onRemove,
  });

  final TextEditingController descriptionController;
  final TextEditingController quantityController;
  final TextEditingController priceController;
  final AppStrings l10n;
  final bool canRemove;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextFormField(
            controller: descriptionController,
            decoration: InputDecoration(
              labelText: l10n.t('lineDescription'),
              border: const OutlineInputBorder(),
            ),
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return l10n.t('fillRequired');
              }
              return null;
            },
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                flex: 2,
                child: TextFormField(
                  controller: quantityController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: l10n.t('quantity'),
                    border: const OutlineInputBorder(),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                flex: 3,
                child: TextFormField(
                  controller: priceController,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: l10n.t('unitPrice'),
                    border: const OutlineInputBorder(),
                  ),
                ),
              ),
              if (canRemove) ...[
                const SizedBox(width: 8),
                IconButton(
                  tooltip: l10n.t('removeLine'),
                  onPressed: onRemove,
                  icon: const Icon(Icons.remove_circle_outline),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}
