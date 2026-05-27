import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_core/models/project_task.dart';
import 'package:leopardo_manager/features/tasks/providers/task_provider.dart';
import 'package:leopardo_manager/features/team/providers/team_provider.dart';

class TaskListScreen extends ConsumerWidget {
  const TaskListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tasksAsync = ref.watch(todayManagerTasksProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Taches du jour',
        subtitle: 'Preparer, assigner et suivre le terrain',
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openCreateSheet(context, ref),
        icon: const Icon(Icons.add_task_rounded),
        label: const Text('Assigner'),
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await ref.refresh(todayManagerTasksProvider.future).then((_) {});
        },
        child: tasksAsync.when(
          loading: () => const MobileEmptyLoading(label: 'Chargement taches'),
          error:
              (error, _) => ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  MobileErrorPanel(
                    message: error.toString(),
                    onRetry: () => ref.invalidate(todayManagerTasksProvider),
                  ),
                ],
              ),
          data: (tasks) {
            if (tasks.isEmpty) {
              return ListView(
                padding: const EdgeInsets.fromLTRB(20, 80, 20, 120),
                children: const [
                  EmptyState(
                    icon: Icons.assignment_turned_in_outlined,
                    title: 'Aucune tache aujourd hui',
                    description:
                        'Assignez une tache recurrente ou ponctuelle a un collaborateur pour guider sa journee.',
                  ),
                ],
              );
            }

            return ListView.builder(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
              itemCount: tasks.length,
              itemBuilder: (_, index) => _TaskCard(task: tasks[index]),
            );
          },
        ),
      ),
    );
  }

  void _openCreateSheet(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => const _CreateTaskSheet(),
    );
  }
}

class _TaskCard extends StatelessWidget {
  const _TaskCard({required this.task});

  final Task task;

  @override
  Widget build(BuildContext context) {
    final color = _priorityColor(task.priority);
    final due =
        task.dueDate == null
            ? 'Aujourd hui'
            : DateFormat('d MMM', 'fr_FR').format(task.dueDate!);

    return MobileListCard(
      icon: task.isDone ? Icons.task_alt_rounded : Icons.radio_button_unchecked,
      iconColor: task.isDone ? AppColors.rh : color,
      title: task.title,
      subtitle: [
        due,
        _priorityLabel(task.priority),
        if (task.category?.isNotEmpty == true) task.category!,
        if (task.estimatedMinutes != null) '${task.estimatedMinutes} min',
      ].join(' - '),
      trailing: MobileStatusPill(
        label: task.isDone ? 'Terminee' : 'A faire',
        color: task.isDone ? AppColors.rh : color,
      ),
      footer:
          task.performanceScore == null
              ? null
              : Text(
                'Score performance ${task.performanceScore!.toStringAsFixed(0)}/100',
                style: AppTypography.caption.copyWith(color: AppColors.rh),
              ),
    );
  }

  static Color _priorityColor(String priority) => switch (priority) {
    'urgent' || 'high' => AppColors.warning,
    'low' => MobileSurface.secondary,
    _ => AppColors.info,
  };

  static String _priorityLabel(String priority) => switch (priority) {
    'urgent' => 'Urgent',
    'high' => 'Haute',
    'low' => 'Basse',
    _ => 'Normale',
  };
}

class _CreateTaskSheet extends ConsumerStatefulWidget {
  const _CreateTaskSheet();

  @override
  ConsumerState<_CreateTaskSheet> createState() => _CreateTaskSheetState();
}

class _CreateTaskSheetState extends ConsumerState<_CreateTaskSheet> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _estimated = TextEditingController(text: '60');
  String _priority = 'normal';
  String _templateKey = 'custom';
  int? _employeeId;
  bool _submitting = false;

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _estimated.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final employeesAsync = ref.watch(teamListProvider);
    final bottom = MediaQuery.of(context).viewInsets.bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Assigner une tache',
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Une tache claire apparaitra dans le pointage employe du jour.',
                style: AppTypography.caption.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 16),
              employeesAsync.when(
                data: _employeeSelector,
                loading:
                    () => const LinearProgressIndicator(
                      minHeight: 3,
                      color: AppColors.rh,
                    ),
                error:
                    (error, _) => TextButton.icon(
                      onPressed: () => ref.invalidate(teamListProvider),
                      icon: const Icon(Icons.refresh),
                      label: const Text('Recharger equipe'),
                    ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _templateKey,
                decoration: const InputDecoration(labelText: 'Modele metier'),
                items: const [
                  DropdownMenuItem(value: 'custom', child: Text('Libre')),
                  DropdownMenuItem(
                    value: 'agriculture_daily',
                    child: Text('Agriculture - tour quotidien'),
                  ),
                  DropdownMenuItem(
                    value: 'maintenance_check',
                    child: Text('Maintenance - controle'),
                  ),
                  DropdownMenuItem(
                    value: 'commerce_opening',
                    child: Text('Commerce - ouverture'),
                  ),
                  DropdownMenuItem(
                    value: 'logistics_round',
                    child: Text('Logistique - tournee'),
                  ),
                ],
                onChanged: _applyTemplate,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _title,
                decoration: const InputDecoration(labelText: 'Titre'),
                validator:
                    (value) =>
                        value == null || value.trim().isEmpty
                            ? 'Titre obligatoire'
                            : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _description,
                minLines: 2,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Consignes'),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _priority,
                      decoration: const InputDecoration(labelText: 'Priorite'),
                      items: const [
                        DropdownMenuItem(value: 'low', child: Text('Basse')),
                        DropdownMenuItem(
                          value: 'normal',
                          child: Text('Normale'),
                        ),
                        DropdownMenuItem(value: 'high', child: Text('Haute')),
                        DropdownMenuItem(
                          value: 'urgent',
                          child: Text('Urgente'),
                        ),
                      ],
                      onChanged:
                          (value) =>
                              setState(() => _priority = value ?? 'normal'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      controller: _estimated,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Duree prevue',
                        suffixText: 'min',
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              ElevatedButton.icon(
                onPressed: _submitting ? null : _submit,
                icon:
                    _submitting
                        ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                        : const Icon(Icons.send_rounded),
                label: const Text('Assigner aujourd hui'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _employeeSelector(List<Employee> employees) {
    final active = employees.where((employee) => employee.status == 'active');
    return DropdownButtonFormField<int>(
      initialValue: _employeeId,
      decoration: const InputDecoration(labelText: 'Collaborateur'),
      items:
          active
              .map(
                (employee) => DropdownMenuItem(
                  value: employee.id,
                  child: Text(employee.fullName),
                ),
              )
              .toList(),
      validator:
          (value) => value == null ? 'Selectionner un collaborateur' : null,
      onChanged: (value) => setState(() => _employeeId = value),
    );
  }

  void _applyTemplate(String? value) {
    final key = value ?? 'custom';
    setState(() {
      _templateKey = key;
      switch (key) {
        case 'agriculture_daily':
          _title.text = 'Tour de parcelle et verification arrosage';
          _description.text =
              'Verifier l etat des zones, signaler les anomalies et confirmer le passage.';
          _estimated.text = '90';
          break;
        case 'maintenance_check':
          _title.text = 'Controle maintenance preventif';
          _description.text =
              'Inspecter les equipements critiques et noter toute intervention necessaire.';
          _estimated.text = '60';
          break;
        case 'commerce_opening':
          _title.text = 'Preparation ouverture magasin';
          _description.text =
              'Verifier caisse, rayon prioritaire et proprete avant ouverture.';
          _estimated.text = '45';
          break;
        case 'logistics_round':
          _title.text = 'Tournee logistique planifiee';
          _description.text =
              'Confirmer chargement, livraison et retour des documents.';
          _estimated.text = '120';
          break;
      }
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      await ref
          .read(taskRepositoryProvider)
          .create(
            title: _title.text,
            description: _description.text,
            employeeId: _employeeId!,
            dueDate: DateTime.now(),
            priority: _priority,
            estimatedMinutes: int.tryParse(_estimated.text.trim()),
            category: _templateKey == 'custom' ? null : _templateKey,
            templateKey: _templateKey == 'custom' ? null : _templateKey,
          );
      ref.invalidate(todayManagerTasksProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tache assignee pour aujourd hui.')),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Echec : $e')));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}
