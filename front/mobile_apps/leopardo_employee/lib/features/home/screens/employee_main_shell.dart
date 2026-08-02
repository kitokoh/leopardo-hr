import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/widgets/leopardo_bottom_nav.dart';

/// Shell widget that wraps all authenticated routes for the Employee app.
///
/// Provides the bottom navigation bar with 4 tabs:
/// - Accueil (Home)
/// - Pointage (Attendance)
/// - Absences
/// - Profil (Profile/Settings)
class EmployeeMainShell extends StatelessWidget {
  const EmployeeMainShell({super.key, required this.child});

  final Widget child;

  static const _items = [
    LeopardoNavItem(
      icon: Icons.home_outlined,
      activeIcon: Icons.home_rounded,
      label: 'Accueil',
      route: '/',
    ),
    LeopardoNavItem(
      icon: Icons.fingerprint_outlined,
      activeIcon: Icons.fingerprint,
      label: 'Pointage',
      route: '/attendance',
    ),
    LeopardoNavItem(
      icon: Icons.event_busy_outlined,
      activeIcon: Icons.event_busy,
      label: 'Absences',
      route: '/absences',
    ),
    LeopardoNavItem(
      icon: Icons.person_outline,
      activeIcon: Icons.person,
      label: 'Profil',
      route: '/profile',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;

    return Scaffold(
      body: child,
      bottomNavigationBar: LeopardoBottomNav(
        currentRoute: location,
        items: _items,
        onTap: (route) => context.go(route),
      ),
    );
  }
}
