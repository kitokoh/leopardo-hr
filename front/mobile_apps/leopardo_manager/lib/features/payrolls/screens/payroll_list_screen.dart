import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/payroll_balance.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/payrolls/providers/payroll_provider.dart';
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
      if (!mounted) return;

      final uri = Uri.file(path);
      final canLaunch = await canLaunchUrl(uri);
      if (!mounted) return;

      if (canLaunch) {
        await launchUrl(uri);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('PDF telecharge: $path'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Erreur: ${e.toString()}'),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _downloadingId = null);
    }
  }

  Future<void> _refresh() async {
    await Future.wait([
      ref.refresh(payrollMobileSummaryProvider.future),
      ref.refresh(payrollsProvider.future),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final payrollsAsync = ref.watch(payrollsProvider);
    final summaryAsync = ref.watch(payrollMobileSummaryProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Paie equipe',
        subtitle: 'Soldes, avances et bulletins',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        color: AppColors.rh,
        backgroundColor: MobileSurface.background,
        onRefresh: _refresh,
        child: payrollsAsync.when(
          data:
              (payrolls) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  _SummaryCard(summaryAsync: summaryAsync),
                  const SizedBox(height: 16),
                  Text(
                    'Bulletins recents',
                    style: AppTypography.bodySmall.copyWith(
                      color: MobileSurface.text,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (payrolls.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: EmptyState(
                        icon: Icons.description,
                        title: 'Aucune fiche de paie',
                        description:
                            'Les bulletins valides apparaitront ici apres traitement.',
                      ),
                    )
                  else
                    ...payrolls.map((payroll) {
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
                                crossAxisAlignment: CrossAxisAlignment.start,
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
                                    semanticsLabel: 'Telechargement en cours',
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
                    }),
                ],
              ),
          loading:
              () => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  _SummaryCard(summaryAsync: summaryAsync),
                  const SizedBox(height: 120),
                  const Center(
                    child: CircularProgressIndicator(
                      semanticsLabel: 'Chargement des fiches de paie',
                    ),
                  ),
                ],
              ),
          error:
              (e, _) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  _SummaryCard(summaryAsync: summaryAsync),
                  const SizedBox(height: 120),
                  Text(
                    e.toString(),
                    style: const TextStyle(color: AppColors.danger),
                  ),
                ],
              ),
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summaryAsync});

  final AsyncValue<PayrollMobileSummary> summaryAsync;

  @override
  Widget build(BuildContext context) {
    return summaryAsync.when(
      data:
          (summary) => MobilePanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const MobileIconBubble(
                      icon: Icons.account_balance_wallet_outlined,
                      color: AppColors.rh,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Solde equipe',
                            style: AppTypography.bodySmall.copyWith(
                              color: MobileSurface.text,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            '${summary.items.length} collaborateur(s)',
                            style: AppTypography.caption.copyWith(
                              color: MobileSurface.secondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _MoneyLine(
                  label: 'Reste a payer',
                  value: summary.remaining,
                  color: AppColors.rh,
                ),
                const SizedBox(height: 8),
                _MoneyLine(
                  label: 'Avances deduites',
                  value: summary.advances,
                  color: AppColors.warning,
                ),
                const SizedBox(height: 12),
                ...summary.items
                    .take(5)
                    .map(
                      (item) => Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                item.employeeName,
                                style: AppTypography.caption.copyWith(
                                  color: MobileSurface.secondary,
                                ),
                              ),
                            ),
                            Text(
                              '${item.remaining.toStringAsFixed(0)} ${item.currency}',
                              style: AppTypography.caption.copyWith(
                                color: MobileSurface.text,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
              ],
            ),
          ),
      loading:
          () => const MobilePanel(
            child: LinearProgressIndicator(
              minHeight: 3,
              color: AppColors.rh,
              backgroundColor: MobileSurface.surface,
            ),
          ),
      error:
          (_, __) => const MobilePanel(
            child: Text(
              'Resume paie temporairement indisponible',
              style: TextStyle(color: MobileSurface.secondary),
            ),
          ),
    );
  }
}

class _MoneyLine extends StatelessWidget {
  const _MoneyLine({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final double value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: AppTypography.caption.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
        ),
        Text(
          value.toStringAsFixed(0),
          style: AppTypography.bodySmall.copyWith(
            color: color,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
