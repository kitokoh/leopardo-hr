import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_employee/features/payrolls/providers/payroll_provider.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
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
        final canLaunch = await canLaunchUrl(uri);
        if (!mounted) return;

        if (canLaunch) {
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
            backgroundColor: AppColors.danger,
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
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Fiches de paie',
        subtitle: 'Bulletins valides et exports PDF',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        color: AppColors.rh,
        backgroundColor: MobileSurface.background,
        onRefresh: () async => await ref.refresh(payrollsProvider.future),
        child: payrollsAsync.when(
          data:
              (payrolls) =>
                  payrolls.isEmpty
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
                          return MobilePanel(
                            margin: const EdgeInsets.only(bottom: 8),
                            child: Row(
                              children: [
                                const MobileIconBubble(
                                  icon: Icons.receipt_long_outlined,
                                  color: AppColors.rh,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Mois ${payroll.month}/${payroll.year}',
                                        style: AppTypography.bodySmall.copyWith(
                                          color: MobileSurface.text,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        '${payroll.netSalary.toStringAsFixed(2)} DZD net',
                                        style: AppTypography.caption.copyWith(
                                          color: MobileSurface.secondary,
                                        ),
                                      ),
                                      if (payroll.status == 'validated')
                                        const Padding(
                                          padding: EdgeInsets.only(top: 6),
                                          child: MobileStatusPill(
                                            label: 'Valide',
                                            color: AppColors.success,
                                            icon: Icons.check_circle,
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                                isDownloading
                                    ? const SizedBox(
                                      width: 24,
                                      height: 24,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        semanticsLabel:
                                            'Telechargement en cours...',
                                      ),
                                    )
                                    : IconButton(
                                      icon: const Icon(
                                        Icons.picture_as_pdf,
                                        color: AppColors.info,
                                      ),
                                      tooltip: 'Telecharger le bulletin PDF',
                                      onPressed:
                                          payroll.pdfPath != null
                                              ? () => _downloadPdf(payroll.id)
                                              : null,
                                    ),
                              ],
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
                      semanticsLabel: 'Chargement des fiches de paie...',
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
