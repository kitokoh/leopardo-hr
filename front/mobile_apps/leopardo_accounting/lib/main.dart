import 'dart:async';

import 'package:flutter/foundation.dart';
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
import 'features/accounting/screens/accounting_home_screen.dart';
import 'features/accounting/screens/create_invoice_screen.dart';
import 'features/accounting/screens/documents_screen.dart';
import 'features/accounting/screens/unpaid_screen.dart';
import 'features/auth/providers/auth_provider.dart';
import 'features/auth/screens/login_screen.dart';

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
        appName: 'Leopardo Accounting',
        initializer: _bootstrap,
        criticalInitializer: _criticalBootstrap,
        optionalInitializer: _optionalBootstrap,
        child: LeopardoAccountingApp(),
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
  // Init non critique (réservé).
}

// ─── Router ───────────────────────────────────────────────────────────────────
//
// Issue #5236 : `/accounting/*` est protégé par auth:sanctum +
// api.manager:comptable,principal — le redirect() exige une session valide
// (pattern ValueNotifier + refreshListenable, cf. leopardo_marketing).

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
        builder: (context, state) => const AccountingHomeScreen(),
      ),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(
        path: '/documents',
        builder: (context, state) => const DocumentsScreen(),
      ),
      GoRoute(
        path: '/create-invoice',
        builder: (context, state) => const CreateInvoiceScreen(),
      ),
      GoRoute(
        path: '/unpaid',
        builder: (context, state) => const UnpaidScreen(),
      ),
    ],
  );
});

// ─── Application ──────────────────────────────────────────────────────────────

class LeopardoAccountingApp extends ConsumerWidget {
  const LeopardoAccountingApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(_routerProvider);
    final preferences = ref.watch(appPreferencesProvider);
    final locale = Locale(AppStrings.of(preferences.preferredLanguage).locale);

    return MaterialApp.router(
      title: 'Leopardo Accounting',
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
