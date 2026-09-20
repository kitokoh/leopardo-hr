import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_cameras/core/i18n/app_strings.dart';
import 'package:leopardo_cameras/core/providers/core_providers.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_device.dart';
import 'package:leopardo_cameras/features/cameras/providers/cameras_providers.dart';
import 'package:leopardo_cameras/features/cameras/widgets/cameras_states.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

/// Mur des caméras du tenant (BC-19 DEVICE, #7426 — AC 1).
///
/// Respecte le flag `cameras` et les permissions : un 403 est rendu comme
/// refus explicite (AC 2), jamais comme un écran vide.
class CamerasHomeScreen extends ConsumerWidget {
  const CamerasHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final wall = ref.watch(cameraWallProvider);
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('appName')),
        backgroundColor: AppColors.mobileDarkBg,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.notification_important_outlined),
            tooltip: l10n.t('alertsTitle'),
            onPressed: () => context.push('/alerts'),
          ),
          IconButton(
            icon: const Icon(Icons.history_outlined),
            tooltip: l10n.t('eventsTitle'),
            onPressed: () => context.push('/events'),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: l10n.t('logout'),
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
      ),
      backgroundColor: bg,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.refresh(cameraWallProvider.future),
          child: wall.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, _) {
              final refusal = classifyRefusal(error);
              if (refusal != CamerasAccessRefusal.none) {
                return CamerasRefusalView(refusal: refusal, l10n: l10n);
              }
              return CamerasErrorView(
                l10n: l10n,
                onRetry: () => ref.invalidate(cameraWallProvider),
              );
            },
            data: (data) {
              if (data.cameras.isEmpty) {
                return ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(32),
                  children: [
                    const SizedBox(height: 48),
                    const Icon(
                      Icons.videocam_off_outlined,
                      size: 48,
                      color: AppColors.security,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      l10n.t('camerasEmpty'),
                      textAlign: TextAlign.center,
                      style: AppTypography.subtitle.copyWith(color: text),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      l10n.t('camerasEmptyHint'),
                      textAlign: TextAlign.center,
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ],
                );
              }

              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    l10n.t('homeTitle'),
                    style: AppTypography.title.copyWith(color: text),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    l10n.t('homeSubtitle'),
                    style: AppTypography.caption.copyWith(color: muted),
                  ),
                  const SizedBox(height: 16),
                  for (final camera in data.cameras) ...[
                    _CameraCard(camera: camera, l10n: l10n),
                    const SizedBox(height: 12),
                  ],
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _CameraCard extends ConsumerWidget {
  const _CameraCard({required this.camera, required this.l10n});

  final CameraDevice camera;
  final AppStrings l10n;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final statusColor =
        camera.isActive ? AppColors.success : AppColors.danger;

    return Card(
      elevation: 0,
      color: AppColors.surfaceFor(context),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => context.push('/camera/${camera.id}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.security.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.videocam_outlined,
                  color: AppColors.security,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      camera.name,
                      style: AppTypography.body.copyWith(
                        color: text,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (camera.location != null &&
                        camera.location!.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        camera.location!,
                        style: AppTypography.caption.copyWith(color: muted),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  l10n.t(camera.isActive ? 'cameraActive' : 'cameraInactive'),
                  style: AppTypography.caption.copyWith(
                    color: statusColor,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(width: 4),
              Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}
