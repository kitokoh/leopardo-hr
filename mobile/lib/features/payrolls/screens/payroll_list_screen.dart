import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/features/payrolls/providers/payroll_provider.dart';

class PayrollListScreen extends ConsumerWidget {
  const PayrollListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final payrollsAsync = ref.watch(payrollsProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Mes Fiches de Paie',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.refresh(payrollsProvider.future),
        child: payrollsAsync.when(
          data: (payrolls) => payrolls.isEmpty
              ? ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: const [
                    SizedBox(height: 80),
                    EmptyState(
                      icon: Icons.description,
                      title: 'Aucune fiche de paie',
                      description:
                          'Vos fiches de paie apparaîtront ici dès qu\'elles seront validées.',
                    ),
                  ],
                )
              : ListView.builder(
                padding: const EdgeInsets.all(20),
                itemCount: payrolls.length,
                itemBuilder: (context, index) {
                  final payroll = payrolls[index];
                  return Card(
                    color: AppColors.cardDark,
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(
                        'Mois: ${payroll.month}/${payroll.year}',
                        style: AppTypography.subtitle.copyWith(
                          color: AppColors.textDark,
                        ),
                      ),
                      subtitle: Text(
                        'Salaire Net: ${payroll.netSalary} DZD',
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textMutedDark,
                        ),
                      ),
                      trailing: const Icon(
                        Icons.download,
                        color: AppColors.info,
                      ),
                    ),
                  );
                },
              ),
          loading: () => const Center(
            child: CircularProgressIndicator(
              semanticsLabel: 'Chargement des fiches de paie...',
            ),
          ),
          error: (e, _) => Center(
          child: Text(e.toString(), style: const TextStyle(color: Colors.red)),
          ),
        ),
      ),
    );
  }
}
