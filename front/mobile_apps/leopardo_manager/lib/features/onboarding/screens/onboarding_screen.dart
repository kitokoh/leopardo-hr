import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
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

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Guide de démarrage',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        color: AppColors.rh,
        backgroundColor: AppColors.cardDark,
        onRefresh: () async =>
            await ref.refresh(onboardingChecklistProvider.future),
        child: checklistAsync.when(
          data: (steps) {
            if (steps.isEmpty) {
              return ListView(
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

            final completed =
                steps.where((s) => s.completed || s.skipped).length;
            final total = steps.length;
            final progress = total > 0 ? completed / total : 0.0;
            final requiredDone =
                steps.where((s) => s.required && s.completed).length;
            final requiredTotal = steps.where((s) => s.required).length;
            final allRequiredDone = requiredDone >= requiredTotal;

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                // ── Hero progress card ─────────────────────────────────
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        AppColors.rh.withValues(alpha: 0.85),
                        AppColors.rh.withValues(alpha: 0.5),
                      ],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.rh.withValues(alpha: 0.3),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Progression',
                            style: AppTypography.subtitle.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              '$completed / $total',
                              style: AppTypography.bodySmall.copyWith(
                                color: Colors.white,
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
                                  backgroundColor:
                                      Colors.white.withValues(alpha: 0.25),
                                  valueColor:
                                      const AlwaysStoppedAnimation<Color>(
                                          Colors.white),
                                  minHeight: 10,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                '${(value * 100).round()}% complété',
                                style: AppTypography.bodySmall.copyWith(
                                  color: Colors.white.withValues(alpha: 0.85),
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
                            const Icon(Icons.verified,
                                color: Colors.greenAccent, size: 16),
                            const SizedBox(width: 6),
                            Text(
                              'Étapes obligatoires complètes !',
                              style: AppTypography.bodySmall.copyWith(
                                color: Colors.greenAccent,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),

                const SizedBox(height: 20),
                Text(
                  'Étapes',
                  style: AppTypography.subtitle
                      .copyWith(color: AppColors.textMutedDark),
                ),
                const SizedBox(height: 10),

                // ── Step cards ────────────────────────────────────────
                ...steps.map((step) {
                  final isDone = step.completed || step.skipped;
                  final isSkipped = step.skipped;

                  return AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    margin: const EdgeInsets.only(bottom: 10),
                    decoration: BoxDecoration(
                      color: isDone
                          ? AppColors.cardDark.withValues(alpha: 0.6)
                          : AppColors.cardDark,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: step.completed
                            ? AppColors.success.withValues(alpha: 0.6)
                            : isSkipped
                                ? AppColors.textMutedDark.withValues(alpha: 0.3)
                                : step.required
                                    ? AppColors.rh.withValues(alpha: 0.4)
                                    : Colors.transparent,
                        width: 1.5,
                      ),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
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
                                      ? AppColors.textMutedDark.withValues(alpha: 0.1)
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
                                      ? AppColors.textMutedDark
                                      : AppColors.rh,
                              size: 22,
                            ),
                          ),

                          const SizedBox(width: 12),

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
                                        style: AppTypography.subtitle.copyWith(
                                          color: isDone
                                              ? AppColors.textMutedDark
                                              : AppColors.textDark,
                                          decoration: isSkipped
                                              ? TextDecoration.lineThrough
                                              : null,
                                        ),
                                      ),
                                    ),
                                    if (step.required && !isDone)
                                      Container(
                                        margin: const EdgeInsets.only(left: 6),
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 6,
                                          vertical: 2,
                                        ),
                                        decoration: BoxDecoration(
                                          color: AppColors.rh.withValues(alpha: 0.15),
                                          borderRadius: BorderRadius.circular(4),
                                        ),
                                        child: Text(
                                          'Requis',
                                          style: AppTypography.bodySmall.copyWith(
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
                                      color: AppColors.textMutedDark,
                                    ),
                                  ),
                                ],
                                if (!isDone) ...[
                                  const SizedBox(height: 12),
                                  Row(
                                    children: [
                                      Expanded(
                                        child: _ActionButton(
                                          label: 'Marquer complété',
                                          icon: Icons.check,
                                          color: AppColors.success,
                                          onPressed: () => _complete(step.key),
                                        ),
                                      ),
                                      if (!step.required) ...[
                                        const SizedBox(width: 8),
                                        _ActionButton(
                                          label: 'Passer',
                                          icon: Icons.skip_next,
                                          color: AppColors.textMutedDark,
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
          loading: () => const Center(
            child: CircularProgressIndicator(
              semanticsLabel: 'Chargement de l\'onboarding...',
            ),
          ),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.error_outline,
                      color: AppColors.danger, size: 48),
                  const SizedBox(height: 16),
                  Text(
                    e.toString(),
                    style: AppTypography.bodySmall
                        .copyWith(color: AppColors.danger),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),
                  TextButton(
                    onPressed: () =>
                        ref.invalidate(onboardingChecklistProvider),
                    child: Text(
                      'Réessayer',
                      style: AppTypography.subtitle.copyWith(color: AppColors.rh),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
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
