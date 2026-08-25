import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_theme.dart';
import 'package:leopardo_core/l10n/l10n.dart';

import 'core/platform_providers.dart';
import 'features/auth/platform_auth_controller.dart';
import 'features/auth/platform_login_screen.dart';
import 'features/companies/company_create_screen.dart';
import 'features/companies/company_detail_screen.dart';
import 'features/companies/company_requests_screen.dart';
import 'features/companies/company_screen.dart';
import 'features/dashboard/platform_dashboard_screen.dart';
import 'features/edge/edge_nodes_screen.dart';
import 'features/support/support_tickets_screen.dart';

Locale? _resolvedLocale(WidgetRef ref) {
  final code = ref.read(appPreferencesProvider).preferredLanguage.trim();
  return code.isEmpty ? null : Locale(code);
}


final platformRouterProvider = Provider<GoRouter>((ref) {
  final authListenable = ValueNotifier<PlatformAuthState>(
    ref.read(platformAuthControllerProvider),
  );

  ref.listen<PlatformAuthState>(platformAuthControllerProvider, (_, next) {
    authListenable.value = next;
  });

  ref.onDispose(authListenable.dispose);

  return GoRouter(
    initialLocation: '/platform/login',
    refreshListenable: authListenable,
    redirect: (context, state) {
      final authState = authListenable.value;
      final location = state.matchedLocation;
      final onLogin = location == '/platform/login';

      // Pendant l'hydratation auth, garder le login visible.
      if (authState.isBootstrapping) {
        return null;
      }

      if (!authState.isAuthenticated && !onLogin) return '/platform/login';
      if (authState.isAuthenticated && onLogin) return '/platform';
      return null;
    },
    routes: [
      GoRoute(
        path: '/platform/login',
        builder: (context, state) => const PlatformLoginScreen(),
      ),
      GoRoute(
        path: '/platform',
        builder: (context, state) => const PlatformDashboardScreen(),
      ),
      GoRoute(
        path: '/platform/companies',
        builder: (context, state) => const CompanyScreen(),
      ),
      GoRoute(
        path: '/platform/companies/new',
        builder: (context, state) => const CompanyCreateScreen(),
      ),
      GoRoute(
        path: '/platform/companies/:companyId',
        builder: (context, state) {
          final companyId = state.pathParameters['companyId']!;
          return CompanyDetailScreen(companyId: companyId);
        },
      ),
      GoRoute(
        path: '/platform/company-requests',
        builder: (context, state) => const CompanyRequestsScreen(),
      ),
      // #3912 — Support tickets
      GoRoute(
        path: '/platform/support-tickets',
        builder: (context, state) => const SupportTicketsScreen(),
      ),
      GoRoute(
        path: '/platform/support-tickets/:ticketId',
        builder: (context, state) {
          final ticketId =
              int.tryParse(state.pathParameters['ticketId'] ?? '') ?? 0;
          return SupportTicketDetailScreen(ticketId: ticketId);
        },
      ),
      // #3912 — Edge nodes
      GoRoute(
        path: '/platform/edge-nodes',
        builder: (context, state) => const EdgeNodesScreen(),
      ),
    ],
  );
});

class PlatformAdminApp extends ConsumerWidget {
  const PlatformAdminApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(platformRouterProvider);

    return MaterialApp.router(
      title: 'Leopardo Platform Admin',
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      // #5515 : le thème suit le système (parité avec employee/hr/manager).
      themeMode: ThemeMode.system,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      // Issue #2761 — locale résolue depuis les préférences utilisateur
      // (fini le Locale('fr') codé qui ignorait appareil/langue).
      // Issue #2761 — locale résolue depuis les préférences utilisateur
      // (fini le Locale('fr') codé qui ignorait la langue de l'appareil).
      locale: _resolvedLocale(ref),
      supportedLocales: const [
        Locale('fr'),
        Locale('ar'),
        Locale('tr'),
        Locale('en'),
      ],
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
    );
  }
}
