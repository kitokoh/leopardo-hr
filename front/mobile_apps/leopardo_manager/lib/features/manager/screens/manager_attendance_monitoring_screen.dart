import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/attendance_log.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_manager/features/attendance/providers/attendance_provider.dart';

class ManagerAttendanceMonitoringScreen extends ConsumerWidget {
  const ManagerAttendanceMonitoringScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final attendance = ref.watch(managerAttendanceTodayProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Presences equipe',
        subtitle: 'Pointages du jour et sessions ouvertes',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(managerAttendanceTodayProvider),
          ),
        ],
      ),
      children: [
        attendance.when(
          loading:
              () => const MobileEmptyLoading(
                label: 'Synchronisation des presences...',
              ),
          error:
              (error, _) => MobileErrorPanel(
                message: error.toString(),
                onRetry: () => ref.invalidate(managerAttendanceTodayProvider),
              ),
          data: (items) => _AttendanceBody(items: items),
        ),
      ],
    );
  }
}

class _AttendanceBody extends StatelessWidget {
  const _AttendanceBody({required this.items});

  final List<AttendanceLog> items;

  @override
  Widget build(BuildContext context) {
    final present = items.where((item) => item.checkIn != null).length;
    final open =
        items
            .where((item) => item.checkIn != null && item.checkOut == null)
            .length;
    final late = items.where((item) => (item.lateMinutes ?? 0) > 0).length;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        MobilePanel(
          child: Row(
            children: [
              Expanded(
                child: _Metric(
                  label: 'Presents',
                  value: '$present',
                  color: AppColors.rh,
                ),
              ),
              Expanded(
                child: _Metric(
                  label: 'Ouverts',
                  value: '$open',
                  color: open > 0 ? AppColors.warning : AppColors.rh,
                ),
              ),
              Expanded(
                child: _Metric(
                  label: 'Retards',
                  value: '$late',
                  color: late > 0 ? AppColors.warning : AppColors.rh,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const MobileSectionLabel('Sessions du jour'),
        if (items.isEmpty)
          const _EmptyState(
            icon: Icons.groups_2_outlined,
            title: 'Aucun pointage aujourd hui',
            message:
                'Les pointages equipe apparaitront ici des qu ils arrivent depuis mobile ou kiosque.',
          )
        else
          ...items.map((log) => _AttendanceRow(log: log)),
      ],
    );
  }
}

class ManagerAnomaliesScreen extends ConsumerWidget {
  const ManagerAnomaliesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final report = ref.watch(managerAnomaliesProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Anomalies',
        subtitle: 'Retards, oublis et pointages a verifier',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(managerAnomaliesProvider),
          ),
        ],
      ),
      children: [
        report.when(
          loading:
              () => const MobileEmptyLoading(label: 'Analyse des anomalies...'),
          error:
              (error, _) => MobileErrorPanel(
                message: error.toString(),
                onRetry: () => ref.invalidate(managerAnomaliesProvider),
              ),
          data: (data) => _AnomalyBody(report: data),
        ),
      ],
    );
  }
}

class _AnomalyBody extends StatelessWidget {
  const _AnomalyBody({required this.report});

  final ManagerAnomalyReport report;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        MobilePanel(
          child: Row(
            children: [
              Expanded(
                child: _Metric(
                  label: 'Total',
                  value: '${report.total}',
                  color: AppColors.info,
                ),
              ),
              Expanded(
                child: _Metric(
                  label: 'Critiques',
                  value: '${report.critical}',
                  color: report.critical > 0 ? AppColors.danger : AppColors.rh,
                ),
              ),
              Expanded(
                child: _Metric(
                  label: 'Retard',
                  value: '${report.lateMinutes}m',
                  color:
                      report.lateMinutes > 0 ? AppColors.warning : AppColors.rh,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const MobileSectionLabel('A traiter'),
        if (report.items.isEmpty)
          const _EmptyState(
            icon: Icons.verified_user_outlined,
            title: 'Aucune anomalie recente',
            message:
                'Les alertes de pointage, sorties manquantes et heures supplementaires apparaitront ici.',
          )
        else
          ...report.items.map((item) => _AnomalyRow(item: item)),
      ],
    );
  }
}

class ManagerCorrectionsScreen extends ConsumerStatefulWidget {
  const ManagerCorrectionsScreen({super.key});

  @override
  ConsumerState<ManagerCorrectionsScreen> createState() =>
      _ManagerCorrectionsScreenState();
}

class _ManagerCorrectionsScreenState
    extends ConsumerState<ManagerCorrectionsScreen> {
  int? _busyId;

  Future<void> _decide(AttendanceCorrection correction, bool approve) async {
    setState(() => _busyId = correction.id);
    try {
      final repo = ref.read(attendanceRepositoryProvider);
      if (approve) {
        await repo.approveCorrection(correction.id);
      } else {
        await repo.rejectCorrection(correction.id);
      }
      if (!mounted) return;
      ref.invalidate(managerCorrectionsProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            approve ? 'Correction appliquee.' : 'Correction refusee.',
          ),
          backgroundColor: approve ? AppColors.rh : AppColors.warning,
        ),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.toString()),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final corrections = ref.watch(managerCorrectionsProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Corrections',
        subtitle: 'Demandes employees en attente RH',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(managerCorrectionsProvider),
          ),
        ],
      ),
      children: [
        corrections.when(
          loading:
              () =>
                  const MobileEmptyLoading(label: 'Chargement des demandes...'),
          error:
              (error, _) => MobileErrorPanel(
                message: error.toString(),
                onRetry: () => ref.invalidate(managerCorrectionsProvider),
              ),
          data:
              (items) => Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  MobilePanel(
                    child: Row(
                      children: [
                        const MobileIconBubble(
                          icon: Icons.fact_check_outlined,
                          color: AppColors.rh,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            '${items.length} demande(s) a traiter',
                            style: AppTypography.subtitle.copyWith(
                              color: MobileSurface.text,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (items.isEmpty)
                    const _EmptyState(
                      icon: Icons.task_alt_rounded,
                      title: 'File de correction vide',
                      message:
                          'Les demandes envoyees depuis les trois points du pointage seront listees ici.',
                    )
                  else
                    ...items.map(
                      (item) => _CorrectionRow(
                        correction: item,
                        busy: _busyId == item.id,
                        onApprove: () => _decide(item, true),
                        onReject: () => _decide(item, false),
                      ),
                    ),
                ],
              ),
        ),
      ],
    );
  }
}

class _AttendanceRow extends StatelessWidget {
  const _AttendanceRow({required this.log});

  final AttendanceLog log;

  @override
  Widget build(BuildContext context) {
    final statusColor =
        log.checkOut == null
            ? AppColors.warning
            : ((log.lateMinutes ?? 0) > 0 ? AppColors.warning : AppColors.rh);

    return MobilePanel(
      margin: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          MobileIconBubble(
            icon: Icons.person_outline_rounded,
            color: statusColor,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  log.employeeName ?? 'Employe',
                  style: AppTypography.bodySmall.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${_time(log.checkIn)} -> ${_time(log.checkOut)} · ${log.workType}',
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.secondary,
                  ),
                ),
              ],
            ),
          ),
          MobileStatusPill(label: log.status, color: statusColor),
        ],
      ),
    );
  }
}

class _AnomalyRow extends StatelessWidget {
  const _AnomalyRow({required this.item});

  final ManagerAnomaly item;

  @override
  Widget build(BuildContext context) {
    final color = switch (item.severity) {
      'critical' => AppColors.danger,
      'warning' => AppColors.warning,
      _ => AppColors.info,
    };

    return MobilePanel(
      margin: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              MobileIconBubble(icon: Icons.warning_amber_rounded, color: color),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  item.title,
                  style: AppTypography.bodySmall.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              MobileStatusPill(label: item.severity, color: color),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            '${item.employeeName} · ${item.date}',
            style: AppTypography.caption.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          if (item.recommendedAction.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              item.recommendedAction,
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.muted,
                height: 1.35,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _CorrectionRow extends StatelessWidget {
  const _CorrectionRow({
    required this.correction,
    required this.busy,
    required this.onApprove,
    required this.onReject,
  });

  final AttendanceCorrection correction;
  final bool busy;
  final VoidCallback onApprove;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      margin: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const MobileIconBubble(
                icon: Icons.edit_calendar_outlined,
                color: AppColors.rh,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  correction.employeeName,
                  style: AppTypography.bodySmall.copyWith(
                    color: MobileSurface.text,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              MobileStatusPill(
                label: correction.status,
                color: AppColors.warning,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            '${correction.date} · ${_time(correction.requestedCheckIn)} -> ${_time(correction.requestedCheckOut)}',
            style: AppTypography.caption.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            correction.reason,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.muted,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: busy ? null : onReject,
                  icon: const Icon(Icons.close_rounded, size: 17),
                  label: const Text('Refuser'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: busy ? null : onApprove,
                  icon:
                      busy
                          ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                          : const Icon(Icons.check_rounded, size: 17),
                  label: const Text('Appliquer'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.rh,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
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

class _Metric extends StatelessWidget {
  const _Metric({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          value,
          style: AppTypography.title.copyWith(
            color: color,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: AppTypography.caption.copyWith(color: MobileSurface.disabled),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return MobilePanel(
      child: Column(
        children: [
          MobileIconBubble(icon: icon, color: AppColors.rh, size: 48),
          const SizedBox(height: 12),
          Text(
            title,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 6),
          Text(
            message,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
              height: 1.4,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

String _time(DateTime? value) {
  if (value == null) return '--:--';
  return DateFormat('HH:mm').format(value.toLocal());
}
