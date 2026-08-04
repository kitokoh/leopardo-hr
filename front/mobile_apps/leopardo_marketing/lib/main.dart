import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:leopardo_core/core/theme/tenant_theme.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';
import 'features/marketing/screens/editorial_calendar_screen.dart';
import 'features/marketing/screens/post_create_screen.dart';

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

final _router = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(
      path: '/',
      builder: (context, state) => const EditorialCalendarScreen(),
    ),
    GoRoute(
      path: '/create-post',
      builder: (context, state) => const PostCreateScreen(),
    ),
  ],
);

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
