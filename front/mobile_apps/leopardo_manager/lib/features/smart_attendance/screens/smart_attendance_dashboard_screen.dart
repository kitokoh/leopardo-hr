import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_manager/features/smart_attendance/providers/smart_attendance_provider.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Tableau de bord Smart Attendance — Manager
class SmartAttendanceDashboardScreen extends ConsumerWidget {
  const SmartAttendanceDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashAsync = ref.watch(smartAttendanceDashboardProvider);
    final pendingAsync = ref.watch(pendingGeoSessionsProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: context.l10n.smartAttendanceTitle,
        subtitle: context.l10n.smartAttendanceDashboardTitle,
        leading: IconButton(
          tooltip: context.l10n.back,
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: context.l10n.refresh,
            onPressed: () {
              ref.invalidate(smartAttendanceDashboardProvider);
              ref.invalidate(pendingGeoSessionsProvider);
            },
          ),
        ],
      ),
      children: [
        RefreshIndicator(
          color: AppColors.rh,
          backgroundColor: MobileSurface.card,
          onRefresh: () async {
            ref.invalidate(smartAttendanceDashboardProvider);
            ref.invalidate(pendingGeoSessionsProvider);
          },
          child: ListView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
            shrinkWrap: true,
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              // ── Stats du jour ──────────────────────────────────────
              dashAsync.when(
                data: (stats) => _StatsGrid(stats: stats),
                loading: () => const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: CircularProgressIndicator(color: AppColors.rh),
                  ),
                ),
                error: (e, _) => _ErrorCard(message: e.toString()),
              ),
              const SizedBox(height: 24),

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
      ],
    );
  }
}

class _StatsGrid extends StatelessWidget {
  const _StatsGrid({required this.stats});
  final Map<String, dynamic> stats;

  @override
  Widget build(BuildContext context) {
    final counters = stats['stats'] as Map<String, dynamic>? ?? {};
    final detected = counters['detected'] ?? 0;
    final pending = counters['pending_validation'] ?? 0;
    final approved = counters['approved'] ?? 0;
    final rejected = counters['rejected'] ?? 0;
    final dateLabel = (stats['today'] as String?) ??
        DateFormat('yyyy-MM-dd').format(DateTime.now());
    DateTime? parsedDate;
    try {
      parsedDate = DateTime.parse(dateLabel);
    } catch (_) {}

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          "Aujourd'hui — ${parsedDate != null ? DateFormat('d MMM yyyy', deviceIntlDateLocale).format(parsedDate) : dateLabel}",
          style: AppTypography.subtitle.copyWith(
            color: MobileSurface.text,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 16),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 1.4,
          children: [
            _StatCard(
              label: context.l10n.saDetected,
              value: detected,
              color: AppColors.rh,
              icon: Icons.radar_outlined,
            ),
            _StatCard(
              label: context.l10n.smartAttendancePending,
              value: pending,
              color: AppColors.warning,
              icon: Icons.pending_actions_outlined,
            ),
            _StatCard(
              label: context.l10n.saApproved,
              value: approved,
              color: AppColors.success,
              icon: Icons.check_circle_outline,
            ),
            _StatCard(
              label: context.l10n.saRejected,
              value: rejected,
              color: AppColors.danger,
              icon: Icons.cancel_outlined,
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
    required this.icon,
  });
  final String label;
  final dynamic value;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(16),
      borderColor: color.withValues(alpha: 0.3),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '$value',
                style: AppTypography.title.copyWith(color: color, fontSize: 28),
              ),
              Icon(icon, color: color.withValues(alpha: 0.8), size: 24),
            ],
          ),
          const Spacer(),
          Text(
            label,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.muted,
              fontWeight: FontWeight.w500,
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
      return GlassCard(
        padding: const EdgeInsets.all(20),
        borderColor: AppColors.success.withValues(alpha: 0.4),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppColors.success.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.check_circle, color: AppColors.success),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                context.l10n.smartAttendanceNoPending,
                style: AppTypography.body.copyWith(
                  color: MobileSurface.text,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return GestureDetector(
      onTap: onTap,
      child: GlassCard(
        padding: const EdgeInsets.all(20),
        borderColor: AppColors.warning.withValues(alpha: 0.5),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.warning.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(
                Icons.rule_folder_outlined,
                color: AppColors.warning,
                size: 28,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.smartAttendancePendingCount(count),
                    style: AppTypography.subtitle.copyWith(
                      color: AppColors.warning,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    context.l10n.smartAttendanceTapToReview,
                    style: AppTypography.bodySmall.copyWith(
                      color: MobileSurface.muted,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: AppColors.warning),
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
    return GlassCard(
      padding: const EdgeInsets.all(16),
      borderColor: AppColors.danger.withValues(alpha: 0.5),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: AppColors.danger),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              context.l10n.smartAttendanceError(message),
              style: AppTypography.bodySmall.copyWith(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
  }
}
