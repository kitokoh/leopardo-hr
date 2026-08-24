import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/payroll_compliance_pill.dart';
import 'package:leopardo_core/models/payment_document.dart';
import 'package:leopardo_core/models/payroll_balance.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_employee/features/payrolls/providers/payroll_provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/core/utils/currency_format.dart';
import 'package:leopardo_core/l10n/l10n.dart';

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
            content: Text(context.l10n.payrollPdfDownloaded(path)),
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
            content: Text(context.l10n.payrollDocumentDownloaded(path)),
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
        title: context.l10n.payrollTitle,
        subtitle: context.l10n.payrollSubtitle,
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
          data: (payrolls) => ListView.builder(
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
                return Padding(
                  padding: const EdgeInsets.only(top: 56),
                  child: EmptyState(
                    icon: Icons.description,
                    title: context.l10n.emptyPayslips,
                    description: context.l10n.payrollEmptyHint,
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
                            context.l10n
                                .payrollMonthLabel(payroll.month, payroll.year),
                            style: AppTypography.bodySmall.copyWith(
                              color: MobileSurface.text,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${NumberFormat.decimalPattern(deviceIntlNumberLocale).format(payroll.netSalary)}${currencySuffix(payroll.currency)} net',
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
                          // Issue #2143 — indicateur de conformité paie
                          // (rétro-compatible : rien si le bloc est absent).
                          if (payroll.compliance != null)
                            Padding(
                              padding: const EdgeInsets.only(top: 6),
                              child: PayrollCompliancePill(
                                compliance: payroll.compliance!,
                                countryCode: payroll.countryCode,
                              ),
                            ),
                        ],
                      ),
                    ),
                    isDownloading
                        ? SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              semanticsLabel: context.l10n.payrollDownloading,
                            ),
                          )
                        : IconButton(
                            icon: const Icon(
                              Icons.picture_as_pdf,
                              color: AppColors.info,
                            ),
                            tooltip: context.l10n.payrollDownloadPayslip,
                            onPressed: payroll.pdfPath != null
                                ? () => _downloadPdf(payroll.id)
                                : null,
                          ),
                  ],
                ),
              );
            },
          ),
          loading: () => ListView(
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
              Center(
                child: CircularProgressIndicator(
                  semanticsLabel: context.l10n.payrollLoading,
                ),
              ),
            ],
          ),
          error: (e, _) => ListView(
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
                      context.l10n.payrollPaymentDocuments,
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
                  context.l10n.payrollNoReceipts,
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
        loading: () => const LinearProgressIndicator(
          minHeight: 3,
          color: AppColors.rh,
          backgroundColor: MobileSurface.surface,
        ),
        error: (_, __) => Text(
          context.l10n.payrollDocsUnavailable,
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
              color: document.isAvailable
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
      data: (balance) => MobilePanel(
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
                        context.l10n.payrollMyBalance,
                        style: AppTypography.bodySmall.copyWith(
                          color: MobileSurface.text,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        context.l10n.payrollPeriodRange(
                            balance.periodStart, balance.periodEnd),
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
              label: context.l10n.payrollRemainingToReceive,
              value: balance.remaining,
              currency: balance.currency,
              color: AppColors.rh,
            ),
            const SizedBox(height: 8),
            _MoneyLine(
              label: context.l10n.payrollAdvancesDeducted,
              value: balance.advances,
              currency: balance.currency,
              color: AppColors.warning,
            ),
            const SizedBox(height: 8),
            _MoneyLine(
              label: context.l10n.payrollAlreadyPaid,
              value: balance.paid,
              currency: balance.currency,
              color: AppColors.info,
            ),
            if (balance.nextPaymentDate.isNotEmpty) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Icon(
                    Icons.event_available_outlined,
                    size: 16,
                    color: MobileSurface.secondary,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      context.l10n.payrollNextPayment(balance.nextPaymentDate),
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    ),
                  ),
                ],
              ),
            ],
            // Issue #2143/#1872 — indicateur discret du niveau de confiance
            // paie du pays quand le payload expose le bloc `compliance` ;
            // rien affiché sinon (rétro-compatible avec les backends
            // antérieurs).
            if (balance.compliance != null) ...[
              const SizedBox(height: 12),
              PayrollCompliancePill(
                compliance: balance.compliance!,
                countryCode: balance.country,
              ),
            ],
          ],
        ),
      ),
      loading: () => const MobilePanel(
        child: LinearProgressIndicator(
          minHeight: 3,
          color: AppColors.rh,
          backgroundColor: MobileSurface.surface,
        ),
      ),
      error: (_, __) => MobilePanel(
        child: Text(
          context.l10n.payrollBalanceUnavailable,
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
          '${NumberFormat.decimalPattern(deviceIntlNumberLocale).format(value)}${currencySuffix(currency)}',
          style: AppTypography.bodySmall.copyWith(
            color: color,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
