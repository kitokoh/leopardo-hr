import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/onboarding/providers/onboarding_provider.dart';

/// Icons associated with each step key for the wizard UI.
const _stepIcons = <String, IconData>{
  'company_info': Icons.business_outlined,
  'first_department': Icons.account_tree_outlined,
  'first_employee': Icons.person_add_outlined,
  'first_attendance': Icons.fingerprint_outlined,
  'invite_manager': Icons.group_add_outlined,
  'configure_schedules': Icons.schedule_outlined,
  'first_report': Icons.bar_chart_outlined,
  'configure_payroll': Icons.payments_outlined,
  'install_kiosk': Icons.tablet_android_outlined,
  'activate_geofence': Icons.location_on_outlined,
};

IconData _iconFor(String key) => _stepIcons[key] ?? Icons.check_circle_outline;

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _progressController;

  @override
  void initState() {
    super.initState();
    _progressController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
  }

  @override
  void dispose() {
    _progressController.dispose();
    super.dispose();
  }

  Future<void> _complete(String stepKey) async {
    try {
      await ref.read(onboardingRepositoryProvider).completeStep(stepKey);
      ref.invalidate(onboardingChecklistProvider);
      _progressController.forward(from: 0);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur : $e'),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    }
  }

  Future<void> _skip(String stepKey) async {
    try {
      await ref.read(onboardingRepositoryProvider).skipStep(stepKey);
      ref.invalidate(onboardingChecklistProvider);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur : $e'),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final checklistAsync = ref.watch(onboardingChecklistProvider);

    return MobilePage(
      title: 'Guide de démarrage',
      showBackButton: true,
      onBack: () => context.pop(),
      children: [
        RefreshIndicator(
          color: AppColors.rh,
          backgroundColor: MobileSurface.card,
          onRefresh: () async =>
              await ref.refresh(onboardingChecklistProvider.future),
          child: checklistAsync.when(
            data: (steps) {
              if (steps.isEmpty) {
                return ListView(
                  shrinkWrap: true,
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: const [
                    SizedBox(height: 80),
                    EmptyState(
                      icon: Icons.rocket_launch_outlined,
                      title: 'Onboarding terminé !',
                      description: 'Toutes les étapes ont été complétées.',
                    ),
                  ],
                );
              }

              final completed = steps
                  .where((s) => s.completed || s.skipped)
                  .length;
              final total = steps.length;
              final progress = total > 0 ? completed / total : 0.0;
              final requiredDone = steps
                  .where((s) => s.required && s.completed)
                  .length;
              final requiredTotal = steps.where((s) => s.required).length;
              final allRequiredDone = requiredDone >= requiredTotal;

              return ListView(
                shrinkWrap: true,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 20,
                ),
                children: [
                  // ── Hero progress card ─────────────────────────────────
                  GlassCard(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Progression',
                              style: AppTypography.subtitle.copyWith(
                                color: MobileSurface.text,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.rh.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Text(
                                '$completed / $total',
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.rh,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        TweenAnimationBuilder<double>(
                          tween: Tween(begin: 0, end: progress),
                          duration: const Duration(milliseconds: 800),
                          curve: Curves.easeOut,
                          builder: (context, value, _) {
                            return Column(
                              children: [
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(6),
                                  child: LinearProgressIndicator(
                                    value: value,
                                    backgroundColor: MobileSurface.border,
                                    valueColor:
                                        const AlwaysStoppedAnimation<Color>(
                                          AppColors.rh,
                                        ),
                                    minHeight: 8,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  '${(value * 100).round()}% complété',
                                  style: AppTypography.bodySmall.copyWith(
                                    color: MobileSurface.muted,
                                  ),
                                ),
                              ],
                            );
                          },
                        ),
                        if (allRequiredDone && completed < total) ...[
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              const Icon(
                                Icons.verified,
                                color: AppColors.success,
                                size: 16,
                              ),
                              const SizedBox(width: 6),
                              Text(
                                'Étapes obligatoires complètes !',
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.success,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),
                  Text(
                    'Étapes à compléter',
                    style: AppTypography.subtitle.copyWith(
                      color: MobileSurface.text,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 12),

                  // ── Step cards ────────────────────────────────────────
                  ...steps.map((step) {
                    final isDone = step.completed || step.skipped;
                    final isSkipped = step.skipped;

                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: GlassCard(
                        padding: const EdgeInsets.all(16),
                        borderColor: step.completed
                            ? AppColors.success.withValues(alpha: 0.6)
                            : isSkipped
                            ? MobileSurface.border.withValues(alpha: 0.3)
                            : step.required
                            ? AppColors.rh.withValues(alpha: 0.4)
                            : MobileSurface.border,
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Step icon
                            Container(
                              width: 42,
                              height: 42,
                              decoration: BoxDecoration(
                                color: step.completed
                                    ? AppColors.success.withValues(alpha: 0.15)
                                    : isSkipped
                                    ? MobileSurface.muted.withValues(alpha: 0.1)
                                    : AppColors.rh.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Icon(
                                step.completed
                                    ? Icons.check_circle
                                    : isSkipped
                                    ? Icons.skip_next
                                    : _iconFor(step.key),
                                color: step.completed
                                    ? AppColors.success
                                    : isSkipped
                                    ? MobileSurface.muted
                                    : AppColors.rh,
                                size: 22,
                              ),
                            ),

                            const SizedBox(width: 16),

                            // Text content
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          step.title,
                                          style: AppTypography.subtitle
                                              .copyWith(
                                                color: isDone
                                                    ? MobileSurface.muted
                                                    : MobileSurface.text,
                                                fontWeight: isDone
                                                    ? FontWeight.normal
                                                    : FontWeight.w600,
                                                decoration: isSkipped
                                                    ? TextDecoration.lineThrough
                                                    : null,
                                              ),
                                        ),
                                      ),
                                      if (step.required && !isDone)
                                        Container(
                                          margin: const EdgeInsets.only(
                                            left: 6,
                                          ),
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 6,
                                            vertical: 2,
                                          ),
                                          decoration: BoxDecoration(
                                            color: AppColors.rh.withValues(
                                              alpha: 0.15,
                                            ),
                                            borderRadius: BorderRadius.circular(
                                              4,
                                            ),
                                          ),
                                          child: Text(
                                            'Requis',
                                            style: AppTypography.bodySmall
                                                .copyWith(
                                                  color: AppColors.rh,
                                                  fontSize: 10,
                                                ),
                                          ),
                                        ),
                                    ],
                                  ),
                                  if (step.description != null) ...[
                                    const SizedBox(height: 4),
                                    Text(
                                      step.description!,
                                      style: AppTypography.bodySmall.copyWith(
                                        color: MobileSurface.muted,
                                      ),
                                    ),
                                  ],
                                  if (!isDone) ...[
                                    const SizedBox(height: 16),
                                    Row(
                                      children: [
                                        Expanded(
                                          child: _ActionButton(
                                            label: 'Marquer complété',
                                            icon: Icons.check,
                                            color: AppColors.success,
                                            onPressed: () =>
                                                _complete(step.key),
                                          ),
                                        ),
                                        if (!step.required) ...[
                                          const SizedBox(width: 8),
                                          _ActionButton(
                                            label: 'Passer',
                                            icon: Icons.skip_next,
                                            color: MobileSurface.muted,
                                            onPressed: () => _skip(step.key),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                ],
              );
            },
            loading: () => const SizedBox(
              height: 400,
              child: Center(
                child: CircularProgressIndicator(
                  color: AppColors.rh,
                  semanticsLabel: 'Chargement de l\'onboarding...',
                ),
              ),
            ),
            error: (e, _) => Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.error_outline,
                      color: AppColors.danger,
                      size: 48,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      e.toString(),
                      style: AppTypography.bodySmall.copyWith(
                        color: AppColors.danger,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    TextButton(
                      onPressed: () =>
                          ref.invalidate(onboardingChecklistProvider),
                      child: Text(
                        'Réessayer',
                        style: AppTypography.subtitle.copyWith(
                          color: AppColors.rh,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.label,
    required this.icon,
    required this.color,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 15),
            const SizedBox(width: 5),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
