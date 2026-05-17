import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/features/payrolls/providers/payroll_provider.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:url_launcher/url_launcher.dart';

class PayrollListScreen extends ConsumerStatefulWidget {
  const PayrollListScreen({super.key});

  @override
  ConsumerState<PayrollListScreen> createState() => _PayrollListScreenState();
}

class _PayrollListScreenState extends ConsumerState<PayrollListScreen> {
  int? _downloadingId;

  Future<void> _downloadPdf(int payslipId) async {
    setState(() => _downloadingId = payslipId);
    try {
      final repo = ref.read(payrollRepositoryProvider);
      final path = await repo.downloadPayslipPdf(payslipId);
      if (mounted) {
        final uri = Uri.file(path);
        if (await canLaunchUrl(uri)) {
          await launchUrl(uri);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('PDF téléchargé: $path'),
              backgroundColor: AppColors.success,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Erreur: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _downloadingId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
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
        onRefresh: () async => await ref.refresh(payrollsProvider.future),
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
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(20),
                  itemCount: payrolls.length,
                  itemBuilder: (context, index) {
                    final payroll = payrolls[index];
                    final isDownloading = _downloadingId == payroll.id;
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
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Salaire Net: ${payroll.netSalary.toStringAsFixed(2)} DZD',
                              style: AppTypography.bodySmall.copyWith(
                                color: AppColors.textMutedDark,
                              ),
                            ),
                            if (payroll.status == 'validated')
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Row(
                                  children: [
                                    const Icon(Icons.check_circle,
                                        size: 14, color: AppColors.success),
                                    const SizedBox(width: 4),
                                    Text(
                                      'Validé',
                                      style: AppTypography.bodySmall.copyWith(
                                        color: AppColors.success,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                          ],
                        ),
                        trailing: isDownloading
                            ? const SizedBox(
                                width: 24,
                                height: 24,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  semanticsLabel: 'Téléchargement en cours...',
                                ),
                              )
                            : IconButton(
                                icon: const Icon(Icons.picture_as_pdf,
                                    color: AppColors.info),
                                tooltip: 'Télécharger le bulletin PDF',
                                onPressed: payroll.pdfPath != null
                                    ? () => _downloadPdf(payroll.id)
                                    : null,
                              ),
                      ),
                    );
                  },
                ),
          loading: () => const SingleChildScrollView(
            physics: AlwaysScrollableScrollPhysics(),
            child: SizedBox(
              height: 400,
              child: Center(
                child: CircularProgressIndicator(
                  semanticsLabel: 'Chargement des fiches de paie...',
                ),
              ),
            ),
          ),
          error: (e, _) => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: SizedBox(
              height: 400,
              child: Center(
                child: Text(
                  e.toString(),
                  style: const TextStyle(color: Colors.red),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
