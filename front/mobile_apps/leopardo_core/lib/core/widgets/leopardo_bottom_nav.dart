import 'package:flutter/material.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

/// Item descriptor for [LeopardoBottomNav].
class LeopardoNavItem {
  const LeopardoNavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.route,
  });

  final IconData icon;
  final IconData activeIcon;
  final String label;
  final String route;
}

/// Premium glass-style bottom navigation bar shared across all Leopardo
/// mobile apps (employee, manager, platform_admin).
///
/// Usage:
/// ```dart
/// LeopardoBottomNav(
///   currentRoute: GoRouterState.of(context).matchedLocation,
///   items: [ ... ],
///   onTap: (route) => context.go(route),
/// )
/// ```
class LeopardoBottomNav extends StatelessWidget {
  const LeopardoBottomNav({
    super.key,
    required this.currentRoute,
    required this.items,
    required this.onTap,
  });

  final String currentRoute;
  final List<LeopardoNavItem> items;
  final ValueChanged<String> onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: MobileSurface.surface,
        border: Border(
          top: BorderSide(color: MobileSurface.border, width: 0.7),
        ),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: items.map((item) {
              final isActive =
                  currentRoute == item.route ||
                  (item.route != '/' && currentRoute.startsWith(item.route));
              return _NavBarItem(
                item: item,
                isActive: isActive,
                onTap: () => onTap(item.route),
              );
            }).toList(),
          ),
        ),
      ),
    );
  }
}

class _NavBarItem extends StatelessWidget {
  const _NavBarItem({
    required this.item,
    required this.isActive,
    required this.onTap,
  });

  final LeopardoNavItem item;
  final bool isActive;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = isActive ? AppColors.rh : MobileSurface.muted;

    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 200),
                child: Icon(
                  isActive ? item.activeIcon : item.icon,
                  key: ValueKey(isActive),
                  color: color,
                  size: 22,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                item.label,
                style: AppTypography.caption.copyWith(
                  color: color,
                  fontWeight: isActive ? FontWeight.w600 : FontWeight.w400,
                  fontSize: 10,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              if (isActive)
                Container(
                  margin: const EdgeInsets.only(top: 3),
                  width: 18,
                  height: 2.5,
                  decoration: BoxDecoration(
                    color: AppColors.rh,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
