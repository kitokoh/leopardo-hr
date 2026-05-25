import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/core/widgets/mobile_surface.dart';
import 'package:leopardo_rh/features/absences/providers/absence_provider.dart';

class AbsenceListScreen extends ConsumerWidget {
  const AbsenceListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final absencesAsync = ref.watch(absencesProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Mes Absences',
        subtitle: 'Demandes, soldes et decisions RH',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showAbsenceRequestSheet(context),
        icon: const Icon(Icons.event_available_outlined),
        label: const Text('Demander'),
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
                    title: 'Aucune absence',
                    description:
                        'Demandez une absence depuis le bouton principal, puis suivez la decision RH ici.',
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

                return MobileListCard(
                  icon: Icons.event_available_outlined,
                  iconColor: color,
                  title: absence.absenceTypeName ?? 'Absence',
                  subtitle:
                      '$dateLabel - ${absence.daysCount.toStringAsFixed(1)} j',
                  trailing: MobileStatusPill(
                    label: _statusLabel(absence.status),
                    color: color,
                  ),
                  footer:
                      absence.reason == null || absence.reason!.trim().isEmpty
                          ? null
                          : Text(
                            absence.reason!,
                            style: AppTypography.caption.copyWith(
                              color: MobileSurface.secondary,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                );
              },
            );
          },
          loading:
              () => const MobileEmptyLoading(label: 'Chargement des absences'),
          error:
              (e, _) => ListView(
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

  static String _formatDate(DateTime date) {
    return DateFormat('d MMM', 'fr_FR').format(date);
  }

  static String _statusLabel(String status) => switch (status) {
    'approved' => 'approuvee',
    'pending' => 'en attente',
    'rejected' => 'rejetee',
    'cancelled' => 'annulee',
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

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
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
                'Nouvelle absence',
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Choisissez le type de solde et la periode a transmettre au RH.',
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 18),
              balances.when(
                data: (rawBalances) {
                  final options =
                      rawBalances
                          .map(_AbsenceTypeOption.fromBalance)
                          .where((option) => option != null)
                          .cast<_AbsenceTypeOption>()
                          .toList();
                  _selectedType ??= options.isNotEmpty ? options.first : null;

                  if (options.isEmpty) {
                    return MobileErrorPanel(
                      message:
                          'Aucun type d absence disponible pour ce compte. Contactez le RH pour configurer les soldes.',
                      onRetry: () => ref.invalidate(leaveBalancesProvider),
                    );
                  }

                  return DropdownButtonFormField<_AbsenceTypeOption>(
                    initialValue:
                        options.contains(_selectedType)
                            ? _selectedType
                            : options.first,
                    dropdownColor: MobileSurface.surface,
                    decoration: const InputDecoration(labelText: 'Type'),
                    items:
                        options
                            .map(
                              (option) => DropdownMenuItem(
                                value: option,
                                child: Text(option.label),
                              ),
                            )
                            .toList(),
                    validator:
                        (value) =>
                            value == null ? 'Type d absence requis' : null,
                    onChanged: (value) => setState(() => _selectedType = value),
                  );
                },
                loading:
                    () => const MobileEmptyLoading(
                      label: 'Chargement des soldes',
                    ),
                error:
                    (error, _) => MobileErrorPanel(
                      message: error.toString(),
                      onRetry: () => ref.invalidate(leaveBalancesProvider),
                    ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _DateTile(
                      label: 'Debut',
                      value: _startDate,
                      onTap: () => _pickDate(isStart: true),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _DateTile(
                      label: 'Fin',
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
                decoration: const InputDecoration(
                  labelText: 'Motif',
                  hintText: 'Ex: rendez-vous medical, conge familial...',
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
          );
      ref.invalidate(absencesProvider);
      ref.invalidate(leaveBalancesProvider);
      await ref.refresh(absencesProvider.future).then((_) {});
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Demande d absence transmise au RH.')),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
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
              DateFormat('d MMM yyyy', 'fr_FR').format(value),
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

  static _AbsenceTypeOption? fromBalance(Map<String, dynamic> balance) {
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
    final suffix =
        remaining == null ? '' : ' - ${remaining.toString()} j disponibles';

    return _AbsenceTypeOption(
      id: id,
      label: '${name == null || name.isEmpty ? 'Absence' : name}$suffix',
    );
  }
}
