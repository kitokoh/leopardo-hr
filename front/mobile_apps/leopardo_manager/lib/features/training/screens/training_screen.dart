import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_manager/features/training/providers/training_provider.dart';

class TrainingScreen extends ConsumerWidget {
  const TrainingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final enrollmentsAsync = ref.watch(trainingEnrollmentsProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Mes Formations',
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
            () async => await ref.refresh(trainingEnrollmentsProvider.future),
        child: enrollmentsAsync.when(
          data:
              (enrollments) =>
                  enrollments.isEmpty
                      ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [
                          SizedBox(height: 80),
                          EmptyState(
                            icon: Icons.school_outlined,
                            title: 'Aucune formation',
                            description:
                                'Vos inscriptions aux formations apparaitront ici.',
                          ),
                        ],
                      )
                      : ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(20),
                        itemCount: enrollments.length,
                        itemBuilder: (context, index) {
                          final enrollment = enrollments[index];
                          return Card(
                            color: AppColors.cardDark,
                            margin: const EdgeInsets.only(bottom: 12),
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    enrollment.courseTitle,
                                    style: AppTypography.subtitle.copyWith(
                                      color: AppColors.textDark,
                                    ),
                                  ),
                                  if (enrollment.sessionDate != null) ...[
                                    const SizedBox(height: 4),
                                    Text(
                                      'Session: ${enrollment.sessionDate}',
                                      style: AppTypography.bodySmall.copyWith(
                                        color: AppColors.textMutedDark,
                                      ),
                                    ),
                                  ],
                                  const SizedBox(height: 12),
                                  Row(
                                    children: [
                                      Expanded(
                                        child: ClipRRect(
                                          borderRadius: BorderRadius.circular(
                                            4,
                                          ),
                                          child: LinearProgressIndicator(
                                            value: enrollment.progress / 100,
                                            backgroundColor:
                                                AppColors.borderDark,
                                            valueColor:
                                                const AlwaysStoppedAnimation<
                                                  Color
                                                >(AppColors.success),
                                            minHeight: 6,
                                          ),
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Text(
                                        '${enrollment.progress}%',
                                        style: AppTypography.bodySmall.copyWith(
                                          color: AppColors.textMutedDark,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  _StatusChip(status: enrollment.status),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          loading:
              () => const SingleChildScrollView(
                physics: AlwaysScrollableScrollPhysics(),
                child: SizedBox(
                  height: 400,
                  child: Center(
                    child: CircularProgressIndicator(
                      semanticsLabel: 'Chargement des formations...',
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

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'completed' => AppColors.success,
      'in_progress' => AppColors.info,
      'dropped' => AppColors.danger,
      _ => AppColors.warning,
    };
    final label = switch (status) {
      'completed' => 'Termine',
      'in_progress' => 'En cours',
      'enrolled' => 'Inscrit',
      'dropped' => 'Abandonne',
      _ => status,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
