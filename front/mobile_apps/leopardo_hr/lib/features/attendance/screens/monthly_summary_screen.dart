import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_hr/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_hr/features/auth/providers/auth_provider.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Ecran "Mon mois" — l'employe voit :
///  - ses heures travaillees du mois
///  - ses heures supplementaires
///  - son gain brut et son du net estimes
///
/// Alimente par GET /api/v1/me/monthly-summary.
class MonthlySummaryScreen extends ConsumerStatefulWidget {
  const MonthlySummaryScreen({super.key});

  @override
  ConsumerState<MonthlySummaryScreen> createState() =>
      _MonthlySummaryScreenState();
}

class _MonthlySummaryScreenState extends ConsumerState<MonthlySummaryScreen> {
  late DateTime _month;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _month = DateTime(now.year, now.month, 1);
  }

  void _shiftMonth(int delta) {
    setState(() {
      _month = DateTime(_month.year, _month.month + delta, 1);
    });
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(monthlySummaryProvider(_month));
    final employee = ref.watch(authProvider).employee;
    final monthLabel = DateFormat.yMMMM(deviceIntlDateLocale).format(_month);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
        title: Text(context.l10n.attendanceMyMonth),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.refresh(monthlySummaryProvider(_month)),
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            _buildMonthSelector(context, monthLabel),
            const SizedBox(height: 20),
            async.when(
              loading: () => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: CircularProgressIndicator(
                    semanticsLabel: context.l10n.monthlySummaryLoading,
                  ),
                ),
              ),
              error: (err, _) {
                final text = err.toString();
                if (text.contains('401') || text.contains('UNAUTHENTICATED')) {
                  WidgetsBinding.instance.addPostFrameCallback((_) {
                    ref.read(authProvider.notifier).logout();
                  });
                  return const SizedBox.shrink();
                }
                return _buildError(context, err);
              },
              data: (summary) =>
                  _buildSummary(context, summary, employee?.fullName),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMonthSelector(BuildContext context, String label) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        IconButton(
          onPressed: () => _shiftMonth(-1),
          icon: const Icon(Icons.chevron_left),
          tooltip: context.l10n.attendancePreviousMonth,
        ),
        Text(label, style: Theme.of(context).textTheme.titleLarge),
        IconButton(
          onPressed: _isCurrentOrFutureMonth() ? null : () => _shiftMonth(1),
          icon: const Icon(Icons.chevron_right),
          tooltip: context.l10n.attendanceNextMonth,
        ),
      ],
    );
  }

  bool _isCurrentOrFutureMonth() {
    final now = DateTime.now();
    return _month.year > now.year ||
        (_month.year == now.year && _month.month >= now.month);
  }

  Widget _buildSummary(
    BuildContext context,
    dynamic summary,
    String? employeeName,
  ) {
    final currencyFormat = NumberFormat.currency(
      locale: deviceIntlDateLocale,
      symbol: summary.currency,
      decimalDigits: 2,
    );
    final dateFormat = DateFormat('dd/MM', deviceIntlDateLocale);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (employeeName != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              employeeName,
              style: Theme.of(context).textTheme.bodyLarge,
              textAlign: TextAlign.center,
            ),
          ),
        _metricCard(
          context,
          icon: Icons.schedule,
          label: context.l10n.attendanceHoursWorkedLabel,
          value: '${summary.hours.toStringAsFixed(2)} h',
          sub: context.l10n.attendanceDaysPresentRatio(
              summary.daysPresent, summary.workingDays),
        ),
        const SizedBox(height: 12),
        _metricCard(
          context,
          icon: Icons.timelapse,
          label: context.l10n.attendanceOvertimeLabel,
          value: '${summary.overtimeHours.toStringAsFixed(2)} h',
          sub: context.l10n.attendanceIncludedGross,
          accent: AppColors.warning,
        ),
        const SizedBox(height: 12),
        _metricCard(
          context,
          icon: Icons.account_balance_wallet,
          label: context.l10n.attendanceGrossEstimate,
          value: currencyFormat.format(summary.gross),
          sub: context.l10n.attendanceBeforeDeductions,
        ),
        const SizedBox(height: 12),
        _metricCard(
          context,
          icon: Icons.paid,
          label: context.l10n.attendanceNetEstimate,
          value: currencyFormat.format(summary.net),
          sub: context.l10n.attendanceDeductionsSub(
              currencyFormat.format(summary.deductions)),
          accent: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(height: 24),
        if (summary.breakdown.isNotEmpty) ...[
          Text(
            context.l10n.attendanceDayDetail,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          ...summary.breakdown.map<Widget>(
            (entry) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  SizedBox(
                    width: 56,
                    child: Text(dateFormat.format(entry.date)),
                  ),
                  Expanded(
                    child: Text(
                      '${entry.hours.toStringAsFixed(2)} h'
                      '${entry.overtimeHours > 0 ? ' (+${entry.overtimeHours.toStringAsFixed(2)} sup)' : ''}',
                    ),
                  ),
                  Text(currencyFormat.format(entry.total)),
                ],
              ),
            ),
          ),
        ],
        const SizedBox(height: 24),
        Text(
          summary.disclaimer.isEmpty
              ? context.l10n.attendanceEstimateDisclaimer
              : summary.disclaimer,
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontSize: 12,
            color: AppColors.textMuted,
            fontStyle: FontStyle.italic,
          ),
        ),
      ],
    );
  }

  Widget _metricCard(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String value,
    String? sub,
    Color? accent,
  }) {
    final color = accent ?? Theme.of(context).colorScheme.primary;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: color.withValues(alpha: 0.15),
            child: Icon(icon, color: color),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: AppColors.textMuted)),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
                ),
                if (sub != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    sub,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildError(BuildContext context, Object err) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline, size: 56, color: AppColors.danger),
          const SizedBox(height: 12),
          Text(
            context.l10n.attendanceLoadFailed(err),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 12),
          ElevatedButton(
            onPressed: () => ref.refresh(monthlySummaryProvider(_month)),
            child: Text(context.l10n.attendanceRetry),
          ),
        ],
      ),
    );
  }
}
