import 'package:flutter/material.dart';
import 'dart:ui' show PlatformDispatcher;
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/core/theme/app_theme.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/features/auth/screens/login_screen.dart';
import 'package:leopardo_rh/features/auth/screens/register_screen.dart';
import 'package:leopardo_rh/features/auth/screens/welcome_screen.dart';
import 'package:leopardo_rh/features/attendance/screens/attendance_screen.dart';
import 'package:leopardo_rh/features/attendance/screens/history_screen.dart';
import 'package:leopardo_rh/features/attendance/screens/monthly_summary_screen.dart';
import 'package:leopardo_rh/features/home/screens/home_screen.dart';
import 'package:leopardo_rh/features/modules/screens/modules_screen.dart';
import 'package:leopardo_rh/features/settings/screens/settings_screen.dart';
import 'package:leopardo_rh/features/team/screens/team_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authListenable = ValueNotifier<AuthState>(ref.read(authProvider));

  ref.listen<AuthState>(authProvider, (_, next) {
    authListenable.value = next;
  });

  ref.onDispose(authListenable.dispose);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: authListenable,
    redirect: (context, state) {
      final authState = authListenable.value;
      final isAuth = authState.employee != null;

      if (authState.isLoading) return null;

      final location = state.matchedLocation;
      const publicRoutes = {'/welcome', '/login', '/register'};
      final onPublic = publicRoutes.contains(location);

      if (!isAuth && !onPublic) return '/welcome';
      if (isAuth && onPublic) return '/';
      return null;
    },
    routes: [
      GoRoute(
        path: '/welcome',
        builder: (context, state) => const WelcomeScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(
        path: '/',
        builder: (context, state) => const HomeScreen(),
      ),
      GoRoute(
        path: '/attendance',
        builder: (context, state) => const AttendanceScreen(),
      ),
      GoRoute(
        path: '/history',
        builder: (context, state) => const HistoryScreen(),
      ),
      GoRoute(
        path: '/me/monthly',
        builder: (context, state) => const MonthlySummaryScreen(),
      ),
      GoRoute(
        path: '/team',
        builder: (context, state) => const TeamScreen(),
      ),
      GoRoute(
        path: '/modules',
        builder: (context, state) => const ModulesScreen(),
      ),
      GoRoute(
        path: '/settings',
        builder: (context, state) => const SettingsScreen(),
      ),
    ],
  );
});

class LeopardoApp extends ConsumerWidget {
  const LeopardoApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);
    final authState = ref.watch(authProvider);
    final preferences = ref.watch(appPreferencesProvider);
    final deviceLanguage = PlatformDispatcher.instance.locale.languageCode.toLowerCase();
    final languageCode = authState.employee?.language ??
        (preferences.preferredLanguage.isNotEmpty ? preferences.preferredLanguage : deviceLanguage);
    final isRtl = authState.employee?.isRtl ?? preferences.isRtl;

    return MaterialApp.router(
      title: 'Leopardo RH',
      theme: AppTheme.darkTheme,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      locale: Locale(languageCode),
      supportedLocales: const [
        Locale('fr'),
        Locale('ar'),
        Locale('tr'),
        Locale('en'),
      ],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      builder: (context, child) {
        return Directionality(
          textDirection: isRtl ? TextDirection.rtl : TextDirection.ltr,
          child: child ?? const SizedBox.shrink(),
        );
      },
    );
  }
}
