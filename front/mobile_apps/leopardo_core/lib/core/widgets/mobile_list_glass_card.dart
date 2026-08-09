import 'package:flutter/material.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';

/// A glassmorphism list item used by the mobile apps (employee/manager).
///
/// Referenced by screen call sites since the #1431 design pass but never
/// implemented in the core package — employee/manager apps did not compile.
/// Params are the union of every call site (absences, salary advances,
/// settings, team, tasks, schedules).
class MobileListGlassCard extends StatelessWidget {
  const MobileListGlassCard({
    super.key,
    required this.icon,
    required this.iconColor,
    required this.title,
    this.subtitle,
    this.trailing,
    this.footer,
    this.onTap,
  });

  final IconData icon;
  final Color iconColor;
  final String title;

  /// String, Widget or `List<Widget>` (task list passes a list of lines).
  final Object? subtitle;
  final Widget? trailing;
  final Widget? footer;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final cardColor = isDark
        ? AppColors.mobileDarkGlass.withValues(alpha: 0.4)
        : Colors.white.withValues(alpha: 0.7);

    final borderColor = isDark
        ? Colors.white.withValues(alpha: 0.1)
        : Colors.black.withValues(alpha: 0.05);

    Widget? subtitleWidget;
    if (subtitle is String) {
      subtitleWidget = Text(
        subtitle! as String,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: isDark ? AppColors.mobileDarkMuted : Colors.grey[600],
            ),
      );
    } else if (subtitle is List<Widget>) {
      subtitleWidget = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: subtitle! as List<Widget>,
      );
    } else if (subtitle is Widget) {
      subtitleWidget = subtitle! as Widget;
    }

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: iconColor.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(icon, color: iconColor, size: 20),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: isDark
                                    ? AppColors.mobileDarkText
                                    : Colors.black87,
                                fontWeight: FontWeight.w600,
                              ),
                        ),
                        if (subtitleWidget != null) ...[
                          const SizedBox(height: 2),
                          subtitleWidget,
                        ],
                      ],
                    ),
                  ),
                  if (trailing != null) ...[
                    const SizedBox(width: 8),
                    trailing!,
                  ],
                ],
              ),
              if (footer != null) ...[
                const SizedBox(height: 10),
                footer!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}
