import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_manager/features/auth/providers/auth_provider.dart';
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
        onPressed: () => _openCreateEmployeeSheet(context),
        icon: const Icon(Icons.person_add),
        label: const Text('Ajouter'),
      ),
    );
  }

  void _openCreateEmployeeSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => const _CreateEmployeeForm(),
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

class _CreateEmployeeForm extends ConsumerStatefulWidget {
  const _CreateEmployeeForm();

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
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _hireDate.text = _formatDate(DateTime.now());
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
                'Nouvel employe',
                style: AppTypography.subtitle.copyWith(
                  color: MobileSurface.text,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Invitation, role, date d embauche et base salariale sont envoyes a l API.',
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
                        : const Text('Envoyer l invitation'),
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
          .read(employeeRepositoryProvider)
          .create(
            firstName: _firstName.text,
            lastName: _lastName.text,
            email: _email.text,
            phone: _phone.text,
            role: _role,
            managerRole: _managerRole,
            matricule: _matricule.text,
            contractStart: _hireDate.text,
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
      ref.invalidate(teamListProvider);
      ref.invalidate(invitationsListProvider);
      await ref.refresh(teamListProvider.future).then((_) {});
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Invitation envoyee.')));
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
