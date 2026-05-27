import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_manager/features/auth/providers/auth_provider.dart';
import 'package:leopardo_manager/features/schedules/data/schedule_repository.dart';
import 'package:leopardo_manager/features/schedules/providers/schedule_provider.dart';
import 'package:leopardo_manager/features/team/data/employee_repository.dart';
import 'package:leopardo_manager/features/team/providers/team_provider.dart';
import 'package:leopardo_core/models/employee.dart';

/// Ecran "Equipe" — reserve aux managers (principal / RH).
/// Permet de lister, creer, archiver un employe et de gerer les invitations.
class TeamScreen extends ConsumerStatefulWidget {
  const TeamScreen({super.key});

  @override
  ConsumerState<TeamScreen> createState() => _TeamScreenState();
}

class _TeamScreenState extends ConsumerState<TeamScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final employee = ref.watch(authProvider).employee;
    if (employee == null || !employee.canManageTeam) {
      return Scaffold(
        backgroundColor: MobileSurface.background,
        appBar: MobileTopBar(
          title: 'Equipe',
          subtitle: 'Acces manager/RH requis',
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
            tooltip: 'Retour',
            onPressed: () => context.pop(),
          ),
        ),
        body: const Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'Seuls les managers principaux et RH peuvent gerer l equipe depuis le mobile.',
              textAlign: TextAlign.center,
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Equipe',
        subtitle: 'Collaborateurs et invitations',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.qr_code_2_rounded),
            tooltip: 'QR entreprise',
            onPressed: () => _openCompanyQrSheet(context),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
            child: Container(
              padding: const EdgeInsets.all(4),
              decoration: MobileSurface.cardDecoration(
                color: MobileSurface.chip,
                radius: 14,
              ),
              child: TabBar(
                controller: _tabController,
                tabs: const [Tab(text: 'Employes'), Tab(text: 'Invitations')],
              ),
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: const [_EmployeesTab(), _InvitationsTab()],
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openAddEmployeeActions(context),
        icon: const Icon(Icons.person_add),
        label: const Text('Ajouter'),
      ),
    );
  }

  void _openAddEmployeeActions(BuildContext context) {
    showModalBottomSheet(
      context: context,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder:
          (_) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Ajouter un collaborateur',
                    style: AppTypography.subtitle.copyWith(
                      color: MobileSurface.text,
                    ),
                  ),
                  const SizedBox(height: 12),
                  ListTile(
                    leading: const Icon(Icons.edit_note_rounded),
                    title: const Text('Formulaire classique'),
                    subtitle: const Text('Saisie manuelle complete'),
                    onTap: () {
                      Navigator.of(context).pop();
                      _openCreateEmployeeSheet(context);
                    },
                  ),
                  ListTile(
                    leading: const Icon(Icons.qr_code_scanner_rounded),
                    title: const Text('Depuis QR employe'),
                    subtitle: const Text('Coller le code fourni'),
                    onTap: () {
                      Navigator.of(context).pop();
                      _openEmployeeQrSheet(context);
                    },
                  ),
                ],
              ),
            ),
          ),
    );
  }

  void _openCreateEmployeeSheet(
    BuildContext context, {
    EmployeeQrPrefill? prefill,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => _CreateEmployeeForm(prefill: prefill),
    );
  }

  void _openEmployeeQrSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder:
          (_) => _EmployeeQrImportSheet(
            onPrefillReady:
                (prefill) =>
                    _openCreateEmployeeSheet(context, prefill: prefill),
          ),
    );
  }

  void _openCompanyQrSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => const _CompanyQrSheet(),
    );
  }
}

class _EmployeesTab extends ConsumerWidget {
  const _EmployeesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(teamListProvider);

    return RefreshIndicator(
      onRefresh: () async {
        await ref.refresh(teamListProvider.future).then((_) {});
      },
      child: async.when(
        loading:
            () => const MobileEmptyLoading(label: 'Chargement de l equipe'),
        error:
            (err, _) => ListView(
              padding: const EdgeInsets.all(20),
              children: [
                MobileErrorPanel(
                  message: err.toString(),
                  onRetry: () => ref.invalidate(teamListProvider),
                ),
              ],
            ),
        data: (employees) {
          if (employees.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                EmptyState(
                  icon: Icons.group_add,
                  title: 'Aucun collaborateur',
                  description:
                      'Commencez par ajouter votre equipe avec le bouton ci-dessous.',
                ),
              ],
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
            itemCount: employees.length,
            itemBuilder: (_, index) {
              final e = employees[index];
              final subtitle = [
                e.email,
                _roleLabel(e),
                if (_employmentLine(e) != null) _employmentLine(e)!,
              ].join(' - ');

              return MobileListCard(
                icon: Icons.person_outline_rounded,
                iconColor: _statusColor(e.status),
                title: '${_initials(e)}  ${e.fullName}',
                subtitle: subtitle,
                trailing: MobileStatusPill(
                  label: _statusLabel(e.status),
                  color: _statusColor(e.status),
                ),
                onTap: () => _showActions(context, ref, e),
              );
            },
          );
        },
      ),
    );
  }

  String _initials(Employee e) {
    final parts = [
      e.firstName,
      e.lastName,
    ].where((p) => p.isNotEmpty).map((p) => p.substring(0, 1).toUpperCase());
    return parts.join();
  }

  String _roleLabel(Employee e) {
    if (e.role == 'manager') {
      return 'Manager ${e.managerRole ?? ''}'.trim();
    }
    return 'Employe';
  }

  String? _employmentLine(Employee e) {
    final parts = <String>[];
    if (e.hireDate != null) {
      final d = e.hireDate!;
      parts.add(
        'Debut ${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}',
      );
    }
    final currency = e.currency ?? 'DZD';
    if (e.salaryType == 'hourly' && e.hourlyRate != null) {
      parts.add('${e.hourlyRate!.toStringAsFixed(0)} $currency/h');
    } else if (e.salaryBase != null && e.salaryBase! > 0) {
      parts.add('${e.salaryBase!.toStringAsFixed(0)} $currency');
    }
    if (e.scheduleName?.trim().isNotEmpty == true) {
      parts.add('Horaire ${e.scheduleName}');
    }
    return parts.isEmpty ? null : parts.join(' · ');
  }

  String _statusLabel(String status) => switch (status) {
    'active' => 'Actif',
    'archived' => 'Archive',
    'blocked' => 'Bloque',
    'suspended' => 'Suspendu',
    _ => status,
  };

  Color _statusColor(String status) => switch (status) {
    'active' => AppColors.rh,
    'suspended' => AppColors.warning,
    'blocked' || 'archived' => AppColors.danger,
    _ => MobileSurface.disabled,
  };

  void _showActions(BuildContext context, WidgetRef ref, Employee employee) {
    showModalBottomSheet(
      context: context,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder:
          (_) => Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  employee.fullName,
                  style: AppTypography.subtitle.copyWith(
                    color: MobileSurface.text,
                  ),
                ),
                Text(
                  employee.email,
                  style: const TextStyle(color: MobileSurface.secondary),
                ),
                const SizedBox(height: 16),
                ListTile(
                  leading: const Icon(Icons.badge_outlined),
                  title: const Text('Voir la fiche'),
                  subtitle: const Text('Coordonnees, poste, salaire, horaire'),
                  onTap: () {
                    Navigator.of(context).pop();
                    _openProfileSheet(context, employee);
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.edit_note_rounded),
                  title: const Text('Modifier la fiche'),
                  subtitle: const Text(
                    'Mettre a jour les champs RH essentiels',
                  ),
                  onTap: () {
                    Navigator.of(context).pop();
                    _openEditEmployeeSheet(context, employee);
                  },
                ),
                if (employee.status != 'archived')
                  ListTile(
                    leading: const Icon(Icons.archive_outlined),
                    title: const Text('Archiver'),
                    onTap: () async {
                      Navigator.of(context).pop();
                      await _archive(context, ref, employee);
                    },
                  ),
              ],
            ),
          ),
    );
  }

  void _openProfileSheet(BuildContext context, Employee employee) {
    showModalBottomSheet(
      context: context,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => _EmployeeProfileSheet(employee: employee),
    );
  }

  void _openEditEmployeeSheet(BuildContext context, Employee employee) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => _EditEmployeeForm(employee: employee),
    );
  }

  Future<void> _archive(
    BuildContext context,
    WidgetRef ref,
    Employee employee,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (_) => AlertDialog(
            title: const Text('Archiver cet employe ?'),
            content: Text(
              '${employee.fullName} n aura plus acces a l application.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Annuler'),
              ),
              TextButton(
                onPressed: () => Navigator.of(context).pop(true),
                child: const Text('Archiver'),
              ),
            ],
          ),
    );
    if (confirmed != true) return;
    try {
      await ref.read(employeeRepositoryProvider).archive(employee.id);
      ref.invalidate(teamListProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Employe archive.')));
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Echec : $e')));
      }
    }
  }
}

class _InvitationsTab extends ConsumerWidget {
  const _InvitationsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(invitationsListProvider);

    return RefreshIndicator(
      onRefresh: () async {
        await ref.refresh(invitationsListProvider.future).then((_) {});
      },
      child: async.when(
        loading:
            () => const MobileEmptyLoading(label: 'Chargement des invitations'),
        error:
            (err, _) => ListView(
              padding: const EdgeInsets.all(20),
              children: [
                MobileErrorPanel(
                  message: err.toString(),
                  onRetry: () => ref.invalidate(invitationsListProvider),
                ),
              ],
            ),
        data: (invitations) {
          if (invitations.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                EmptyState(
                  icon: Icons.mark_email_read_outlined,
                  title: 'Aucune invitation en cours',
                  description:
                      'Les invitations envoyees a vos futurs collaborateurs s afficheront ici.',
                ),
              ],
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
            itemCount: invitations.length,
            itemBuilder: (_, index) {
              final inv = invitations[index];
              final color = _invitationColor(inv.status);
              return MobileListCard(
                icon: Icons.mail_outline_rounded,
                iconColor: color,
                title: inv.email,
                subtitle:
                    inv.sentAt == null
                        ? 'Invitation ${_invitationLabel(inv.status)}'
                        : 'Dernier envoi ${inv.sentAt!.day.toString().padLeft(2, '0')}/${inv.sentAt!.month.toString().padLeft(2, '0')}',
                trailing: MobileStatusPill(
                  label: _invitationLabel(inv.status),
                  color: color,
                ),
                footer:
                    inv.status == 'pending'
                        ? Align(
                          alignment: Alignment.centerLeft,
                          child: TextButton.icon(
                            onPressed: () async => _resend(context, ref, inv),
                            icon: const Icon(Icons.send_rounded, size: 16),
                            label: const Text('Renvoyer'),
                          ),
                        )
                        : null,
              );
            },
          );
        },
      ),
    );
  }

  String _invitationLabel(String status) => switch (status) {
    'pending' => 'En attente',
    'sent' => 'Envoyee',
    'accepted' => 'Acceptee',
    'expired' => 'Expiree',
    'revoked' => 'Revoquee',
    _ => status,
  };

  Color _invitationColor(String status) => switch (status) {
    'accepted' => AppColors.rh,
    'sent' || 'pending' => AppColors.info,
    'expired' || 'revoked' => AppColors.danger,
    _ => MobileSurface.disabled,
  };

  Future<void> _resend(
    BuildContext context,
    WidgetRef ref,
    Invitation inv,
  ) async {
    try {
      await ref.read(employeeRepositoryProvider).resendInvitation(inv.id);
      ref.invalidate(invitationsListProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Invitation renvoyee.')));
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Echec : $e')));
      }
    }
  }
}

class _EmployeeProfileSheet extends StatelessWidget {
  const _EmployeeProfileSheet({required this.employee});

  final Employee employee;

  @override
  Widget build(BuildContext context) {
    final currency = employee.currency ?? 'DZD';
    final salary =
        employee.salaryType == 'hourly'
            ? '${employee.hourlyRate?.toStringAsFixed(0) ?? '0'} $currency/h'
            : '${employee.salaryBase?.toStringAsFixed(0) ?? '0'} $currency';

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                MobileIconBubble(
                  icon: Icons.person_outline_rounded,
                  color: AppColors.rh,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        employee.fullName,
                        style: AppTypography.subtitle.copyWith(
                          color: MobileSurface.text,
                        ),
                      ),
                      Text(
                        employee.email,
                        style: AppTypography.caption.copyWith(
                          color: MobileSurface.secondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            MobilePanel(
              child: Column(
                children: [
                  _ProfileLine(
                    icon: Icons.phone_outlined,
                    label: 'Telephone',
                    value: employee.phone ?? 'Non renseigne',
                  ),
                  _ProfileLine(
                    icon: Icons.work_outline_rounded,
                    label: 'Poste',
                    value: employee.jobTitle ?? 'Non renseigne',
                  ),
                  _ProfileLine(
                    icon: Icons.apartment_rounded,
                    label: 'Departement',
                    value: employee.department ?? 'Non renseigne',
                  ),
                  _ProfileLine(
                    icon: Icons.place_outlined,
                    label: 'Lieu',
                    value: employee.workLocation ?? 'Non renseigne',
                  ),
                  _ProfileLine(
                    icon: Icons.schedule_outlined,
                    label: 'Horaire',
                    value: employee.scheduleName ?? 'Horaire par defaut',
                  ),
                  _ProfileLine(
                    icon: Icons.payments_outlined,
                    label: 'Salaire',
                    value: salary,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileLine extends StatelessWidget {
  const _ProfileLine({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Icon(icon, size: 18, color: MobileSurface.secondary),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              label,
              style: AppTypography.caption.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.text,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CompanyQrSheet extends ConsumerWidget {
  const _CompanyQrSheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_companyQrProvider);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: async.when(
          loading:
              () => const Center(
                heightFactor: 4,
                child: CircularProgressIndicator(),
              ),
          error:
              (err, _) => MobileErrorPanel(
                message: err.toString(),
                onRetry: () => ref.invalidate(_companyQrProvider),
              ),
          data:
              (payload) => Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'QR entreprise',
                    style: AppTypography.subtitle.copyWith(
                      color: MobileSurface.text,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'A partager a un employe pour demander son integration chez ${payload.companyName}.',
                    style: AppTypography.bodySmall.copyWith(
                      color: MobileSurface.secondary,
                    ),
                  ),
                  const SizedBox(height: 16),
                  MobilePanel(
                    color: MobileSurface.chip,
                    child: SelectableText(
                      payload.token,
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.text,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  ElevatedButton.icon(
                    onPressed: () async {
                      await Clipboard.setData(
                        ClipboardData(text: payload.token),
                      );
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('QR copie.')),
                        );
                      }
                    },
                    icon: const Icon(Icons.copy_rounded),
                    label: const Text('Copier le QR'),
                  ),
                ],
              ),
        ),
      ),
    );
  }
}

class _EmployeeQrImportSheet extends ConsumerStatefulWidget {
  const _EmployeeQrImportSheet({required this.onPrefillReady});

  final ValueChanged<EmployeeQrPrefill> onPrefillReady;

  @override
  ConsumerState<_EmployeeQrImportSheet> createState() =>
      _EmployeeQrImportSheetState();
}

class _EmployeeQrImportSheetState
    extends ConsumerState<_EmployeeQrImportSheet> {
  final _tokenCtrl = TextEditingController();
  bool _loading = false;

  @override
  void dispose() {
    _tokenCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        20,
        20,
        MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Importer depuis QR',
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 6),
          Text(
            'Collez le code QR employe. Le formulaire restera modifiable avant invitation.',
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _tokenCtrl,
            minLines: 3,
            maxLines: 5,
            style: const TextStyle(color: MobileSurface.text),
            decoration: const InputDecoration(
              labelText: 'Code QR employe',
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 14),
          ElevatedButton.icon(
            onPressed: _loading ? null : _scan,
            icon:
                _loading
                    ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                    : const Icon(Icons.qr_code_scanner_rounded),
            label: const Text('Lire et pre-remplir'),
          ),
        ],
      ),
    );
  }

  Future<void> _scan() async {
    final token = _tokenCtrl.text.trim();
    if (token.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Collez le code QR.')));
      return;
    }

    setState(() => _loading = true);
    try {
      final prefill = await ref
          .read(employeeRepositoryProvider)
          .scanEmployeeQr(token);
      if (!mounted) return;
      Navigator.of(context).pop();
      widget.onPrefillReady(prefill);
    } catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('QR invalide : $e')));
    }
  }
}

final _companyQrProvider = FutureProvider.autoDispose<CompanyQrPayload>((
  ref,
) async {
  return ref.watch(employeeRepositoryProvider).getCompanyQrPayload();
});

class _ScheduleSelector extends StatelessWidget {
  const _ScheduleSelector({
    required this.schedules,
    required this.selectedId,
    required this.onChanged,
  });

  final List<WorkSchedule> schedules;
  final int? selectedId;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    if (schedules.isEmpty) {
      return MobilePanel(
        color: AppColors.warning.withValues(alpha: 0.08),
        child: Row(
          children: [
            const Icon(Icons.schedule_outlined, color: AppColors.warning),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'Aucun horaire cree. Vous pourrez en definir dans le module Horaires.',
                style: AppTypography.caption.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
            ),
            TextButton(
              onPressed: () => context.push('/schedules'),
              child: const Text('Ouvrir'),
            ),
          ],
        ),
      );
    }

    return DropdownButtonFormField<int?>(
      initialValue: selectedId,
      dropdownColor: MobileSurface.surface,
      decoration: const InputDecoration(
        labelText: 'Horaire de travail',
        prefixIcon: Icon(Icons.schedule_outlined),
      ),
      items: [
        const DropdownMenuItem<int?>(
          value: null,
          child: Text('Horaire par defaut'),
        ),
        ...schedules.map(
          (schedule) => DropdownMenuItem<int?>(
            value: schedule.id,
            child: Text(
              '${schedule.name} · ${schedule.startTime}-${schedule.endTime}',
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ),
      ],
      onChanged: onChanged,
    );
  }
}

class _EditEmployeeForm extends ConsumerStatefulWidget {
  const _EditEmployeeForm({required this.employee});

  final Employee employee;

  @override
  ConsumerState<_EditEmployeeForm> createState() => _EditEmployeeFormState();
}

class _EditEmployeeFormState extends ConsumerState<_EditEmployeeForm> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _firstName;
  late final TextEditingController _lastName;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  late final TextEditingController _hireDate;
  late final TextEditingController _salaryBase;
  late final TextEditingController _hourlyRate;
  late final TextEditingController _department;
  late final TextEditingController _jobTitle;
  late final TextEditingController _workLocation;
  late String _salaryType;
  late int? _scheduleId;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    final e = widget.employee;
    _firstName = TextEditingController(text: e.firstName);
    _lastName = TextEditingController(text: e.lastName);
    _email = TextEditingController(text: e.email);
    _phone = TextEditingController(text: e.phone ?? '');
    _hireDate = TextEditingController(
      text: e.hireDate == null ? '' : _formatDate(e.hireDate!),
    );
    _salaryBase = TextEditingController(
      text: e.salaryBase == null ? '' : e.salaryBase!.toStringAsFixed(0),
    );
    _hourlyRate = TextEditingController(
      text: e.hourlyRate == null ? '' : e.hourlyRate!.toStringAsFixed(0),
    );
    _department = TextEditingController(text: e.department ?? '');
    _jobTitle = TextEditingController(text: e.jobTitle ?? '');
    _workLocation = TextEditingController(text: e.workLocation ?? '');
    _salaryType = e.salaryType ?? 'fixed';
    _scheduleId = e.scheduleId;
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _hireDate.dispose();
    _salaryBase.dispose();
    _hourlyRate.dispose();
    _department.dispose();
    _jobTitle.dispose();
    _workLocation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final schedulesAsync = ref.watch(schedulesProvider);
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
                'Modifier la fiche',
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                widget.employee.fullName,
                style: AppTypography.caption.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _firstName,
                      decoration: const InputDecoration(labelText: 'Prenom'),
                      validator:
                          (value) =>
                              value == null || value.trim().isEmpty
                                  ? 'Obligatoire'
                                  : null,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      controller: _lastName,
                      decoration: const InputDecoration(labelText: 'Nom'),
                      validator:
                          (value) =>
                              value == null || value.trim().isEmpty
                                  ? 'Obligatoire'
                                  : null,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(labelText: 'Email'),
                validator:
                    (value) =>
                        value == null || !value.contains('@')
                            ? 'Email invalide'
                            : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'Telephone'),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _hireDate,
                readOnly: true,
                decoration: const InputDecoration(
                  labelText: 'Date d embauche',
                  suffixIcon: Icon(Icons.calendar_today_outlined),
                ),
                onTap: _pickHireDate,
              ),
              const SizedBox(height: 14),
              schedulesAsync.when(
                data:
                    (schedules) => _ScheduleSelector(
                      schedules: schedules,
                      selectedId: _scheduleId,
                      onChanged: (value) => setState(() => _scheduleId = value),
                    ),
                loading:
                    () => const LinearProgressIndicator(
                      minHeight: 3,
                      color: AppColors.rh,
                    ),
                error:
                    (error, stackTrace) => TextButton.icon(
                      onPressed: () => ref.invalidate(schedulesProvider),
                      icon: const Icon(Icons.refresh),
                      label: const Text('Recharger les horaires'),
                    ),
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                initialValue: _salaryType,
                dropdownColor: MobileSurface.surface,
                decoration: const InputDecoration(labelText: 'Mode salaire'),
                items: const [
                  DropdownMenuItem(value: 'fixed', child: Text('Mensuel')),
                  DropdownMenuItem(value: 'hourly', child: Text('Horaire')),
                  DropdownMenuItem(value: 'daily', child: Text('Journalier')),
                ],
                onChanged:
                    (value) => setState(() => _salaryType = value ?? 'fixed'),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _salaryType == 'hourly' ? _hourlyRate : _salaryBase,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText:
                      _salaryType == 'hourly'
                          ? 'Taux horaire'
                          : 'Salaire de base',
                ),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _department,
                decoration: const InputDecoration(labelText: 'Departement'),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _jobTitle,
                decoration: const InputDecoration(labelText: 'Poste'),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _workLocation,
                decoration: const InputDecoration(labelText: 'Lieu de travail'),
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
                        : const Icon(Icons.save_outlined),
                label: const Text('Enregistrer'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickHireDate() async {
    final initial = widget.employee.hireDate ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1970),
      lastDate: DateTime.now().add(const Duration(days: 1)),
    );
    if (picked == null) return;
    setState(() => _hireDate.text = _formatDate(picked));
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final patch = <String, dynamic>{
        'first_name': _firstName.text.trim(),
        'last_name': _lastName.text.trim(),
        'email': _email.text.trim(),
        'phone': _phone.text.trim(),
        if (_hireDate.text.trim().isNotEmpty)
          'contract_start': _hireDate.text.trim(),
        if (_scheduleId != null) 'schedule_id': _scheduleId,
        'salary_type': _salaryType,
        if (_salaryType == 'hourly')
          'hourly_rate': _parseAmount(_hourlyRate.text)
        else
          'salary_base': _parseAmount(_salaryBase.text),
        'extra_data': {
          'department': _department.text.trim(),
          'job_title': _jobTitle.text.trim(),
          'work_location': _workLocation.text.trim(),
        },
      };

      await ref
          .read(employeeRepositoryProvider)
          .update(widget.employee.id, patch);
      ref.invalidate(teamListProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Fiche collaborateur mise a jour.')),
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

  static String _formatDate(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  static double? _parseAmount(String raw) {
    final normalized = raw.trim().replaceAll(',', '.');
    if (normalized.isEmpty) return null;
    return double.tryParse(normalized);
  }
}

class _CreateEmployeeForm extends ConsumerStatefulWidget {
  const _CreateEmployeeForm({this.prefill});

  final EmployeeQrPrefill? prefill;

  @override
  ConsumerState<_CreateEmployeeForm> createState() =>
      _CreateEmployeeFormState();
}

class _CreateEmployeeFormState extends ConsumerState<_CreateEmployeeForm> {
  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _matricule = TextEditingController();
  final _hireDate = TextEditingController();
  final _salaryBase = TextEditingController();
  final _hourlyRate = TextEditingController();
  final _department = TextEditingController();
  final _jobTitle = TextEditingController();
  final _workLocation = TextEditingController();
  String _role = 'employee';
  String? _managerRole;
  String _salaryType = 'fixed';
  int? _scheduleId;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _hireDate.text = _formatDate(DateTime.now());
    final prefill = widget.prefill;
    if (prefill != null) {
      _firstName.text = prefill.firstName;
      _lastName.text = prefill.lastName;
      _phone.text = prefill.phone ?? '';
    }
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _matricule.dispose();
    _hireDate.dispose();
    _salaryBase.dispose();
    _hourlyRate.dispose();
    _department.dispose();
    _jobTitle.dispose();
    _workLocation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    final schedulesAsync = ref.watch(schedulesProvider);
    return Padding(
      padding: EdgeInsets.only(bottom: bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
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
                widget.prefill == null
                    ? 'Nouvel employe'
                    : 'Nouvel employe via QR',
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                widget.prefill == null
                    ? 'Invitation, role, date d embauche et base salariale sont envoyes a l API.'
                    : 'Profil pre-rempli depuis QR. Renseignez l email professionnel unique de cette entreprise.',
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _firstName,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(labelText: 'Prenom'),
                validator:
                    (v) =>
                        (v == null || v.trim().isEmpty) ? 'Obligatoire' : null,
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _lastName,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(labelText: 'Nom'),
                validator:
                    (v) =>
                        (v == null || v.trim().isEmpty) ? 'Obligatoire' : null,
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _email,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(
                  labelText: 'Email professionnel',
                ),
                keyboardType: TextInputType.emailAddress,
                validator: (v) {
                  if (v == null || v.trim().isEmpty) return 'Obligatoire';
                  if (!v.contains('@')) return 'Email invalide';
                  return null;
                },
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _phone,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(
                  labelText: 'Telephone (optionnel)',
                ),
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _matricule,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(
                  labelText: 'Matricule (optionnel)',
                ),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _hireDate,
                style: const TextStyle(color: MobileSurface.text),
                readOnly: true,
                decoration: const InputDecoration(
                  labelText: 'Date d embauche',
                  suffixIcon: Icon(Icons.calendar_today_outlined),
                ),
                onTap: _pickHireDate,
                validator:
                    (v) =>
                        (v == null || v.trim().isEmpty) ? 'Obligatoire' : null,
              ),
              const SizedBox(height: 16),
              schedulesAsync.when(
                data:
                    (schedules) => _ScheduleSelector(
                      schedules: schedules,
                      selectedId: _scheduleId,
                      onChanged: (value) => setState(() => _scheduleId = value),
                    ),
                loading:
                    () => const LinearProgressIndicator(
                      minHeight: 3,
                      color: AppColors.rh,
                    ),
                error:
                    (error, stackTrace) => TextButton.icon(
                      onPressed: () => ref.invalidate(schedulesProvider),
                      icon: const Icon(Icons.refresh),
                      label: const Text('Recharger les horaires'),
                    ),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _role,
                dropdownColor: MobileSurface.surface,
                decoration: const InputDecoration(labelText: 'Role'),
                items: const [
                  DropdownMenuItem(value: 'employee', child: Text('Employe')),
                  DropdownMenuItem(value: 'manager', child: Text('Manager')),
                ],
                onChanged:
                    (v) => setState(() {
                      _role = v ?? 'employee';
                      if (_role != 'manager') _managerRole = null;
                    }),
              ),
              if (_role == 'manager') ...[
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  initialValue: _managerRole,
                  dropdownColor: MobileSurface.surface,
                  decoration: const InputDecoration(
                    labelText: 'Type de manager',
                  ),
                  items: const [
                    DropdownMenuItem(value: 'rh', child: Text('RH')),
                    DropdownMenuItem(value: 'dept', child: Text('Departement')),
                    DropdownMenuItem(
                      value: 'comptable',
                      child: Text('Comptable'),
                    ),
                    DropdownMenuItem(
                      value: 'superviseur',
                      child: Text('Superviseur'),
                    ),
                  ],
                  validator:
                      (v) =>
                          (_role == 'manager' && (v == null || v.isEmpty))
                              ? 'Selectionnez un type'
                              : null,
                  onChanged: (v) => setState(() => _managerRole = v),
                ),
              ],
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _salaryType,
                dropdownColor: MobileSurface.surface,
                decoration: const InputDecoration(labelText: 'Type de paie'),
                items: const [
                  DropdownMenuItem(
                    value: 'fixed',
                    child: Text('Mensuel / fixe'),
                  ),
                  DropdownMenuItem(value: 'hourly', child: Text('Horaire')),
                  DropdownMenuItem(value: 'daily', child: Text('Journalier')),
                ],
                onChanged: (v) => setState(() => _salaryType = v ?? 'fixed'),
              ),
              const SizedBox(height: 8),
              if (_salaryType == 'hourly')
                TextFormField(
                  controller: _hourlyRate,
                  style: const TextStyle(color: MobileSurface.text),
                  decoration: const InputDecoration(
                    labelText: 'Taux horaire',
                    suffixText: 'DZD/h',
                  ),
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  validator: _positiveNumberValidator,
                )
              else
                TextFormField(
                  controller: _salaryBase,
                  style: const TextStyle(color: MobileSurface.text),
                  decoration: InputDecoration(
                    labelText:
                        _salaryType == 'daily'
                            ? 'Salaire journalier'
                            : 'Salaire mensuel brut',
                    suffixText: 'DZD',
                  ),
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  validator: _positiveNumberValidator,
                ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _jobTitle,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(
                  labelText: 'Poste (optionnel)',
                ),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _department,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(
                  labelText: 'Departement (optionnel)',
                ),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _workLocation,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(
                  labelText: 'Lieu de travail (optionnel)',
                ),
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _submitting ? null : _submit,
                child:
                    _submitting
                        ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                        : Text(
                          widget.prefill == null
                              ? 'Envoyer l invitation'
                              : 'Creer depuis QR et inviter',
                        ),
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
      final repo = ref.read(employeeRepositoryProvider);
      final prefill = widget.prefill;

      if (prefill != null) {
        await repo.createFromQr(
          qrToken: prefill.token,
          email: _email.text,
          matricule: _matricule.text,
          contractStart: _hireDate.text,
          scheduleId: _scheduleId,
          salaryType: _salaryType,
          salaryBase:
              _salaryType == 'hourly' ? null : _parseAmount(_salaryBase.text),
          hourlyRate:
              _salaryType == 'hourly' ? _parseAmount(_hourlyRate.text) : null,
          department: _department.text,
          jobTitle: _jobTitle.text,
          workLocation: _workLocation.text,
          sendInvitation: true,
        );
      } else {
        await repo.create(
          firstName: _firstName.text,
          lastName: _lastName.text,
          email: _email.text,
          phone: _phone.text,
          role: _role,
          managerRole: _managerRole,
          matricule: _matricule.text,
          contractStart: _hireDate.text,
          scheduleId: _scheduleId,
          salaryType: _salaryType,
          salaryBase:
              _salaryType == 'hourly' ? null : _parseAmount(_salaryBase.text),
          hourlyRate:
              _salaryType == 'hourly' ? _parseAmount(_hourlyRate.text) : null,
          department: _department.text,
          jobTitle: _jobTitle.text,
          workLocation: _workLocation.text,
          sendInvitation: true,
        );
      }
      ref.invalidate(teamListProvider);
      ref.invalidate(invitationsListProvider);
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Employe ajoute.')));
      }
    } catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Echec : $e')));
      }
    }
  }

  Future<void> _pickHireDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.tryParse(_hireDate.text) ?? now,
      firstDate: DateTime(now.year - 20),
      lastDate: now.add(const Duration(days: 365)),
    );
    if (picked != null) {
      setState(() => _hireDate.text = _formatDate(picked));
    }
  }

  String? _positiveNumberValidator(String? value) {
    final parsed = _parseAmount(value ?? '');
    if (parsed == null || parsed <= 0) return 'Montant obligatoire';
    return null;
  }

  double? _parseAmount(String value) {
    return double.tryParse(value.trim().replaceAll(',', '.'));
  }

  String _formatDate(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }
}
