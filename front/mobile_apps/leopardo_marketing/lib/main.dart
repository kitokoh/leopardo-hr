import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:leopardo_core/core/branding/tenant_theme.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';

import 'features/auth/providers/auth_provider.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/marketing/screens/marketing_home_screen.dart';
import 'core/providers/core_providers.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };

  // Anti page noire (doctrine v4.16.188+) : runApp immédiat, sans aucun await
  // bloquant avant le premier frame. L'init intl passe par le StartupGate.
  runApp(
    const ProviderScope(
      child: StartupGate(
        appName: 'Leopardo Marketing',
        initializer: _bootstrap,
        criticalInitializer: _criticalBootstrap,
        optionalInitializer: _optionalBootstrap,
        child: LeopardoMarketingApp(),
      ),
    ),
  );
}

/// Init critique exécutée par le StartupGate APRÈS le premier rendu :
/// les formats de date FR sont requis par le calendrier marketing.
Future<void> _criticalBootstrap() async {
  await initializeDateFormatting('fr_FR', null);
}

Future<void> _bootstrap() async {
  // Issue #3006 : l'état d'authentification est porté par authProvider
  // (checkAuth au démarrage) ; le routeur redirige vers /login si besoin.
}

Future<void> _optionalBootstrap() async {
  // Init non critique (réservé).
}

// ─── Router ───────────────────────────────────────────────────────────────────
//
// Issue #3293 : la bottom nav déclarait 3 onglets (« Calendrier » /,
// « Publier » /create-post, « Stats » /stats) alors que seul `/` était
// enregistré dans le GoRouter → GoException garantie au tap (écran coincé,
// shell remplacé). Tant que les écrans publication/stats n'existent pas
// (app marketing en skeleton, cf. #3006), le routeur n'expose que la home —
// pas de navigation morte. Réintroduire les onglets AVEC leurs routes le jour
// où les écrans arrivent.
//
// Issue #3006 : le redirect() exige une session (`/marketing/*` est protégé
// par auth:sanctum + api.manager:marketing,principal) — sans session, l'app
// affichait des 401 en cascade. Le routeur redirige vers /login tant que
// authProvider n'a pas d'employé authentifié (pattern ValueNotifier +
// refreshListenable, cf. leopardo_manager/app.dart).

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
        builder: (context, state) => const MarketingHomeScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
    ],
  );
});

// ─── Application ──────────────────────────────────────────────────────────────

class LeopardoMarketingApp extends ConsumerWidget {
  const LeopardoMarketingApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(_routerProvider);

    return MaterialApp.router(
      title: 'Leopardo Marketing',
      theme: TenantTheme.apply(ThemeData.light(), null),
      darkTheme: TenantTheme.apply(ThemeData.dark(), null),
      // Issue #3053 : ne pas forcer le dark — suivre le système.
      themeMode: ThemeMode.system,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
    );
  }
}
