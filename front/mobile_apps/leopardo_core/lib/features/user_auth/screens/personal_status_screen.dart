import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/providers/core_providers.dart';
import 'package:leopardo_core/features/user_auth/providers/user_auth_provider.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/models/app_user.dart';

/// #5540 — Écran de sélection des statuts personnels cumulables.
///
/// Permet à l'utilisateur de cocher ses statuts actuels :
/// étudiant, salarié, entrepreneur, en recherche d'emploi.
/// Les statuts sont envoyés à l'API via PATCH /user/personal-statuses.
class PersonalStatusScreen extends ConsumerStatefulWidget {
  const PersonalStatusScreen({super.key});

  @override
  ConsumerState<PersonalStatusScreen> createState() =>
      _PersonalStatusScreenState();
}

class _PersonalStatusScreenState extends ConsumerState<PersonalStatusScreen> {
  late Set<PersonalStatus> _selected;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final user = ref.read(userAuthProvider).user;
    _selected = (user?.personalStatuses ?? []).toSet();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final repo = ref.read(userAuthRepositoryProvider);
      final updated =
          await repo.updatePersonalStatuses(_selected.toList());
      await ref.read(userAuthProvider.notifier).setUser(updated);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.personalOnboardingStatusSaved),
          backgroundColor: AppColors.success,
        ),
      );
      context.pop();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.personalOnboardingStatusError),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _toggle(PersonalStatus status) {
    setState(() {
      if (_selected.contains(status)) {
        _selected.remove(status);
      } else {
        _selected.add(status);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    final statuses = [
      (PersonalStatus.student, l10n.personalOnboardingStatusStudent,
          Icons.school_outlined),
      (PersonalStatus.employee, l10n.personalOnboardingStatusEmployee,
          Icons.badge_outlined),
      (PersonalStatus.entrepreneur, l10n.personalOnboardingStatusEntrepreneur,
          Icons.business_center_outlined),
      (PersonalStatus.seekingEmployment,
          l10n.personalOnboardingStatusSeeking, Icons.search_outlined),
    ];

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: bg,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: text),
          onPressed: () => context.pop(),
        ),
        title: Text(
          l10n.personalOnboardingStatusTitle,
          style: AppTypography.subtitle.copyWith(color: text),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Text(
                    l10n.personalOnboardingStatusSubtitle,
                    style: AppTypography.bodySmall.copyWith(color: muted),
                  ),
                  const SizedBox(height: 24),
                  ...statuses.map(
                    (entry) {
                      final (status, label, icon) = entry;
                      final checked = _selected.contains(status);
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _StatusTile(
                          icon: icon,
                          label: label,
                          checked: checked,
                          onTap: () => _toggle(status),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.rh,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(
                          l10n.personalOnboardingStatusSave,
                          style: AppTypography.body.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatusTile extends StatelessWidget {
  const _StatusTile({
    required this.icon,
    required this.label,
    required this.checked,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool checked;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: checked
              ? AppColors.rh.withValues(alpha: 0.12)
              : MobileSurface.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: checked ? AppColors.rh : MobileSurface.border,
            width: checked ? 1.5 : 1.0,
          ),
        ),
        child: Row(
          children: [
            Icon(
              icon,
              color: checked ? AppColors.rh : MobileSurface.secondary,
              size: 22,
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                label,
                style: AppTypography.body.copyWith(
                  color: checked ? AppColors.rh : text,
                  fontWeight:
                      checked ? FontWeight.w600 : FontWeight.normal,
                ),
              ),
            ),
            Checkbox(
              value: checked,
              onChanged: (_) => onTap(),
              activeColor: AppColors.rh,
              materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
          ],
        ),
      ),
    );
  }
}
