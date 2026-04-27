import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// APV Design v3 — Badge statut Leopardo.
///
/// Factory centralisee : on ne code plus une couleur de statut ailleurs que
/// dans docs/STATUTS.md + AppColors.forStatus + ce widget.
///
/// Exemples :
///   LeopardoBadge.present()
///   LeopardoBadge.late()
///   LeopardoBadge.domain('finance', 'Finance activable')
class LeopardoBadge extends StatelessWidget {
  const LeopardoBadge({
    super.key,
    required this.label,
    required this.color,
    this.icon,
  });

  final String label;
  final Color color;
  final IconData? icon;

  factory LeopardoBadge.forStatus(String status, String label) {
    return LeopardoBadge(
      label: label,
      color: AppColors.forStatus(status),
    );
  }

  factory LeopardoBadge.present({String label = 'Présent'}) =>
      LeopardoBadge(label: label, color: AppColors.success, icon: Icons.check_circle);

  factory LeopardoBadge.late({String label = 'En retard'}) =>
      LeopardoBadge(label: label, color: AppColors.warning, icon: Icons.schedule);

  factory LeopardoBadge.absent({String label = 'Absent'}) =>
      LeopardoBadge(label: label, color: AppColors.danger, icon: Icons.error_outline);

  factory LeopardoBadge.onLeave({String label = 'En congé'}) =>
      LeopardoBadge(label: label, color: AppColors.info, icon: Icons.beach_access);

  factory LeopardoBadge.domain(String domain, String label, {IconData? icon}) {
    return LeopardoBadge(
      label: label,
      color: AppColors.forDomain(domain),
      icon: icon,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}
