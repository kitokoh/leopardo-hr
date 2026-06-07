import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/schedules/data/schedule_repository.dart';
import 'package:leopardo_manager/features/schedules/providers/schedule_provider.dart';
import 'package:leopardo_manager/features/team/providers/team_provider.dart';

class ScheduleListScreen extends ConsumerWidget {
  const ScheduleListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final schedulesAsync = ref.watch(schedulesProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Regles entreprise',
        subtitle: 'Horaires, repos, conges et heures supp',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showScheduleSheet(context, ref),
        icon: const Icon(Icons.add_alarm_outlined),
        label: const Text('Nouvelle regle'),
      ),
      body: RefreshIndicator(
        color: AppColors.rh,
        backgroundColor: MobileSurface.background,
        onRefresh: () async {
          await ref.refresh(schedulesProvider.future).then((_) {});
        },
        child: schedulesAsync.when(
          data: (schedules) {
            if (schedules.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(24, 80, 24, 160),
                children: const [
                  EmptyState(
                    icon: Icons.schedule_outlined,
                    title: 'Aucune regle entreprise',
                    description:
                        'Creez la premiere regle pour cadrer horaires, repos, conges, pauses et heures supplementaires.',
                  ),
                ],
              );
            }

            return ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
              itemCount: schedules.length,
              itemBuilder: (context, index) {
                final schedule = schedules[index];
                return MobileListCard(
                  icon: Icons.schedule_rounded,
                  iconColor: schedule.isDefault ? AppColors.rh : AppColors.info,
                  title: schedule.name,
                  subtitle:
                      '${schedule.startTime} - ${schedule.endTime} | pause ${schedule.breakMinutes} min | repos ${schedule.restDaysLabel}',
                  trailing:
                      schedule.isDefault
                          ? const MobileStatusPill(
                            label: 'Defaut',
                            color: AppColors.rh,
                          )
                          : MobileStatusPill(
                            label: '${schedule.workDays.length} j/sem',
                            color: AppColors.info,
                          ),
                  onTap: () => _showScheduleSheet(context, ref, schedule),
                  footer: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _ScheduleFooter(schedule: schedule),
                      const SizedBox(height: 10),
                      TextButton.icon(
                        onPressed:
                            () => _showAssignSheet(context, ref, schedule),
                        icon: const Icon(Icons.group_add_outlined, size: 17),
                        label: const Text('Affecter aux employes'),
                      ),
                    ],
                  ),
                );
              },
            );
          },
          loading:
              () => const MobileEmptyLoading(label: 'Chargement des regles'),
          error:
              (error, _) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  MobileErrorPanel(
                    message: error.toString(),
                    onRetry: () => ref.invalidate(schedulesProvider),
                  ),
                ],
              ),
        ),
      ),
    );
  }

  void _showScheduleSheet(
    BuildContext context,
    WidgetRef ref, [
    WorkSchedule? schedule,
  ]) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => _ScheduleFormSheet(schedule: schedule),
    );
  }

  void _showAssignSheet(
    BuildContext context,
    WidgetRef ref,
    WorkSchedule schedule,
  ) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => _ScheduleAssignSheet(schedule: schedule),
    );
  }
}

class _ScheduleFooter extends StatelessWidget {
  const _ScheduleFooter({required this.schedule});

  final WorkSchedule schedule;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        MobileStatusPill(
          label: 'Tolerance ${schedule.lateToleranceMinutes} min',
          color: AppColors.warning,
        ),
        MobileStatusPill(label: schedule.leaveRulesLabel, color: AppColors.rh),
        MobileStatusPill(
          label:
              'Supp/j ${schedule.overtimeThresholdDaily.toStringAsFixed(1)}h',
          color: AppColors.ia,
        ),
        MobileStatusPill(
          label:
              'Supp/sem ${schedule.overtimeThresholdWeekly.toStringAsFixed(0)}h',
          color: AppColors.info,
        ),
        if (schedule.assignmentNotes?.trim().isNotEmpty == true)
          const MobileStatusPill(
            label: 'Notes internes',
            color: AppColors.info,
          ),
      ],
    );
  }
}

class _ScheduleAssignSheet extends ConsumerStatefulWidget {
  const _ScheduleAssignSheet({required this.schedule});

  final WorkSchedule schedule;

  @override
  ConsumerState<_ScheduleAssignSheet> createState() =>
      _ScheduleAssignSheetState();
}

class _ScheduleAssignSheetState extends ConsumerState<_ScheduleAssignSheet> {
  final Set<int> _selectedIds = <int>{};
  bool _submitting = false;
  bool _selectionHydrated = false;

  @override
  Widget build(BuildContext context) {
    final teamAsync = ref.watch(teamListProvider);

    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        16,
        20,
        MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: SafeArea(
        top: false,
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.78,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  decoration: BoxDecoration(
                    color: MobileSurface.border,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                'Affecter une regle entreprise',
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                widget.schedule.name,
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Les employes deja rattaches a cette regle sont preselectionnes.',
                style: AppTypography.caption.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 16),
              Expanded(
                child: teamAsync.when(
                  data: (employees) {
                    _hydrateCurrentAssignments(employees);

                    return _EmployeeSelectionList(
                      employees: employees,
                      selectedIds: _selectedIds,
                      onToggle: _toggleEmployee,
                    );
                  },
                  loading:
                      () =>
                          const MobileEmptyLoading(label: 'Chargement equipe'),
                  error:
                      (error, _) => MobileErrorPanel(
                        message: error.toString(),
                        onRetry: () => ref.invalidate(teamListProvider),
                      ),
                ),
              ),
              const SizedBox(height: 12),
              MobilePrimaryAction(
                icon: Icons.done_all_outlined,
                label:
                    _selectedIds.isEmpty
                        ? 'Selectionner des employes'
                        : 'Affecter ${_selectedIds.length} employe(s)',
                onPressed: _selectedIds.isEmpty || _submitting ? null : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _toggleEmployee(int employeeId) {
    setState(() {
      if (_selectedIds.contains(employeeId)) {
        _selectedIds.remove(employeeId);
      } else {
        _selectedIds.add(employeeId);
      }
    });
  }

  void _hydrateCurrentAssignments(List<Employee> employees) {
    if (_selectionHydrated) return;

    _selectedIds.addAll(
      employees
          .where((employee) => employee.scheduleId == widget.schedule.id)
          .map((employee) => employee.id),
    );
    _selectionHydrated = true;
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      final count = await ref
          .read(scheduleRepositoryProvider)
          .assignEmployees(widget.schedule.id, _selectedIds.toList());
      ref.invalidate(teamListProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Regle affectee a $count employe(s).')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _EmployeeSelectionList extends StatelessWidget {
  const _EmployeeSelectionList({
    required this.employees,
    required this.selectedIds,
    required this.onToggle,
  });

  final List<Employee> employees;
  final Set<int> selectedIds;
  final void Function(int employeeId) onToggle;

  @override
  Widget build(BuildContext context) {
    if (employees.isEmpty) {
      return const EmptyState(
        icon: Icons.people_outline,
        title: 'Aucun employe',
        description: 'Ajoutez d abord des employes pour affecter une regle.',
      );
    }

    return ListView.separated(
      itemCount: employees.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final employee = employees[index];
        final selected = selectedIds.contains(employee.id);

        return InkWell(
          onTap: () => onToggle(employee.id),
          borderRadius: BorderRadius.circular(14),
          child: Ink(
            padding: const EdgeInsets.all(12),
            decoration: MobileSurface.cardDecoration(
              color:
                  selected
                      ? AppColors.rh.withValues(alpha: 0.12)
                      : MobileSurface.chip,
              borderColor:
                  selected
                      ? AppColors.rh.withValues(alpha: 0.45)
                      : MobileSurface.border,
              radius: 14,
            ),
            child: Row(
              children: [
                Checkbox(
                  value: selected,
                  onChanged: (_) => onToggle(employee.id),
                  activeColor: AppColors.rh,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        employee.fullName,
                        style: AppTypography.body.copyWith(
                          color: MobileSurface.text,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        employee.scheduleName == null
                            ? 'Aucune regle affectee'
                            : 'Actuel : ${employee.scheduleName}',
                        style: AppTypography.caption.copyWith(
                          color: MobileSurface.secondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ScheduleFormSheet extends ConsumerStatefulWidget {
  const _ScheduleFormSheet({this.schedule});

  final WorkSchedule? schedule;

  @override
  ConsumerState<_ScheduleFormSheet> createState() => _ScheduleFormSheetState();
}

class _ScheduleFormSheetState extends ConsumerState<_ScheduleFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameCtrl;
  late final TextEditingController _breakCtrl;
  late final TextEditingController _toleranceCtrl;
  late final TextEditingController _dailyCtrl;
  late final TextEditingController _weeklyCtrl;
  late final TextEditingController _leaveDaysCtrl;
  late final TextEditingController _notesCtrl;
  late TimeOfDay _startTime;
  late TimeOfDay _endTime;
  late Set<int> _workDays;
  late bool _isDefault;
  bool _submitting = false;

  static const _dayLabels = {
    1: 'Lun',
    2: 'Mar',
    3: 'Mer',
    4: 'Jeu',
    5: 'Ven',
    6: 'Sam',
    7: 'Dim',
  };

  @override
  void initState() {
    super.initState();
    final schedule = widget.schedule;
    _nameCtrl = TextEditingController(
      text: schedule?.name ?? 'Horaire journee',
    );
    _breakCtrl = TextEditingController(
      text: (schedule?.breakMinutes ?? 60).toString(),
    );
    _toleranceCtrl = TextEditingController(
      text: (schedule?.lateToleranceMinutes ?? 15).toString(),
    );
    _dailyCtrl = TextEditingController(
      text: (schedule?.overtimeThresholdDaily ?? 8).toStringAsFixed(1),
    );
    _weeklyCtrl = TextEditingController(
      text: (schedule?.overtimeThresholdWeekly ?? 40).toStringAsFixed(0),
    );
    _leaveDaysCtrl = TextEditingController(
      text: (schedule?.leaveRules.isNotEmpty == true
              ? schedule!.leaveRules.first.daysPerYear ?? 21
              : 21)
          .toStringAsFixed(0),
    );
    _notesCtrl = TextEditingController(text: schedule?.assignmentNotes ?? '');
    _startTime = _parseTime(schedule?.startTime ?? '08:00');
    _endTime = _parseTime(schedule?.endTime ?? '17:00');
    _workDays = {
      ...(schedule?.workDays ?? const [1, 2, 3, 4, 5]),
    };
    _isDefault = schedule?.isDefault ?? false;
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _breakCtrl.dispose();
    _toleranceCtrl.dispose();
    _dailyCtrl.dispose();
    _weeklyCtrl.dispose();
    _leaveDaysCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final editing = widget.schedule != null;

    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        16,
        20,
        MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  decoration: BoxDecoration(
                    color: MobileSurface.border,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                editing ? 'Modifier la regle' : 'Nouvelle regle entreprise',
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Ces regles servent au pointage, aux repos, aux conges, aux pauses et aux heures supplementaires.',
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 18),
              _ScheduleTextField(
                controller: _nameCtrl,
                label: 'Nom',
                icon: Icons.badge_outlined,
                validator:
                    (value) =>
                        value == null || value.trim().isEmpty
                            ? 'Nom obligatoire'
                            : null,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _TimeButton(
                      label: 'Debut',
                      value: _formatTime(_startTime),
                      onTap: () => _pickTime(isStart: true),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _TimeButton(
                      label: 'Fin',
                      value: _formatTime(_endTime),
                      onTap: () => _pickTime(isStart: false),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _ScheduleTextField(
                      controller: _breakCtrl,
                      label: 'Pause min',
                      icon: Icons.coffee_outlined,
                      keyboardType: TextInputType.number,
                      validator: _positiveIntValidator,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _ScheduleTextField(
                      controller: _toleranceCtrl,
                      label: 'Tolerance retard',
                      icon: Icons.timer_outlined,
                      keyboardType: TextInputType.number,
                      validator: _positiveIntValidator,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _ScheduleTextField(
                      controller: _dailyCtrl,
                      label: 'Supp/jour h',
                      icon: Icons.trending_up_rounded,
                      keyboardType: TextInputType.number,
                      validator: _positiveDoubleValidator,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _ScheduleTextField(
                      controller: _weeklyCtrl,
                      label: 'Supp/sem h',
                      icon: Icons.calendar_view_week_outlined,
                      keyboardType: TextInputType.number,
                      validator: _positiveDoubleValidator,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _ScheduleTextField(
                controller: _leaveDaysCtrl,
                label: 'Conge annuel jours',
                icon: Icons.beach_access_outlined,
                keyboardType: TextInputType.number,
                validator: _positiveDoubleValidator,
              ),
              const SizedBox(height: 16),
              Text(
                'Jours travailles',
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.text,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children:
                    _dayLabels.entries.map((entry) {
                      final selected = _workDays.contains(entry.key);
                      return FilterChip(
                        selected: selected,
                        label: Text(entry.value),
                        onSelected: (_) => _toggleDay(entry.key),
                        selectedColor: AppColors.rh.withValues(alpha: 0.18),
                        checkmarkColor: AppColors.rh,
                      );
                    }).toList(),
              ),
              const SizedBox(height: 12),
              SwitchListTile.adaptive(
                value: _isDefault,
                contentPadding: EdgeInsets.zero,
                activeThumbColor: AppColors.rh,
                title: Text(
                  'Horaire par defaut',
                  style: AppTypography.body.copyWith(color: MobileSurface.text),
                ),
                subtitle: Text(
                  'Il sera applique aux nouveaux profils si aucun horaire precis n est choisi.',
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.secondary,
                  ),
                ),
                onChanged: (value) => setState(() => _isDefault = value),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _notesCtrl,
                maxLines: 3,
                maxLength: 1000,
                style: AppTypography.body.copyWith(color: MobileSurface.text),
                decoration: InputDecoration(
                  labelText: 'Regles internes',
                  hintText:
                      'Repos, consignes de pause, conges, exceptions terrain...',
                  prefixIcon: const Icon(
                    Icons.rule_folder_outlined,
                    color: MobileSurface.secondary,
                  ),
                  filled: true,
                  fillColor: MobileSurface.chip,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              MobilePrimaryAction(
                icon: editing ? Icons.save_outlined : Icons.add_alarm_outlined,
                label: editing ? 'Enregistrer' : 'Creer la regle',
                onPressed: _submitting ? null : _submit,
              ),
              if (editing && widget.schedule?.isDefault == false) ...[
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: TextButton.icon(
                    onPressed: _submitting ? null : _delete,
                    icon: const Icon(Icons.delete_outline),
                    label: const Text('Supprimer'),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickTime({required bool isStart}) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: isStart ? _startTime : _endTime,
      builder:
          (context, child) => MediaQuery(
            data: MediaQuery.of(context).copyWith(alwaysUse24HourFormat: true),
            child: child ?? const SizedBox.shrink(),
          ),
    );

    if (picked == null) return;
    setState(() => isStart ? _startTime = picked : _endTime = picked);
  }

  void _toggleDay(int day) {
    setState(() {
      if (_workDays.contains(day)) {
        if (_workDays.length > 1) _workDays.remove(day);
      } else {
        _workDays.add(day);
      }
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _submitting = true);
    final payload = _payload();

    try {
      final repository = ref.read(scheduleRepositoryProvider);
      final schedule = widget.schedule;
      if (schedule == null) {
        await repository.create(payload);
      } else {
        await repository.update(schedule.id, payload);
      }
      ref.invalidate(schedulesProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Horaire enregistre.')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _delete() async {
    final schedule = widget.schedule;
    if (schedule == null || schedule.isDefault) return;

    setState(() => _submitting = true);
    try {
      await ref.read(scheduleRepositoryProvider).delete(schedule.id);
      ref.invalidate(schedulesProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Horaire supprime.')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  SchedulePayload _payload() {
    final breakMinutes = int.parse(_breakCtrl.text);
    final workDays = _workDays.toList()..sort();
    final restDays =
        <int>{1, 2, 3, 4, 5, 6, 7}.difference(_workDays).toList()..sort();
    final leaveDays = double.parse(_leaveDaysCtrl.text.replaceAll(',', '.'));

    return SchedulePayload(
      name: _nameCtrl.text,
      startTime: _formatTime(_startTime),
      endTime: _formatTime(_endTime),
      breakMinutes: breakMinutes,
      breakRules:
          breakMinutes > 0
              ? [
                ScheduleBreakRule(
                  label: 'Pause principale',
                  startTime: null,
                  endTime: null,
                  minutes: breakMinutes,
                  isPaid: false,
                ),
              ]
              : const [],
      workDays: workDays,
      restDays: restDays,
      leaveRules: [
        ScheduleLeaveRule(
          label: 'Conge annuel',
          type: 'annual',
          daysPerYear: leaveDays,
        ),
      ],
      assignmentNotes: _notesCtrl.text,
      lateToleranceMinutes: int.parse(_toleranceCtrl.text),
      overtimeThresholdDaily: double.parse(
        _dailyCtrl.text.replaceAll(',', '.'),
      ),
      overtimeThresholdWeekly: double.parse(
        _weeklyCtrl.text.replaceAll(',', '.'),
      ),
      isDefault: _isDefault,
    );
  }

  String? _positiveIntValidator(String? value) {
    final parsed = int.tryParse(value ?? '');
    if (parsed == null || parsed < 0) return 'Nombre invalide';
    return null;
  }

  String? _positiveDoubleValidator(String? value) {
    final parsed = double.tryParse((value ?? '').replaceAll(',', '.'));
    if (parsed == null || parsed < 0) return 'Nombre invalide';
    return null;
  }

  static TimeOfDay _parseTime(String value) {
    final parts = value.split(':');
    return TimeOfDay(
      hour: int.tryParse(parts.first) ?? 8,
      minute: parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0,
    );
  }

  static String _formatTime(TimeOfDay value) {
    return '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
  }
}

class _ScheduleTextField extends StatelessWidget {
  const _ScheduleTextField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboardType,
    this.validator,
  });

  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboardType;
  final String? Function(String?)? validator;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      validator: validator,
      style: AppTypography.body.copyWith(color: MobileSurface.text),
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: MobileSurface.secondary),
        filled: true,
        fillColor: MobileSurface.chip,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }
}

class _TimeButton extends StatelessWidget {
  const _TimeButton({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Ink(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: MobileSurface.chip,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: MobileSurface.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: AppTypography.caption.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              style: AppTypography.subtitle.copyWith(color: AppColors.rh),
            ),
          ],
        ),
      ),
    );
  }
}
