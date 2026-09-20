import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:leopardo_core/core/branding/tenant_theme.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';
import 'package:leopardo_core/l10n/l10n.dart';

import 'core/i18n/app_strings.dart';
import 'core/providers/core_providers.dart';
import 'features/auth/providers/cameras_auth_provider.dart';
import 'features/auth/screens/cameras_login_screen.dart';
import 'features/cameras/screens/camera_alerts_screen.dart';
import 'features/cameras/screens/camera_events_screen.dart';
import 'features/cameras/screens/camera_live_screen.dart';
import 'features/cameras/screens/cameras_home_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };

  // Anti page noire : runApp immédiat, sans await bloquant avant le premier
  // frame. L'init intl passe par le StartupGate.
  runApp(
    const ProviderScope(
      child: StartupGate(
        appName: 'Leopardo Caméras',
        initializer: _bootstrap,
        criticalInitializer: _criticalBootstrap,
        optionalInitializer: _optionalBootstrap,
        child: LeopardoCamerasApp(),
      ),
    ),
  );
}

Future<void> _bootstrap() async {
  // L'état d'authentification est porté par authProvider (checkAuth au
  // démarrage) ; le routeur redirige vers /login si besoin.
}

Future<void> _criticalBootstrap() async {
  // #4336 : initialiser les 4 locales (fr/ar/tr/en) comme les autres apps.
  await initializeDateFormatting(deviceIntlDateLocale, null);
  await initializeDateFormatting('fr_CA', null);
  await initializeDateFormatting('fr_BE', null);
  await initializeDateFormatting('ar', null);
  await initializeDateFormatting('ar_SA', null);
  await initializeDateFormatting('ar_MA', null);
  await initializeDateFormatting('tr', null);
  await initializeDateFormatting('tr_TR', null);
  await initializeDateFormatting('en', null);
  await initializeDateFormatting('en_US', null);
  await initializeDateFormatting('en_GB', null);
}

Future<void> _optionalBootstrap() async {
  // Init non critique (réservé — le push des alertes arrive avec #7427).
}

// ─── Router ───────────────────────────────────────────────────────────────────
//
// `/cameras/*` est protégé par auth:sanctum + tenant + module.cameras +
// api.manager — le redirect() exige une session valide (pattern
// ValueNotifier + refreshListenable, cf. leopardo_travel_agent).
//
// `/events?focus=<id>` est la cible du deep link notification (#7427) :
// une alerte reçue ouvre directement l'événement concerné (AC 3).

final _routerProvider = Provider<GoRouter>((ref) {
  final authListenable = ValueNotifier<AuthState>(ref.read(authProvider));
  ref.listen<AuthState>(authProvider, (previous, next) {
    authListenable.value = next;
  });
  ref.onDispose(authListenable.dispose);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: authListenable,
    redirect: (context, state) {
      final authState = authListenable.value;
      final isAuth = authState.employee != null;

      // Pendant l'hydratation auth, garder l'écran courant visible.
      if (authState.isLoading) {
        return null;
      }

      final loggingIn = state.matchedLocation == '/login';
      if (!isAuth && !loggingIn) {
        return '/login';
      }
      if (isAuth && loggingIn) {
        return '/';
      }
      return null;
    },
    routes: [
      GoRoute(
        path: '/',
        builder: (context, state) => const CamerasHomeScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const CamerasLoginScreen(),
      ),
      GoRoute(
        path: '/camera/:id',
        builder: (context, state) => CameraLiveScreen(
          cameraId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/events',
        builder: (context, state) => CameraEventsScreen(
          focusEventId:
              int.tryParse(state.uri.queryParameters['focus'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/alerts',
        builder: (context, state) => const CameraAlertsScreen(),
      ),
    ],
  );
});

// ─── Application ──────────────────────────────────────────────────────────────

class LeopardoCamerasApp extends ConsumerWidget {
  const LeopardoCamerasApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(_routerProvider);
    final preferences = ref.watch(appPreferencesProvider);
    final locale = Locale(AppStrings.of(preferences.preferredLanguage).locale);

    return MaterialApp.router(
      title: 'Leopardo Caméras',
      theme: TenantTheme.apply(ThemeData.light(), null),
      darkTheme: TenantTheme.apply(ThemeData.dark(), null),
      themeMode: ThemeMode.system,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      locale: locale,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
    );
  }
}
