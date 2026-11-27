import 'dart:async';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:material_ui/material_ui.dart';
import 'package:cupertino_ui/cupertino_ui.dart'
    show GlobalCupertinoLocalizations;
import 'package:flutter_localizations/flutter_localizations.dart'
    hide GlobalMaterialLocalizations, GlobalCupertinoLocalizations;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_surface/leopardo_surface.dart';
import 'package:url_strategy/url_strategy.dart';

import 'app_router.dart';

/// Entrée de **Leopardo Pharmacies** — application dédiée à la verticale `pharmacy`.
///
/// ## Ce que cette app est
///
/// Un ASSEMBLAGE. Le moteur (découverte, fiches, parcours, identités de
/// vitrine) vit dans `leopardo_surface` et est partagé par les huit apps
/// grand public. Ce fichier ne fait que trois choses : poser la stratégie
/// d'URL web, initialiser les locales, monter le routeur de la verticale.
///
/// ## Aucun écran de connexion
///
/// Le parcours critique est « ouverture → recherche → fiche → commander ». Le
/// compte n'est demandé qu'à la dernière étape. Un garde de route au démarrage
/// forcerait une inscription pour consulter la fiche d'un restaurant trouvée
/// par QR code — ce serait contredire la promesse produit.
///
/// ## Anti page noire
///
/// `runApp` est appelé IMMÉDIATEMENT ; toute initialisation bloquante passe par
/// `StartupGate`, qui applique un timeout et continue en mode dégradé plutôt
/// que de laisser un écran noir.
///
/// ## Pourquoi la stratégie d'URL est posée AVANT `runApp`
///
/// Par défaut, Flutter web rend chaque route en `/#/restaurants/le-bistrot` :
/// le fragment n'est jamais envoyé au serveur, donc aucun crawler ne voit
/// l'URL. `setPathUrlStrategy` fait lire/écrire le chemin réel, ce qui est la
/// condition pour que `pharmacies.leopardo.app` soit indexable.
void main() {
  WidgetsFlutterBinding.ensureInitialized();

  if (kIsWeb) {
    setPathUrlStrategy();
  }

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };

  runApp(
    const ProviderScope(
      child: StartupGate(
        appName: 'Leopardo Pharmacies',
        initializer: _bootstrap,
        criticalInitializer: _criticalBootstrap,
        optionalInitializer: _optionalBootstrap,
        child: LeopardoPharmacyApp(),
      ),
    ),
  );
}

Future<void> _bootstrap() async {
  // Aucune authentification à restaurer : l'app est consultable sans compte.
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

class LeopardoPharmacyApp extends ConsumerWidget {
  const LeopardoPharmacyApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(appRouterProvider);
    final strings = ref.watch(appStringsProvider);

    return MaterialApp.router(
      title: 'Leopardo Pharmacies',
      // Thème PROPRE à l'app grand public, aligné sur le portail web. Le thème
      // de la verticale (palette, contraste AA) est appliqué par les écrans du
      // socle via `surfaceTheme(identity)` — ici on pose la base commune.
      theme: PublicTheme.light(),
      darkTheme: PublicTheme.dark(),
      themeMode: ThemeMode.system,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      // La locale suit le catalogue : c'est le MÊME objet qui décide des textes
      // et de la locale Material, donc les libellés système ne peuvent pas être
      // dans une autre langue que l'interface.
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
