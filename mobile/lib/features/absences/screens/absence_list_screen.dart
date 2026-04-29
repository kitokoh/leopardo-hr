import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/features/absences/providers/absence_provider.dart';

class AbsenceListScreen extends ConsumerWidget {
  const AbsenceListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final absencesAsync = ref.watch(absencesProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Mes Absences',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: absencesAsync.when(
        data: (absences) => absences.isEmpty
            ? const EmptyState(
                icon: Icons.calendar_today,
                title: 'Aucune absence',
                description:
                    'Vous n\'avez pas encore fait de demande d\'absence.',
              )
            : ListView.builder(
                padding: const EdgeInsets.all(20),
                itemCount: absences.length,
                itemBuilder: (context, index) {
                  final absence = absences[index];
                  return Card(
                    color: AppColors.cardDark,
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(
                        absence.absenceTypeName ?? 'Absence',
                        style: AppTypography.subtitle.copyWith(
                          color: AppColors.textDark,
                        ),
                      ),
                      subtitle: Text(
                        '${absence.startDate.day}/${absence.startDate.month} - ${absence.endDate.day}/${absence.endDate.month}',
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textMutedDark,
                        ),
                      ),
                      trailing: Text(
                        absence.status,
                        style: TextStyle(
                          color: _getStatusColor(absence.status),
                        ),
                      ),
                    ),
                  );
                },
              ),
        loading: () => const Center(
          child: CircularProgressIndicator(
            semanticsLabel: 'Chargement des absences...',
          ),
        ),
        error: (e, _) => Center(
          child: Text(e.toString(), style: const TextStyle(color: Colors.red)),
        ),
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'approved':
        return AppColors.rh;
      case 'pending':
        return AppColors.info;
      case 'rejected':
        return Colors.red;
      default:
        return AppColors.textMutedDark;
    }
  }
}
