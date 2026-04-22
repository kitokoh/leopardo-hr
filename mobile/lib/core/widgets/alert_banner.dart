import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

/// APV Design v3 — AlertBanner.
///
/// Bandeau d'alerte contextuel, pose en haut d'un ecran (Home, Dashboard).
/// Le ton est conversationnel (pas "ERROR 500" mais "Oups, on reessaie ?").
///
/// Les couleurs et icones sont tirees des tokens AppColors pour rester
/// alignees avec le web (tailwind.config.js).
class AlertBanner extends StatelessWidget {
  const AlertBanner({
    super.key,
    required this.message,
    this.level = AlertLevel.info,
    this.icon,
    this.onDismiss,
    this.action,
  });

  final String message;
  final AlertLevel level;
  final IconData? icon;
  final VoidCallback? onDismiss;
  final Widget? action;

  Color get _color => switch (level) {
        AlertLevel.success => AppColors.success,
        AlertLevel.warning => AppColors.warning,
        AlertLevel.danger => AppColors.danger,
        AlertLevel.info => AppColors.info,
      };

  IconData get _defaultIcon => switch (level) {
        AlertLevel.success => Icons.check_circle_outline,
        AlertLevel.warning => Icons.warning_amber_outlined,
        AlertLevel.danger => Icons.error_outline,
        AlertLevel.info => Icons.info_outline,
      };

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: _color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _color.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Icon(icon ?? _defaultIcon, color: _color),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: AppTypography.bodySmall.copyWith(color: _color),
            ),
          ),
          if (action != null) action!,
          if (onDismiss != null)
            IconButton(
              icon: Icon(Icons.close, color: _color, size: 18),
              onPressed: onDismiss,
              visualDensity: VisualDensity.compact,
            ),
        ],
      ),
    );
  }
}

enum AlertLevel { success, warning, danger, info }
