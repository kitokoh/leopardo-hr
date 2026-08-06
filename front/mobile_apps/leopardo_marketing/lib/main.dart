import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:leopardo_core/core/branding/tenant_theme.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';
import 'package:leopardo_core/core/widgets/leopardo_bottom_nav.dart';
import 'features/marketing/screens/editorial_calendar_screen.dart';
import 'features/marketing/screens/post_create_screen.dart';
import 'features/marketing/screens/stats_dashboard_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('fr_FR', null);

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };

  runApp(
    ProviderScope(
      child: StartupGate(
        appName: 'Leopardo Marketing',
        initializer: _bootstrap,
        criticalInitializer: () async {},
        optionalInitializer: () async {},
        child: const LeopardoMarketingApp(),
      ),
    ),
  );
}

Future<void> _bootstrap() async {
  // Initialization logic for marketing app
}

// ─── Navigation items ─────────────────────────────────────────────────────────

const _navItems = [
  LeopardoNavItem(
    icon: Icons.calendar_today_outlined,
    activeIcon: Icons.calendar_today_rounded,
    label: 'Calendrier',
    route: '/',
  ),
  LeopardoNavItem(
    icon: Icons.add_box_outlined,
    activeIcon: Icons.add_box_rounded,
    label: 'Publier',
    route: '/create-post',
  ),
  LeopardoNavItem(
    icon: Icons.bar_chart_outlined,
    activeIcon: Icons.bar_chart_rounded,
    label: 'Stats',
    route: '/stats',
  ),
];

// ─── Router ───────────────────────────────────────────────────────────────────

final _router = GoRouter(
  initialLocation: '/',
  routes: [
    // Shell persistant : bottom nav partagé entre les 3 onglets principaux.
    ShellRoute(
      builder: (context, state, child) => _MarketingShell(
        currentRoute: state.matchedLocation,
        child: child,
      ),
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) => const EditorialCalendarScreen(),
        ),
        GoRoute(
          path: '/stats',
          builder: (context, state) => const StatsDashboardScreen(),
        ),
      ],
    ),
    // Hors shell : la création de post prend tout l'écran.
    GoRoute(
      path: '/create-post',
      builder: (context, state) => const PostCreateScreen(),
    ),
  ],
);

/// Shell layout avec la bottom nav. La route `/create-post` est hors shell
/// pour prendre tout l'écran (full-screen modal-like).
class _MarketingShell extends StatelessWidget {
  const _MarketingShell({
    required this.currentRoute,
    required this.child,
  });

  final String currentRoute;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: child,
      bottomNavigationBar: LeopardoBottomNav(
        currentRoute: currentRoute,
        items: _navItems,
        onTap: (route) => context.go(route),
      ),
    );
  }
}

// ─── Application ──────────────────────────────────────────────────────────────

class LeopardoMarketingApp extends StatelessWidget {
  const LeopardoMarketingApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'Leopardo Marketing',
      theme: TenantTheme.light(),
      darkTheme: TenantTheme.dark(),
      themeMode: ThemeMode.dark, // Default to dark for Glassmorphism
      routerConfig: _router,
      debugShowCheckedModeBanner: false,
    );
  }
}
