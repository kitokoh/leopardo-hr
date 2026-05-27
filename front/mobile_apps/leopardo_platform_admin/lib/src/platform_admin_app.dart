import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_theme.dart';
import 'package:leopardo_core/l10n/l10n.dart';

import 'features/auth/platform_auth_controller.dart';
import 'features/companies/company_detail_screen.dart';
import 'features/auth/platform_login_screen.dart';
import 'features/companies/company_create_screen.dart';
import 'features/companies/company_requests_screen.dart';
import 'features/companies/company_screen.dart';
import 'features/dashboard/platform_dashboard_screen.dart';

final platformRouterProvider = Provider<GoRouter>((ref) {
  final authListenable = ValueNotifier<PlatformAuthState>(
    ref.read(platformAuthControllerProvider),
  );

  ref.listen<PlatformAuthState>(platformAuthControllerProvider, (_, next) {
    authListenable.value = next;
  });

  ref.onDispose(authListenable.dispose);

  return GoRouter(
    initialLocation: '/platform',
    refreshListenable: authListenable,
    redirect: (context, state) {
      final authState = authListenable.value;
      final location = state.matchedLocation;
      final onLogin = location == '/platform/login';

      if (authState.isBootstrapping) return null;
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
      themeMode: ThemeMode.dark,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      locale: const Locale('fr'),
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
