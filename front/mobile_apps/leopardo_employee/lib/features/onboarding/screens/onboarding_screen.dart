import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_employee/features/onboarding/providers/onboarding_provider.dart';

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  Future<void> _complete(int stepId) async {
    try {
      await ref.read(onboardingRepositoryProvider).completeStep(stepId);
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

  Future<void> _skip(int stepId) async {
    try {
      await ref.read(onboardingRepositoryProvider).skipStep(stepId);
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
      title: 'Onboarding',
      showBackButton: true,
      onBack: () => context.pop(),
      children: [
        RefreshIndicator(
          onRefresh: () async =>
              await ref.refresh(onboardingChecklistProvider.future),
          color: AppColors.rh,
          backgroundColor: MobileSurface.card,
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
                      title: 'Onboarding termine !',
                      description: 'Toutes les etapes ont ete completees.',
                    ),
                  ],
                );
              }
              final completed =
                  steps.where((s) => s.completed || s.skipped).length;
              final total = steps.length;
              final progress = total > 0 ? completed / total : 0.0;

              return ListView(
                shrinkWrap: true,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 20,
                ),
                children: [
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
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.rh.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Text(
                                '${(progress * 100).toInt()}%',
                                style: AppTypography.caption.copyWith(
                                  color: AppColors.rh,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: LinearProgressIndicator(
                            value: progress,
                            backgroundColor: MobileSurface.border,
                            valueColor: const AlwaysStoppedAnimation<Color>(
                              AppColors.rh,
                            ),
                            minHeight: 8,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '$completed sur $total etapes',
                          style: AppTypography.bodySmall.copyWith(
                            color: MobileSurface.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Etapes a completer',
                    style: AppTypography.subtitle.copyWith(
                      color: MobileSurface.text,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 12),
                  ...steps.map((step) {
                    final isDone = step.completed || step.skipped;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: GlassCard(
                        padding: EdgeInsets.zero,
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 8,
                          ),
                          leading: Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: isDone
                                  ? AppColors.success.withValues(alpha: 0.15)
                                  : MobileSurface.border.withValues(alpha: 0.5),
                              shape: BoxShape.circle,
                            ),
                            child: Icon(
                              isDone ? Icons.check : Icons.flag_outlined,
                              color: isDone
                                  ? AppColors.success
                                  : MobileSurface.text,
                              size: 20,
                            ),
                          ),
                          title: Text(
                            step.title,
                            style: AppTypography.body.copyWith(
                              color: isDone
                                  ? MobileSurface.muted
                                  : MobileSurface.text,
                              fontWeight:
                                  isDone ? FontWeight.normal : FontWeight.w600,
                              decoration:
                                  isDone ? TextDecoration.lineThrough : null,
                            ),
                          ),
                          subtitle: step.description != null
                              ? Padding(
                                  padding: const EdgeInsets.only(top: 4),
                                  child: Text(
                                    step.description!,
                                    style: AppTypography.bodySmall.copyWith(
                                      color: MobileSurface.muted,
                                    ),
                                  ),
                                )
                              : null,
                          trailing: isDone
                              ? null
                              : Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    IconButton(
                                      icon: const Icon(
                                        Icons.skip_next_rounded,
                                        color: MobileSurface.muted,
                                      ),
                                      tooltip: 'Passer',
                                      onPressed: () => _skip(step.id),
                                    ),
                                    Container(
                                      decoration: BoxDecoration(
                                        color: AppColors.success.withValues(
                                          alpha: 0.1,
                                        ),
                                        shape: BoxShape.circle,
                                      ),
                                      child: IconButton(
                                        icon: const Icon(
                                          Icons.check_rounded,
                                          color: AppColors.success,
                                        ),
                                        tooltip: 'Terminer',
                                        onPressed: () => _complete(step.id),
                                      ),
                                    ),
                                  ],
                                ),
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
            error: (e, _) => SizedBox(
              height: 400,
              child: Center(
                child: Text(
                  e.toString(),
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
