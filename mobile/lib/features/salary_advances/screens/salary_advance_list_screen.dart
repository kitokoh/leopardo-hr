import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/features/salary_advances/providers/salary_advance_provider.dart';

class SalaryAdvanceListScreen extends ConsumerWidget {
  const SalaryAdvanceListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final advancesAsync = ref.watch(salaryAdvancesProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Mes Avances',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: advancesAsync.when(
        data:
            (advances) =>
                advances.isEmpty
                    ? const EmptyState(
                      icon: Icons.payments,
                      title: 'Aucune avance',
                      description:
                          'Vous n\'avez pas encore demandé d\'avance de salaire.',
                    )
                    : ListView.builder(
                      padding: const EdgeInsets.all(20),
                      itemCount: advances.length,
                      itemBuilder: (context, index) {
                        final advance = advances[index];
                        return Card(
                          color: AppColors.cardDark,
                          margin: const EdgeInsets.only(bottom: 12),
                          child: ListTile(
                            title: Text(
                              '${advance.amount} DZD',
                              style: AppTypography.subtitle.copyWith(
                                color: AppColors.textDark,
                              ),
                            ),
                            subtitle: Text(
                              advance.reason ?? 'Aucun motif',
                              style: AppTypography.bodySmall.copyWith(
                                color: AppColors.textMutedDark,
                              ),
                            ),
                            trailing: Text(
                              advance.status,
                              style: TextStyle(
                                color: _getStatusColor(advance.status),
                              ),
                            ),
                          ),
                        );
                      },
                    ),
        loading: () => const Center(child: CircularProgressIndicator()),
        error:
            (e, _) => Center(
              child: Text(
                e.toString(),
                style: const TextStyle(color: Colors.red),
              ),
            ),
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'active':
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
