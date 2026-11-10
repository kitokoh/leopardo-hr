import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';
import 'package:leopardo_core/l10n/l10n.dart';

import 'core/navigation/app_shell.dart';
import 'core/providers/app_providers.dart';
import 'core/theme/public_theme.dart';

/// Entrée de l'application grand public Leopardo (couloir 16).
///
/// ## Le choix le plus important de ce fichier : aucun écran de connexion
///
/// Cette app n'a PAS de `redirect` vers `/login` — contrairement à
/// `leopardo_travel_agent` (`main.dart:94-104`) dont toutes les routes exigent
/// une session `auth:sanctum`. Ici le parcours critique est
/// « ouverture → recherche → fiche → Contacter », et le compte n'est demandé
/// qu'à la dernière étape, en M2. Ajouter un garde de route maintenant
/// contredirait la promesse produit et forcerait une inscription pour consulter
/// la fiche d'un restaurant trouvée par QR code.
///
/// ## Anti page noire
///
/// `runApp` est appelé IMMÉDIATEMENT ; toute initialisation bloquante passe par
/// `StartupGate`, qui applique un timeout et continue en mode dégradé plutôt que
/// de laisser un écran noir. C'est le patron des autres apps du dépôt
/// (`leopardo_travel_agent/lib/main.dart:27-45`).
void main() {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };

  runApp(
    const ProviderScope(
      child: StartupGate(
        appName: 'Leopardo',
        initializer: _bootstrap,
        criticalInitializer: _criticalBootstrap,
        optionalInitializer: _optionalBootstrap,
        child: LeopardoPublicApp(),
      ),
    ),
  );
}

Future<void> _bootstrap() async {
  // Aucune authentification à restaurer : l'app est consultable sans compte.
  // C'est volontaire et documenté ici pour qu'un futur contributeur ne
  // « complète » pas ce vide par un `checkAuth()` qui réintroduirait un écran
  // de connexion au démarrage.
}

Future<void> _criticalBootstrap() async {
  // Les 4 locales de l'app. `initializeDateFormatting` est indispensable pour
  // que les dates (horaires, prochaine ouverture) ne lèvent pas une
  // LocalizationError sur fr/ar/tr — le défaut d'`intl` ne connaît que `en_US`.
  await initializeDateFormatting(deviceIntlDateLocale, null);
  await initializeDateFormatting('fr', null);
  await initializeDateFormatting('fr_FR', null);
  await initializeDateFormatting('ar', null);
  await initializeDateFormatting('tr', null);
  await initializeDateFormatting('en', null);
  await initializeDateFormatting('en_US', null);
}

Future<void> _optionalBootstrap() async {
  // Réservé : rien n'est critique au-delà des locales.
}

class LeopardoPublicApp extends ConsumerWidget {
  const LeopardoPublicApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(appRouterProvider);
    final strings = ref.watch(appStringsProvider);

    return MaterialApp.router(
      title: 'Leopardo',
      // #9359 — thème PROPRE à l'app grand public, aligné sur le portail web
      // (`front/web/tailwind.config.ts`). Avant, `TenantTheme.apply(..., null)`
      // retournait la ThemeData Material inchangée : l'app n'avait aucune
      // identité visuelle et ne ressemblait pas à `/` ni à `/decouvrir`.
      // `TenantTheme` reste utilisé par les apps métier, qui portent la marque
      // de leur tenant ; ici il n'y a pas de tenant.
      theme: PublicTheme.light(),
      darkTheme: PublicTheme.dark(),
      themeMode: ThemeMode.system,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      // La locale suit le catalogue : c'est le MÊME objet qui décide des
      // textes et de la locale Material, donc les libellés système (boutons de
      // dialogue, sélecteurs de date) ne peuvent pas être dans une autre
      // langue que l'interface.
      locale: Locale(strings.locale),
      supportedLocales: const [
        Locale('fr'),
        Locale('en'),
        Locale('ar'),
        Locale('tr'),
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
