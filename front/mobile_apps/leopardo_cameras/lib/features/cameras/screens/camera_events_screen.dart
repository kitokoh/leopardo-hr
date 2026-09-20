import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_cameras/core/i18n/app_strings.dart';
import 'package:leopardo_cameras/core/providers/core_providers.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_event.dart';
import 'package:leopardo_cameras/features/cameras/providers/cameras_providers.dart';
import 'package:leopardo_cameras/features/cameras/widgets/cameras_states.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

/// Journal des événements de la chaîne vidéo (BC-19 DEVICE, #7426 — AC 3,
/// consomme GET /cameras/events livré par #7427).
///
/// `focusEventId` (route `/events?focus=<id>`) est la cible du deep link
/// notification (#7427) : l'événement concerné est mis en évidence.
/// RGPD : l'API n'expose aucun chemin de snapshot, seulement `has_snapshot`.
class CameraEventsScreen extends ConsumerWidget {
  const CameraEventsScreen({super.key, this.focusEventId});

  final int? focusEventId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final events = ref.watch(cameraEventsProvider);
    final bg = AppColors.backgroundFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('eventsTitle')),
        backgroundColor: AppColors.mobileDarkBg,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: l10n.t('refresh'),
            onPressed: () => ref.invalidate(cameraEventsProvider),
          ),
        ],
      ),
      backgroundColor: bg,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.refresh(cameraEventsProvider.future),
          child: events.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, _) {
              final refusal = classifyRefusal(error);
              if (refusal != CamerasAccessRefusal.none) {
                return CamerasRefusalView(refusal: refusal, l10n: l10n);
              }
              return CamerasErrorView(
                l10n: l10n,
                onRetry: () => ref.invalidate(cameraEventsProvider),
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
                      Icons.history_outlined,
                      size: 48,
                      color: AppColors.security,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      l10n.t('eventsEmpty'),
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
                  final event = items[index];
                  return _EventCard(
                    event: event,
                    l10n: l10n,
                    highlighted: event.id == focusEventId,
                  );
                },
              );
            },
          ),
        ),
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({
    required this.event,
    required this.l10n,
    this.highlighted = false,
  });

  final CameraEvent event;
  final AppStrings l10n;
  final bool highlighted;

  IconData get _icon => switch (event.type) {
        'person' => Icons.person_outline,
        'vehicle' => Icons.directions_car_outlined,
        'line_crossing' => Icons.linear_scale_outlined,
        'tamper' => Icons.report_problem_outlined,
        _ => Icons.motion_photos_on_outlined,
      };

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Card(
      elevation: 0,
      color: AppColors.surfaceFor(context),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: highlighted
            ? const BorderSide(color: AppColors.security, width: 2)
            : BorderSide.none,
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(_icon, color: AppColors.security),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l10n.t('eventType_${event.type}'),
                    style: AppTypography.body.copyWith(
                      color: text,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    event.cameraName ?? '#${event.cameraId}',
                    style: AppTypography.caption.copyWith(color: muted),
                  ),
                  if (event.detectedAt != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      event.detectedAt!,
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ],
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(
                        event.hasSnapshot
                            ? Icons.photo_camera_outlined
                            : Icons.hide_image_outlined,
                        size: 14,
                        color: muted,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        l10n.t(
                          event.hasSnapshot
                              ? 'eventSnapshot'
                              : 'eventNoSnapshot',
                        ),
                        style: AppTypography.caption.copyWith(color: muted),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            SeverityBadge(severity: event.severity, l10n: l10n),
          ],
        ),
      ),
    );
  }
}
