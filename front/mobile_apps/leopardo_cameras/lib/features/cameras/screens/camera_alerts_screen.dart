import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_cameras/core/i18n/app_strings.dart';
import 'package:leopardo_cameras/core/providers/core_providers.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_alert.dart';
import 'package:leopardo_cameras/features/cameras/providers/cameras_providers.dart';
import 'package:leopardo_cameras/features/cameras/widgets/cameras_states.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

/// Alertes caméra dédupliquées (BC-19 DEVICE, #7426 — consomme
/// GET /cameras/alerts livré par #7427). Cycle open -> acknowledged ->
/// resolved, actions idempotentes. Une alerte pointe vers son événement
/// (`camera_event_id`) : « ouvrir » navigue vers `/events?focus=<id>`
/// (même cible que le deep link notification, AC 3).
class CameraAlertsScreen extends ConsumerWidget {
  const CameraAlertsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final alerts = ref.watch(cameraAlertsProvider);
    final bg = AppColors.backgroundFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('alertsTitle')),
        backgroundColor: AppColors.mobileDarkBg,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: l10n.t('refresh'),
            onPressed: () => ref.invalidate(cameraAlertsProvider),
          ),
        ],
      ),
      backgroundColor: bg,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.refresh(cameraAlertsProvider.future),
          child: alerts.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, _) {
              final refusal = classifyRefusal(error);
              if (refusal != CamerasAccessRefusal.none) {
                return CamerasRefusalView(refusal: refusal, l10n: l10n);
              }
              return CamerasErrorView(
                l10n: l10n,
                onRetry: () => ref.invalidate(cameraAlertsProvider),
              );
            },
            data: (items) {
              if (items.isEmpty) {
                return ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(32),
                  children: [
                    const SizedBox(height: 48),
                    const Icon(
                      Icons.notifications_off_outlined,
                      size: 48,
                      color: AppColors.security,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      l10n.t('alertsEmpty'),
                      textAlign: TextAlign.center,
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ],
                );
              }

              return ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  return _AlertCard(alert: items[index], l10n: l10n);
                },
              );
            },
          ),
        ),
      ),
    );
  }
}

class _AlertCard extends ConsumerWidget {
  const _AlertCard({required this.alert, required this.l10n});

  final CameraAlert alert;
  final AppStrings l10n;

  Future<void> _run(
    BuildContext context,
    WidgetRef ref,
    Future<void> Function() action,
  ) async {
    try {
      await action();
      ref.invalidate(cameraAlertsProvider);
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(l10n.t('alertActionError'))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final repository = ref.read(camerasRepositoryProvider);
    final statusColor = switch (alert.status) {
      'open' => AppColors.danger,
      'acknowledged' => AppColors.warning,
      _ => AppColors.success,
    };

    return Card(
      elevation: 0,
      color: AppColors.surfaceFor(context),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: alert.cameraEventId != null
            ? () => context.push('/events?focus=${alert.cameraEventId}')
            : () => context.push('/camera/${alert.cameraId}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          l10n.t('eventType_${alert.type}'),
                          style: AppTypography.body.copyWith(
                            color: text,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          alert.cameraName ?? '#${alert.cameraId}',
                          style:
                              AppTypography.caption.copyWith(color: muted),
                        ),
                        if (alert.createdAt != null) ...[
                          const SizedBox(height: 2),
                          Text(
                            alert.createdAt!,
                            style:
                                AppTypography.caption.copyWith(color: muted),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      SeverityBadge(severity: alert.severity, l10n: l10n),
                      const SizedBox(height: 4),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          l10n.t('alertStatus_${alert.status}'),
                          style: AppTypography.caption.copyWith(
                            color: statusColor,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              if (!alert.isResolved) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    if (alert.isOpen) ...[
                      OutlinedButton(
                        onPressed: () => _run(
                          context,
                          ref,
                          () => repository.acknowledgeAlert(alert.id),
                        ),
                        child: Text(l10n.t('alertAcknowledge')),
                      ),
                      const SizedBox(width: 8),
                    ],
                    FilledButton(
                      onPressed: () => _run(
                        context,
                        ref,
                        () => repository.resolveAlert(alert.id),
                      ),
                      child: Text(l10n.t('alertResolve')),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
