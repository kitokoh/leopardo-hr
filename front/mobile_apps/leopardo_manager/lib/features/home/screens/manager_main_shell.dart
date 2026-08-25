import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/widgets/leopardo_bottom_nav.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Shell widget that wraps all authenticated routes for the Manager app.
///
/// Provides the bottom navigation bar with 5 tabs:
/// - Accueil (Home)
/// - Equipe (Team)
/// - Pointage (Smart Attendance)
/// - Validations (Approvals)
/// - Profil (Settings)
class ManagerMainShell extends StatelessWidget {
  const ManagerMainShell({super.key, required this.child});

  final Widget child;

  List<LeopardoNavItem> _items(BuildContext context) => [
        LeopardoNavItem(
          icon: Icons.dashboard_outlined,
          activeIcon: Icons.dashboard_rounded,
          label: 'Accueil',
          route: '/',
        ),
        LeopardoNavItem(
          icon: Icons.group_outlined,
          activeIcon: Icons.group,
          label: context.l10n.shellTeam,
          route: '/team',
        ),
        LeopardoNavItem(
          icon: Icons.fingerprint_outlined,
          activeIcon: Icons.fingerprint,
          label: 'Pointage',
          route: '/attendance/geo',
        ),
        LeopardoNavItem(
          icon: Icons.checklist_outlined,
          activeIcon: Icons.checklist,
          label: 'Validations',
          route: '/approvals',
        ),
        LeopardoNavItem(
          icon: Icons.settings_outlined,
          activeIcon: Icons.settings,
          label: context.l10n.shellSettings,
          route: '/settings',
        ),
      ];

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;

    return Scaffold(
      body: child,
      bottomNavigationBar: LeopardoBottomNav(
        currentRoute: location,
        items: _items(context),
        onTap: (route) => context.go(route),
      ),
    );
  }
}
