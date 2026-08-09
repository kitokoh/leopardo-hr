import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_hr/features/smart_attendance/providers/smart_attendance_provider.dart';

/// Tableau de bord Smart Attendance — Manager
///
/// Affiche :
/// - Compteurs du jour (sessions détectées, en attente, approuvées, rejetées)
/// - Bouton vers la liste des sessions à valider
class SmartAttendanceDashboardScreen extends ConsumerWidget {
  const SmartAttendanceDashboardScreen({super.key});

  static const Color _bg = AppColors.mobileDarkBg;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashAsync = ref.watch(smartAttendanceDashboardProvider);
    final pendingAsync = ref.watch(pendingGeoSessionsProvider);

    return Scaffold(
      backgroundColor: _bg,
      appBar: MobileTopBar(
        title: 'Smart Attendance',
        subtitle: 'Pointage GPS — tableau de bord équipe',
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Actualiser',
            onPressed: () {
              ref.invalidate(smartAttendanceDashboardProvider);
              ref.invalidate(pendingGeoSessionsProvider);
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(smartAttendanceDashboardProvider);
          ref.invalidate(pendingGeoSessionsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // ── Stats du jour ──────────────────────────────────────
            dashAsync.when(
              data: (stats) => _StatsGrid(stats: stats),
              loading: () => const Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: CircularProgressIndicator(),
                ),
              ),
              error: (e, _) => _ErrorCard(message: e.toString()),
            ),
            const SizedBox(height: 20),

            // ── Bouton sessions pending ────────────────────────────
            pendingAsync.when(
              data: (sessions) => _PendingCard(
                count: sessions.length,
                onTap: () => context.push('/smart-attendance/pending'),
              ),
              loading: () => const SizedBox.shrink(),
              error: (_, __) => const SizedBox.shrink(),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatsGrid extends StatelessWidget {
  const _StatsGrid({required this.stats});
  final Map<String, dynamic> stats;

  @override
  Widget build(BuildContext context) {
    // API: { today: "2026-07-01", stats: { detected: N, ... }, pending: [...] }
    final counters = stats['stats'] as Map<String, dynamic>? ?? {};
    final detected = counters['detected'] ?? 0;
    final pending = counters['pending_validation'] ?? 0;
    final approved = counters['approved'] ?? 0;
    final rejected = counters['rejected'] ?? 0;
    final dateLabel =
        (stats['today'] as String?) ??
        DateFormat('yyyy-MM-dd').format(DateTime.now());
    DateTime? parsedDate;
    try {
      parsedDate = DateTime.parse(dateLabel);
    } catch (_) {}

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          "Aujourd'hui — ${parsedDate != null ? DateFormat('d MMM yyyy', 'fr_FR').format(parsedDate) : dateLabel}",
          style: AppTypography.bodySmall.copyWith(
            color: AppColors.textMutedDark,
          ),
        ),
        const SizedBox(height: 12),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 1.6,
          children: [
            _StatCard(
              label: 'Détectées',
              value: detected,
              color: AppColors.info,
            ),
            _StatCard(
              label: 'En attente',
              value: pending,
              color: Colors.orange,
            ),
            _StatCard(
              label: 'Approuvées',
              value: approved,
              color: Colors.green,
            ),
            _StatCard(
              label: 'Rejetées',
              value: rejected,
              color: AppColors.danger,
            ),
          ],
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.color,
  });
  final String label;
  final dynamic value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.mobileDarkSurface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text('$value', style: AppTypography.title.copyWith(color: color)),
          const SizedBox(height: 4),
          Text(
            label,
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textMutedDark,
            ),
          ),
        ],
      ),
    );
  }
}

class _PendingCard extends StatelessWidget {
  const _PendingCard({required this.count, required this.onTap});
  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    if (count == 0) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.mobileDarkSurface,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            const Icon(Icons.check_circle_outline, color: Colors.green),
            const SizedBox(width: 12),
            Text(
              'Aucune session en attente de validation',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textDark,
              ),
            ),
          ],
        ),
      );
    }

    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.orange.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.orange.withValues(alpha: 0.5)),
        ),
        child: Row(
          children: [
            const Icon(
              Icons.pending_actions_rounded,
              color: Colors.orange,
              size: 28,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '$count session${count > 1 ? 's' : ''} en attente',
                    style: AppTypography.subtitle.copyWith(
                      color: Colors.orange,
                    ),
                  ),
                  Text(
                    'Appuyez pour valider ou rejeter',
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.textMutedDark,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Colors.orange),
          ],
        ),
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.danger.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        'Erreur : $message',
        style: AppTypography.bodySmall.copyWith(color: AppColors.danger),
      ),
    );
  }
}
