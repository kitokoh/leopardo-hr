import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/schedules/data/schedule_repository.dart';
import 'package:leopardo_manager/features/schedules/providers/schedule_provider.dart';

class ScheduleListScreen extends ConsumerWidget {
  const ScheduleListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final schedulesAsync = ref.watch(schedulesProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Horaires',
        subtitle: 'Regles, pauses et heures supp',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showScheduleSheet(context, ref),
        icon: const Icon(Icons.add_alarm_outlined),
        label: const Text('Nouvel horaire'),
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
                    title: 'Aucun horaire',
                    description:
                        'Creez le premier horaire pour cadrer les presences, pauses et heures supplementaires.',
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
                      '${schedule.startTime} - ${schedule.endTime} · pause ${schedule.breakMinutes} min',
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
                  footer: _ScheduleFooter(schedule: schedule),
                );
              },
            );
          },
          loading:
              () => const MobileEmptyLoading(label: 'Chargement des horaires'),
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
      ],
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
                editing ? 'Modifier horaire' : 'Nouvel horaire',
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Ces regles servent au pointage, aux pauses et aux heures supplementaires.',
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
              const SizedBox(height: 16),
              MobilePrimaryAction(
                icon: editing ? Icons.save_outlined : Icons.add_alarm_outlined,
                label: editing ? 'Enregistrer' : 'Creer horaire',
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
    return SchedulePayload(
      name: _nameCtrl.text,
      startTime: _formatTime(_startTime),
      endTime: _formatTime(_endTime),
      breakMinutes: int.parse(_breakCtrl.text),
      workDays: _workDays.toList()..sort(),
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
