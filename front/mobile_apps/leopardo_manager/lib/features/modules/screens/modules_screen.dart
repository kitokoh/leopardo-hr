import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/leopardo_badge.dart';
import 'package:leopardo_manager/features/auth/providers/auth_provider.dart';
import 'package:leopardo_manager/features/modules/providers/modules_provider.dart';
import 'package:leopardo_manager/features/team/providers/team_provider.dart';
import 'package:leopardo_core/models/app_notification.dart';
import 'package:leopardo_core/models/evaluation.dart';
import 'package:leopardo_core/models/payroll_record.dart';
import 'package:leopardo_core/models/salary_advance.dart';

class ModulesScreen extends ConsumerStatefulWidget {
  const ModulesScreen({super.key});

  @override
  ConsumerState<ModulesScreen> createState() => _ModulesScreenState();
}

class _ModulesScreenState extends ConsumerState<ModulesScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final employee = ref.watch(authProvider).employee;
    final isManager = employee?.isManager == true;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Modules RH'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Evaluations'),
            Tab(text: 'Avances'),
            Tab(text: 'Paies'),
            Tab(text: 'Notifications'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _EvaluationsTab(isManager: isManager),
          _SalaryAdvancesTab(isManager: isManager),
          _PayrollsTab(isManager: isManager),
          const _NotificationsTab(),
        ],
      ),
    );
  }
}

class _EvaluationsTab extends ConsumerWidget {
  const _EvaluationsTab({required this.isManager});

  final bool isManager;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncValue = ref.watch(evaluationsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.refresh(evaluationsProvider.future),
      child: asyncValue.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error:
            (error, _) => ListView(
              children: [
                const SizedBox(height: 80),
                Center(child: Text('Erreur : $error')),
              ],
            ),
        data: (items) {
          if (items.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: [
                if (isManager) ...[
                  FilledButton.icon(
                    onPressed: () => _openCreateEvaluationSheet(context),
                    icon: const Icon(Icons.add_chart),
                    label: const Text('Nouvelle evaluation'),
                  ),
                  const SizedBox(height: 24),
                ],
                const EmptyState(
                  icon: Icons.insights_outlined,
                  title: 'Aucune evaluation',
                  description:
                      'Les evaluations manager/employe disponibles sur l API apparaitront ici.',
                ),
              ],
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(20),
            itemCount: items.length + (isManager ? 1 : 0),
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, index) {
              if (isManager && index == 0) {
                return FilledButton.icon(
                  onPressed: () => _openCreateEvaluationSheet(context),
                  icon: const Icon(Icons.add_chart),
                  label: const Text('Nouvelle evaluation'),
                );
              }

              final item = items[isManager ? index - 1 : index];
              return _EvaluationCard(
                item: item,
                isManager: isManager,
                onAction: () => _showEvaluationActions(context, ref, item),
              );
            },
          );
        },
      ),
    );
  }

  void _openCreateEvaluationSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _CreateEvaluationSheet(),
    );
  }

  void _showEvaluationActions(
    BuildContext context,
    WidgetRef ref,
    Evaluation evaluation,
  ) {
    showModalBottomSheet(
      context: context,
      builder:
          (_) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (isManager && evaluation.status == 'draft')
                    ListTile(
                      leading: const Icon(Icons.send_outlined),
                      title: const Text('Soumettre'),
                      onTap: () async {
                        Navigator.of(context).pop();
                        await _runAction(
                          context,
                          ref,
                          () => ref
                              .read(modulesRepositoryProvider)
                              .submitEvaluation(evaluation.id),
                          successMessage: 'Evaluation soumise.',
                        );
                      },
                    ),
                  if (isManager && evaluation.status == 'draft')
                    ListTile(
                      leading: const Icon(Icons.delete_outline),
                      title: const Text('Supprimer'),
                      onTap: () async {
                        Navigator.of(context).pop();
                        await _runAction(
                          context,
                          ref,
                          () => ref
                              .read(modulesRepositoryProvider)
                              .deleteEvaluation(evaluation.id),
                          successMessage: 'Evaluation supprimee.',
                        );
                      },
                    ),
                  if (!isManager && evaluation.status == 'submitted')
                    ListTile(
                      leading: const Icon(Icons.verified_outlined),
                      title: const Text('Accuser reception'),
                      onTap: () async {
                        Navigator.of(context).pop();
                        await _runAction(
                          context,
                          ref,
                          () => ref
                              .read(modulesRepositoryProvider)
                              .acknowledgeEvaluation(evaluation.id),
                          successMessage: 'Evaluation accusee reception.',
                        );
                      },
                    ),
                ],
              ),
            ),
          ),
    );
  }

  Future<void> _runAction(
    BuildContext context,
    WidgetRef ref,
    Future<void> Function() action, {
    required String successMessage,
  }) async {
    try {
      await action();
      ref.invalidate(evaluationsProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(successMessage)));
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Echec : $error')));
      }
    }
  }
}

class _EvaluationCard extends StatelessWidget {
  const _EvaluationCard({
    required this.item,
    required this.isManager,
    required this.onAction,
  });

  final Evaluation item;
  final bool isManager;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    final person =
        isManager
            ? item.employee?.fullName ?? 'Collaborateur'
            : item.evaluator?.fullName ?? 'Manager';
    final score = item.score != null ? item.score!.toStringAsFixed(1) : '-';

    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        title: Text(item.period),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(person),
              Text('Score : $score / 5'),
              if ((item.overallComment ?? '').isNotEmpty)
                Text(
                  item.overallComment!,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
            ],
          ),
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            LeopardoBadge.forStatus(item.status, _label(item.status)),
            const SizedBox(height: 8),
            IconButton(
              onPressed: onAction,
              icon: const Icon(Icons.more_horiz),
              tooltip: 'Actions',
            ),
          ],
        ),
      ),
    );
  }

  String _label(String status) => switch (status) {
    'draft' => 'Brouillon',
    'submitted' => 'Soumise',
    'acknowledged' => 'Lue',
    _ => status,
  };
}

class _SalaryAdvancesTab extends ConsumerWidget {
  const _SalaryAdvancesTab({required this.isManager});

  final bool isManager;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncValue = ref.watch(salaryAdvancesProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.refresh(salaryAdvancesProvider.future),
      child: asyncValue.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error:
            (error, _) => ListView(
              children: [
                const SizedBox(height: 80),
                Center(child: Text('Erreur : $error')),
              ],
            ),
        data: (items) {
          return ListView.separated(
            padding: const EdgeInsets.all(20),
            itemCount: items.length + 1,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, index) {
              if (index == 0) {
                return FilledButton.icon(
                  onPressed: () => _openRequestSheet(context),
                  icon: const Icon(Icons.request_quote_outlined),
                  label: const Text('Demander une avance'),
                );
              }

              if (items.isEmpty) {
                return const EmptyState(
                  icon: Icons.payments_outlined,
                  title: 'Aucune avance',
                  description:
                      'Les demandes d avances sur salaire remonteront ici.',
                );
              }

              final item = items[index - 1];
              return Card(
                child: ListTile(
                  contentPadding: const EdgeInsets.all(16),
                  title: Text('${item.amount?.toStringAsFixed(0) ?? '-'} FCFA'),
                  subtitle: Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(item.reason ?? 'Sans motif'),
                        Text('Mensualites : ${item.repaymentMonths ?? '-'}'),
                        if ((item.decisionComment ?? '').isNotEmpty)
                          Text(item.decisionComment!),
                      ],
                    ),
                  ),
                  trailing: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      LeopardoBadge.forStatus(
                        item.status,
                        _advanceLabel(item.status),
                      ),
                      const SizedBox(height: 8),
                      IconButton(
                        onPressed:
                            () => _showAdvanceActions(context, ref, item),
                        icon: const Icon(Icons.more_horiz),
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  void _openRequestSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _CreateAdvanceSheet(),
    );
  }

  void _showAdvanceActions(
    BuildContext context,
    WidgetRef ref,
    SalaryAdvance item,
  ) {
    showModalBottomSheet(
      context: context,
      builder:
          (_) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (isManager && item.status == 'pending')
                    ListTile(
                      leading: const Icon(Icons.check_circle_outline),
                      title: const Text('Approuver'),
                      onTap: () async {
                        Navigator.of(context).pop();
                        await _decisionDialog(
                          context,
                          title: 'Approuver cette avance',
                          onSubmit:
                              (comment) => ref
                                  .read(modulesRepositoryProvider)
                                  .approveSalaryAdvance(
                                    item.id,
                                    decisionComment: comment,
                                  ),
                          ref: ref,
                        );
                      },
                    ),
                  if (isManager && item.status == 'pending')
                    ListTile(
                      leading: const Icon(Icons.highlight_off),
                      title: const Text('Rejeter'),
                      onTap: () async {
                        Navigator.of(context).pop();
                        await _decisionDialog(
                          context,
                          title: 'Rejeter cette avance',
                          onSubmit:
                              (comment) => ref
                                  .read(modulesRepositoryProvider)
                                  .rejectSalaryAdvance(
                                    item.id,
                                    decisionComment: comment,
                                  ),
                          ref: ref,
                        );
                      },
                    ),
                  if (!isManager && item.status == 'pending')
                    ListTile(
                      leading: const Icon(Icons.cancel_outlined),
                      title: const Text('Annuler ma demande'),
                      onTap: () async {
                        Navigator.of(context).pop();
                        try {
                          await ref
                              .read(modulesRepositoryProvider)
                              .cancelSalaryAdvance(item.id);
                          ref.invalidate(salaryAdvancesProvider);
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Demande annulee.')),
                            );
                          }
                        } catch (error) {
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('Echec : $error')),
                            );
                          }
                        }
                      },
                    ),
                ],
              ),
            ),
          ),
    );
  }

  Future<void> _decisionDialog(
    BuildContext context, {
    required String title,
    required Future<SalaryAdvance> Function(String comment) onSubmit,
    required WidgetRef ref,
  }) async {
    final controller = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (_) => AlertDialog(
            title: Text(title),
            content: TextField(
              controller: controller,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Commentaire'),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Annuler'),
              ),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(true),
                child: const Text('Valider'),
              ),
            ],
          ),
    );
    if (confirmed != true) return;

    try {
      await onSubmit(controller.text);
      ref.invalidate(salaryAdvancesProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Decision enregistree.')));
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Echec : $error')));
      }
    }
  }

  String _advanceLabel(String status) => switch (status) {
    'pending' => 'En attente',
    'approved' => 'Approuvee',
    'rejected' => 'Rejetee',
    'cancelled' => 'Annulee',
    _ => status,
  };
}

class _PayrollsTab extends ConsumerWidget {
  const _PayrollsTab({required this.isManager});

  final bool isManager;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncValue = ref.watch(payrollsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.refresh(payrollsProvider.future),
      child: asyncValue.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error:
            (error, _) => ListView(
              children: [
                const SizedBox(height: 80),
                Center(child: Text('Erreur : $error')),
              ],
            ),
        data: (items) {
          return ListView.separated(
            padding: const EdgeInsets.all(20),
            itemCount: items.length + (isManager ? 1 : 0),
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, index) {
              if (isManager && index == 0) {
                return FilledButton.icon(
                  onPressed: () => _openCreatePayrollSheet(context),
                  icon: const Icon(Icons.receipt_long_outlined),
                  label: const Text('Creer un bulletin'),
                );
              }

              if (items.isEmpty) {
                return const EmptyState(
                  icon: Icons.request_page_outlined,
                  title: 'Aucun bulletin',
                  description:
                      'Les bulletins exposes par l API seront visibles ici.',
                );
              }

              final item = items[isManager ? index - 1 : index];
              return Card(
                child: ListTile(
                  contentPadding: const EdgeInsets.all(16),
                  title: Text(
                    '${_monthName(item.periodMonth)} ${item.periodYear}',
                  ),
                  subtitle: Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(item.employee?.fullName ?? 'Mon bulletin'),
                        Text(
                          'Brut : ${item.grossSalary?.toStringAsFixed(0) ?? '-'}',
                        ),
                        Text(
                          'Net : ${item.netSalary?.toStringAsFixed(0) ?? '-'}',
                        ),
                      ],
                    ),
                  ),
                  trailing: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      LeopardoBadge.forStatus(
                        item.status,
                        _payrollLabel(item.status),
                      ),
                      const SizedBox(height: 8),
                      IconButton(
                        onPressed:
                            () => _showPayrollActions(context, ref, item),
                        icon: const Icon(Icons.more_horiz),
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  void _openCreatePayrollSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _CreatePayrollSheet(),
    );
  }

  void _showPayrollActions(
    BuildContext context,
    WidgetRef ref,
    PayrollRecord item,
  ) {
    if (!isManager) return;

    showModalBottomSheet(
      context: context,
      builder:
          (_) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (item.status == 'draft')
                    ListTile(
                      leading: const Icon(Icons.verified_outlined),
                      title: const Text('Valider'),
                      onTap: () async {
                        Navigator.of(context).pop();
                        try {
                          await ref
                              .read(modulesRepositoryProvider)
                              .validatePayroll(item.id);
                          ref.invalidate(payrollsProvider);
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Bulletin valide.')),
                            );
                          }
                        } catch (error) {
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('Echec : $error')),
                            );
                          }
                        }
                      },
                    ),
                  ListTile(
                    leading: const Icon(Icons.delete_outline),
                    title: const Text('Supprimer'),
                    onTap: () async {
                      Navigator.of(context).pop();
                      try {
                        await ref
                            .read(modulesRepositoryProvider)
                            .deletePayroll(item.id);
                        ref.invalidate(payrollsProvider);
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Bulletin supprime.')),
                          );
                        }
                      } catch (error) {
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text('Echec : $error')),
                          );
                        }
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
    );
  }

  String _monthName(int month) {
    if (month < 1 || month > 12) return 'Mois $month';
    return DateFormat.MMMM('fr_FR').format(DateTime(2026, month));
  }

  String _payrollLabel(String status) => switch (status) {
    'draft' => 'Brouillon',
    'validated' => 'Valide',
    'paid' => 'Paye',
    _ => status,
  };
}

class _NotificationsTab extends ConsumerWidget {
  const _NotificationsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncValue = ref.watch(notificationsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.refresh(notificationsProvider.future),
      child: asyncValue.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error:
            (error, _) => ListView(
              children: [
                const SizedBox(height: 80),
                Center(child: Text('Erreur : $error')),
              ],
            ),
        data: (items) {
          return ListView.separated(
            padding: const EdgeInsets.all(20),
            itemCount: items.length + 1,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, index) {
              if (index == 0) {
                return OutlinedButton.icon(
                  onPressed: () async {
                    try {
                      await ref
                          .read(modulesRepositoryProvider)
                          .markAllNotificationsRead();
                      ref.invalidate(notificationsProvider);
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text(
                              'Toutes les notifications sont lues.',
                            ),
                          ),
                        );
                      }
                    } catch (error) {
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Echec : $error')),
                        );
                      }
                    }
                  },
                  icon: const Icon(Icons.done_all),
                  label: const Text('Tout marquer comme lu'),
                );
              }

              if (items.isEmpty) {
                return const EmptyState(
                  icon: Icons.notifications_none,
                  title: 'Aucune notification',
                  description:
                      'Les notifications RH exposees par l API remonteront ici.',
                );
              }

              final item = items[index - 1];
              return _NotificationCard(
                item: item,
                onTap: () => _showNotificationActions(context, ref, item),
              );
            },
          );
        },
      ),
    );
  }

  void _showNotificationActions(
    BuildContext context,
    WidgetRef ref,
    AppNotification item,
  ) {
    showModalBottomSheet(
      context: context,
      builder:
          (_) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (!item.isRead)
                    ListTile(
                      leading: const Icon(Icons.mark_email_read_outlined),
                      title: const Text('Marquer comme lue'),
                      onTap: () async {
                        Navigator.of(context).pop();
                        try {
                          await ref
                              .read(modulesRepositoryProvider)
                              .markNotificationRead(item.id);
                          ref.invalidate(notificationsProvider);
                        } catch (error) {
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('Echec : $error')),
                            );
                          }
                        }
                      },
                    ),
                  ListTile(
                    leading: const Icon(Icons.delete_outline),
                    title: const Text('Supprimer'),
                    onTap: () async {
                      Navigator.of(context).pop();
                      try {
                        await ref
                            .read(modulesRepositoryProvider)
                            .deleteNotification(item.id);
                        ref.invalidate(notificationsProvider);
                      } catch (error) {
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text('Echec : $error')),
                          );
                        }
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.item, required this.onTap});

  final AppNotification item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final unreadTint = AppColors.tint(
      context,
      AppColors.info,
      lightAlpha: 0.10,
      darkAlpha: 0.22,
    );
    final muted = AppColors.textSecondaryFor(context);

    return Card(
      color: item.isRead ? null : unreadTint,
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        onTap: onTap,
        title: Text(item.title),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Text(item.body),
        ),
        trailing: Icon(
          item.isRead ? Icons.drafts_outlined : Icons.markunread_outlined,
          color: item.isRead ? muted : AppColors.info,
        ),
      ),
    );
  }
}

class _CreateEvaluationSheet extends ConsumerStatefulWidget {
  const _CreateEvaluationSheet();

  @override
  ConsumerState<_CreateEvaluationSheet> createState() =>
      _CreateEvaluationSheetState();
}

class _CreateEvaluationSheetState
    extends ConsumerState<_CreateEvaluationSheet> {
  final _formKey = GlobalKey<FormState>();
  final _periodController = TextEditingController(
    text: DateFormat('yyyy-MM').format(DateTime.now()),
  );
  final _scoreController = TextEditingController();
  final _strengthsController = TextEditingController();
  final _improvementsController = TextEditingController();
  final _commentController = TextEditingController();
  int? _employeeId;
  bool _submitting = false;

  @override
  void dispose() {
    _periodController.dispose();
    _scoreController.dispose();
    _strengthsController.dispose();
    _improvementsController.dispose();
    _commentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final team = ref.watch(teamListProvider);
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
                'Nouvelle evaluation',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              team.when(
                loading:
                    () => const Padding(
                      padding: EdgeInsets.symmetric(vertical: 20),
                      child: Center(child: CircularProgressIndicator()),
                    ),
                error: (error, _) => Text('Equipe indisponible : $error'),
                data:
                    (employees) => DropdownButtonFormField<int>(
                      initialValue: _employeeId,
                      decoration: const InputDecoration(
                        labelText: 'Collaborateur',
                      ),
                      items:
                          employees
                              .map(
                                (employee) => DropdownMenuItem<int>(
                                  value: employee.id,
                                  child: Text(employee.fullName),
                                ),
                              )
                              .toList(),
                      validator:
                          (value) =>
                              value == null
                                  ? 'Choisissez un collaborateur'
                                  : null,
                      onChanged: (value) => setState(() => _employeeId = value),
                    ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _periodController,
                decoration: const InputDecoration(labelText: 'Periode'),
                validator:
                    (value) =>
                        (value == null || value.trim().isEmpty)
                            ? 'Periode requise'
                            : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _scoreController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(labelText: 'Score / 5'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _strengthsController,
                decoration: const InputDecoration(labelText: 'Forces'),
                maxLines: 2,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _improvementsController,
                decoration: const InputDecoration(
                  labelText: 'Axes d amelioration',
                ),
                maxLines: 2,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _commentController,
                decoration: const InputDecoration(
                  labelText: 'Commentaire global',
                ),
                maxLines: 3,
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: Text(_submitting ? 'Creation...' : 'Creer'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _employeeId == null) return;

    setState(() => _submitting = true);
    try {
      await ref
          .read(modulesRepositoryProvider)
          .createEvaluation(
            employeeId: _employeeId!,
            period: _periodController.text,
            score: double.tryParse(_scoreController.text.replaceAll(',', '.')),
            strengths: _strengthsController.text,
            improvements: _improvementsController.text,
            overallComment: _commentController.text,
          );
      ref.invalidate(evaluationsProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Evaluation creee.')));
    } catch (error) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    }
  }
}

class _CreateAdvanceSheet extends ConsumerStatefulWidget {
  const _CreateAdvanceSheet();

  @override
  ConsumerState<_CreateAdvanceSheet> createState() =>
      _CreateAdvanceSheetState();
}

class _CreateAdvanceSheetState extends ConsumerState<_CreateAdvanceSheet> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _reasonController = TextEditingController();
  final _monthsController = TextEditingController(text: '3');
  bool _submitting = false;

  @override
  void dispose() {
    _amountController.dispose();
    _reasonController.dispose();
    _monthsController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
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
                'Demande d avance',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(labelText: 'Montant'),
                validator:
                    (value) =>
                        (double.tryParse((value ?? '').replaceAll(',', '.')) ==
                                null)
                            ? 'Montant invalide'
                            : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _monthsController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Nombre de mensualites',
                ),
                validator:
                    (value) =>
                        (int.tryParse(value ?? '') == null)
                            ? 'Valeur invalide'
                            : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _reasonController,
                decoration: const InputDecoration(labelText: 'Motif'),
                maxLines: 3,
                validator:
                    (value) =>
                        (value == null || value.trim().isEmpty)
                            ? 'Motif requis'
                            : null,
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: Text(_submitting ? 'Envoi...' : 'Envoyer'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _submitting = true);
    try {
      await ref
          .read(modulesRepositoryProvider)
          .createSalaryAdvance(
            amount: double.parse(_amountController.text.replaceAll(',', '.')),
            reason: _reasonController.text,
            repaymentMonths: int.parse(_monthsController.text),
          );
      ref.invalidate(salaryAdvancesProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Demande envoyee.')));
    } catch (error) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    }
  }
}

class _CreatePayrollSheet extends ConsumerStatefulWidget {
  const _CreatePayrollSheet();

  @override
  ConsumerState<_CreatePayrollSheet> createState() =>
      _CreatePayrollSheetState();
}

class _CreatePayrollSheetState extends ConsumerState<_CreatePayrollSheet> {
  final _formKey = GlobalKey<FormState>();
  final _grossController = TextEditingController();
  final _bonusesController = TextEditingController(text: '0');
  final _deductionsController = TextEditingController(text: '0');
  final _overtimeController = TextEditingController(text: '0');
  final _cotisationsController = TextEditingController(text: '0');
  final _irController = TextEditingController(text: '0');
  final _advanceController = TextEditingController(text: '0');
  final _absenceController = TextEditingController(text: '0');
  final _penaltyController = TextEditingController(text: '0');
  int? _employeeId;
  int _month = DateTime.now().month;
  int _year = DateTime.now().year;
  bool _submitting = false;

  @override
  void dispose() {
    _grossController.dispose();
    _bonusesController.dispose();
    _deductionsController.dispose();
    _overtimeController.dispose();
    _cotisationsController.dispose();
    _irController.dispose();
    _advanceController.dispose();
    _absenceController.dispose();
    _penaltyController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final team = ref.watch(teamListProvider);
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
                'Creer un bulletin',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              team.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => Text('Equipe indisponible : $error'),
                data:
                    (employees) => DropdownButtonFormField<int>(
                      initialValue: _employeeId,
                      decoration: const InputDecoration(
                        labelText: 'Collaborateur',
                      ),
                      items:
                          employees
                              .map(
                                (employee) => DropdownMenuItem<int>(
                                  value: employee.id,
                                  child: Text(employee.fullName),
                                ),
                              )
                              .toList(),
                      validator:
                          (value) =>
                              value == null
                                  ? 'Choisissez un collaborateur'
                                  : null,
                      onChanged: (value) => setState(() => _employeeId = value),
                    ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<int>(
                      initialValue: _month,
                      decoration: const InputDecoration(labelText: 'Mois'),
                      items: List.generate(
                        12,
                        (index) => DropdownMenuItem<int>(
                          value: index + 1,
                          child: Text('${index + 1}'),
                        ),
                      ),
                      onChanged:
                          (value) => setState(() => _month = value ?? _month),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: DropdownButtonFormField<int>(
                      initialValue: _year,
                      decoration: const InputDecoration(labelText: 'Annee'),
                      items: List.generate(5, (index) {
                        final year = DateTime.now().year - 2 + index;
                        return DropdownMenuItem<int>(
                          value: year,
                          child: Text('$year'),
                        );
                      }),
                      onChanged:
                          (value) => setState(() => _year = value ?? _year),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _moneyField(_grossController, 'Salaire brut', required: true),
              const SizedBox(height: 12),
              _moneyField(_bonusesController, 'Bonus'),
              const SizedBox(height: 12),
              _moneyField(_deductionsController, 'Retenues'),
              const SizedBox(height: 12),
              _moneyField(_overtimeController, 'Heures supplementaires'),
              const SizedBox(height: 12),
              _moneyField(_cotisationsController, 'Cotisations'),
              const SizedBox(height: 12),
              _moneyField(_irController, 'IR'),
              const SizedBox(height: 12),
              _moneyField(_advanceController, 'Deduction avance'),
              const SizedBox(height: 12),
              _moneyField(_absenceController, 'Deduction absence'),
              const SizedBox(height: 12),
              _moneyField(_penaltyController, 'Penalite'),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: Text(_submitting ? 'Creation...' : 'Creer'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _moneyField(
    TextEditingController controller,
    String label, {
    bool required = false,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(labelText: label),
      validator: (value) {
        if (!required && (value == null || value.trim().isEmpty)) return null;
        return double.tryParse((value ?? '').replaceAll(',', '.')) == null
            ? 'Valeur invalide'
            : null;
      },
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _employeeId == null) return;

    double parse(TextEditingController controller) =>
        double.parse(controller.text.replaceAll(',', '.'));

    setState(() => _submitting = true);
    try {
      await ref
          .read(modulesRepositoryProvider)
          .createPayroll(
            employeeId: _employeeId!,
            periodMonth: _month,
            periodYear: _year,
            grossSalary: parse(_grossController),
            bonuses: parse(_bonusesController),
            deductions: parse(_deductionsController),
            overtimeAmount: parse(_overtimeController),
            cotisations: parse(_cotisationsController),
            irAmount: parse(_irController),
            advanceDeduction: parse(_advanceController),
            absenceDeduction: parse(_absenceController),
            penaltyDeduction: parse(_penaltyController),
          );
      ref.invalidate(payrollsProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Bulletin cree.')));
    } catch (error) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Echec : $error')));
    }
  }
}
