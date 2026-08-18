// ignore_for_file: use_build_context_synchronously
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_hr/core/providers/core_providers.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_decision_actions.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_hr/features/absences/providers/absence_provider.dart';
import 'package:leopardo_hr/features/auth/providers/auth_provider.dart';
import 'package:leopardo_core/models/absence.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/l10n/l10n.dart';

class AbsenceListScreen extends ConsumerStatefulWidget {
  const AbsenceListScreen({super.key});

  @override
  ConsumerState<AbsenceListScreen> createState() => _AbsenceListScreenState();
}

class _AbsenceListScreenState extends ConsumerState<AbsenceListScreen> {
  int? _downloadingProofId;

  @override
  Widget build(BuildContext context) {
    final absencesAsync = ref.watch(absencesProvider);
    final actor = ref.watch(authProvider).employee;

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: context.l10n.absencesTitle,
        subtitle: context.l10n.absencesSubtitle,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: context.l10n.back,
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showAbsenceRequestSheet(context),
        icon: const Icon(Icons.event_available_outlined),
        label: Text(context.l10n.absencesRequest),
      ),
      body: RefreshIndicator(
        color: AppColors.rh,
        backgroundColor: MobileSurface.background,
        onRefresh: () async => ref.refresh(absencesProvider.future),
        child: absencesAsync.when(
          data: (absences) {
            if (absences.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(24, 80, 24, 160),
                children: const [
                  EmptyState(
                    icon: Icons.calendar_today,
                    title: context.l10n.absencesEmptyTitle,
                    description: context.l10n.absencesEmptyHint,
                  ),
                ],
              );
            }

            return ListView.builder(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
              itemCount: absences.length,
              itemBuilder: (context, index) {
                final absence = absences[index];
                final color = _getStatusColor(absence.status);
                final dateLabel =
                    '${_formatDate(absence.startDate)} - ${_formatDate(absence.endDate)}';
                final requester =
                    absence.employeeName?.trim().isNotEmpty == true
                    ? absence.employeeName!
                    : '${context.l10n.absencesEmployeeLabel} #${absence.employeeId}';

                return MobileListCard(
                  icon: Icons.event_available_outlined,
                  iconColor: color,
                  title: requester,
                  subtitle:
                      '${absence.absenceTypeName ?? context.l10n.absencesTypeFallback} - $dateLabel - ${absence.daysCount.toStringAsFixed(1)} j',
                  trailing: MobileStatusPill(
                    label: _statusLabel(absence.status, context.l10n),
                    color: color,
                  ),
                  footer: _absenceFooter(context, absence, actor: actor),
                );
              },
            );
          },
          loading: () =>
              MobileEmptyLoading(label: context.l10n.absencesLoading),
          error: (e, _) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(20),
            children: [
              MobileErrorPanel(
                message: e.toString(),
                onRetry: () => ref.invalidate(absencesProvider),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showAbsenceRequestSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => const _AbsenceRequestSheet(),
    );
  }

  Widget? _absenceFooter(
    BuildContext context,
    Absence absence, {
    required Employee? actor,
  }) {
    final details = _absenceContext(absence);
    final proofButton = absence.hasProof
        ? _proofButton(context, absence.id)
        : null;

    if (absence.status == 'pending') {
      if (_canDecideAbsence(actor, absence)) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            details,
            if (proofButton != null) proofButton,
            const SizedBox(height: 12),
            MobileDecisionActions(
              approveLabel: context.l10n.absencesApprove,
              rejectLabel: context.l10n.absencesReject,
              onApprove: () => _confirmApproveAbsence(context, absence),
              onReject: () => _showRejectAbsenceSheet(context, absence.id),
            ),
          ],
        );
      }

      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          details,
          if (proofButton != null) proofButton,
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => _confirmCancelAbsence(context, absence.id),
              icon: const Icon(Icons.close_rounded, size: 16),
              label: Text(context.l10n.absencesCancelRequest),
            ),
          ),
        ],
      );
    }

    if (proofButton == null) return details;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [details, proofButton],
    );
  }

  // PA2-MOB-006: quick access to the supporting document ( "pieces" )
  // attached to the request, so managers get who/what/why AND attachments.
  Widget _proofButton(BuildContext context, int absenceId) {
    return Align(
      alignment: Alignment.centerLeft,
      child: TextButton.icon(
        onPressed: _downloadingProofId == absenceId
            ? null
            : () => _viewProof(context, absenceId),
        icon: _downloadingProofId == absenceId
            ? const SizedBox(
                width: 14,
                height: 14,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.attach_file_rounded, size: 16),
        label: Text(context.l10n.absencesViewProof),
      ),
    );
  }

  Future<void> _viewProof(BuildContext context, int absenceId) async {
    setState(() => _downloadingProofId = absenceId);
    try {
      final path = await ref
          .read(absenceRepositoryProvider)
          .downloadProof(absenceId);
      if (!context.mounted) return;

      final uri = Uri.file(path);
      final canLaunch = await canLaunchUrl(uri);
      if (!context.mounted) return;

      if (canLaunch) {
        await launchUrl(uri);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${context.l10n.absencesProofDownloaded}$path')),
        );
      }
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('${context.l10n.absencesFailure}$error')));
    } finally {
      if (mounted) setState(() => _downloadingProofId = null);
    }
  }

  Widget _absenceContext(Absence absence) {
    final reason = absence.reason?.trim().isNotEmpty == true
        ? absence.reason!.trim()
        : context.l10n.absencesReasonMissing;
    final submittedAt = absence.createdAt;
    final submittedLabel = submittedAt == null
        ? context.l10n.absencesDateMissing
        : DateFormat('d MMM yyyy', deviceIntlDateLocale).format(submittedAt);
    final requester = [
      if (absence.employeeName?.trim().isNotEmpty == true)
        absence.employeeName!.trim(),
      if (absence.employeeEmail?.trim().isNotEmpty == true)
        absence.employeeEmail!.trim(),
    ].join(' - ');
    final company = absence.companyName?.trim().isNotEmpty == true
        ? absence.companyName!.trim()
        : context.l10n.absencesCurrentCompany;
    return Text(
      '${requester.isEmpty ? '' : '${context.l10n.absencesRequesterLabel}$requester\n'}${context.l10n.absencesCompanyLabel}$company\n${context.l10n.absencesRequestLabel}$submittedLabel\n${context.l10n.absencesReasonLabel}$reason',
      style: AppTypography.caption.copyWith(
        color: MobileSurface.secondary,
        height: 1.35,
      ),
    );
  }

  Future<void> _confirmApproveAbsence(
    BuildContext context,
    Absence absence,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(context.l10n.absencesApproveTitle),
        content: Text(
          '${absence.employeeName ?? '${context.l10n.absencesEmployeeLabel} #${absence.employeeId}'} - ${absence.absenceTypeName ?? context.l10n.absencesTypeFallback}\n${_formatDate(absence.startDate)} - ${_formatDate(absence.endDate)} (${absence.daysCount.toStringAsFixed(1)} j)\n\nMotif : ${absence.reason?.trim().isNotEmpty == true ? absence.reason!.trim() : context.l10n.absencesReasonNotProvided}\n\n${context.l10n.absencesApproveBody}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(context.l10n.back),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(context.l10n.absencesApprove),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await ref.read(absenceRepositoryProvider).approveAbsence(absence.id);
      ref.invalidate(absencesProvider);
      ref.invalidate(leaveBalancesProvider);
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.absencesApprovedSnack)));
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('${context.l10n.absencesFailure}$error')));
    }
  }

  void _showRejectAbsenceSheet(BuildContext context, int absenceId) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => MobileDecisionCommentSheet(
        title: context.l10n.absencesRejectTitle,
        helper: context.l10n.absencesRejectHelper,
        submitLabel: context.l10n.absencesReject,
        danger: true,
        onSubmit: (comment) async {
          await ref
              .read(absenceRepositoryProvider)
              .rejectAbsence(absenceId: absenceId, reason: comment);
          ref.invalidate(absencesProvider);
          ref.invalidate(leaveBalancesProvider);
        },
        successMessage: context.l10n.absencesRejectedSnack,
      ),
    );
  }

  static bool _canDecideAbsence(Employee? actor, Absence absence) {
    if (actor == null) return false;
    if (actor.id == absence.employeeId) return false;
    return actor.isPrincipal ||
        actor.isHr ||
        actor.capabilities.contains('absences.manage') ||
        actor.capabilities.contains('absences.approve');
  }

  Future<void> _confirmCancelAbsence(
    BuildContext context,
    int absenceId,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(context.l10n.absencesCancelTitle),
        content: Text(context.l10n.absencesCancelBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(context.l10n.absencesKeep),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(context.l10n.absencesCancel),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await ref.read(absenceRepositoryProvider).cancelAbsence(absenceId);
      ref.invalidate(absencesProvider);
      ref.invalidate(leaveBalancesProvider);
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.absencesCancelledSnack)),
      );
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('${context.l10n.absencesFailure}$error')));
    }
  }

  static String _formatDate(DateTime date) {
    return DateFormat('d MMM', deviceIntlDateLocale).format(date);
  }

  static String _statusLabel(String status, AppLocalizations l10n) => switch (status) {
    'approved' => l10n.absencesStatusApproved,
    'pending' => l10n.absencesStatusPending,
    'rejected' => l10n.absencesStatusRejected,
    'cancelled' => l10n.absencesStatusCancelled,
    _ => status,
  };

  static Color _getStatusColor(String status) {
    switch (status) {
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

class _AbsenceRequestSheet extends ConsumerStatefulWidget {
  const _AbsenceRequestSheet();

  @override
  ConsumerState<_AbsenceRequestSheet> createState() =>
      _AbsenceRequestSheetState();
}

class _AbsenceRequestSheetState extends ConsumerState<_AbsenceRequestSheet> {
  final _formKey = GlobalKey<FormState>();
  final _reasonController = TextEditingController();
  DateTime _startDate = DateTime.now().add(const Duration(days: 1));
  DateTime _endDate = DateTime.now().add(const Duration(days: 1));
  _AbsenceTypeOption? _selectedType;
  bool _submitting = false;
  // PA2-MOB-006: optional supporting document (medical note,
  // justification letter, etc.) attached to the request.
  File? _proofFile;

  @override
  void dispose() {
    _reasonController.dispose();
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
    final balances = ref.watch(leaveBalancesProvider);

    return Padding(
      padding: EdgeInsets.fromLTRB(22, 18, 22, bottom + 24),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
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
                context.l10n.absencesNewAbsence,
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                context.l10n.absencesNewAbsenceHint,
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 18),
              balances.when(
                data: (rawBalances) {
                  final options = rawBalances
                      .map((balance) => _AbsenceTypeOption.fromBalance(balance, context.l10n))
                      .where((option) => option != null)
                      .cast<_AbsenceTypeOption>()
                      .toList();
                  _selectedType ??= options.isNotEmpty ? options.first : null;

                  if (options.isEmpty) {
                    return MobileErrorPanel(
                      message:
                          context.l10n.absencesNoTypeAvailable,
                      onRetry: () => ref.invalidate(leaveBalancesProvider),
                    );
                  }

                  return DropdownButtonFormField<_AbsenceTypeOption>(
                    initialValue: options.contains(_selectedType)
                        ? _selectedType
                        : options.first,
                    dropdownColor: MobileSurface.surface,
                    decoration: InputDecoration(labelText: context.l10n.absencesType),
                    items: options
                        .map(
                          (option) => DropdownMenuItem(
                            value: option,
                            child: Text(option.label),
                          ),
                        )
                        .toList(),
                    validator: (value) =>
                        value == null ? context.l10n.absencesTypeRequired : null,
                    onChanged: (value) => setState(() => _selectedType = value),
                  );
                },
                loading: () =>
                    MobileEmptyLoading(label: context.l10n.absencesBalancesLoading),
                error: (error, _) => MobileErrorPanel(
                  message: error.toString(),
                  onRetry: () => ref.invalidate(leaveBalancesProvider),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _DateTile(
                      label: context.l10n.absencesStart,
                      value: _startDate,
                      onTap: () => _pickDate(isStart: true),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _DateTile(
                      label: context.l10n.absencesEnd,
                      value: _endDate,
                      onTap: () => _pickDate(isStart: false),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _reasonController,
                maxLines: 3,
                maxLength: 180,
                style: const TextStyle(color: MobileSurface.text),
                decoration: InputDecoration(
                  labelText: context.l10n.absencesReason,
                  hintText: context.l10n.absencesReasonHint,
                ),
                validator: (value) => value == null || value.trim().length < 4
                    ? context.l10n.absencesReasonRequired
                    : null,
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _pickProof,
                icon: const Icon(Icons.attach_file_rounded, size: 18),
                label: Text(
                  _proofFile == null
                      ? context.l10n.absencesAttachProof
                      : context.l10n.absencesProofAttached,
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
                    : Text(context.l10n.absencesSubmitToHr),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickDate({required bool isStart}) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: isStart ? _startDate : _endDate,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: DateTime(now.year + 2),
    );
    if (picked == null) return;

    setState(() {
      if (isStart) {
        _startDate = picked;
        if (_endDate.isBefore(_startDate)) _endDate = _startDate;
      } else {
        _endDate = picked.isBefore(_startDate) ? _startDate : picked;
      }
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final selectedType = _selectedType;
    if (selectedType == null) return;

    setState(() => _submitting = true);
    try {
      await ref
          .read(absenceRepositoryProvider)
          .requestAbsence(
            absenceTypeId: selectedType.id,
            startDate: _startDate,
            endDate: _endDate,
            reason: _reasonController.text,
            proofFilePath: _proofFile?.path,
          );
      ref.invalidate(absencesProvider);
      ref.invalidate(leaveBalancesProvider);
      await ref.refresh(absencesProvider.future).then((_) {});
      if (!context.mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.absencesSubmittedSnack)),
      );
    } catch (error) {
      if (!context.mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('${context.l10n.absencesFailure}$error')));
    }
  }
}

class _DateTile extends StatelessWidget {
  const _DateTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final DateTime value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: MobileSurface.cardDecoration(
          color: MobileSurface.chip,
          radius: 12,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: AppTypography.caption.copyWith(
                color: MobileSurface.disabled,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              DateFormat('d MMM yyyy', deviceIntlDateLocale).format(value),
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.text,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AbsenceTypeOption {
  const _AbsenceTypeOption({required this.id, required this.label});

  final int id;
  final String label;

  static _AbsenceTypeOption? fromBalance(Map<String, dynamic> balance, AppLocalizations l10n) {
    final rawType = balance['absence_type'] ?? balance['absenceType'];
    if (rawType is! Map) return null;
    final type = rawType.cast<String, dynamic>();
    final id = int.tryParse(type['id']?.toString() ?? '');
    if (id == null) return null;

    final name = type['name']?.toString();
    final remaining =
        balance['remaining_days'] ??
        balance['remaining'] ??
        balance['balance'] ??
        balance['available_days'];
    final suffix = remaining == null
        ? ''
        : ' - ${remaining.toString()}${l10n.absencesDaysAvailable}';

    return _AbsenceTypeOption(
      id: id,
      label: '${name == null || name.isEmpty ? l10n.absencesTypeFallback : name}$suffix',
    );
  }
}
