import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_hr/features/smart_attendance/data/models/geo_attendance_session.dart';
import 'package:leopardo_hr/features/smart_attendance/providers/smart_attendance_provider.dart';

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
          .read(hrSmartAttendanceRepositoryProvider)
          .approveSession(session.id);
      ref.invalidate(pendingGeoSessionsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Session approuvée ✓'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text('Erreur : $e'), backgroundColor: AppColors.danger),
        );
      }
    }
  }

  Future<void> _reject(GeoAttendanceSession session) async {
    _noteController.clear();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF111B2E),
        title: Text(
          'Motif du rejet',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        content: TextField(
          controller: _noteController,
          style: const TextStyle(color: AppColors.textDark),
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Expliquez la raison du rejet...',
            hintStyle: TextStyle(color: AppColors.textMutedDark),
            enabledBorder: UnderlineInputBorder(
              borderSide: BorderSide(color: AppColors.borderDark),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(
              'Rejeter',
              style: TextStyle(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        await ref
            .read(hrSmartAttendanceRepositoryProvider)
            .rejectSession(session.id, note: _noteController.text.trim());
        ref.invalidate(pendingGeoSessionsProvider);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Session rejetée')),
          );
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Erreur : $e'),
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

    return Scaffold(
      backgroundColor: const Color(0xFF0B1120),
      appBar: MobileTopBar(
        title: 'Sessions à valider',
        subtitle: 'Smart Attendance — GPS',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(pendingGeoSessionsProvider),
          ),
        ],
      ),
      body: sessionsAsync.when(
        data: (sessions) {
          if (sessions.isEmpty) {
            return const EmptyState(
              icon: Icons.check_circle_outline,
              title: 'Tout est à jour',
              description: 'Aucune session GPS en attente de validation.',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(pendingGeoSessionsProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: sessions.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
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
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Text(
            'Erreur : $e',
            style: TextStyle(color: AppColors.danger),
          ),
        ),
      ),
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
    final fmt = DateFormat('d MMM · HH:mm', 'fr_FR');

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF111B2E),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.orange.withOpacity(0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.person_outline_rounded,
                  size: 18, color: AppColors.textMutedDark),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  session.employeeName.isNotEmpty
                      ? session.employeeName
                      : 'Employé #${session.employeeId}',
                  style: AppTypography.subtitle
                      .copyWith(color: AppColors.textDark),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(Icons.login_rounded,
                  size: 14, color: AppColors.textMutedDark),
              const SizedBox(width: 4),
              Text(
                'Entrée : ${fmt.format(session.startedAt.toLocal())}',
                style: AppTypography.bodySmall
                    .copyWith(color: AppColors.textMutedDark),
              ),
            ],
          ),
          if (session.endedAt != null) ...[
            const SizedBox(height: 2),
            Row(
              children: [
                const Icon(Icons.logout_rounded,
                    size: 14, color: AppColors.textMutedDark),
                const SizedBox(width: 4),
                Text(
                  'Sortie : ${fmt.format(session.endedAt!.toLocal())} · ${session.durationLabel}',
                  style: AppTypography.bodySmall
                      .copyWith(color: AppColors.textMutedDark),
                ),
              ],
            ),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onReject,
                  icon: const Icon(Icons.close_rounded, size: 16),
                  label: const Text('Rejeter'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.danger,
                    side: BorderSide(color: AppColors.danger.withOpacity(0.5)),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton.icon(
                  onPressed: onApprove,
                  icon: const Icon(Icons.check_rounded, size: 16),
                  label: const Text('Approuver'),
                  style: FilledButton.styleFrom(backgroundColor: Colors.green),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
