import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_employee/features/salary_advances/providers/salary_advance_provider.dart';
import 'package:leopardo_core/models/salary_advance.dart';

class SalaryAdvanceListScreen extends ConsumerWidget {
  const SalaryAdvanceListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final advancesAsync = ref.watch(salaryAdvancesProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Avances',
        subtitle: 'Demandes, statuts et remboursement',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showRequestSheet(context),
        icon: const Icon(Icons.add_card_outlined),
        label: const Text('Demander'),
      ),
      body: RefreshIndicator(
        color: AppColors.rh,
        backgroundColor: MobileSurface.background,
        onRefresh: () async {
          await ref.refresh(salaryAdvancesProvider.future).then((_) {});
        },
        child: advancesAsync.when(
          data: (advances) {
            if (advances.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(24, 80, 24, 160),
                children: const [
                  EmptyState(
                    icon: Icons.payments,
                    title: 'Aucune avance',
                    description:
                        'Demandez une avance en quelques secondes, puis suivez la decision RH ici.',
                  ),
                ],
              );
            }

            return ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
              itemCount: advances.length,
              itemBuilder: (context, index) {
                final advance = advances[index];
                final color = _getStatusColor(advance.status);
                final amount =
                    '${(advance.amount ?? 0).toStringAsFixed(0)} DZD';
                final reason =
                    advance.reason?.trim().isNotEmpty == true
                        ? advance.reason!
                        : 'Aucun motif';
                final months = advance.repaymentMonths;

                return Semantics(
                  label:
                      'Avance de $amount, motif : $reason, statut ${_getStatusLabel(advance.status)}.',
                  container: true,
                  child: ExcludeSemantics(
                    child: MobileListCard(
                      icon: Icons.payments_outlined,
                      iconColor: color,
                      title: amount,
                      subtitle:
                          months == null ? reason : '$reason - $months mois',
                      trailing: MobileStatusPill(
                        label: _getStatusLabel(advance.status),
                        color: color,
                      ),
                      footer: _advanceFooter(context, ref, advance),
                    ),
                  ),
                );
              },
            );
          },
          loading:
              () => const MobileEmptyLoading(label: 'Chargement des avances'),
          error:
              (e, _) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  MobileErrorPanel(
                    message: e.toString(),
                    onRetry: () => ref.invalidate(salaryAdvancesProvider),
                  ),
                ],
              ),
        ),
      ),
    );
  }

  Widget? _advanceFooter(
    BuildContext context,
    WidgetRef ref,
    SalaryAdvance advance,
  ) {
    if (advance.status != 'pending') return null;

    return Align(
      alignment: Alignment.centerLeft,
      child: TextButton.icon(
        onPressed: () => _confirmCancelAdvance(context, ref, advance.id),
        icon: const Icon(Icons.close_rounded, size: 16),
        label: const Text('Annuler la demande'),
      ),
    );
  }

  void _showRequestSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => const _SalaryAdvanceRequestSheet(),
    );
  }

  Future<void> _confirmCancelAdvance(
    BuildContext context,
    WidgetRef ref,
    int advanceId,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (_) => AlertDialog(
            title: const Text('Annuler cette avance ?'),
            content: const Text(
              'La demande en attente sera retiree avant decision RH.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Garder'),
              ),
              TextButton(
                onPressed: () => Navigator.of(context).pop(true),
                child: const Text('Annuler'),
              ),
            ],
          ),
    );
    if (confirmed != true) return;

    try {
      await ref.read(salaryAdvanceRepositoryProvider).cancelAdvance(advanceId);
      ref.invalidate(salaryAdvancesProvider);
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Demande d avance annulee.')),
      );
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    }
  }

  static String _getStatusLabel(String status) {
    switch (status) {
      case 'active':
        return 'active';
      case 'approved':
        return 'approuvee';
      case 'pending':
        return 'en attente';
      case 'rejected':
        return 'rejetee';
      case 'cancelled':
        return 'annulee';
      default:
        return status;
    }
  }

  static Color _getStatusColor(String status) {
    switch (status) {
      case 'active':
      case 'approved':
        return AppColors.rh;
      case 'pending':
        return AppColors.info;
      case 'rejected':
        return AppColors.danger;
      default:
        return MobileSurface.disabled;
    }
  }
}

class _SalaryAdvanceRequestSheet extends ConsumerStatefulWidget {
  const _SalaryAdvanceRequestSheet();

  @override
  ConsumerState<_SalaryAdvanceRequestSheet> createState() =>
      _SalaryAdvanceRequestSheetState();
}

class _SalaryAdvanceRequestSheetState
    extends ConsumerState<_SalaryAdvanceRequestSheet> {
  final _formKey = GlobalKey<FormState>();
  final _amountCtrl = TextEditingController();
  final _reasonCtrl = TextEditingController();
  int _repaymentMonths = 1;
  bool _submitting = false;

  @override
  void dispose() {
    _amountCtrl.dispose();
    _reasonCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(22, 18, 22, bottom + 24),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 38,
                height: 4,
                decoration: BoxDecoration(
                  color: MobileSurface.border,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Text(
              'Demande d avance',
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 4),
            Text(
              'La demande sera transmise au RH pour validation.',
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
            const SizedBox(height: 18),
            TextFormField(
              controller: _amountCtrl,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              style: const TextStyle(color: MobileSurface.text),
              decoration: const InputDecoration(
                labelText: 'Montant demande',
                suffixText: 'DZD',
              ),
              validator: (value) {
                final amount = _parseAmount(value ?? '');
                if (amount == null || amount <= 0) {
                  return 'Montant obligatoire';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: _repaymentMonths,
              decoration: const InputDecoration(labelText: 'Remboursement'),
              dropdownColor: MobileSurface.surface,
              items:
                  List.generate(12, (index) => index + 1)
                      .map(
                        (months) => DropdownMenuItem(
                          value: months,
                          child: Text('$months mois'),
                        ),
                      )
                      .toList(),
              onChanged:
                  (value) => setState(() => _repaymentMonths = value ?? 1),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _reasonCtrl,
              maxLines: 3,
              maxLength: 180,
              style: const TextStyle(color: MobileSurface.text),
              decoration: const InputDecoration(
                labelText: 'Motif',
                hintText: 'Ex: besoin familial urgent',
              ),
              validator:
                  (value) =>
                      value == null || value.trim().length < 4
                          ? 'Motif obligatoire'
                          : null,
            ),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _submitting ? null : _submit,
              child:
                  _submitting
                      ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                      : const Text('Soumettre au RH'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      await ref
          .read(salaryAdvanceRepositoryProvider)
          .requestAdvance(
            amount: _parseAmount(_amountCtrl.text) ?? 0,
            repaymentMonths: _repaymentMonths,
            reason: _reasonCtrl.text,
          );
      ref.invalidate(salaryAdvancesProvider);
      await ref.refresh(salaryAdvancesProvider.future).then((_) {});
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Demande d avance transmise au RH.')),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $e')));
    }
  }

  double? _parseAmount(String value) {
    return double.tryParse(value.trim().replaceAll(',', '.'));
  }
}
