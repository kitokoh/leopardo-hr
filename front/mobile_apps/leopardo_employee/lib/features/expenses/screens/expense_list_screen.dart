import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_employee/features/expenses/providers/expense_provider.dart';

class ExpenseListScreen extends ConsumerStatefulWidget {
  const ExpenseListScreen({super.key});

  @override
  ConsumerState<ExpenseListScreen> createState() => _ExpenseListScreenState();
}

class _ExpenseListScreenState extends ConsumerState<ExpenseListScreen> {
  final _categoryController = TextEditingController();
  final _amountController = TextEditingController();
  final _descController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _categoryController.dispose();
    _amountController.dispose();
    _descController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_amountController.text);
    if (_categoryController.text.isEmpty || amount == null || amount <= 0) {
      return;
    }
    setState(() => _submitting = true);
    try {
      await ref
          .read(expenseRepositoryProvider)
          .submitClaim(
            category: _categoryController.text,
            amount: amount,
            date: DateTime.now().toIso8601String().split('T').first,
            description:
                _descController.text.isNotEmpty ? _descController.text : null,
          );
      _categoryController.clear();
      _amountController.clear();
      _descController.clear();
      if (mounted) {
        ref.invalidate(expenseClaimsProvider);
        Navigator.of(context).pop();
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _showAddDialog() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.cardDark,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder:
          (ctx) => Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              20,
              20,
              MediaQuery.of(ctx).viewInsets.bottom + 20,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Nouvelle note de frais',
                  style: AppTypography.subtitle.copyWith(
                    color: AppColors.textDark,
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _categoryController,
                  style: const TextStyle(color: AppColors.textDark),
                  decoration: const InputDecoration(
                    labelText: 'Categorie',
                    labelStyle: TextStyle(color: AppColors.textMutedDark),
                    enabledBorder: UnderlineInputBorder(
                      borderSide: BorderSide(color: AppColors.borderDark),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _amountController,
                  keyboardType: TextInputType.number,
                  style: const TextStyle(color: AppColors.textDark),
                  decoration: const InputDecoration(
                    labelText: 'Montant',
                    labelStyle: TextStyle(color: AppColors.textMutedDark),
                    enabledBorder: UnderlineInputBorder(
                      borderSide: BorderSide(color: AppColors.borderDark),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _descController,
                  style: const TextStyle(color: AppColors.textDark),
                  decoration: const InputDecoration(
                    labelText: 'Description (optionnel)',
                    labelStyle: TextStyle(color: AppColors.textMutedDark),
                    enabledBorder: UnderlineInputBorder(
                      borderSide: BorderSide(color: AppColors.borderDark),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: _submitting ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.rh,
                  ),
                  child:
                      _submitting
                          ? const SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                          : const Text('Soumettre'),
                ),
              ],
            ),
          ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final claimsAsync = ref.watch(expenseClaimsProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Mes Notes de Frais',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _showAddDialog,
        backgroundColor: AppColors.rh,
        child: const Icon(Icons.add),
      ),
      body: RefreshIndicator(
        onRefresh: () async => await ref.refresh(expenseClaimsProvider.future),
        child: claimsAsync.when(
          data:
              (claims) =>
                  claims.isEmpty
                      ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [
                          SizedBox(height: 80),
                          EmptyState(
                            icon: Icons.receipt_long_outlined,
                            title: 'Aucune note de frais',
                            description:
                                'Appuyez sur + pour soumettre une note de frais.',
                          ),
                        ],
                      )
                      : ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(20),
                        itemCount: claims.length,
                        itemBuilder: (context, index) {
                          final claim = claims[index];
                          final statusColor = switch (claim.status) {
                            'approved' => AppColors.success,
                            'rejected' => AppColors.danger,
                            _ => AppColors.warning,
                          };
                          return Card(
                            color: AppColors.cardDark,
                            margin: const EdgeInsets.only(bottom: 12),
                            child: ListTile(
                              title: Text(
                                '${claim.category} — ${claim.amount.toStringAsFixed(2)} ${claim.currency}',
                                style: AppTypography.subtitle.copyWith(
                                  color: AppColors.textDark,
                                ),
                              ),
                              subtitle: Text(
                                '${claim.date}${claim.description != null ? " • ${claim.description}" : ""}',
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.textMutedDark,
                                ),
                              ),
                              trailing: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: statusColor.withValues(alpha: 0.15),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  claim.status.toUpperCase(),
                                  style: TextStyle(
                                    color: statusColor,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
          loading:
              () => const SingleChildScrollView(
                physics: AlwaysScrollableScrollPhysics(),
                child: SizedBox(
                  height: 400,
                  child: Center(
                    child: CircularProgressIndicator(
                      semanticsLabel: 'Chargement des notes de frais...',
                    ),
                  ),
                ),
              ),
          error:
              (e, _) => SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: SizedBox(
                  height: 400,
                  child: Center(
                    child: Text(
                      e.toString(),
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ),
                ),
              ),
        ),
      ),
    );
  }
}
