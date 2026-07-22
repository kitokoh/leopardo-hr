import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_employee/features/auth/providers/auth_provider.dart';

/// Dedicated profile screen for leopardo_employee.
///
/// Shows the employee's real account info (photo, name, job title,
/// department, email) sourced directly from [authProvider] (no mocked
/// data), and offers a quick language switch, following the same
/// ConsumerWidget + provider pattern already used in
/// `features/settings/screens/settings_screen.dart`.
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  static const Map<String, String> _languageLabels = {
    'fr': 'Francais',
    'ar': 'العربية',
    'tr': 'Turkce',
    'en': 'English',
  };

  bool _languageSaving = false;
  String? _selectedLanguage;

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final employee = authState.employee;
    _selectedLanguage ??= _languageLabels.containsKey(employee?.language)
        ? employee!.language
        : 'fr';

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Mon profil',
        subtitle: 'Informations de compte',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: employee == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                _buildIdentityCard(employee),
                const SizedBox(height: 20),
                _buildInfoSection(employee),
                const SizedBox(height: 20),
                _buildLanguageSection(context, authState),
                SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
              ],
            ),
    );
  }

  Widget _buildIdentityCard(Employee employee) {
    final initial = employee.fullName.isNotEmpty
        ? employee.fullName[0].toUpperCase()
        : '?';

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Row(
        children: [
          CircleAvatar(
            radius: 32,
            backgroundColor: AppColors.rh,
            backgroundImage: employee.photoUrl != null
                ? NetworkImage(employee.photoUrl!)
                : null,
            child: employee.photoUrl == null
                ? Text(
                    initial,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 24,
                      fontWeight: FontWeight.w600,
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
                  employee.fullName,
                  style: AppTypography.title.copyWith(
                    color: MobileSurface.text,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Text(
                  employee.jobTitle ?? 'Poste non renseigne',
                  style: AppTypography.bodySmall.copyWith(
                    color: MobileSurface.secondary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoSection(Employee employee) {
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
            icon: Icons.work_outline_rounded,
            label: 'Poste',
            value: employee.jobTitle ?? 'Non renseigne',
          ),
          _buildInfoRow(
            icon: Icons.apartment_outlined,
            label: 'Departement',
            value: employee.department ?? 'Non renseigne',
          ),
          _buildInfoRow(
            icon: Icons.email_outlined,
            label: 'Email',
            value: employee.email,
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
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          MobileIconBubble(icon: icon, color: AppColors.rh, size: 34),
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

  Widget _buildLanguageSection(BuildContext context, AuthState authState) {
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
            'Cette preference est synchronisee avec votre compte.',
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _selectedLanguage,
            decoration: const InputDecoration(labelText: 'Langue preferee'),
            items: _languageLabels.entries
                .map(
                  (entry) => DropdownMenuItem<String>(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: _languageSaving
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
            onPressed: _languageSaving ? null : _saveLanguage,
            child: Text(
              _languageSaving ? 'Mise a jour...' : 'Mettre a jour la langue',
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _saveLanguage() async {
    final language = _selectedLanguage;
    if (language == null) return;

    setState(() => _languageSaving = true);
    final success = await ref
        .read(authProvider.notifier)
        .updatePreferredLanguage(language);

    if (!mounted) return;
    setState(() => _languageSaving = false);

    if (success) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Langue mise a jour.')));
    }
  }
}
