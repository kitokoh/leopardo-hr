import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_theme.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/features/auth/screens/login_screen.dart';
import 'package:leopardo_rh/features/attendance/screens/attendance_screen.dart';
import 'package:leopardo_rh/features/attendance/screens/history_screen.dart';

class LeopardoApp extends ConsumerWidget {
  const LeopardoApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final isAuth = authState.employee != null;

    final router = GoRouter(
      initialLocation: '/',
      redirect: (context, state) {
        if (authState.isLoading) return null;
        final loggingIn = state.matchedLocation == '/login';
        if (!isAuth && !loggingIn) return '/login';
        if (isAuth && loggingIn) return '/';
        return null;
      },
      routes: [
        GoRoute(
          path: '/login',
          builder: (context, state) => const LoginScreen(),
        ),
        GoRoute(
          path: '/',
          builder: (context, state) => const AttendanceScreen(),
        ),
        GoRoute(
          path: '/history',
          builder: (context, state) => const HistoryScreen(),
        ),
      ],
    );

    return MaterialApp.router(
      title: 'Leopardo RH',
      theme: AppTheme.darkTheme,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
    );
  }
}
