import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/core/widgets/mobile_surface.dart';
import 'package:leopardo_rh/features/absences/providers/absence_provider.dart';

class AbsenceListScreen extends ConsumerWidget {
  const AbsenceListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final absencesAsync = ref.watch(absencesProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Mes Absences',
        subtitle: 'Demandes et decisions RH',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: RefreshIndicator(
        color: AppColors.rh,
        backgroundColor: MobileSurface.background,
        onRefresh: () async => ref.refresh(absencesProvider.future),
        child: absencesAsync.when(
          data:
              (absences) =>
                  absences.isEmpty
                      ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [
                          SizedBox(height: 80),
                          EmptyState(
                            icon: Icons.calendar_today,
                            title: 'Aucune absence',
                            description:
                                'Vous n\'avez pas encore fait de demande d\'absence.',
                          ),
                        ],
                      )
                      : ListView.builder(
                        padding: const EdgeInsets.all(20),
                        itemCount: absences.length,
                        itemBuilder: (context, index) {
                          final absence = absences[index];
                          final color = _getStatusColor(absence.status);
                          return MobilePanel(
                            margin: const EdgeInsets.only(bottom: 8),
                            child: Row(
                              children: [
                                MobileIconBubble(
                                  icon: Icons.event_available_outlined,
                                  color: color,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        absence.absenceTypeName ?? 'Absence',
                                        style: AppTypography.bodySmall.copyWith(
                                          color: MobileSurface.text,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        '${absence.startDate.day}/${absence.startDate.month} - ${absence.endDate.day}/${absence.endDate.month}',
                                        style: AppTypography.caption.copyWith(
                                          color: MobileSurface.secondary,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                MobileStatusPill(
                                  label: absence.status,
                                  color: color,
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          loading:
              () => const Center(
                child: CircularProgressIndicator(
                  semanticsLabel: 'Chargement des absences...',
                ),
              ),
          error:
              (e, _) => Center(
                child: Text(
                  e.toString(),
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
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
        return AppColors.danger;
      default:
        return AppColors.textMutedDark;
    }
  }
}
