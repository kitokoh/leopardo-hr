import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_decision_actions.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_manager/features/auth/providers/auth_provider.dart';
import 'package:leopardo_manager/features/salary_advances/providers/salary_advance_provider.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_core/models/salary_advance.dart';

class SalaryAdvanceListScreen extends ConsumerWidget {
  const SalaryAdvanceListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final advancesAsync = ref.watch(salaryAdvancesProvider);
    final actor = ref.watch(authProvider).employee;

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
                final amount = _formatMoney(advance.amount, advance.currency);
                final reason =
                    advance.reason?.trim().isNotEmpty == true
                        ? advance.reason!
                        : 'Aucun motif';
                final months = advance.repaymentMonths;
                final requester =
                    advance.employeeName?.trim().isNotEmpty == true
                        ? advance.employeeName!
                        : 'Employe #${advance.employeeId}';

                return Semantics(
                  label:
                      '$requester demande une avance de $amount, motif : $reason, statut ${_getStatusLabel(advance)}.',
                  container: true,
                  child: ExcludeSemantics(
                    child: MobileListCard(
                      icon: Icons.payments_outlined,
                      iconColor: color,
                      title: requester,
                      subtitle:
                          months == null
                              ? '$amount - $reason'
                              : '$amount - $reason - $months mois',
                      trailing: MobileStatusPill(
                        label: _getStatusLabel(advance),
                        color: color,
                      ),
                      footer: _advanceFooter(
                        context,
                        ref,
                        advance,
                        actor: actor,
                      ),
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
    SalaryAdvance advance, {
    required Employee? actor,
  }) {
    final details = _advanceContext(advance);

    final canManage = _canDecideAdvance(actor, advance);

    if (advance.status != 'pending') {
      if (canManage && _canMarkPaid(advance)) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            details,
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => _confirmMarkPaid(context, ref, advance),
                icon: const Icon(Icons.verified_outlined, size: 18),
                label: const Text('Marquer avance envoyee'),
              ),
            ),
          ],
        );
      }

      return details;
    }

    if (canManage) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          details,
          const SizedBox(height: 12),
          MobileDecisionActions(
            approveLabel: 'Approuver',
            rejectLabel: 'Refuser',
            onApprove: () => _confirmApproveAdvance(context, ref, advance),
            onReject: () => _showRejectAdvanceSheet(context, ref, advance.id),
          ),
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        details,
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: () => _confirmCancelAdvance(context, ref, advance.id),
            icon: const Icon(Icons.close_rounded, size: 16),
            label: const Text('Annuler la demande'),
          ),
        ),
      ],
    );
  }

  Widget _advanceContext(SalaryAdvance advance) {
    final amount = _formatMoney(advance.amount, advance.currency);
    final reason =
        advance.reason?.trim().isNotEmpty == true
            ? advance.reason!.trim()
            : 'Motif non renseigne';
    final requestedAt = advance.requestedAt ?? advance.createdAt;
    final date =
        requestedAt == null
            ? 'Date non renseignee'
            : DateFormat('d MMM yyyy', 'fr_FR').format(requestedAt);
    final repayment =
        advance.repaymentMonths == null
            ? 'Remboursement a definir'
            : '${advance.repaymentMonths} mois';
    final requester = [
      if (advance.employeeName?.trim().isNotEmpty == true)
        advance.employeeName!.trim(),
      if (advance.employeeEmail?.trim().isNotEmpty == true)
        advance.employeeEmail!.trim(),
    ].join(' - ');
    final company =
        advance.companyName?.trim().isNotEmpty == true
            ? advance.companyName!.trim()
            : 'Entreprise courante';
    final validation = _validationLabel(advance.validationStatus);
    final payment = [
      if (advance.managerApprovedAt != null)
        'Validation manager : ${DateFormat('d MMM yyyy', 'fr_FR').format(advance.managerApprovedAt!)}',
      if (advance.paymentDeclaredAt != null)
        'Paiement declare : ${DateFormat('d MMM yyyy', 'fr_FR').format(advance.paymentDeclaredAt!)}',
      if (advance.employeeConfirmedAt != null)
        'Reception employee : ${DateFormat('d MMM yyyy', 'fr_FR').format(advance.employeeConfirmedAt!)}',
    ].join('\n');

    return Text(
      '${requester.isEmpty ? '' : 'Demandeur : $requester\n'}Entreprise : $company\nMontant : $amount\nDate : $date\nMotif : $reason\nRemboursement : $repayment\nValidation : $validation${payment.isEmpty ? '' : '\n$payment'}',
      style: AppTypography.caption.copyWith(
        color: MobileSurface.secondary,
        height: 1.35,
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

  Future<void> _confirmApproveAdvance(
    BuildContext context,
    WidgetRef ref,
    SalaryAdvance advance,
  ) async {
    final amount = _formatMoney(advance.amount, advance.currency);
    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (_) => AlertDialog(
            title: const Text('Approuver cette avance ?'),
            content: Text(
              '${advance.employeeName ?? 'Employe #${advance.employeeId}'} demande $amount.\n\nMotif : ${advance.reason?.trim().isNotEmpty == true ? advance.reason!.trim() : 'non renseigne'}\n\nLa decision sera envoyee a l employe.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Retour'),
              ),
              TextButton(
                onPressed: () => Navigator.of(context).pop(true),
                child: const Text('Approuver'),
              ),
            ],
          ),
    );
    if (confirmed != true) return;

    try {
      await ref
          .read(salaryAdvanceRepositoryProvider)
          .approveAdvance(
            advanceId: advance.id,
            repaymentMonths: advance.repaymentMonths,
          );
      ref.invalidate(salaryAdvancesProvider);
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Avance approuvee.')));
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    }
  }

  Future<void> _confirmMarkPaid(
    BuildContext context,
    WidgetRef ref,
    SalaryAdvance advance,
  ) async {
    final amount = _formatMoney(advance.amount, advance.currency);
    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (_) => AlertDialog(
            title: const Text('Avance envoyee ?'),
            content: Text(
              'Confirmez que $amount ont ete envoyes a ${advance.employeeName ?? 'Employe #${advance.employeeId}'}.\n\nL employe recevra ensuite la demande de confirmation de reception.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Retour'),
              ),
              TextButton(
                onPressed: () => Navigator.of(context).pop(true),
                child: const Text('Confirmer'),
              ),
            ],
          ),
    );
    if (confirmed != true) return;

    try {
      await ref
          .read(salaryAdvanceRepositoryProvider)
          .markPaid(advanceId: advance.id);
      ref.invalidate(salaryAdvancesProvider);
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Paiement declare.')));
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    }
  }

  void _showRejectAdvanceSheet(
    BuildContext context,
    WidgetRef ref,
    int advanceId,
  ) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder:
          (_) => MobileDecisionCommentSheet(
            title: 'Refuser l avance',
            helper: 'Le commentaire aide l employe a comprendre la decision.',
            submitLabel: 'Refuser',
            danger: true,
            onSubmit: (comment) async {
              await ref
                  .read(salaryAdvanceRepositoryProvider)
                  .rejectAdvance(advanceId: advanceId, comment: comment);
              ref.invalidate(salaryAdvancesProvider);
            },
            successMessage: 'Avance refusee.',
          ),
    );
  }

  static bool _canDecideAdvance(Employee? actor, SalaryAdvance advance) {
    if (actor == null) return false;
    if (actor.id == advance.employeeId) return false;
    return actor.isPrincipal ||
        actor.isHr ||
        actor.capabilities.contains('salary_advances.manage') ||
        actor.capabilities.contains('salary_advances.approve');
  }

  static bool _canMarkPaid(SalaryAdvance advance) {
    final validation = advance.validationStatus ?? '';
    return validation == 'manager_approved' ||
        (validation.isEmpty && advance.status == 'approved') ||
        advance.status == 'approved';
  }

  static String _getStatusLabel(SalaryAdvance advance) {
    final validation = advance.validationStatus;
    if (validation == 'manager_approved') return 'validee';
    if (validation == 'payment_declared') return 'envoyee';
    if (validation == 'employee_confirmed') return 'recue';

    switch (advance.status) {
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
        return advance.status;
    }
  }

  static String _validationLabel(String? status) {
    switch (status) {
      case 'manager_approved':
        return 'manager valide, paiement a declarer';
      case 'payment_declared':
        return 'paiement declare, attente confirmation employe';
      case 'employee_confirmed':
        return 'reception confirmee par l employe';
      case 'rejected':
        return 'rejetee';
      case 'pending':
      case null:
        return 'en attente';
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

  static String _formatMoney(double? amount, String currency) =>
      '${(amount ?? 0).toStringAsFixed(0)} $currency';
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
    final currency = ref.watch(authProvider).employee?.currency ?? 'DZD';

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
              decoration: InputDecoration(
                labelText: 'Montant demande',
                suffixText: currency,
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
