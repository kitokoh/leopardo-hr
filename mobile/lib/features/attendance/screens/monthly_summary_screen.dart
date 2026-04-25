import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_rh/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';

/// Ecran "Mon mois" — l'employe voit :
///  - ses heures travaillees du mois
///  - ses heures supplementaires
///  - son gain brut et son du net estimes
///
/// Alimente par GET /api/v1/me/monthly-summary.
class MonthlySummaryScreen extends ConsumerStatefulWidget {
  const MonthlySummaryScreen({super.key});

  @override
  ConsumerState<MonthlySummaryScreen> createState() => _MonthlySummaryScreenState();
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
    final monthLabel = DateFormat.yMMMM('fr_FR').format(_month);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
        title: const Text('Mon mois'),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.refresh(monthlySummaryProvider(_month)),
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            _buildMonthSelector(context, monthLabel),
            const SizedBox(height: 20),
            async.when(
              loading: () => const Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: CircularProgressIndicator(
                    semanticsLabel: 'Chargement du résumé mensuel...',
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
              data: (summary) => _buildSummary(context, summary, employee?.fullName),
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
          tooltip: 'Mois precedent',
        ),
        Text(label, style: Theme.of(context).textTheme.titleLarge),
        IconButton(
          onPressed: _isCurrentOrFutureMonth() ? null : () => _shiftMonth(1),
          icon: const Icon(Icons.chevron_right),
          tooltip: 'Mois suivant',
        ),
      ],
    );
  }

  bool _isCurrentOrFutureMonth() {
    final now = DateTime.now();
    return _month.year > now.year || (_month.year == now.year && _month.month >= now.month);
  }

  Widget _buildSummary(BuildContext context, dynamic summary, String? employeeName) {
    final currencyFormat = NumberFormat.currency(
      locale: 'fr_FR',
      symbol: summary.currency,
      decimalDigits: 2,
    );
    final dateFormat = DateFormat('dd/MM', 'fr_FR');

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
          label: 'Heures travaillees',
          value: '${summary.hours.toStringAsFixed(2)} h',
          sub: '${summary.daysPresent} jours presents / ${summary.workingDays} ouvres',
        ),
        const SizedBox(height: 12),
        _metricCard(
          context,
          icon: Icons.timelapse,
          label: 'Heures supplementaires',
          value: '${summary.overtimeHours.toStringAsFixed(2)} h',
          sub: 'Incluses dans le gain brut',
          accent: Colors.orangeAccent,
        ),
        const SizedBox(height: 12),
        _metricCard(
          context,
          icon: Icons.account_balance_wallet,
          label: 'Gain brut estime',
          value: currencyFormat.format(summary.gross),
          sub: 'Avant deductions legales',
        ),
        const SizedBox(height: 12),
        _metricCard(
          context,
          icon: Icons.paid,
          label: 'Du net estime',
          value: currencyFormat.format(summary.net),
          sub: 'Deductions: ${currencyFormat.format(summary.deductions)}',
          accent: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(height: 24),
        if (summary.breakdown.isNotEmpty) ...[
          Text('Detail par jour', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          ...summary.breakdown.map<Widget>((entry) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  children: [
                    SizedBox(
                      width: 56,
                      child: Text(dateFormat.format(entry.date)),
                    ),
                    Expanded(
                      child: Text('${entry.hours.toStringAsFixed(2)} h'
                          '${entry.overtimeHours > 0 ? ' (+${entry.overtimeHours.toStringAsFixed(2)} sup)' : ''}'),
                    ),
                    Text(currencyFormat.format(entry.total)),
                  ],
                ),
              )),
        ],
        const SizedBox(height: 24),
        Text(
          summary.disclaimer.isEmpty
              ? 'Estimation non officielle - le bulletin de paie fait foi.'
              : summary.disclaimer,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 12, color: Colors.grey, fontStyle: FontStyle.italic),
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
                Text(label, style: const TextStyle(color: Colors.grey)),
                const SizedBox(height: 4),
                Text(value,
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: color,
                    )),
                if (sub != null) ...[
                  const SizedBox(height: 2),
                  Text(sub, style: const TextStyle(fontSize: 12, color: Colors.grey)),
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
          const Icon(Icons.error_outline, size: 56, color: Colors.redAccent),
          const SizedBox(height: 12),
          Text('Impossible de charger les donnees : $err',
              textAlign: TextAlign.center),
          const SizedBox(height: 12),
          ElevatedButton(
            onPressed: () => ref.refresh(monthlySummaryProvider(_month)),
            child: const Text('Reessayer'),
          ),
        ],
      ),
    );
  }
}
