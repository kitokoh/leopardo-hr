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
import 'package:leopardo_rh/features/contracts/screens/contract_screen.dart';
import 'package:leopardo_rh/features/training/screens/training_screen.dart';
import 'package:leopardo_rh/features/expenses/screens/expense_list_screen.dart';
import 'package:leopardo_rh/features/ai_chat/screens/ai_chat_screen.dart';
import 'package:leopardo_rh/features/ai_voice/screens/ai_voice_screen.dart';
import 'package:leopardo_rh/features/vehicle_position/screens/vehicle_map_screen.dart';
import 'package:leopardo_rh/features/approvals/screens/approval_screen.dart';
import 'package:leopardo_rh/features/onboarding/screens/onboarding_screen.dart';
import 'package:leopardo_rh/l10n/l10n.dart';

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
      GoRoute(
        path: '/contracts',
        builder: (context, state) => const ContractScreen(),
      ),
      GoRoute(
        path: '/training',
        builder: (context, state) => const TrainingScreen(),
      ),
      GoRoute(
        path: '/expenses',
        builder: (context, state) => const ExpenseListScreen(),
      ),
      GoRoute(
        path: '/ai-chat',
        builder: (context, state) => const AiChatScreen(),
      ),
      GoRoute(
        path: '/ai-voice',
        builder: (context, state) => const AiVoiceScreen(),
      ),
      GoRoute(
        path: '/vehicle-map',
        builder: (context, state) => const VehicleMapScreen(),
      ),
      GoRoute(
        path: '/approvals',
        builder: (context, state) => const ApprovalScreen(),
      ),
      GoRoute(
        path: '/onboarding',
        builder: (context, state) => const OnboardingScreen(),
      ),
    ],
  );
});

class LeopardoApp extends ConsumerWidget {
  const LeopardoApp({super.key});

  Locale _resolveLocale(String rawLocale) {
    final normalized = rawLocale.trim().replaceAll('_', '-');
    final parts =
        normalized.split('-').where((part) => part.isNotEmpty).toList();

    if (parts.isEmpty) {
      return const Locale('fr');
    }

    final languageCode = parts.first.toLowerCase();
    final countryCode = parts.length > 1 ? parts[1].toUpperCase() : null;

    if (!const {'fr', 'ar', 'tr', 'en'}.contains(languageCode)) {
      return const Locale('fr');
    }

    return countryCode == null || countryCode.isEmpty
        ? Locale(languageCode)
        : Locale(languageCode, countryCode);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);
    final authState = ref.watch(authProvider);
    final preferences = ref.watch(appPreferencesProvider);
    final deviceLanguage =
        PlatformDispatcher.instance.locale.toLanguageTag().toLowerCase();
    final languageCode =
        authState.employee?.language ??
        (preferences.preferredLanguage.isNotEmpty
            ? preferences.preferredLanguage
            : deviceLanguage);
    final isRtl = authState.employee?.isRtl ?? preferences.isRtl;
    final locale = _resolveLocale(languageCode);

    return MaterialApp.router(
      title: 'Leopardo RH',
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: ThemeMode.light,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      locale: locale,
      supportedLocales: const [
        Locale('fr'),
        Locale('fr', 'FR'),
        Locale('fr', 'BE'),
        Locale('fr', 'CA'),
        Locale('ar'),
        Locale('ar', 'SA'),
        Locale('ar', 'MA'),
        Locale('tr'),
        Locale('tr', 'TR'),
        Locale('en'),
        Locale('en', 'US'),
        Locale('en', 'GB'),
      ],
      localizationsDelegates: const [
        AppLocalizations.delegate,
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
      localeResolutionCallback: (requestedLocale, supportedLocales) {
        if (requestedLocale == null) {
          return const Locale('fr');
        }

        final normalized = _resolveLocale(
          requestedLocale.countryCode == null
              ? requestedLocale.languageCode
              : '${requestedLocale.languageCode}-${requestedLocale.countryCode}',
        );

        for (final supported in supportedLocales) {
          if (supported.languageCode == normalized.languageCode &&
              (supported.countryCode == null ||
                  supported.countryCode == normalized.countryCode)) {
            return supported;
          }
        }

        for (final supported in supportedLocales) {
          if (supported.languageCode == normalized.languageCode) {
            return supported;
          }
        }

        return const Locale('fr');
      },
    );
  }
}
