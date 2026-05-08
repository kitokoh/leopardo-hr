import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/features/evaluations/providers/evaluation_provider.dart';

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
          'Mes Évaluations',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.refresh(evaluationsProvider.future),
        child: evaluationsAsync.when(
          data:
              (evaluations) =>
                  evaluations.isEmpty
                      ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [
                          SizedBox(height: 80),
                          EmptyState(
                            icon: Icons.assignment_turned_in,
                            title: 'Aucune évaluation',
                            description:
                                'Vous n\'avez pas encore d\'évaluation enregistrée.',
                          ),
                        ],
                      )
                      : ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(20),
                        itemCount: evaluations.length,
                        itemBuilder: (context, index) {
                          final evaluation = evaluations[index];
                          final period = evaluation.period;
                          final score = evaluation.score?.toString() ?? "non définie";
                          final status = _getStatusLabel(evaluation.status);

                          return Semantics(
                            label:
                                'Évaluation pour la période $period, score $score, statut $status.',
                            container: true,
                            child: ExcludeSemantics(
                              child: Card(
                                color: AppColors.cardDark,
                                margin: const EdgeInsets.only(bottom: 12),
                                child: ListTile(
                                  title: Text(
                                    'Période: $period',
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
                              ),
                            ),
                          );
                        },
                      ),
          loading:
              () => const Center(
                child: CircularProgressIndicator(
                  semanticsLabel: 'Chargement des évaluations...',
                ),
              ),
        error:
            (e, _) => Center(
              child: Text(
                e.toString(),
                style: const TextStyle(color: Colors.red),
              ),
            ),
        ),
      ),
    );
  }

  String _getStatusLabel(String status) {
    switch (status) {
      case 'acknowledged':
        return 'reçue';
      case 'submitted':
        return 'soumise';
      case 'draft':
        return 'brouillon';
      default:
        return status;
    }
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
