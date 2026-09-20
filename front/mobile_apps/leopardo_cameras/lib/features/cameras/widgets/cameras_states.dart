import 'package:flutter/material.dart';
import 'package:leopardo_cameras/core/i18n/app_strings.dart';
import 'package:leopardo_cameras/features/cameras/providers/cameras_providers.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

/// États partagés des écrans caméras (AC 2 : refus explicite, jamais un
/// écran vide).

/// Refus d'accès explicite : module non activé (flag `cameras`) ou rôle
/// insuffisant (`api.manager`).
class CamerasRefusalView extends StatelessWidget {
  const CamerasRefusalView({
    super.key,
    required this.refusal,
    required this.l10n,
  });

  final CamerasAccessRefusal refusal;
  final AppStrings l10n;

  @override
  Widget build(BuildContext context) {
    final locked = refusal == CamerasAccessRefusal.featureLocked;
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              locked ? Icons.lock_outline : Icons.block_outlined,
              size: 48,
              color: AppColors.warning,
            ),
            const SizedBox(height: 16),
            Text(
              l10n.t(locked ? 'featureLockedTitle' : 'forbiddenTitle'),
              textAlign: TextAlign.center,
              style: AppTypography.subtitle.copyWith(color: text),
            ),
            const SizedBox(height: 8),
            Text(
              l10n.t(locked ? 'featureLockedBody' : 'forbiddenBody'),
              textAlign: TextAlign.center,
              style: AppTypography.caption.copyWith(color: muted),
            ),
          ],
        ),
      ),
    );
  }
}

/// Erreur générique avec bouton « Réessayer ».
class CamerasErrorView extends StatelessWidget {
  const CamerasErrorView({
    super.key,
    required this.l10n,
    required this.onRetry,
  });

  final AppStrings l10n;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final muted = AppColors.textSecondaryFor(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.cloud_off_outlined,
              size: 48,
              color: AppColors.danger,
            ),
            const SizedBox(height: 16),
            Text(
              l10n.t('loadError'),
              textAlign: TextAlign.center,
              style: AppTypography.caption.copyWith(color: muted),
            ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: Text(l10n.t('retry')),
            ),
          ],
        ),
      ),
    );
  }
}

/// Pastille de sévérité (info/warning/high/critical) — couleurs AppColors.
class SeverityBadge extends StatelessWidget {
  const SeverityBadge({super.key, required this.severity, required this.l10n});

  final String severity;
  final AppStrings l10n;

  Color get _color => switch (severity) {
        'critical' => AppColors.danger,
        'high' => AppColors.warning,
        'warning' => AppColors.warning,
        _ => AppColors.info,
      };

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: _color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        l10n.t('severity_$severity'),
        style: AppTypography.caption.copyWith(
          color: _color,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
