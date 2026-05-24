import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
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
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.refresh(salaryAdvancesProvider.future),
        child: advancesAsync.when(
          data:
              (advances) =>
                  advances.isEmpty
                      ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [
                          SizedBox(height: 80),
                          EmptyState(
                            icon: Icons.payments,
                            title: 'Aucune avance',
                            description:
                                'Vous n\'avez pas encore demandé d\'avance de salaire.',
                          ),
                        ],
                      )
                      : ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(20),
                        itemCount: advances.length,
                        itemBuilder: (context, index) {
                          final advance = advances[index];
                          final amount = '${advance.amount ?? 0} DZD';
                          final reason = advance.reason ?? 'Aucun motif';
                          final status = _getStatusLabel(advance.status);

                          return Semantics(
                            label:
                                'Avance de $amount, motif : $reason, statut $status.',
                            container: true,
                            child: ExcludeSemantics(
                              child: Card(
                                color: AppColors.cardDark,
                                margin: const EdgeInsets.only(bottom: 12),
                                child: ListTile(
                                  title: Text(
                                    amount,
                                    style: AppTypography.subtitle.copyWith(
                                      color: AppColors.textDark,
                                    ),
                                  ),
                                  subtitle: Text(
                                    reason,
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
                              ),
                            ),
                          );
                        },
                      ),
          loading:
              () => const Center(
                child: CircularProgressIndicator(
                  semanticsLabel: 'Chargement des avances...',
                ),
              ),
          error:
              (e, _) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  SizedBox(
                    height: MediaQuery.of(context).size.height * 0.4,
                    child: Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(
                          e.toString(),
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: AppColors.danger),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
        ),
      ),
    );
  }

  String _getStatusLabel(String status) {
    switch (status) {
      case 'active':
        return 'active';
      case 'approved':
        return 'approuvée';
      case 'pending':
        return 'en attente';
      case 'rejected':
        return 'rejetée';
      case 'cancelled':
        return 'annulée';
      default:
        return status;
    }
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'active':
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
