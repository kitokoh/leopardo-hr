import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_manager/features/evaluations/providers/evaluation_provider.dart';

class EvaluationListScreen extends ConsumerWidget {
  const EvaluationListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final evaluationsAsync = ref.watch(evaluationsProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Mes Ã‰valuations',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: evaluationsAsync.when(
        data: (evaluations) => evaluations.isEmpty
            ? const EmptyState(
                icon: Icons.assignment_turned_in,
                title: 'Aucune Ã©valuation',
                description:
                    'Vous n\'avez pas encore d\'Ã©valuation enregistrÃ©e.',
              )
            : ListView.builder(
                padding: const EdgeInsets.all(20),
                itemCount: evaluations.length,
                itemBuilder: (context, index) {
                  final evaluation = evaluations[index];
                  return GlassCard(
                    color: AppColors.cardDark,
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(
                        'PÃ©riode: ${evaluation.period}',
                        style: AppTypography.subtitle.copyWith(
                          color: AppColors.textDark,
                        ),
                      ),
                      subtitle: Text(
                        'Score: ${evaluation.score ?? "N/A"}',
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textMutedDark,
                        ),
                      ),
                      trailing: Text(
                        evaluation.status,
                        style: TextStyle(
                          color: _getStatusColor(evaluation.status),
                        ),
                      ),
                    ),
                  );
                },
              ),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Text(
            e.toString(),
            style: const TextStyle(color: AppColors.danger),
          ),
        ),
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'acknowledged':
        return AppColors.rh;
      case 'submitted':
        return AppColors.info;
      case 'draft':
        return AppColors.textMutedDark;
      default:
        return AppColors.textMutedDark;
    }
  }
}

