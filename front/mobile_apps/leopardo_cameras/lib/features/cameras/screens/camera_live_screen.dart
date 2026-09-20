import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_cameras/core/i18n/app_strings.dart';
import 'package:leopardo_cameras/core/providers/core_providers.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_device.dart';
import 'package:leopardo_cameras/features/cameras/providers/cameras_providers.dart';
import 'package:leopardo_cameras/features/cameras/widgets/cameras_states.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

/// Visionnage en direct d'une caméra (BC-19 DEVICE, #7426 — AC 2).
///
/// Consomme `GET /cameras/{id}/stream-token` : le flux WebRTC (MediaMTX,
/// chaîne vidéo #7424) est signé par un jeton JWT à durée limitée — même
/// contrat que la surface web (#7425). Aucun identifiant RTSP ne transite
/// ni n'est journalisé côté mobile (AC 4). Un tenant sans le flag `cameras`
/// (ou un rôle non-manager) voit un refus explicite, pas un écran vide.
class CameraLiveScreen extends ConsumerWidget {
  const CameraLiveScreen({super.key, required this.cameraId});

  final int cameraId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final stream = ref.watch(cameraStreamProvider(cameraId));
    final bg = AppColors.backgroundFor(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('liveTitle')),
        backgroundColor: AppColors.mobileDarkBg,
        foregroundColor: Colors.white,
      ),
      backgroundColor: bg,
      body: SafeArea(
        child: stream.when(
          loading: () => Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const CircularProgressIndicator(),
                const SizedBox(height: 16),
                Text(
                  l10n.t('liveLoading'),
                  style: AppTypography.caption.copyWith(
                    color: AppColors.textSecondaryFor(context),
                  ),
                ),
              ],
            ),
          ),
          error: (error, _) {
            final refusal = classifyRefusal(error);
            if (refusal != CamerasAccessRefusal.none) {
              return CamerasRefusalView(refusal: refusal, l10n: l10n);
            }
            return CamerasErrorView(
              l10n: l10n,
              onRetry: () => ref.invalidate(cameraStreamProvider(cameraId)),
            );
          },
          data: (camera) => _LiveBody(camera: camera, l10n: l10n),
        ),
      ),
    );
  }
}

class _LiveBody extends ConsumerWidget {
  const _LiveBody({required this.camera, required this.l10n});

  final CameraDevice camera;
  final AppStrings l10n;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Zone de rendu du direct — alimentée par la chaîne vidéo (#7424).
        // Le lecteur WebRTC embarqué suit dans une itération dédiée : cette
        // zone matérialise l'état du flux signé (jamais un écran vide).
        AspectRatio(
          aspectRatio: 16 / 9,
          child: Container(
            decoration: BoxDecoration(
              color: AppColors.mobileDarkBg,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  camera.isActive
                      ? Icons.play_circle_outline
                      : Icons.videocam_off_outlined,
                  size: 56,
                  color: Colors.white,
                ),
                const SizedBox(height: 8),
                Text(
                  camera.name,
                  style: AppTypography.subtitle
                      .copyWith(color: Colors.white),
                ),
                const SizedBox(height: 4),
                Text(
                  l10n.t(
                    camera.isActive ? 'liveStreamInfo' : 'cameraInactive',
                  ),
                  style: AppTypography.caption
                      .copyWith(color: Colors.white70),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          l10n.t('liveStreamHint'),
          style: AppTypography.caption.copyWith(color: muted),
        ),
        const SizedBox(height: 16),
        Card(
          elevation: 0,
          color: AppColors.surfaceFor(context),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _InfoRow(
                  label: l10n.t('liveStreamUrl'),
                  value: camera.streamUrl ?? l10n.t('noData'),
                  text: text,
                  muted: muted,
                ),
                const SizedBox(height: 12),
                _InfoRow(
                  label: l10n.t('liveTokenExpiresAt'),
                  value: camera.tokenExpiresAt ?? l10n.t('noData'),
                  text: text,
                  muted: muted,
                ),
                if (camera.location != null &&
                    camera.location!.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: l10n.t('location'),
                    value: camera.location!,
                    text: text,
                    muted: muted,
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: () =>
              ref.invalidate(cameraStreamProvider(camera.id)),
          icon: const Icon(Icons.refresh),
          label: Text(l10n.t('liveRefreshToken')),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.label,
    required this.value,
    required this.text,
    required this.muted,
  });

  final String label;
  final String value;
  final Color text;
  final Color muted;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: AppTypography.caption.copyWith(color: muted)),
        const SizedBox(height: 2),
        Text(
          value,
          style: AppTypography.bodySmall.copyWith(color: text),
        ),
      ],
    );
  }
}
