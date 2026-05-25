import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/onboarding/providers/onboarding_provider.dart';

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
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Erreur : $e')));
      }
    }
  }

  Future<void> _skip(int stepId) async {
    try {
      await ref.read(onboardingRepositoryProvider).skipStep(stepId);
      ref.invalidate(onboardingChecklistProvider);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Erreur : $e')));
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
          'Onboarding',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh:
            () async => await ref.refresh(onboardingChecklistProvider.future),
        child: checklistAsync.when(
          data: (steps) {
            if (steps.isEmpty) {
              return ListView(
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
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.cardDark,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      Text(
                        'Progression',
                        style: AppTypography.subtitle.copyWith(
                          color: AppColors.textDark,
                        ),
                      ),
                      const SizedBox(height: 12),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(6),
                        child: LinearProgressIndicator(
                          value: total > 0 ? completed / total : 0,
                          backgroundColor: AppColors.borderDark,
                          valueColor: const AlwaysStoppedAnimation<Color>(
                            AppColors.success,
                          ),
                          minHeight: 10,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '$completed / $total etapes',
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textMutedDark,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                ...steps.map((step) {
                  final isDone = step.completed || step.skipped;
                  return Card(
                    color: AppColors.cardDark,
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: Icon(
                        isDone
                            ? Icons.check_circle
                            : Icons.radio_button_unchecked,
                        color:
                            isDone
                                ? AppColors.success
                                : AppColors.textMutedDark,
                      ),
                      title: Text(
                        step.title,
                        style: AppTypography.subtitle.copyWith(
                          color:
                              isDone
                                  ? AppColors.textMutedDark
                                  : AppColors.textDark,
                          decoration:
                              isDone ? TextDecoration.lineThrough : null,
                        ),
                      ),
                      subtitle:
                          step.description != null
                              ? Text(
                                step.description!,
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.textMutedDark,
                                ),
                              )
                              : null,
                      trailing:
                          isDone
                              ? null
                              : Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  IconButton(
                                    icon: const Icon(
                                      Icons.skip_next,
                                      color: AppColors.textMutedDark,
                                      size: 20,
                                    ),
                                    tooltip: 'Passer',
                                    onPressed: () => _skip(step.id),
                                  ),
                                  IconButton(
                                    icon: const Icon(
                                      Icons.check,
                                      color: AppColors.success,
                                      size: 20,
                                    ),
                                    tooltip: 'Terminer',
                                    onPressed: () => _complete(step.id),
                                  ),
                                ],
                              ),
                    ),
                  );
                }),
              ],
            );
          },
          loading:
              () => const SingleChildScrollView(
                physics: AlwaysScrollableScrollPhysics(),
                child: SizedBox(
                  height: 400,
                  child: Center(
                    child: CircularProgressIndicator(
                      semanticsLabel: 'Chargement de l\'onboarding...',
                    ),
                  ),
                ),
              ),
          error:
              (e, _) => SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: SizedBox(
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
      ),
    );
  }
}
