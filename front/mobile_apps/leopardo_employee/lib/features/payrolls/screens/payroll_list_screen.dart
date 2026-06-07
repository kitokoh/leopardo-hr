import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/payment_document.dart';
import 'package:leopardo_core/models/payroll_balance.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_employee/features/payrolls/providers/payroll_provider.dart';
import 'package:url_launcher/url_launcher.dart';

class PayrollListScreen extends ConsumerStatefulWidget {
  const PayrollListScreen({super.key});

  @override
  ConsumerState<PayrollListScreen> createState() => _PayrollListScreenState();
}

class _PayrollListScreenState extends ConsumerState<PayrollListScreen> {
  int? _downloadingId;
  int? _downloadingDocumentId;

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

  Future<void> _downloadPaymentDocument(PaymentDocument document) async {
    setState(() => _downloadingDocumentId = document.id);
    try {
      final repo = ref.read(payrollRepositoryProvider);
      final path = await repo.downloadPaymentDocument(document);
      if (!mounted) return;

      final uri = Uri.file(path);
      final canLaunch = await canLaunchUrl(uri);
      if (!mounted) return;

      if (canLaunch) {
        await launchUrl(uri);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Document telecharge: $path'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Document indisponible: ${e.toString()}'),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _downloadingDocumentId = null);
    }
  }

  Future<void> _refresh() async {
    await Future.wait([
      ref.refresh(payrollBalanceProvider.future),
      ref.refresh(payrollsProvider.future),
      ref.refresh(paymentDocumentsProvider.future),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final payrollsAsync = ref.watch(payrollsProvider);
    final balanceAsync = ref.watch(payrollBalanceProvider);
    final documentsAsync = ref.watch(paymentDocumentsProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Paie et solde',
        subtitle: 'Solde courant, avances et bulletins',
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
              (payrolls) => ListView.builder(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                itemCount: payrolls.isEmpty ? 2 : payrolls.length + 1,
                itemBuilder: (context, index) {
                  if (index == 0) {
                    return Column(
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(bottom: 16),
                          child: _BalanceCard(balanceAsync: balanceAsync),
                        ),
                        Padding(
                          padding: const EdgeInsets.only(bottom: 16),
                          child: _PaymentDocumentsCard(
                            documentsAsync: documentsAsync,
                            downloadingId: _downloadingDocumentId,
                            onDownload: _downloadPaymentDocument,
                          ),
                        ),
                      ],
                    );
                  }

                  if (payrolls.isEmpty) {
                    return const Padding(
                      padding: EdgeInsets.only(top: 56),
                      child: EmptyState(
                        icon: Icons.description,
                        title: 'Aucune fiche de paie',
                        description:
                            'Vos fiches de paie apparaitront ici des qu elles seront validees.',
                      ),
                    );
                  }

                  final payroll = payrolls[index - 1];
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
                                '${payroll.netSalary.toStringAsFixed(2)} ${payroll.currency} net',
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
                },
              ),
          loading:
              () => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  _BalanceCard(balanceAsync: balanceAsync),
                  const SizedBox(height: 16),
                  _PaymentDocumentsCard(
                    documentsAsync: documentsAsync,
                    downloadingId: _downloadingDocumentId,
                    onDownload: _downloadPaymentDocument,
                  ),
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
                  _BalanceCard(balanceAsync: balanceAsync),
                  const SizedBox(height: 16),
                  _PaymentDocumentsCard(
                    documentsAsync: documentsAsync,
                    downloadingId: _downloadingDocumentId,
                    onDownload: _downloadPaymentDocument,
                  ),
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

class _PaymentDocumentsCard extends StatelessWidget {
  const _PaymentDocumentsCard({
    required this.documentsAsync,
    required this.downloadingId,
    required this.onDownload,
  });

  final AsyncValue<List<PaymentDocument>> documentsAsync;
  final int? downloadingId;
  final ValueChanged<PaymentDocument> onDownload;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      child: documentsAsync.when(
        data: (documents) {
          final visible = documents.take(4).toList();
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const MobileIconBubble(
                    icon: Icons.folder_copy_outlined,
                    color: AppColors.info,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Documents paiement',
                      style: AppTypography.bodySmall.copyWith(
                        color: MobileSurface.text,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  MobileStatusPill(
                    label: '${documents.length}',
                    color: AppColors.info,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              if (visible.isEmpty)
                Text(
                  'Aucun recu ou bordereau disponible pour le moment.',
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.secondary,
                  ),
                )
              else
                ...visible.map(
                  (document) => _PaymentDocumentTile(
                    document: document,
                    isDownloading: downloadingId == document.id,
                    onDownload: onDownload,
                  ),
                ),
            ],
          );
        },
        loading:
            () => const LinearProgressIndicator(
              minHeight: 3,
              color: AppColors.rh,
              backgroundColor: MobileSurface.surface,
            ),
        error:
            (_, __) => Text(
              'Documents temporairement indisponibles',
              style: AppTypography.caption.copyWith(
                color: MobileSurface.secondary,
              ),
            ),
      ),
    );
  }
}

class _PaymentDocumentTile extends StatelessWidget {
  const _PaymentDocumentTile({
    required this.document,
    required this.isDownloading,
    required this.onDownload,
  });

  final PaymentDocument document;
  final bool isDownloading;
  final ValueChanged<PaymentDocument> onDownload;

  @override
  Widget build(BuildContext context) {
    final color = switch (document.status) {
      'available' => AppColors.success,
      'failed' => AppColors.danger,
      'generating' => AppColors.warning,
      _ => MobileSurface.secondary,
    };

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        children: [
          Icon(Icons.description_outlined, size: 18, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  document.typeLabel,
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  document.statusLabel,
                  style: AppTypography.caption.copyWith(color: color),
                ),
              ],
            ),
          ),
          if (isDownloading)
            const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          else
            IconButton(
              tooltip: 'Telecharger',
              icon: const Icon(Icons.download_outlined),
              color:
                  document.isAvailable
                      ? AppColors.info
                      : MobileSurface.secondary,
              onPressed:
                  document.isAvailable ? () => onDownload(document) : null,
            ),
        ],
      ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.balanceAsync});

  final AsyncValue<PayrollBalance> balanceAsync;

  @override
  Widget build(BuildContext context) {
    return balanceAsync.when(
      data:
          (balance) => MobilePanel(
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
                            'Mon solde',
                            style: AppTypography.bodySmall.copyWith(
                              color: MobileSurface.text,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            '${balance.periodStart} - ${balance.periodEnd}',
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
                  label: 'Reste a recevoir',
                  value: balance.remaining,
                  currency: balance.currency,
                  color: AppColors.rh,
                ),
                const SizedBox(height: 8),
                _MoneyLine(
                  label: 'Avances deduites',
                  value: balance.advances,
                  currency: balance.currency,
                  color: AppColors.warning,
                ),
                const SizedBox(height: 8),
                _MoneyLine(
                  label: 'Deja paye',
                  value: balance.paid,
                  currency: balance.currency,
                  color: AppColors.info,
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
              'Solde temporairement indisponible',
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
    required this.currency,
    required this.color,
  });

  final String label;
  final double value;
  final String currency;
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
          '${value.toStringAsFixed(0)} $currency',
          style: AppTypography.bodySmall.copyWith(
            color: color,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
