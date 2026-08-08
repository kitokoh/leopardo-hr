import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_employee/features/auth/providers/auth_provider.dart';

/// Dedicated employee profile screen: photo, identity, role, and quick
/// language switcher. Reads the current [Employee] straight from
/// [authProvider] â€” no mocked data. Follows the ConsumerWidget +
/// FutureProvider pattern already used across `features/settings`.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  static const Map<String, String> _languageLabels = {
    'fr': 'Francais',
    'ar': 'Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©',
    'tr': 'Turkce',
    'en': 'English',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final employee = authState.employee;
    final l10n = context.l10n;

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: l10n.profileTitle,
        subtitle: l10n.profileSubtitle,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: l10n.profileBackTooltip,
          onPressed: () => context.pop(),
        ),
      ),
      body: employee == null
          ? MobileEmptyLoading(label: l10n.profileLoading)
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                _ProfileHeader(employee: employee),
                const SizedBox(height: 20),
                _ProfileDetailsCard(employee: employee),
                const SizedBox(height: 20),
                _ProfileLanguageCard(
                  employee: employee,
                  languageLabels: _languageLabels,
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () => context.push('/settings'),
                  icon: const Icon(Icons.settings_outlined),
                  label: Text(l10n.profileOpenSettings),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(46),
                  ),
                ),
                SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
              ],
            ),
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.employee});

  final Employee employee;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final initials = employee.fullName.isNotEmpty
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
                    initials,
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
                  employee.jobTitle ?? l10n.profileJobTitleUnset,
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
}

class _ProfileDetailsCard extends StatelessWidget {
  const _ProfileDetailsCard({required this.employee});

  final Employee employee;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.profileDetailsTitle,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 14),
          _ProfileInfoRow(
            icon: Icons.mail_outline_rounded,
            label: l10n.profileEmailLabel,
            value: employee.email,
          ),
          _ProfileInfoRow(
            icon: Icons.apartment_outlined,
            label: l10n.profileDepartmentLabel,
            value: employee.department ?? l10n.profileValueUnset,
          ),
          _ProfileInfoRow(
            icon: Icons.badge_outlined,
            label: l10n.profileJobTitleLabel,
            value: employee.jobTitle ?? l10n.profileValueUnset,
          ),
          if (employee.matricule != null)
            _ProfileInfoRow(
              icon: Icons.tag_outlined,
              label: l10n.profileMatriculeLabel,
              value: employee.matricule!,
            ),
        ],
      ),
    );
  }
}

class _ProfileInfoRow extends StatelessWidget {
  const _ProfileInfoRow({
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
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: MobileSurface.muted),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.secondary,
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
}

class _ProfileLanguageCard extends ConsumerStatefulWidget {
  const _ProfileLanguageCard({
    required this.employee,
    required this.languageLabels,
  });

  final Employee employee;
  final Map<String, String> languageLabels;

  @override
  ConsumerState<_ProfileLanguageCard> createState() =>
      _ProfileLanguageCardState();
}

class _ProfileLanguageCardState extends ConsumerState<_ProfileLanguageCard> {
  late String _selectedLanguage;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _selectedLanguage = widget.languageLabels.containsKey(
      widget.employee.language,
    )
        ? widget.employee.language
        : 'fr';
  }

  Future<void> _saveLanguage() async {
    setState(() => _saving = true);
    final success = await ref
        .read(authProvider.notifier)
        .updatePreferredLanguage(_selectedLanguage);

    if (!mounted) return;
    setState(() => _saving = false);

    if (!context.mounted) return;
    final l10n = context.l10n;
    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.profileLanguageUpdated)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.commonLanguageLabel,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _selectedLanguage,
            decoration: InputDecoration(labelText: l10n.commonLanguageLabel),
            items: widget.languageLabels.entries
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
          const SizedBox(height: 14),
          FilledButton(
            onPressed: _saving ? null : _saveLanguage,
            child: Text(
              _saving
                  ? l10n.profileLanguageSaving
                  : l10n.profileLanguageSave,
            ),
          ),
        ],
      ),
    );
  }
}

