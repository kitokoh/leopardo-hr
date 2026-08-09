// ignore_for_file: use_build_context_synchronously
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_employee/features/auth/providers/auth_provider.dart';
import 'package:leopardo_employee/features/salary_advances/providers/salary_advance_provider.dart';
import 'package:leopardo_core/models/salary_advance.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:leopardo_core/core/widgets/mobile_list_glass_card.dart';

class SalaryAdvanceListScreen extends ConsumerStatefulWidget {
  const SalaryAdvanceListScreen({super.key});

  @override
  ConsumerState<SalaryAdvanceListScreen> createState() =>
      _SalaryAdvanceListScreenState();
}

class _SalaryAdvanceListScreenState
    extends ConsumerState<SalaryAdvanceListScreen> {
  int? _downloadingProofId;

  @override
  Widget build(BuildContext context) {
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
                final amount = _formatMoney(advance.amount, advance.currency);
                final reason = advance.reason?.trim().isNotEmpty == true
                    ? advance.reason!
                    : 'Aucun motif';
                final months = advance.repaymentMonths;

                return Semantics(
                  label:
                      'Avance de $amount, motif : $reason, statut ${_getStatusLabel(advance)}.',
                  container: true,
                  child: ExcludeSemantics(
                    child: MobileListGlassCard(
                      icon: Icons.payments_outlined,
                      iconColor: color,
                      title: amount,
                      subtitle: months == null
                          ? reason
                          : '$reason - $months mois',
                      trailing: MobileStatusPill(
                        label: _getStatusLabel(advance),
                        color: color,
                      ),
                      footer: _advanceFooter(context, advance),
                    ),
                  ),
                );
              },
            );
          },
          loading: () =>
              const MobileEmptyLoading(label: 'Chargement des avances'),
          error: (e, _) => ListView(
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

  Widget? _advanceFooter(BuildContext context, SalaryAdvance advance) {
    final proofButton = advance.hasProof
        ? _proofButton(context, advance.id)
        : null;

    if (advance.validationStatus == 'payment_declared') {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Le manager a declare le paiement. Confirmez uniquement apres reception effective.',
            style: AppTypography.caption.copyWith(
              color: MobileSurface.secondary,
              height: 1.35,
            ),
          ),
          if (proofButton != null) proofButton,
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: FilledButton.icon(
              onPressed: () => _confirmReceived(context, advance.id),
              icon: const Icon(Icons.verified_user_outlined, size: 16),
              label: const Text('Confirmer reception'),
            ),
          ),
        ],
      );
    }

    if (advance.status != 'pending') {
      return proofButton;
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (proofButton != null) proofButton,
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: () => _confirmCancelAdvance(context, advance.id),
            icon: const Icon(Icons.close_rounded, size: 16),
            label: const Text('Annuler la demande'),
          ),
        ),
      ],
    );
  }

  // PA2-MOB-006: quick access to the supporting document ( "pieces" )
  // attached to the advance request.
  Widget _proofButton(BuildContext context, int advanceId) {
    return Align(
      alignment: Alignment.centerLeft,
      child: TextButton.icon(
        onPressed: _downloadingProofId == advanceId
            ? null
            : () => _viewProof(context, advanceId),
        icon: _downloadingProofId == advanceId
            ? const SizedBox(
                width: 14,
                height: 14,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.attach_file_rounded, size: 16),
        label: const Text('Voir la piece jointe'),
      ),
    );
  }

  Future<void> _viewProof(BuildContext context, int advanceId) async {
    setState(() => _downloadingProofId = advanceId);
    try {
      final path = await ref
          .read(salaryAdvanceRepositoryProvider)
          .downloadProof(advanceId);
      if (!context.mounted) return;

      final uri = Uri.file(path);
      final canLaunch = await canLaunchUrl(uri);
      if (!context.mounted) return;

      if (canLaunch) {
        await launchUrl(uri);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Piece jointe telechargee: $path')),
        );
      }
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    } finally {
      if (mounted) setState(() => _downloadingProofId = null);
    }
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
    int advanceId,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
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

  Future<void> _confirmReceived(BuildContext context, int advanceId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Confirmer la reception ?'),
        content: const Text(
          'Confirmez seulement si le montant est effectivement arrive. Cette action sera historisee.',
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
          .confirmReceived(advanceId);
      ref.invalidate(salaryAdvancesProvider);
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Reception confirmee.')));
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    }
  }

  static String _getStatusLabel(SalaryAdvance advance) {
    switch (advance.validationStatus) {
      case 'manager_approved':
        return 'validee';
      case 'payment_declared':
        return 'a confirmer';
      case 'employee_confirmed':
        return 'recue';
    }

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
  // PA2-MOB-006: optional supporting document (justification, quote,
  // invoice, etc.) attached to the request.
  File? _proofFile;

  @override
  void dispose() {
    _amountCtrl.dispose();
    _reasonCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickProof() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 85,
    );
    if (picked == null || !mounted) return;
    setState(() => _proofFile = File(picked.path));
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
              items: List.generate(12, (index) => index + 1)
                  .map(
                    (months) => DropdownMenuItem(
                      value: months,
                      child: Text('$months mois'),
                    ),
                  )
                  .toList(),
              onChanged: (value) =>
                  setState(() => _repaymentMonths = value ?? 1),
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
              validator: (value) => value == null || value.trim().length < 4
                  ? 'Motif obligatoire'
                  : null,
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _pickProof,
              icon: const Icon(Icons.attach_file_rounded, size: 18),
              label: Text(
                _proofFile == null
                    ? 'Joindre une piece (optionnel)'
                    : 'Piece jointe',
              ),
            ),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
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
            proofFilePath: _proofFile?.path,
          );
      ref.invalidate(salaryAdvancesProvider);
      await ref.refresh(salaryAdvancesProvider.future).then((_) {});
      if (!context.mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Demande d avance transmise au RH.')),
      );
    } catch (e) {
      if (!context.mounted) return;
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

