import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/features/attendance_geo/data/models/geo_attendance_session.dart';
import 'package:leopardo_manager/features/attendance_geo/providers/attendance_geo_provider.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Écran liste des sessions GPS en attente de validation — Manager
class PendingGeoSessionsScreen extends ConsumerStatefulWidget {
  const PendingGeoSessionsScreen({super.key});

  @override
  ConsumerState<PendingGeoSessionsScreen> createState() =>
      _PendingGeoSessionsScreenState();
}

class _PendingGeoSessionsScreenState
    extends ConsumerState<PendingGeoSessionsScreen> {
  final _noteController = TextEditingController();

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _approve(GeoAttendanceSession session) async {
    try {
      await ref
          .read(managerAttendanceGeoRepositoryProvider)
          .approveSession(session.id);
      ref.invalidate(pendingGeoSessionsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.l10n.sessionApproved),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.l10n.smartAttendanceError(e)),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    }
  }

  Future<void> _reject(GeoAttendanceSession session) async {
    _noteController.clear();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: MobileSurface.card,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(
          context.l10n.smartAttendanceRejectReason,
          style: AppTypography.subtitle.copyWith(
            color: MobileSurface.text,
            fontWeight: FontWeight.w600,
          ),
        ),
        content: TextField(
          controller: _noteController,
          style: AppTypography.body.copyWith(color: MobileSurface.text),
          maxLines: 3,
          decoration: InputDecoration(
            hintText: context.l10n.smartAttendanceRejectHint,
            hintStyle: AppTypography.body.copyWith(color: MobileSurface.muted),
            enabledBorder: OutlineInputBorder(
              borderSide: BorderSide(color: MobileSurface.border),
              borderRadius: BorderRadius.circular(12),
            ),
            focusedBorder: OutlineInputBorder(
              borderSide: const BorderSide(color: AppColors.rh),
              borderRadius: BorderRadius.circular(12),
            ),
            filled: true,
            fillColor: MobileSurface.surface,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(
              context.l10n.smartAttendanceCancel,
              style: AppTypography.body.copyWith(color: MobileSurface.text),
            ),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.danger,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
              elevation: 0,
            ),
            child: const Text('Rejeter'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        await ref
            .read(managerAttendanceGeoRepositoryProvider)
            .rejectSession(session.id, note: _noteController.text.trim());
        ref.invalidate(pendingGeoSessionsProvider);
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(context.l10n.sessionRejected)));
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.l10n.errorPrefix(e.toString())),
              backgroundColor: AppColors.danger,
            ),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final sessionsAsync = ref.watch(pendingGeoSessionsProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: context.l10n.pendingSessionsToValidate,
        subtitle: context.l10n.smartAttendanceSessionsTitle,
        leading: IconButton(
          tooltip: context.l10n.back,
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            tooltip: context.l10n.refresh,
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(pendingGeoSessionsProvider),
          ),
        ],
      ),
      children: [
        sessionsAsync.when(
          data: (sessions) {
            if (sessions.isEmpty) {
              return Padding(
                padding: const EdgeInsets.only(top: 80),
                child: EmptyState(
                  icon: Icons.check_circle_outline,
                  title: context.l10n.pendingSessionsUpToDate,
                  description: context.l10n.pendingSessionsEmpty,
                ),
              );
            }
            return RefreshIndicator(
              color: AppColors.rh,
              backgroundColor: MobileSurface.card,
              onRefresh: () async => ref.invalidate(pendingGeoSessionsProvider),
              child: ListView.separated(
                shrinkWrap: true,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 20,
                ),
                itemCount: sessions.length,
                separatorBuilder: (_, __) => const SizedBox(height: 16),
                itemBuilder: (context, index) {
                  final session = sessions[index];
                  return _SessionCard(
                    session: session,
                    onApprove: () => _approve(session),
                    onReject: () => _reject(session),
                  );
                },
              ),
            );
          },
          loading: () => const Center(
            child: Padding(
              padding: EdgeInsets.only(top: 80),
              child: CircularProgressIndicator(color: AppColors.rh),
            ),
          ),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.only(top: 80),
              child: Text(
                context.l10n.smartAttendanceError(e),
                style: AppTypography.body.copyWith(color: AppColors.danger),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _SessionCard extends StatelessWidget {
  const _SessionCard({
    required this.session,
    required this.onApprove,
    required this.onReject,
  });

  final GeoAttendanceSession session;
  final VoidCallback onApprove;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('d MMM · HH:mm', deviceIntlDateLocale);

    return GlassCard(
      padding: const EdgeInsets.all(20),
      borderColor: AppColors.warning.withValues(alpha: 0.4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: MobileSurface.border.withValues(alpha: 0.5),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.person,
                  size: 20,
                  color: MobileSurface.muted,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  session.employeeName.isNotEmpty
                      ? session.employeeName
                      : context.l10n.employeeNumber(
                          session.employeeId.toString(),
                        ),
                  style: AppTypography.subtitle.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              const Icon(
                Icons.login_rounded,
                size: 16,
                color: MobileSurface.muted,
              ),
              const SizedBox(width: 8),
              Text(
                context.l10n.sessionEntryAt(
                  fmt.format(session.startedAt.toLocal()),
                ),
                style: AppTypography.bodySmall.copyWith(
                  color: MobileSurface.muted,
                ),
              ),
            ],
          ),
          if (session.endedAt != null) ...[
            const SizedBox(height: 6),
            Row(
              children: [
                const Icon(
                  Icons.logout_rounded,
                  size: 16,
                  color: MobileSurface.muted,
                ),
                const SizedBox(width: 8),
                Text(
                  context.l10n.smartAttendanceSessionExit(
                      fmt.format(session.endedAt!.toLocal()),
                      session.durationLabel),
                  style: AppTypography.bodySmall.copyWith(
                    color: MobileSurface.muted,
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onReject,
                  icon: const Icon(Icons.close_rounded, size: 16),
                  label: Text(context.l10n.smartAttendanceReject),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.danger,
                    side: BorderSide(
                      color: AppColors.danger.withValues(alpha: 0.5),
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton.icon(
                  onPressed: onApprove,
                  icon: const Icon(Icons.check_rounded, size: 16),
                  label: Text(context.l10n.smartAttendanceApprove),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.success,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    elevation: 0,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
