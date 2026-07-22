import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_employee/features/auth/providers/auth_provider.dart';

/// Écran "Mon profil" dédié à leopardo_employee.
///
/// Affiche les informations employé réelles (photo, nom, poste, département,
/// email) issues du provider d'authentification existant (pas de données
/// mockées) et propose un changement de langue rapide qui délègue au flux
/// déjà en place dans SettingsScreen (mise à jour serveur puis rechargement
/// de l'app).
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  static const Map<String, String> _languageLabels = {
    'fr': 'Francais',
    'ar': 'العربية',
    'tr': 'Turkce',
    'en': 'English',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final employee = authState.employee;

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Mon profil',
        subtitle: 'Informations personnelles et langue',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: employee == null
          ? Center(
              child: Text(
                'Profil indisponible pour le moment.',
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.secondary,
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                _buildHeaderCard(context, employee),
                const SizedBox(height: 20),
                _buildInfoSection(context, employee),
                const SizedBox(height: 20),
                _buildLanguageSection(context, ref, employee),
                SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
              ],
            ),
    );
  }

  Widget _buildHeaderCard(BuildContext context, Employee employee) {
    final photoUrl = employee.photoUrl;
    final fullName = employee.fullName;
    final jobTitle = employee.jobTitle;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Row(
        children: [
          CircleAvatar(
            radius: 32,
            backgroundColor: MobileSurface.chip,
            backgroundImage:
                (photoUrl != null && photoUrl.isNotEmpty)
                    ? NetworkImage(photoUrl)
                    : null,
            child: (photoUrl == null || photoUrl.isEmpty)
                ? Text(
                    fullName.isNotEmpty ? fullName[0].toUpperCase() : '?',
                    style: AppTypography.subtitle.copyWith(
                      color: MobileSurface.text,
                    ),
                  )
                : null,
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  fullName,
                  style: AppTypography.subtitle.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (jobTitle != null && jobTitle.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    jobTitle,
                    style: AppTypography.bodySmall.copyWith(
                      color: MobileSurface.secondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoSection(BuildContext context, Employee employee) {
    final department = employee.department;
    final jobTitle = employee.jobTitle;
    final email = employee.email;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Informations',
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 14),
          _buildInfoRow(
            icon: Icons.badge_outlined,
            label: 'Poste',
            value: (jobTitle != null && jobTitle.isNotEmpty)
                ? jobTitle
                : 'Non renseigne',
          ),
          _buildInfoRow(
            icon: Icons.apartment_outlined,
            label: 'Departement',
            value: (department != null && department.isNotEmpty)
                ? department
                : 'Non renseigne',
          ),
          _buildInfoRow(
            icon: Icons.email_outlined,
            label: 'Email',
            value: email,
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: AppColors.rh),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.muted,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: AppTypography.body.copyWith(
                    color: MobileSurface.text,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLanguageSection(
    BuildContext context,
    WidgetRef ref,
    Employee employee,
  ) {
    return _LanguageSelector(currentLanguage: employee.language);
  }
}

class _LanguageSelector extends ConsumerStatefulWidget {
  const _LanguageSelector({required this.currentLanguage});

  final String currentLanguage;

  @override
  ConsumerState<_LanguageSelector> createState() => _LanguageSelectorState();
}

class _LanguageSelectorState extends ConsumerState<_LanguageSelector> {
  late String _selectedLanguage;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _selectedLanguage =
        ProfileScreen._languageLabels.containsKey(widget.currentLanguage)
            ? widget.currentLanguage
            : 'fr';
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final success = await ref
        .read(authProvider.notifier)
        .updatePreferredLanguage(_selectedLanguage);

    if (!mounted) return;
    setState(() => _saving = false);

    if (success) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Langue mise a jour.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Langue',
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 8),
          Text(
            'Cette preference est synchronisee avec votre compte et pilote aussi le mode RTL.',
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _selectedLanguage,
            decoration: const InputDecoration(labelText: 'Langue preferee'),
            items: ProfileScreen._languageLabels.entries
                .map(
                  (entry) => DropdownMenuItem<String>(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: _saving
                ? null
                : (value) {
                    if (value == null) return;
                    setState(() => _selectedLanguage = value);
                  },
          ),
          const SizedBox(height: 16),
          if (authState.error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                authState.error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: Text(_saving ? 'Mise a jour...' : 'Mettre a jour la langue'),
          ),
        ],
      ),
    );
  }
}
