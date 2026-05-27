import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/monthly_summary.dart';
import 'package:leopardo_employee/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_employee/features/auth/providers/auth_provider.dart';

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
    _month = DateTime(now.year, now.month);
  }

  void _shiftMonth(int delta) {
    setState(() => _month = DateTime(_month.year, _month.month + delta));
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(monthlySummaryProvider(_month));
    final employee = ref.watch(authProvider).employee;
    final monthLabel = DateFormat.yMMMM('fr_FR').format(_month);

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Mon mois complet',
        subtitle: employee?.fullName ?? 'Suivi personnel',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Actualiser',
            onPressed: () => ref.invalidate(monthlySummaryProvider(_month)),
          ),
        ],
      ),
      children: [
        _MonthSelector(
          label: monthLabel,
          canGoNext: !_isCurrentOrFutureMonth(),
          onPrevious: () => _shiftMonth(-1),
          onNext: () => _shiftMonth(1),
        ),
        const SizedBox(height: 16),
        async.when(
          loading: () => const _MonthlyLoadingState(),
          error: (err, _) {
            final text = err.toString();
            if (text.contains('401') || text.contains('UNAUTHENTICATED')) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                ref.read(authProvider.notifier).logout();
              });
              return const SizedBox.shrink();
            }

            return _MonthlyErrorState(
              message: text,
              onRetry: () => ref.invalidate(monthlySummaryProvider(_month)),
            );
          },
          data:
              (summary) => _MonthlySummaryBody(
                summary: summary,
                onHistory: () => context.push('/history'),
              ),
        ),
      ],
    );
  }

  bool _isCurrentOrFutureMonth() {
    final now = DateTime.now();
    return _month.year > now.year ||
        (_month.year == now.year && _month.month >= now.month);
  }
}

class _MonthSelector extends StatelessWidget {
  const _MonthSelector({
    required this.label,
    required this.canGoNext,
    required this.onPrevious,
    required this.onNext,
  });

  final String label;
  final bool canGoNext;
  final VoidCallback onPrevious;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      child: Row(
        children: [
          IconButton(
            onPressed: onPrevious,
            icon: const Icon(Icons.chevron_left),
            color: MobileSurface.secondary,
            tooltip: 'Mois precedent',
          ),
          Expanded(
            child: Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: MobileSurface.text,
                fontSize: 18,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          IconButton(
            onPressed: canGoNext ? onNext : null,
            icon: const Icon(Icons.chevron_right),
            color: MobileSurface.secondary,
            disabledColor: MobileSurface.disabled,
            tooltip: 'Mois suivant',
          ),
        ],
      ),
    );
  }
}

class _MonthlyLoadingState extends StatelessWidget {
  const _MonthlyLoadingState();

  @override
  Widget build(BuildContext context) {
    return const MobilePanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LinearProgressIndicator(
            minHeight: 3,
            color: AppColors.rh,
            backgroundColor: MobileSurface.border,
          ),
          SizedBox(height: 16),
          Text(
            'Synchronisation du mois...',
            style: TextStyle(
              color: MobileSurface.text,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          SizedBox(height: 6),
          Text(
            'Si aucune donnee n existe encore, un resume vide sera affiche.',
            style: TextStyle(color: MobileSurface.muted, fontSize: 13),
          ),
        ],
      ),
    );
  }
}

class _MonthlyErrorState extends StatelessWidget {
  const _MonthlyErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const MobileIconBubble(
            icon: Icons.cloud_off,
            color: AppColors.danger,
          ),
          const SizedBox(height: 14),
          const Text(
            'Resume indisponible',
            style: TextStyle(
              color: MobileSurface.text,
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: MobileSurface.muted, fontSize: 13),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Reessayer'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.rh,
                foregroundColor: Colors.white,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MonthlySummaryBody extends StatelessWidget {
  const _MonthlySummaryBody({required this.summary, required this.onHistory});

  final MonthlySummary summary;
  final VoidCallback onHistory;

  bool get _isEmpty => summary.hours <= 0 && summary.breakdown.isEmpty;

  @override
  Widget build(BuildContext context) {
    final currency = NumberFormat.currency(
      locale: 'fr_FR',
      symbol: summary.currency,
      decimalDigits: 2,
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _HeroPanel(summary: summary, currency: currency),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(
              child: _MetricTile(
                label: 'Presence',
                value: '${summary.daysPresent}/${summary.workingDays}',
                detail: '${summary.daysAbsent} abs.',
                icon: Icons.event_available,
                color: AppColors.rh,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _MetricTile(
                label: 'Heures sup.',
                value: '${summary.overtimeHours.toStringAsFixed(1)} h',
                detail: 'Incluses brut',
                icon: Icons.timelapse,
                color: AppColors.warning,
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        if (_isEmpty)
          _EmptyMonthPanel(onHistory: onHistory)
        else ...[
          const MobileSectionLabel('Detail par jour'),
          ...summary.breakdown.map((entry) => _BreakdownRow(entry: entry)),
        ],
        const SizedBox(height: 18),
        Text(
          summary.disclaimer.isEmpty
              ? 'Estimation non officielle. Le bulletin de paie fait foi.'
              : summary.disclaimer,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: MobileSurface.disabled,
            fontSize: 12,
            fontStyle: FontStyle.italic,
          ),
        ),
      ],
    );
  }
}

class _HeroPanel extends StatelessWidget {
  const _HeroPanel({required this.summary, required this.currency});

  final MonthlySummary summary;
  final NumberFormat currency;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const MobileIconBubble(icon: Icons.payments, color: AppColors.rh),
              const Spacer(),
              MobileStatusPill(
                label: summary.hours > 0 ? 'Actif' : 'Aucune donnee',
                color: summary.hours > 0 ? AppColors.rh : MobileSurface.muted,
                icon: summary.hours > 0 ? Icons.check_circle : Icons.info,
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Text(
            'Net estime',
            style: TextStyle(color: MobileSurface.muted, fontSize: 12),
          ),
          const SizedBox(height: 4),
          Text(
            currency.format(summary.net),
            style: const TextStyle(
              color: MobileSurface.text,
              fontSize: 34,
              fontWeight: FontWeight.w800,
              letterSpacing: 0,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _InlineFigure(
                  label: 'Heures',
                  value: '${summary.hours.toStringAsFixed(1)} h',
                ),
              ),
              Expanded(
                child: _InlineFigure(
                  label: 'Brut',
                  value: currency.format(summary.gross),
                ),
              ),
              Expanded(
                child: _InlineFigure(
                  label: 'Retenues',
                  value: currency.format(summary.deductions),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InlineFigure extends StatelessWidget {
  const _InlineFigure({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(color: MobileSurface.disabled, fontSize: 11),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: MobileSurface.secondary,
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({
    required this.label,
    required this.value,
    required this.detail,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final String detail;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(height: 12),
          Text(
            value,
            style: TextStyle(
              color: color,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(color: MobileSurface.text, fontSize: 12),
          ),
          Text(
            detail,
            style: const TextStyle(color: MobileSurface.disabled, fontSize: 11),
          ),
        ],
      ),
    );
  }
}

class _EmptyMonthPanel extends StatelessWidget {
  const _EmptyMonthPanel({required this.onHistory});

  final VoidCallback onHistory;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const MobileIconBubble(
            icon: Icons.calendar_today,
            color: MobileSurface.muted,
          ),
          const SizedBox(height: 14),
          const Text(
            'Aucun pointage sur ce mois',
            style: TextStyle(
              color: MobileSurface.text,
              fontSize: 17,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Le mois est bien charge. Les gains et heures resteront a zero tant qu aucun pointage valide n existe.',
            style: TextStyle(color: MobileSurface.muted, fontSize: 13),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: onHistory,
            icon: const Icon(Icons.history),
            label: const Text('Voir l historique'),
            style: OutlinedButton.styleFrom(
              foregroundColor: MobileSurface.secondary,
              side: const BorderSide(color: MobileSurface.border),
            ),
          ),
        ],
      ),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  const _BreakdownRow({required this.entry});

  final MonthlyBreakdownEntry entry;

  @override
  Widget build(BuildContext context) {
    final day = DateFormat('EEE d', 'fr_FR').format(entry.date);

    return MobilePanel(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          SizedBox(
            width: 64,
            child: Text(
              day,
              style: const TextStyle(
                color: MobileSurface.secondary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Text(
              '${entry.hours.toStringAsFixed(1)} h'
              '${entry.overtimeHours > 0 ? ' +${entry.overtimeHours.toStringAsFixed(1)} sup.' : ''}',
              style: const TextStyle(color: MobileSurface.muted, fontSize: 13),
            ),
          ),
          Text(
            entry.total.toStringAsFixed(0),
            style: const TextStyle(
              color: AppColors.rh,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
