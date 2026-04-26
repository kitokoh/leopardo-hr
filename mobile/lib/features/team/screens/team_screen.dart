import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/core/widgets/leopardo_badge.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/features/team/data/employee_repository.dart';
import 'package:leopardo_rh/features/team/providers/team_provider.dart';
import 'package:leopardo_rh/models/employee.dart';

/// Ecran "Equipe" — reserve aux managers (principal / RH).
/// Permet de lister, creer, archiver un employe et de gerer les invitations.
class TeamScreen extends ConsumerStatefulWidget {
  const TeamScreen({super.key});

  @override
  ConsumerState<TeamScreen> createState() => _TeamScreenState();
}

class _TeamScreenState extends ConsumerState<TeamScreen> with SingleTickerProviderStateMixin {
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
        appBar: AppBar(
          title: const Text('Equipe'),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back),
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
      appBar: AppBar(
        title: const Text('Equipe'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Employes'),
            Tab(text: 'Invitations'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: const [
          _EmployeesTab(),
          _InvitationsTab(),
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
      onRefresh: () async => ref.refresh(teamListProvider),
      child: async.when(
        loading: () => Center(
          child: Semantics(
            label: 'Chargement des employés...',
            child: const CircularProgressIndicator(),
          ),
        ),
        error: (err, _) => Center(child: Text('Erreur : $err')),
        data: (employees) {
          if (employees.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                EmptyState(
                  icon: Icons.group_add,
                  title: 'Aucun collaborateur',
                  description: 'Commencez par ajouter votre equipe avec le bouton ci-dessous.',
                ),
              ],
            );
          }
          return ListView.separated(
            itemCount: employees.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, index) {
              final e = employees[index];
              return ListTile(
                leading: ExcludeSemantics(
                  child: CircleAvatar(
                    child: Text(_initials(e)),
                  ),
                ),
                title: Text(e.fullName),
                subtitle: Text('${e.email}\nRole : ${_roleLabel(e)}'),
                isThreeLine: true,
                trailing: LeopardoBadge.forStatus(e.status, _statusLabel(e.status)),
                onTap: () => _showActions(context, ref, e),
              );
            },
          );
        },
      ),
    );
  }

  String _initials(Employee e) {
    final parts = [e.firstName, e.lastName]
        .where((p) => p.isNotEmpty)
        .map((p) => p.substring(0, 1).toUpperCase());
    return parts.join();
  }

  String _roleLabel(Employee e) {
    if (e.role == 'manager') {
      return 'Manager ${e.managerRole ?? ''}'.trim();
    }
    return 'Employe';
  }

  String _statusLabel(String status) => switch (status) {
        'active' => 'Actif',
        'archived' => 'Archive',
        'blocked' => 'Bloque',
        'suspended' => 'Suspendu',
        _ => status,
      };

  void _showActions(BuildContext context, WidgetRef ref, Employee employee) {
    showModalBottomSheet(
      context: context,
      builder: (_) => Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(employee.fullName, style: Theme.of(context).textTheme.titleLarge),
            Text(employee.email, style: const TextStyle(color: Colors.grey)),
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

  Future<void> _archive(BuildContext context, WidgetRef ref, Employee employee) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Archiver cet employe ?'),
        content: Text('${employee.fullName} n aura plus acces a l application.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Annuler')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Archiver')),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await ref.read(employeeRepositoryProvider).archive(employee.id);
      ref.invalidate(teamListProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Employe archive.')));
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Echec : $e')));
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
      onRefresh: () async => ref.refresh(invitationsListProvider),
      child: async.when(
        loading: () => Center(
          child: Semantics(
            label: 'Chargement des invitations...',
            child: const CircularProgressIndicator(),
          ),
        ),
        error: (err, _) => Center(child: Text('Erreur : $err')),
        data: (invitations) {
          if (invitations.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                EmptyState(
                  icon: Icons.mark_email_read_outlined,
                  title: 'Aucune invitation en cours',
                  description: 'Les invitations envoyees a vos futurs collaborateurs s afficheront ici.',
                ),
              ],
            );
          }
          return ListView.separated(
            itemCount: invitations.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, index) {
              final inv = invitations[index];
              return ListTile(
                leading: const Icon(Icons.mail_outline, color: AppColors.ia),
                title: Text(inv.email),
                subtitle: Row(
                  children: [
                    LeopardoBadge.forStatus(inv.status, _invitationLabel(inv.status)),
                  ],
                ),
                trailing: inv.status == 'pending'
                    ? IconButton(
                        icon: const Icon(Icons.send),
                        tooltip: 'Renvoyer',
                        onPressed: () async => _resend(context, ref, inv),
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

  Future<void> _resend(BuildContext context, WidgetRef ref, Invitation inv) async {
    try {
      await ref.read(employeeRepositoryProvider).resendInvitation(inv.id);
      ref.invalidate(invitationsListProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Invitation renvoyee.')));
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Echec : $e')));
      }
    }
  }
}

class _CreateEmployeeForm extends ConsumerStatefulWidget {
  const _CreateEmployeeForm();

  @override
  ConsumerState<_CreateEmployeeForm> createState() => _CreateEmployeeFormState();
}

class _CreateEmployeeFormState extends ConsumerState<_CreateEmployeeForm> {
  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  String _role = 'employee';
  String? _managerRole;
  bool _submitting = false;

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
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
              Text('Nouvel employe', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              TextFormField(
                controller: _firstName,
                decoration: const InputDecoration(labelText: 'Prenom'),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Obligatoire' : null,
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _lastName,
                decoration: const InputDecoration(labelText: 'Nom'),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Obligatoire' : null,
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _email,
                decoration: const InputDecoration(labelText: 'Email professionnel'),
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
                decoration: const InputDecoration(labelText: 'Telephone (optionnel)'),
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                value: _role,
                decoration: const InputDecoration(labelText: 'Role'),
                items: const [
                  DropdownMenuItem(value: 'employee', child: Text('Employe')),
                  DropdownMenuItem(value: 'manager', child: Text('Manager')),
                ],
                onChanged: (v) => setState(() {
                  _role = v ?? 'employee';
                  if (_role != 'manager') _managerRole = null;
                }),
              ),
              if (_role == 'manager') ...[
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  value: _managerRole,
                  decoration: const InputDecoration(labelText: 'Type de manager'),
                  items: const [
                    DropdownMenuItem(value: 'rh', child: Text('RH')),
                    DropdownMenuItem(value: 'dept', child: Text('Departement')),
                    DropdownMenuItem(value: 'comptable', child: Text('Comptable')),
                    DropdownMenuItem(value: 'superviseur', child: Text('Superviseur')),
                  ],
                  validator: (v) => (_role == 'manager' && (v == null || v.isEmpty))
                      ? 'Selectionnez un type'
                      : null,
                  onChanged: (v) => setState(() => _managerRole = v),
                ),
              ],
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _submitting ? null : _submit,
                child: _submitting
                    ? Semantics(
                        label: 'Envoi en cours...',
                        child: const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
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
      await ref.read(employeeRepositoryProvider).create(
            firstName: _firstName.text,
            lastName: _lastName.text,
            email: _email.text,
            phone: _phone.text,
            role: _role,
            managerRole: _managerRole,
            sendInvitation: true,
          );
      ref.invalidate(teamListProvider);
      ref.invalidate(invitationsListProvider);
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Invitation envoyee.')),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Echec : $e')),
        );
      }
    }
  }
}
