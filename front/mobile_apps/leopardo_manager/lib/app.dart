import 'dart:async';

import 'package:flutter/material.dart';

import 'dart:ui' show PlatformDispatcher;

import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/branding/tenant_theme.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_core/core/theme/app_theme.dart';
import 'package:leopardo_core/features/auth/providers/auth_provider.dart';
import 'package:leopardo_core/features/auth/screens/access_denied_screen.dart';
import 'package:leopardo_core/features/auth/screens/login_screen.dart';
import 'package:leopardo_core/features/auth/screens/register_screen.dart';
import 'package:leopardo_core/features/auth/screens/welcome_screen.dart';
import 'package:leopardo_manager/features/attendance/screens/attendance_screen.dart';
import 'package:leopardo_manager/features/attendance/screens/history_screen.dart';
import 'package:leopardo_manager/features/attendance/screens/monthly_summary_screen.dart';
import 'package:leopardo_core/features/home/screens/home_screen.dart';
import 'package:leopardo_core/features/home/screens/modules_hub_screen.dart';
import 'package:leopardo_core/features/absences/screens/absence_list_screen.dart';
import 'package:leopardo_core/features/salary_advances/screens/salary_advance_list_screen.dart';
import 'package:leopardo_manager/features/payrolls/screens/payroll_list_screen.dart';
import 'package:leopardo_core/features/notifications/screens/notification_list_screen.dart';
import 'package:leopardo_core/features/evaluations/screens/evaluation_list_screen.dart';
import 'package:leopardo_core/features/cabinet/screens/cabinet_screen.dart';
import 'package:leopardo_manager/features/settings/screens/settings_screen.dart';
import 'package:leopardo_core/features/team/screens/team_screen.dart';
import 'package:leopardo_core/features/tasks/screens/task_list_screen.dart';
import 'package:leopardo_core/features/user_auth/screens/user_register_screen.dart';
import 'package:leopardo_core/features/user_auth/screens/user_login_screen.dart';
import 'package:leopardo_manager/features/user_auth/screens/user_home_screen.dart';
import 'package:leopardo_core/features/user_auth/screens/company_request_screen.dart';
import 'package:leopardo_manager/features/ai_chat/screens/ai_chat_screen.dart';
import 'package:leopardo_manager/features/vehicle_position/screens/vehicle_map_screen.dart';
import 'package:leopardo_manager/features/approvals/screens/approval_screen.dart';
import 'package:leopardo_manager/features/manager/screens/manager_attendance_monitoring_screen.dart';
import 'package:leopardo_core/features/company_branding/screens/company_branding_screen.dart';
import 'package:leopardo_core/features/company_branding/providers/tenant_branding_provider.dart';
import 'package:leopardo_core/features/schedules/screens/schedule_list_screen.dart';
import 'package:leopardo_manager/features/smart_attendance/screens/smart_attendance_dashboard_screen.dart';
import 'package:leopardo_manager/features/smart_attendance/screens/pending_sessions_screen.dart';
import 'package:leopardo_core/l10n/l10n.dart';

import 'package:leopardo_manager/features/home/screens/manager_main_shell.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authListenable = ValueNotifier<AuthState>(ref.read(authProvider));

  ref.listen<AuthState>(authProvider, (_, next) {
    authListenable.value = next;
  });

  ref.onDispose(authListenable.dispose);

  return GoRouter(
    initialLocation: '/welcome',
    refreshListenable: authListenable,
    // Issue #2748 — écran de secours au lieu d'une page blanche/erreur
    // quand une navigation ne matche aucune route (ex. Cabinet avant fix).
    errorBuilder: (context, state) => Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.error_outline,
                size: 48,
                color: Colors.redAccent,
              ),
              const SizedBox(height: 12),
              Text(
                context.l10n.errorUnexpected,
                style:
                    const TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              Text(context.l10n.pageNotFound, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => context.go('/'),
                child: Text(context.l10n.backToHome),
              ),
            ],
          ),
        ),
      ),
    ),
    redirect: (context, state) {
      final authState = authListenable.value;
      final isAuth = authState.employee != null;
      final location = state.matchedLocation;

      // Pendant l'hydratation auth, garder l'ecran courant visible.
      if (authState.isLoading) {
        return null;
      }

      const publicRoutes = {
        '/welcome',
        '/login',
        '/register',
        '/user-register',
        '/user-login',
        '/user-home',
        '/company-request',
        '/access-denied',
      };
      final onPublic = publicRoutes.contains(location);
      final isAuthorized =
          isAuth && (authState.employee!.isManager || authState.employee!.isHr);

      if (!isAuth && !onPublic) return '/welcome';
      if (isAuth && !isAuthorized) {
        // T116 : plus de boucle /welcome ↔ / — écran « accès refusé » explicite
        // pour un utilisateur connecté sans le rôle de cette app.
        return location == '/access-denied' ? null : '/access-denied';
      }
      if (isAuth && onPublic) return '/';

      return null;
    },
    routes: [
      // --- Public routes (no bottom nav) ---
      GoRoute(
        path: '/welcome',
        builder: (context, state) => const WelcomeScreen(),
      ),
      GoRoute(
        path: '/access-denied',
        builder: (context, state) => const AccessDeniedScreen(),
      ),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterScreen(),
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

      // --- Authenticated routes with bottom nav ---
      ShellRoute(
        builder: (context, state, child) => ManagerMainShell(child: child),
        routes: [
          GoRoute(path: '/', builder: (context, state) => const HomeScreen()),
          // Issue #3205 — routes modules/quick actions restaurées : le manifeste
          // MobileExperienceService les sert toujours et l'UI les pousse
          // (context.push sur module.route / action.route). La PR #3117 les
          // avait retirées par erreur (régression #2212 — garde CI rouge).
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
          GoRoute(
            path: '/team',
            builder: (context, state) => const TeamScreen(),
          ),
          GoRoute(
            path: '/tasks',
            builder: (context, state) => const TaskListScreen(),
          ),
          GoRoute(
            path: '/cabinet',
            builder: (context, state) => const CabinetScreen(),
          ),
          // Issue #2748 — l'écran Cabinet pousse /cabinet/folder/{id}
          // (même convention que employee/HR) : la route 3 segments
          // manquait ici → GoError au tap sur un dossier.
          // T121/#3004 : garde int.tryParse — deep-link non numérique → écran
          // vide plutôt qu'un crash FormatException (route déclarée UNE fois).
          GoRoute(
            path: '/cabinet/folder/:folderId',
            builder: (context, state) {
              final folderId = int.tryParse(
                state.pathParameters['folderId'] ?? '',
              );
              if (folderId == null) {
                return const Scaffold(body: SizedBox.shrink());
              }
              final folderName = state.extra as String?;
              return CabinetScreen(folderId: folderId, folderName: folderName);
            },
          ),
          GoRoute(
            path: '/settings',
            builder: (context, state) => const SettingsScreen(),
          ),
          GoRoute(
            path: '/ai-chat',
            builder: (context, state) => const AiChatScreen(),
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
            path: '/schedules',
            builder: (context, state) => const ScheduleListScreen(),
          ),
          GoRoute(
            path: '/company/branding',
            builder: (context, state) => const CompanyBrandingScreen(),
          ),
          GoRoute(
            path: '/manager/attendance',
            builder: (context, state) =>
                const ManagerAttendanceMonitoringScreen(),
          ),
          GoRoute(
            path: '/manager/anomalies',
            builder: (context, state) => const ManagerAnomaliesScreen(),
          ),
          GoRoute(
            path: '/manager/corrections',
            builder: (context, state) => const ManagerCorrectionsScreen(),
          ),
          // ── Smart Attendance ──────────────────────────────────────────
          GoRoute(
            path: '/smart-attendance',
            builder: (context, state) => const SmartAttendanceDashboardScreen(),
          ),
          GoRoute(
            path: '/smart-attendance/pending',
            builder: (context, state) => const PendingGeoSessionsScreen(),
          ),
        ],
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
    ref.listen<AuthState>(authProvider, (previous, next) {
      final previousEmployeeId = previous?.employee?.id;
      final currentEmployeeId = next.employee?.id;

      if (currentEmployeeId != null &&
          currentEmployeeId != previousEmployeeId) {
        unawaited(
          ref
              .read(pushNotificationServiceProvider)
              .initialize(apiClient: ref.read(apiClientProvider)),
        );
        // Replay any check-in/check-out saved offline while disconnected
        // (issue #1289): without this the offline_punches Hive box is
        // never drained.
        unawaited(ref.read(offlineSyncServiceProvider).init());
      }
    });

    final router = ref.watch(routerProvider);
    final authState = ref.watch(authProvider);
    final preferences = ref.watch(appPreferencesProvider);
    final deviceLanguage =
        PlatformDispatcher.instance.locale.toLanguageTag().toLowerCase();
    final languageCode = authState.employee?.language ??
        (preferences.preferredLanguage.isNotEmpty
            ? preferences.preferredLanguage
            : deviceLanguage);
    final isRtl = authState.employee?.isRtl ?? preferences.isRtl;
    final locale = _resolveLocale(languageCode);
    final branding = ref.watch(
      tenantBrandingProvider.select(
        (value) => value.maybeWhen(data: (data) => data, orElse: () => null),
      ),
    );

    return MaterialApp.router(
      title: branding?.displayName ?? 'Leopardo RH',
      theme: TenantTheme.apply(AppTheme.lightTheme, branding),
      darkTheme: TenantTheme.apply(AppTheme.darkTheme, branding),
      themeMode: ThemeMode.system,
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
