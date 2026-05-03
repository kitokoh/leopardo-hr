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
import 'package:leopardo_rh/features/home/screens/modules_hub_screen.dart';
import 'package:leopardo_rh/features/absences/screens/absence_list_screen.dart';
import 'package:leopardo_rh/features/salary_advances/screens/salary_advance_list_screen.dart';
import 'package:leopardo_rh/features/payrolls/screens/payroll_list_screen.dart';
import 'package:leopardo_rh/features/notifications/screens/notification_list_screen.dart';
import 'package:leopardo_rh/features/evaluations/screens/evaluation_list_screen.dart';
import 'package:leopardo_rh/features/cabinet/screens/cabinet_screen.dart';
import 'package:leopardo_rh/features/settings/screens/settings_screen.dart';
import 'package:leopardo_rh/features/team/screens/team_screen.dart';
import 'package:leopardo_rh/features/user_auth/screens/user_register_screen.dart';
import 'package:leopardo_rh/features/user_auth/screens/user_login_screen.dart';
import 'package:leopardo_rh/features/user_auth/screens/user_home_screen.dart';
import 'package:leopardo_rh/features/user_auth/screens/company_request_screen.dart';

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
      const publicRoutes = {
        '/welcome',
        '/login',
        '/register',
        '/user-register',
        '/user-login',
        '/user-home',
        '/company-request',
      };
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
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(path: '/', builder: (context, state) => const HomeScreen()),
      GoRoute(
        path: '/modules',
        builder: (context, state) => const ModulesHubScreen(),
      ),
      GoRoute(
        path: '/absences',
        builder: (context, state) => const AbsenceListScreen(),
      ),
      GoRoute(
        path: '/salary-advances',
        builder: (context, state) => const SalaryAdvanceListScreen(),
      ),
      GoRoute(
        path: '/payrolls',
        builder: (context, state) => const PayrollListScreen(),
      ),
      GoRoute(
        path: '/notifications',
        builder: (context, state) => const NotificationListScreen(),
      ),
      GoRoute(
        path: '/evaluations',
        builder: (context, state) => const EvaluationListScreen(),
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
      GoRoute(path: '/team', builder: (context, state) => const TeamScreen()),
      GoRoute(
        path: '/modules/rh',
        builder: (context, state) => const ModulesScreen(),
      ),
      GoRoute(
        path: '/cabinet',
        builder: (context, state) => const CabinetScreen(),
      ),
      GoRoute(
        path: '/cabinet/folder/:folderId',
        builder: (context, state) {
          final folderId = int.parse(state.pathParameters['folderId']!);
          final folderName = state.extra as String?;
          return CabinetScreen(folderId: folderId, folderName: folderName);
        },
      ),
      GoRoute(
        path: '/settings',
        builder: (context, state) => const SettingsScreen(),
      ),
      GoRoute(
        path: '/user-register',
        builder: (context, state) => const UserRegisterScreen(),
      ),
      GoRoute(
        path: '/user-login',
        builder: (context, state) => const UserLoginScreen(),
      ),
      GoRoute(
        path: '/user-home',
        builder: (context, state) => const UserHomeScreen(),
      ),
      GoRoute(
        path: '/company-request',
        builder: (context, state) => const CompanyRequestScreen(),
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
    final deviceLanguage =
        PlatformDispatcher.instance.locale.languageCode.toLowerCase();
    final languageCode = authState.employee?.language ??
        (preferences.preferredLanguage.isNotEmpty
            ? preferences.preferredLanguage
            : deviceLanguage);
    final isRtl = authState.employee?.isRtl ?? preferences.isRtl;

    return MaterialApp.router(
      title: 'Leopardo RH',
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: ThemeMode.light,
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
